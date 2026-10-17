<?php
$path = __DIR__ . '/../course_registration.php';
$lines = file($path);
$stack = [];
for($i=0;$i<count($lines);$i++){
    $l = $lines[$i];
    $len = strlen($l);
    for($j=0;$j<$len;$j++){
        $ch = $l[$j];
        if($ch === '{') $stack[] = $i+1;
        if($ch === '}') array_pop($stack);
    }
}
if($stack){
    echo "Unmatched opens: \n";
    foreach($stack as $ln) echo "line $ln\n";
} else {
    echo "No unmatched opens\n";
}
