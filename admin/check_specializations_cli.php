<?php
require_once __DIR__ . '/../functions.php';
$db = db_connect();
$specs = ['SWD','NCC'];
$out = [];
foreach($specs as $s){
  $row = null;
  $p = $db->prepare('SELECT id,name,code FROM programs WHERE code = ? LIMIT 1');
  if($p){ $p->bind_param('s', $s); $p->execute(); $res = $p->get_result(); $row = $res->fetch_assoc(); }
  $courses = [];
  if($row){
    $c = $db->prepare('SELECT id,name,course_code FROM courses WHERE program_id = ? ORDER BY name');
    if($c){ $c->bind_param('i', $row['id']); $c->execute(); $rc = $c->get_result(); while($r = $rc->fetch_assoc()) $courses[] = $r; }
  }
  $out[$s] = ['program' => $row, 'courses' => $courses];
}
echo json_encode($out, JSON_PRETTY_PRINT), PHP_EOL;
