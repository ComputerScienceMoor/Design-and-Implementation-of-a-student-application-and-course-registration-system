<?php
$path = __DIR__ . '/../course_registration.php';
$lines = file($path);
$count = 0;
$last = null;
for($i=0;$i<count($lines);$i++){
    $l = $lines[$i];
    $count += substr_count($l, '{') - substr_count($l, '}');
    if($count > 0) $last = $i+1;
}
echo "$count last_open_line:$last\n";
// print context around the last open line if found
if($last){
    $start = max(1, $last-5);
    $end = min(count($lines), $last+5);
    for($i=$start;$i<=$end;$i++){
        $num = str_pad($i,4,' ', STR_PAD_LEFT);
        echo "$num: ". rtrim($lines[$i-1]) ."\n";
    }
}
