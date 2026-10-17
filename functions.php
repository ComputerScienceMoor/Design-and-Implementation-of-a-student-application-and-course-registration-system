<?php
// Disable display of PHP errors to avoid emitting HTML into AJAX JSON responses
@ini_set('display_errors', '0');
@error_reporting(0);
require_once __DIR__.'/config.php';
// Add these to the top of the file
require_once __DIR__.'/PHPMailer/src/Exception.php';
require_once __DIR__.'/PHPMailer/src/PHPMailer.php';
require_once __DIR__.'/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function ensure_schema(){
    static $done = false;
    if($done) return;

    $db = db_connect();

    $db->query("CREATE TABLE IF NOT EXISTS `admin_program_access` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `admin_id` INT NOT NULL,
        `program_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_admin_program` (`admin_id`,`program_id`),
        FOREIGN KEY (`admin_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ensure the payments table exists (used for medical fee / clearance gating)
    $db->query("CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `title` VARCHAR(100) NOT NULL,
        `amount` INT NOT NULL,
        `reference` VARCHAR(100) NOT NULL UNIQUE,
        `status` ENUM('pending','verified','failed') NOT NULL DEFAULT 'pending',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_student_title_status` (`student_id`,`title`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if(!column_exists($db, DB_NAME, 'users', 'application_program_id')){
        $db->query('ALTER TABLE `users` ADD COLUMN `application_program_id` INT NULL AFTER `status`');
    }
    if(!column_exists($db, DB_NAME, 'users', 'application_department')){
        $db->query('ALTER TABLE `users` ADD COLUMN `application_department` VARCHAR(100) NULL AFTER `application_program_id`');
    }
    if(!column_exists($db, DB_NAME, 'users', 'application_level')){
        $db->query('ALTER TABLE `users` ADD COLUMN `application_level` VARCHAR(50) NULL AFTER `application_department`');
    }
    if(!column_exists($db, DB_NAME, 'admin_users', 'email')){
        $db->query('ALTER TABLE `admin_users` ADD COLUMN `email` VARCHAR(255) NULL AFTER `username`');
    }
    if(!column_exists($db, DB_NAME, 'admin_users', 'department')){
        $db->query('ALTER TABLE `admin_users` ADD COLUMN `department` VARCHAR(100) NULL AFTER `email`');
    }

    $done = true;
}

function get_programs($db){
    $programs = [];
    $stmt = $db->prepare('SELECT id,name FROM programs ORDER BY name ASC');
    if($stmt){
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()){
            $programs[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => $row['name'] ?? ''
            ];
        }
        $stmt->close();
    }
    return $programs;
}

function get_programs_by_department($db){
    $programs = get_programs($db);
    $byDepartment = [];

    foreach ($programs as $program) {
        $department = trim((string)($program['name'] ?? ''));
        if ($department === '') {
            continue;
        }
        $byDepartment[$department] = [
            $program
        ];
    }

    return $byDepartment;
}

function get_program_name($db, $program_id){
    $program_id = intval($program_id);
    if($program_id <= 0) return '';
    $stmt = $db->prepare('SELECT name FROM programs WHERE id = ? LIMIT 1');
    if(!$stmt) return '';
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();
    return $name ?: '';
}

function get_admin_program_ids($db, $admin_id){
    $admin_id = intval($admin_id);
    $ids = [];
    if($admin_id <= 0) return $ids;

    $stmt = $db->prepare('SELECT program_id FROM admin_program_access WHERE admin_id = ? ORDER BY program_id ASC');
    if(!$stmt) return $ids;
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $stmt->bind_result($program_id);
    while($stmt->fetch()){
        $program_id = intval($program_id);
        if($program_id > 0) $ids[] = $program_id;
    }
    $stmt->close();
    return $ids;
}

function resolve_program_id_from_selection($db, $value){
    $value = trim((string)$value);
    if($value === '') return 0;

    if(preg_match('/^\d+$/', $value)){
        $pid = intval($value);
        $stmt = $db->prepare('SELECT id FROM programs WHERE id = ? LIMIT 1');
        if($stmt){
            $stmt->bind_param('i', $pid);
            $stmt->execute();
            $stmt->bind_result($found);
            if($stmt->fetch()){
                $stmt->close();
                return intval($found);
            }
            $stmt->close();
        }
    }

    $stmt = $db->prepare('SELECT id FROM programs WHERE name = ? LIMIT 1');
    if($stmt){
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $stmt->bind_result($found);
        if($stmt->fetch()){
            $stmt->close();
            return intval($found);
        }
        $stmt->close();
    }

    $stmt = $db->prepare('SELECT id FROM programs WHERE code = ? LIMIT 1');
    if($stmt){
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $stmt->bind_result($found);
        if($stmt->fetch()){
            $stmt->close();
            return intval($found);
        }
        $stmt->close();
    }

    return 0;
}

function user_matches_admin_program_access($db, $user_row, $admin_id, $accessibleProgramIds = []){
    $admin_id = intval($admin_id);
    if($admin_id <= 0) return false;

    $programIds = [];
    foreach((array)$accessibleProgramIds as $pid){
        $pid = intval($pid);
        if($pid > 0) $programIds[] = $pid;
    }
    if(empty($programIds)) return false;

    $candidateIds = [];
    if(isset($user_row['application_program_id'])){
        $pid = intval($user_row['application_program_id']);
        if($pid > 0) $candidateIds[] = $pid;
    }
    if(isset($user_row['registered_program'])){
        $rp = trim((string)($user_row['registered_program'] ?? ''));
        if(preg_match('/^\d+$/', $rp)){
            $pid = intval($rp);
            if($pid > 0) $candidateIds[] = $pid;
        }
    }
    if(isset($user_row['application_department'])){
        $resolved = resolve_program_id_from_selection($db, $user_row['application_department']);
        if($resolved > 0) $candidateIds[] = $resolved;
    }

    foreach($candidateIds as $pid){
        if(in_array($pid, $programIds, true)) return true;
    }

    return false;
}

function admin_can_access_program($db, $admin_id, $program_id){
    $admin_id = intval($admin_id);
    $program_id = intval($program_id);
    if($admin_id <= 0 || $program_id <= 0) return false;
    $ids = get_admin_program_ids($db, $admin_id);
    return in_array($program_id, $ids, true);
}

ensure_schema();

function redirect($url){
    header('Location: '.$url);
    exit;
}

/**
 * Checks whether a student has a verified medical fee payment.
 * Single source of truth for the medical clearance gate — used by both the
 * Step 3 display and Step 3 POST handling in new_student.php.
 * Defensive: if the query can't even be prepared (e.g. payments table missing
 * or a column name mismatch), logs the real reason and treats it as unpaid
 * instead of fatal-erroring the whole page.
 */
function is_medical_fee_paid($db, $uid){
    $stmt = $db->prepare("SELECT status FROM payments WHERE student_id = ? AND title = 'medical_fee' AND status = 'verified'");
    if(!$stmt){
        error_log('is_medical_fee_paid() prepare failed: ' . $db->error);
        return false;
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $row;
}

function send_welcome_email($to) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'akinwunmipleasant13@gmail.com'; // Update this
        $mail->Password   = 'hhzw piwb cyay xihm'; // Update this
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Change to SMTPS
                        $mail->Port       = 465;


        $mail->setFrom('akinwunmipleasant13@gmail.com', 'FCAHPT Portal');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to FCAHPT';
        $mail->Body    = '<h1>Welcome!</h1><p>Thank you for signing up.</p>';
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
function normalize_matric_program_code($program_code, $edu_type = 'nd', $specialization = ''){
    $code = '';
    if(strtolower(trim($edu_type)) === 'hnd' && !empty($specialization)){
        $code = $specialization;
    } else {
        $code = $program_code ?? '';
    }
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]/', '', $code);
    $code = preg_replace('/^(ND|HND)/', '', $code);
    return $code ?: 'GEN';
}

/**
 * Generate a program-aware matric number.
 * Format: {EDU}{PROG}{YY}/{NNN} e.g. NDNATIONALDI26/001 or HNDSWD26/012
 * - $edu_type: 'nd' or 'hnd'
 * - $program_code: short code (alphanumeric) for the program; if empty will be derived from program name
 */
function generate_program_matric($db, $program_code, $edu_type = 'nd', $specialization = ''){
    $eduPrefix = (strtolower(trim($edu_type)) === 'hnd') ? 'HND' : 'ND';
    $prog = normalize_matric_program_code($program_code, $edu_type, $specialization);
    $year = date('y');
    $prefix = $eduPrefix . $prog . $year;
    $like = $prefix . '/%';
    $stmt = $db->prepare('SELECT MAX(CAST(SUBSTRING_INDEX(matric_no, "/", -1) AS UNSIGNED)) FROM users WHERE matric_no LIKE ?');
    if(!$stmt) return null;
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $stmt->bind_result($maxSeq);
    $stmt->fetch();
    $stmt->close();
    $next = intval($maxSeq) + 1;
    $seq = sprintf('%03d', $next);
    return $prefix . '/' . $seq;
}

function column_exists($db, $schema, $table, $column){
    $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->bind_param('sss', $schema, $table, $column);
    $q->execute();
    $q->bind_result($c);
    $q->fetch();
    $q->close();
    return intval($c) > 0;
}

/**
 * Update a user's status in a guarded way.
 * Only allows updates when $source is in the allowed list (to prevent arbitrary programmatic changes).
 * Records an audit entry in registration_audit when status changes.
 * Returns true on success, false on failure.
 */
function update_user_status($db, $user_id, $new_status, $source = ''){
    $allowed_statuses = ['prospective','new','returning','pending','completed','failed'];
    if(!in_array($new_status, $allowed_statuses, true)) return false;

    // Allowed sources that can change status programmatically
    $allowed_sources = ['enrollment','upload','postutme','admin'];
    if(empty($source) || !in_array($source, $allowed_sources, true)){
        return false;
    }

    $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
    if(!$stmt) return false;
    $stmt->bind_param('si', $new_status, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if($ok){
        // try to record audit (best-effort)
        try{
            if(function_exists('column_exists') && column_exists($db, DB_NAME, 'registration_audit', 'id')){
                $actor_ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $a = $db->prepare('INSERT INTO registration_audit (user_id, course_id, action, actor_ip) VALUES (?, NULL, ?, ?)');
                if($a){
                    $action = 'status_change:'.$source.':'.$new_status;
                    $a->bind_param('iss', $user_id, $action, $actor_ip);
                    $a->execute();
                    $a->close();
                }
            }
        } catch(\Throwable $e){ /* ignore audit failures */ }
    }
    return (bool)$ok;
}

/**
 * Create directory safely without raising warnings on concurrent calls.
 * Returns true on success or if the directory already exists.
 */
function safe_mkdir($path, $mode = 0755){
    if(is_dir($path)) return true;
    // suppress race-condition warnings but still return boolean
    try{
        return @mkdir($path, $mode, true) || is_dir($path);
    } catch(Exception $e){
        return is_dir($path);
    }
}