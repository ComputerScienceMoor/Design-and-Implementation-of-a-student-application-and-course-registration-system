<?php
$path = __DIR__ . '/../course_registration.php';
$lines = file($path);
$inphp = false; $count = 0;
for($i=0;$i<count($lines);$i++){
    $line = $lines[$i];
    if(!$inphp){
        if(strpos($line, '<?php') !== false){
            $inphp = true;
            $after = substr($line, strpos($line,'<?php')+5);
            // process after
            $count += substr_count($after, '{') - substr_count($after, '}');
            echo str_pad($i+1,4,' ', STR_PAD_LEFT)." PHP_START count=$count: ".rtrim($after)."\n";
        }
    } else {
        if(strpos($line, '?>') !== false){
            $before = substr($line, 0, strpos($line,'?>'));
            $count += substr_count($before, '{') - substr_count($before, '}');
            echo str_pad($i+1,4,' ', STR_PAD_LEFT)." PHP_END count=$count: ".rtrim($before)."\n";
            $inphp = false;
            continue;
        }
        $count += substr_count($line, '{') - substr_count($line, '}');
        echo str_pad($i+1,4,' ', STR_PAD_LEFT)." count=$count: ".rtrim($line)."\n";
    }
}
echo "FINAL count=$count\n";
