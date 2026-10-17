<?php
// Archived copy of check_programs.php
require 'config.php';
$db = db_connect();
$res = $db->query('SELECT id,name,code FROM programs ORDER BY id');
if (!$res) {
    echo 'ERROR: '.$db->error."\n";
    exit(1);
}
while ($r = $res->fetch_assoc()) {
    echo $r['id'].'|'.$r['name'].'|'.$r['code']."\n";
}
