<?php
// Simulate registration then deregistration for debugging seats behavior.
require_once __DIR__ . '/../functions.php';
$db = db_connect();
$uid = intval($argv[1] ?? 19);
$course_id = intval($argv[2] ?? 63);
echo "Simulate for user={$uid} course={$course_id}\n";

function seats($db, $cid){
  $r = $db->query('SELECT id, COALESCE(seats_available, seats_total, 50) AS seats FROM courses WHERE id=' . intval($cid));
  return $r ? $r->fetch_assoc() : null;
}

// ensure user not registered
$db->query('DELETE FROM student_course_registrations WHERE user_id=' . $uid . ' AND course_id=' . $course_id);

echo "Seats before: "; print_r(seats($db, $course_id));

// Register (transaction)
try{
  $db->begin_transaction();
  $upd = $db->prepare('UPDATE courses SET seats_available = COALESCE(seats_available, seats_total, 50) - 1 WHERE id = ? AND COALESCE(seats_available, seats_total, 50) > 0');
  $upd->bind_param('i', $course_id);
  $upd->execute();
  if($upd->affected_rows <= 0){
    $db->rollback(); echo "Register failed: no seats\n"; exit(1);
  }
  $ins = $db->prepare('INSERT INTO student_course_registrations (user_id, course_id, program_id) VALUES (?, ?, (SELECT program_id FROM courses WHERE id = ?))');
  $ins->bind_param('iii',$uid,$course_id,$course_id);
  $ins->execute();
  $db->commit();
  echo "Registered OK\n";
} catch(Exception $e){ $db->rollback(); echo "Register exception: ".$e->getMessage()."\n"; exit(1); }

echo "Seats after register: "; print_r(seats($db, $course_id));

// Deregister (transaction)
try{
  $db->begin_transaction();
  $del = $db->prepare('DELETE FROM student_course_registrations WHERE user_id = ? AND course_id = ?');
  $del->bind_param('ii',$uid,$course_id);
  $del->execute();
  $inc = $db->prepare('UPDATE courses SET seats_available = COALESCE(seats_available, seats_total, 50) + 1 WHERE id = ?');
  $inc->bind_param('i',$course_id);
  $inc->execute();
  $db->commit();
  echo "Deregistered OK\n";
} catch(Exception $e){ $db->rollback(); echo "Deregister exception: ".$e->getMessage()."\n"; exit(1); }

echo "Seats after deregister: "; print_r(seats($db, $course_id));

?>
