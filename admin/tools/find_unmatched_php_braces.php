<?php
$path = __DIR__ . '/../course_registration.php';
$txt = file_get_contents($path);
$inphp = false;
$stack = [];
$lines = explode("\n", $txt);
for($i=0;$i<count($lines);$i++){
    $line = $lines[$i];
    $pos = 0;
    while(true){
        if(!$inphp){
            $start = strpos($line, '<?php', $pos);
            if($start === false) break;
            $inphp = true;
            $pos = $start + 5;
            // continue scanning rest of line as PHP
        } else {
            $end = strpos($line, '?>', $pos);
            $segment = ($end === false) ? substr($line, $pos) : substr($line, $pos, $end - $pos);
            // count braces in segment
            for($j=0;$j<strlen($segment);$j++){
                $ch = $segment[$j];
                if($ch === '{') $stack[] = $i+1;
                if($ch === '}') array_pop($stack);
            }
            if($end === false) break; // no closing tag on this line
            $inphp = false;
            $pos = $end + 2;
        }
    }
}
if($stack){ echo "Unmatched opens in PHP at lines:\n"; foreach($stack as $ln) echo "line $ln\n"; } else { echo "No unmatched PHP opens\n"; }
