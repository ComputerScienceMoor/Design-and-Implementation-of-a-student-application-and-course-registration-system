<?php
require_once __DIR__ . '/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();

// Simple diagnostic page for schema and sample data
$checks = [];
$checks['db_name'] = DB_NAME;
$tables = ['courses','student_course_registrations','users'];
foreach($tables as $t){
    $res = $db->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA='".$db->real_escape_string(DB_NAME)."' AND TABLE_NAME='".$db->real_escape_string($t)."'");
    $row = $res ? $res->fetch_assoc() : null;
    $checks["table_{$t}"] = intval($row['c'] ?? 0) > 0 ? 'exists' : 'missing';
}

// Check important columns
$cols = [
    'courses' => ['seats_available','seats_total'],
    'student_course_registrations' => ['registered_at']
];
foreach($cols as $table => $carr){
    foreach($carr as $col){
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
        $q->bind_param('sss', DB_NAME, $table, $col);
        $q->execute(); $q->bind_result($cnt); $q->fetch(); $q->close();
        $checks["col_{$table}_{$col}"] = intval($cnt) > 0 ? 'exists' : 'missing';
    }
}

// Sample course rows
$sample = [];
$res = $db->query('SELECT id,name,seats_available,seats_total FROM courses LIMIT 10');
if($res){ while($r = $res->fetch_assoc()) $sample[] = $r; }

header('Content-Type: application/json');
echo json_encode(['checks'=>$checks,'sample_courses'=>$sample], JSON_PRETTY_PRINT);
exit;