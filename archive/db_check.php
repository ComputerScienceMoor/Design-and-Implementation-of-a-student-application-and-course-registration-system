<?php
// Archived copy of db_check.php
require_once 'functions.php';
$db = db_connect();
$cols = ['education_type','it_letter_path','nd_result_path','cgpa_path','nd_institution','it_place','it_duration','next_of_kin_name','next_of_kin_email','next_of_kin_phone','next_of_kin_address','jamb_no'];
$placeholders = implode("','", $cols);
$sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('$placeholders')";
$res = $db->query($sql);
$found = [];
if($res){
    while($r = $res->fetch_assoc()) $found[] = $r['COLUMN_NAME'];
}
echo "Expected columns:\n" . implode(", ", $cols) . "\n\nFound columns:\n" . implode(", ", $found) . "\n";
if(count($found) === count($cols)){
    echo "All columns present.\n";
} else {
    echo "Some columns missing.\n";
}
