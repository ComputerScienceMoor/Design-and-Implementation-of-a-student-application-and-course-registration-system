<?php
$path = __DIR__ . '/../course_registration.php';
$lines = file($path);
$count = 0;
$first = null;
for($i=0;$i<count($lines);$i++){
    $l = $lines[$i];
    $count += substr_count($l, '{') - substr_count($l, '}');
    if($count > 0 && $first === null) $first = $i+1;
}
if($first){
    echo "first_open_line:$first\n";
    $start = max(1, $first-10);
    $end = min(count($lines), $first+20);
    for($i=$start;$i<=$end;$i++){
        $num = str_pad($i,4,' ', STR_PAD_LEFT);
        echo "$num: ". rtrim($lines[$i-1]) ."\n";
    }
} else {
    echo "No unmatched opening brace found.\n";
}
