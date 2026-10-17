<?php
// CLI helper: print user registration info for debugging (user id passed as first arg)
require_once __DIR__ . '/../functions.php';
$uid = intval($argv[1] ?? 19);
$db = db_connect();
$out = ['user' => null, 'resolved_program' => null, 'courses' => []];
$q = $db->prepare('SELECT id,email,registered_program,specialization,matric_no,status FROM users WHERE id = ? LIMIT 1');
if($q){ $q->bind_param('i', $uid); $q->execute(); $res = $q->get_result(); $out['user'] = $res->fetch_assoc(); }
if(!empty($out['user'])){
  $rp = trim($out['user']['registered_program'] ?? '');
  if($rp !== ''){
    if(preg_match('/^\d+$/', $rp)){
      $p = $db->prepare('SELECT id,name,code FROM programs WHERE id = ? LIMIT 1');
      if($p){ $p->bind_param('i',$rp); $p->execute(); $r = $p->get_result(); $prog = $r->fetch_assoc(); if($prog) $out['resolved_program'] = $prog; }
    }
    if(empty($out['resolved_program'])){
      $p2 = $db->prepare('SELECT id,name,code FROM programs WHERE code = ? OR name = ? LIMIT 1');
      if($p2){ $p2->bind_param('ss',$rp,$rp); $p2->execute(); $r2 = $p2->get_result(); $prog2 = $r2->fetch_assoc(); if($prog2) $out['resolved_program'] = $prog2; }
    }
  }
  if(empty($out['resolved_program']) && !empty($out['user']['specialization'])){
    $spec = strtoupper(trim($out['user']['specialization']));
    $ps = $db->prepare('SELECT id,name,code FROM programs WHERE code = ? LIMIT 1');
    if($ps){ $ps->bind_param('s', $spec); $ps->execute(); $r = $ps->get_result(); $prog = $r->fetch_assoc(); if($prog) $out['resolved_program'] = $prog; }
  }

  if(!empty($out['resolved_program']['id'])){
    $pid = intval($out['resolved_program']['id']);
    $c = $db->prepare('SELECT id,name,course_code FROM courses WHERE program_id = ? ORDER BY name');
    if($c){ $c->bind_param('i', $pid); $c->execute(); $rc = $c->get_result(); while($r = $rc->fetch_assoc()) $out['courses'][] = $r; }
  }
}
echo json_encode($out, JSON_PRETTY_PRINT), PHP_EOL;
