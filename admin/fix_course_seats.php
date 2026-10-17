<?php
require_once __DIR__ . '/../functions.php';
$db = db_connect();
$updated = 0;
@mkdir(__DIR__ . '/../logs',0755,true);
$logfile = __DIR__ . '/../logs/course_registration.log';

$stmt = $db->prepare('SELECT id, COALESCE(seats_total,50) AS seats_total FROM courses');
if(!$stmt){ echo "Failed to prepare select courses: " . $db->error; exit; }
$stmt->execute(); $res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $cid = intval($row['id']);
    $seats_total = intval($row['seats_total']);
    $cstmt = $db->prepare('SELECT COUNT(*) AS regcount FROM student_course_registrations WHERE course_id = ?');
    if(!$cstmt) continue;
    $cstmt->bind_param('i', $cid); $cstmt->execute(); $cr = $cstmt->get_result()->fetch_assoc(); $regcount = intval($cr['regcount'] ?? 0);
    $newSeats = max(0, $seats_total - $regcount);
    $ustmt = $db->prepare('UPDATE courses SET seats_available = ? WHERE id = ?');
    if($ustmt){ $ustmt->bind_param('ii', $newSeats, $cid); $ustmt->execute(); $updated++; @file_put_contents($logfile, date('[Y-m-d H:i:s] ').json_encode(['action'=>'fix_seats','course'=>$cid,'seats_total'=>$seats_total,'regcount'=>$regcount,'newSeats'=>$newSeats])."\n", FILE_APPEND); }
}

echo "Fixed seats for {$updated} courses.\n";

?>