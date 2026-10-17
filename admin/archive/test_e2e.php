<?php
// Archived test E2E script
require_once __DIR__.'/../../functions.php';
$db = db_connect();
$ts = time();
$email = 'e2e_test_'.$ts.'@example.com';
$password = password_hash('password', PASSWORD_DEFAULT);
// create user
$stmt = $db->prepare('INSERT INTO users (email,password_hash,status) VALUES (?, ?, ?)');
$status = 'new';
$stmt->bind_param('sss',$email,$password,$status);
if(!$stmt->execute()){ echo "Failed to create test user\n"; exit(1); }
$uid = $db->insert_id;
echo "Created test user id=$uid email=$email\n";

// prepare sample PNG file content (minimal PNG)
$png = hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010806000000' .
               '1f15c4890000000a49444154789c6360000002000100' .
               '05fe02fea1b10000000049454e44ae426082');
$uploadsDir = __DIR__.'/../../uploads';
if(!is_dir($uploadsDir)) mkdir($uploadsDir,0755,true);
$files = [];
foreach(['it_letter.png','nd_result.png','cgpa.png'] as $fname){
    $tmp = sys_get_temp_dir().'/tmp_'.$fname;
    file_put_contents($tmp,$png);
    $destRel = 'uploads/'.pathinfo($fname,PATHINFO_FILENAME).'_user_'.$uid.'_'.time().'_'.basename($fname);
    $destAbs = dirname(__DIR__,2).'/'.$destRel;
    if(!copy($tmp,$destAbs)){
        echo "Failed to copy $tmp to $destAbs\n"; exit(1);
    }
    @chmod($destAbs,0644);
    unlink($tmp);
    $files[] = $destRel;
    echo "Created sample file: $destRel\n";
}

// update user as HND applicant and attach files
$education_type = 'hnd';
$nd_institution = 'E2E Institute';
$it_place = 'E2E Place';
$it_duration = '6 months';
$it_letter_path = $files[0];
$nd_result_path = $files[1];
$cgpa_path = $files[2];

$stmt = $db->prepare('UPDATE users SET education_type=?, nd_institution=?, it_place=?, it_duration=?, it_letter_path=?, nd_result_path=?, cgpa_path=? WHERE id = ?');
$stmt->bind_param('sssssssi', $education_type, $nd_institution, $it_place, $it_duration, $it_letter_path, $nd_result_path, $cgpa_path, $uid);
if(!$stmt->execute()){ echo "Failed to update test user with HND data\n"; exit(1); }

echo "Updated user $uid with HND data and uploaded files.\n";

// verify
$stmt = $db->prepare('SELECT id,email,education_type,nd_institution,it_place,it_duration,it_letter_path,nd_result_path,cgpa_path FROM users WHERE id = ?');
$stmt->bind_param('i',$uid);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
print_r($row);

echo "E2E simulation complete. Check uploads/ and DB for the record.\n";

?>
