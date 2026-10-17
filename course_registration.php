<?php
// course_registration.php - atomic register/deregister endpoint
@ini_set('display_errors', 0);
@error_reporting(0);
require_once 'functions.php';

$db = db_connect();
// If no session, AJAX POSTs should receive JSON error, GETs should redirect to login page
if(empty($_SESSION['user_id'])){
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'message'=>'Not authenticated']);
        exit;
    }
    redirect('login.php');
}
$uid = intval($_SESSION['user_id']);

$stmt = $db->prepare('SELECT status, profile_complete FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$eligible = ($user && in_array($user['status'], ['new','completed']) && $user['profile_complete']);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $course_id = intval($_POST['course_id'] ?? 0);
    $program_id = intval($_POST['program_id'] ?? 0);

    if(!$eligible){ echo json_encode(['success'=>false,'message'=>'Not eligible']); exit; }
    if($course_id <= 0){ echo json_encode(['success'=>false,'message'=>'Invalid course']); exit; }

    // Best-effort ensure seats_available exists
    if(function_exists('column_exists') && !column_exists($db, DB_NAME, 'courses', 'seats_available')){
        @$db->query("ALTER TABLE courses ADD COLUMN seats_available INT DEFAULT NULL");
        if(function_exists('column_exists') && column_exists($db, DB_NAME, 'courses', 'seats_total')){
            @$db->query('UPDATE courses SET seats_available = seats_total WHERE seats_available IS NULL');
        } else {
            @$db->query('UPDATE courses SET seats_available = 50 WHERE seats_available IS NULL');
        }
    }

    if($action === 'register_course'){
        // check existing
        $chk = $db->prepare('SELECT COUNT(*) as c FROM student_course_registrations WHERE user_id = ? AND course_id = ?');
        $chk->bind_param('ii',$uid,$course_id); $chk->execute(); $rc = $chk->get_result()->fetch_assoc();
        if(intval($rc['c'] ?? 0) > 0){ echo json_encode(['success'=>false,'message'=>'Already registered']); exit; }

        try{
            $db->begin_transaction();
            $q = $db->prepare('SELECT COALESCE(seats_available, seats_total, 50) AS seats FROM courses WHERE id = ? FOR UPDATE');
            $q->bind_param('i',$course_id); $q->execute(); $r = $q->get_result()->fetch_assoc(); $seats = intval($r['seats'] ?? 0);
            if($seats <= 0){ $db->rollback(); echo json_encode(['success'=>false,'message'=>'No seats available']); exit; }
            $new = max(0, $seats - 1);
            $u = $db->prepare('UPDATE courses SET seats_available = ? WHERE id = ?'); $u->bind_param('ii',$new,$course_id); $u->execute();
            $ins = $db->prepare('INSERT INTO student_course_registrations (user_id, course_id, program_id) VALUES (?,?,?)'); $ins->bind_param('iii',$uid,$course_id,$program_id); $ins->execute();
            $db->commit();
            echo json_encode(['success'=>true,'message'=>'Registered','seats_available'=>$new]);
        } catch(Exception $e){ @$db->rollback(); echo json_encode(['success'=>false,'message'=>'Registration error']); }
        exit;
    }

    if($action === 'deregister_course'){
        $chk = $db->prepare('SELECT COUNT(*) as c FROM student_course_registrations WHERE user_id = ? AND course_id = ?');
        $chk->bind_param('ii',$uid,$course_id); $chk->execute(); $rc = $chk->get_result()->fetch_assoc();
        if(intval($rc['c'] ?? 0) === 0){ echo json_encode(['success'=>false,'message'=>'Not registered']); exit; }

        try{
            $db->begin_transaction();
            $q = $db->prepare('SELECT COALESCE(seats_available, seats_total, 50) AS seats FROM courses WHERE id = ? FOR UPDATE');
            $q->bind_param('i',$course_id); $q->execute(); $r = $q->get_result()->fetch_assoc(); $seats = intval($r['seats'] ?? 0);
            $del = $db->prepare('DELETE FROM student_course_registrations WHERE user_id = ? AND course_id = ?'); $del->bind_param('ii',$uid,$course_id); $del->execute();
            $new = $seats + 1; $u = $db->prepare('UPDATE courses SET seats_available = ? WHERE id = ?'); $u->bind_param('ii',$new,$course_id); $u->execute();
            $db->commit();
            echo json_encode(['success'=>true,'message'=>'Deregistered','seats_available'=>$new]);
        } catch(Exception $e){ @$db->rollback(); echo json_encode(['success'=>false,'message'=>'Deregistration error']); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']); exit;
}

// Simple GET view
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Course Registration</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <div class="card" style="max-width: 600px; margin: 60px auto; padding: 40px;">
      <h1 style="margin: 0 0 16px; font-size: 1.8rem; color: var(--primary-dark);">Course Registration</h1>
      <p style="margin: 0; color: var(--muted); font-size: 1.05rem;">The registration endpoint is active. Use the <a href="dashboard.php">dashboard</a> to register and manage your courses.</p>
    </div>
  </div>
</body>
</html>
