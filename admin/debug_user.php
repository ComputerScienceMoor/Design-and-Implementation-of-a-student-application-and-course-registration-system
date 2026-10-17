<?php
// Quick debug page: resolves a user's registered program and lists courses for that program.
require_once __DIR__ . '/../functions.php';
if(empty($_SESSION['is_admin'])){
  echo "Access restricted. Admins only."; exit;
}
$db = db_connect();
$uid = intval($_GET['uid'] ?? 19);
$out = ['user_id' => $uid, 'user' => null, 'resolved_program' => null, 'courses' => []];

$q = $db->prepare('SELECT id,email,registered_program,specialization,matric_no,status FROM users WHERE id = ? LIMIT 1');
if($q){ $q->bind_param('i', $uid); $q->execute(); $res = $q->get_result(); $out['user'] = $res->fetch_assoc(); }

if(!empty($out['user'])){
  $rp = trim($out['user']['registered_program'] ?? '');
  if($rp !== ''){
    // numeric id
    if(preg_match('/^\d+$/', $rp)){
      $p = $db->prepare('SELECT id,name,code FROM programs WHERE id = ? LIMIT 1');
      if($p){ $p->bind_param('i', $rp); $p->execute(); $r = $p->get_result(); $prog = $r->fetch_assoc(); if($prog) $out['resolved_program'] = $prog; }
    }
    // try code or exact name
    if(empty($out['resolved_program'])){
      $p2 = $db->prepare('SELECT id,name,code FROM programs WHERE code = ? OR name = ? LIMIT 1');
      if($p2){ $p2->bind_param('ss', $rp, $rp); $p2->execute(); $r2 = $p2->get_result(); $prog2 = $r2->fetch_assoc(); if($prog2) $out['resolved_program'] = $prog2; }
    }
  }
  // if resolved, load courses
  if(!empty($out['resolved_program']['id'])){
    $pid = intval($out['resolved_program']['id']);
    $c = $db->prepare('SELECT id,name,course_code FROM courses WHERE program_id = ? ORDER BY name');
    if($c){ $c->bind_param('i', $pid); $c->execute(); $rc = $c->get_result(); while($r = $rc->fetch_assoc()) $out['courses'][] = $r; }
  }
}

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Debug: User Program Resolution</h2>';
echo '<pre>'.htmlspecialchars(print_r($out, true)).'</pre>';
echo '<p>Open <a href="../migrate.php">migrate.php</a> to seed programs/courses if missing.</p>';
?>
