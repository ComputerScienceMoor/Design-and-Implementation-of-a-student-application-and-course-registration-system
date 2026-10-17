<?php
// Archived copy of dump_program_groups.php
require_once __DIR__.'/../../config.php';
$db = db_connect();
$programGroups = [];
$selectCols = 'id,name';
if(function_exists('column_exists') && column_exists($db, DB_NAME, 'programs', 'code')){
    $selectCols .= ',code';
}
$resP = $db->query("SELECT $selectCols FROM programs ORDER BY name");
while($r = $resP->fetch_assoc()){
    $pid = intval($r['id']);
    $pname = $r['name'];
    $pcode = array_key_exists('code', $r) ? $r['code'] : null;
    if(strpos($pname, ' - ') !== false){
        $parts = explode(' - ', $pname, 2);
        $base = $parts[0];
        $label = $parts[1];
    } else {
        $base = $pname;
        $label = $pname;
    }
    if(!isset($programGroups[$base])) $programGroups[$base] = [];
    $programGroups[$base][] = ['id'=>$pid, 'name'=>$pname, 'label'=>$label, 'code'=>$pcode];
}
echo json_encode($programGroups, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
