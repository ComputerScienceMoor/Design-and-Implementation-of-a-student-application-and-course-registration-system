<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$id = intval($_GET['id'] ?? 0);
if($id <= 0) redirect('users.php');
$db = db_connect();
function safe_prepare($db,$sql){
  try{ return $db->prepare($sql); } catch (mysqli_sql_exception $e){ return false; }
}
$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $action = $_POST['action'] ?? '';
    if($action === 'pass'){
        $status = 'new';
        if(update_user_status($db, $id, $status, 'admin')){
          // ensure profile_complete reset
          $stmt = $db->prepare('UPDATE users SET profile_complete = 0 WHERE id = ?');
          if($stmt){ $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close(); }
          $message = 'Student has passed Post-UTME and is now a New Student. They must complete biodata enrollment.';
        }
        if(isset($stmt) && $stmt){} // keep parity
        if($message){
            $q = $db->prepare('SELECT email FROM users WHERE id = ?');
            $q->bind_param('i',$id);
            $q->execute();
            $q->store_result();
            $q->bind_result($email);
            $q->fetch();
            $q->free_result();
            $q->close();
            if($email) send_welcome_email($email);
          $_SESSION['flash_success'] = $message;
          redirect('index.php');
        } else {
            $stmt->close();
        }
    } elseif($action === 'fail'){
        $status = 'failed';
        if(update_user_status($db, $id, $status, 'admin')){
          $message = 'Student has been marked as failed. You can review documentation or update status as needed.';
          $_SESSION['flash_error'] = $message;
          redirect('index.php');
        }
    }
}

$stmt = safe_prepare($db, 'SELECT email,full_name,phone,jamb_no,status,profile_complete,education_type,nd_institution,it_place,it_duration,parent_name,parent_phone,parent_email,next_of_kin_name,next_of_kin_email,next_of_kin_phone,next_of_kin_address,o_level_path,jamb_path,post_utme_path,passport_path,birth_certificate_path,indigene_letter_path,it_letter_path,nd_result_path,cgpa_path,admission_letter_path,acceptance_letter_path,undertaking_form_path,xray_path,blood_test_path,weight,height,blood_group,genotype,allergies,medical_history FROM users WHERE id = ?');
if($stmt){
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
} else {
  $stmt = $db->prepare('SELECT email,jamb_no,phone,status,o_level_path,jamb_path,post_utme_path FROM users WHERE id = ?');
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  if($user){
    $user += [
      'full_name' => null,
      'profile_complete' => null,
      'education_type' => null,
      'nd_institution' => null,
      'it_place' => null,
      'it_duration' => null,
      'parent_name' => null,
      'parent_phone' => null,
      'parent_email' => null,
      'next_of_kin_name' => null,
      'next_of_kin_email' => null,
      'next_of_kin_phone' => null,
      'next_of_kin_address' => null,
      'passport_path' => null,
      'birth_certificate_path' => null,
      'indigene_letter_path' => null,
      'it_letter_path' => null,
      'nd_result_path' => null,
      'cgpa_path' => null,
      'admission_letter_path' => null,
      'acceptance_letter_path' => null,
      'undertaking_form_path' => null,
      'xray_path' => null,
      'blood_test_path' => null,
      'weight' => null,
      'height' => null,
      'blood_group' => null,
      'genotype' => null,
      'allergies' => null,
      'medical_history' => null
    ];
  }
}
if(!$user) redirect('users.php');

// fetch other uploaded documents for admin review
$otherDocs = [];
$stmtOther = safe_prepare($db, 'SELECT id, file_path, label, created_at FROM student_other_documents WHERE user_id = ? ORDER BY created_at DESC');
if($stmtOther){
  $stmtOther->bind_param('i', $id);
  $stmtOther->execute();
  $resO = $stmtOther->get_result();
  while($r = $resO->fetch_assoc()) $otherDocs[] = $r;
}
?>
<?php include 'header.php'; ?>
  <h2>Files for <?php echo htmlspecialchars($user['email'] ?? 'student'); ?></h2>
  <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></p>
  <p><strong>Status:</strong> <?php echo htmlspecialchars($user['status']); ?></p>
  <p><strong>JAMB Number:</strong> <?php echo htmlspecialchars($user['jamb_no'] ?? 'N/A'); ?></p>
  <p><strong>Education Type:</strong> <?php echo htmlspecialchars(strtoupper($user['education_type'] ?? 'ND')); ?></p>
  <p><strong>Profile Complete:</strong> <?php echo $user['profile_complete'] ? 'Yes' : 'No'; ?></p>

  <?php if($message): ?><div class="success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

  <h3>Student Uploaded Documents</h3>
  <ul>
    <?php
    $edu_type = strtolower(preg_replace('/\s+/', '', $user['education_type'] ?? ''));
    $docs = [
      'passport_path' => 'Passport Photo',
      'birth_certificate_path' => 'Birth Certificate',
      'indigene_letter_path' => 'Indigene Letter',
      'o_level_path' => 'O-Level Result',
      'jamb_path' => 'JAMB Result',
      'it_letter_path' => 'Industrial Training Letter',
      'nd_result_path' => 'ND Result',
      'xray_path' => 'X-ray Scan',
      'blood_test_path' => 'Blood Test',
      'admission_letter_path' => 'Admission Letter',
      'acceptance_letter_path' => 'Acceptance Letter',
      'undertaking_form_path' => 'Student Undertaking Form'
    ];
    // Don't require/show CGPA/evidence of result for HND applicants
    if($edu_type !== 'hnd'){
      $docs['cgpa_path'] = 'Evidence of Result / CGPA';
    }
    foreach($docs as $field => $label): ?>
      <?php if(!empty($user[$field])): ?>
        <li><?php echo htmlspecialchars($label); ?>: <a href="../<?php echo htmlspecialchars($user[$field]); ?>" target="_blank">View/Download</a></li>
      <?php else: ?>
        <li><?php echo htmlspecialchars($label); ?>: <em>Not uploaded</em></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ul>

  <h3>Additional Student Documents (uploaded by student)</h3>
  <?php if(empty($otherDocs)): ?>
    <div><em>No additional documents uploaded.</em></div>
  <?php else: ?>
    <ul>
      <?php foreach($otherDocs as $od): ?>
        <li><?php echo htmlspecialchars($od['label'] ?: 'Other Document'); ?>: <a href="../<?php echo htmlspecialchars($od['file_path']); ?>" target="_blank">View/Download</a> (Uploaded <?php echo htmlspecialchars($od['created_at']); ?>)</li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h3>HND / Education Details</h3>
  <ul>
    <li>Education Type: <?php echo htmlspecialchars(strtoupper($user['education_type'] ?? 'ND')); ?></li>
    <li>ND Institution: <?php echo htmlspecialchars($user['nd_institution'] ?? 'N/A'); ?></li>
    <li>Industrial Training Place: <?php echo htmlspecialchars($user['it_place'] ?? 'N/A'); ?></li>
    <li>Industrial Training Duration: <?php echo htmlspecialchars($user['it_duration'] ?? 'N/A'); ?></li>
  </ul>

  <h3>Medical Information</h3>
  <ul>
    <li>Weight: <?php echo htmlspecialchars($user['weight'] ?? 'N/A'); ?></li>
    <li>Height: <?php echo htmlspecialchars($user['height'] ?? 'N/A'); ?></li>
    <li>Blood Group: <?php echo htmlspecialchars($user['blood_group'] ?? 'N/A'); ?></li>
    <li>Genotype: <?php echo htmlspecialchars($user['genotype'] ?? 'N/A'); ?></li>
    <li>Allergies: <?php echo htmlspecialchars($user['allergies'] ?? 'N/A'); ?></li>
    <li>Medical History: <?php echo htmlspecialchars($user['medical_history'] ?? 'N/A'); ?></li>
  </ul>

  <h3>Guardian and Next of Kin</h3>
  <ul>
    <li>Parent / Guardian Name: <?php echo htmlspecialchars($user['parent_name'] ?? 'N/A'); ?></li>
    <li>Parent / Guardian Phone: <?php echo htmlspecialchars($user['parent_phone'] ?? 'N/A'); ?></li>
    <li>Parent / Guardian Email: <?php echo htmlspecialchars($user['parent_email'] ?? 'N/A'); ?></li>
    <li>Next of Kin Name: <?php echo htmlspecialchars($user['next_of_kin_name'] ?? 'N/A'); ?></li>
    <li>Next of Kin Email: <?php echo htmlspecialchars($user['next_of_kin_email'] ?? 'N/A'); ?></li>
    <li>Next of Kin Phone: <?php echo htmlspecialchars($user['next_of_kin_phone'] ?? 'N/A'); ?></li>
    <li>Next of Kin Address: <?php echo htmlspecialchars($user['next_of_kin_address'] ?? 'N/A'); ?></li>
  </ul>

  <h3>Post-UTME (admin upload)</h3>
  <?php if(!empty($user['post_utme_path'])): ?>
    <div>Post-UTME: <a href="../<?php echo htmlspecialchars($user['post_utme_path']); ?>" target="_blank">View/Download</a></div>
  <?php else: ?>
    <div>No Post-UTME uploaded yet.</div>
  <?php endif; ?>

  <?php if($user['status'] === 'pending'): ?>
    <h3>Upload Post-UTME Result</h3>
    <p>Upload a Post-UTME result for this student. After upload the student's status will be set to "pending" until you confirm pass or fail.</p>
    <form method="post" action="upload_postutme.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
      <label>Post-UTME Result (PDF/JPG/PNG)</label>
      <input type="file" name="postutme" required>
      <button type="submit">Upload</button>
    </form>

    <?php if(!empty($user['post_utme_path'])): ?>
      <!-- <h3>Decision</h3>
      <p>Mark this student as admitted or not admitted after reviewing the Post-UTME result.</p>
      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap">
        <input type="hidden" name="action" value="pass">
        <button type="submit" class="btn btn-success">Mark as Passed</button>
      </form>
      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
        <input type="hidden" name="action" value="fail">
        <button type="submit" class="btn btn-danger">Mark as Failed</button>
      </form> -->
    <?php endif; ?>
  <?php else: ?>
    <div class="status-message <?php echo $user['status'] === 'new' ? 'status-success' : 'status-error'; ?>">
      <span class="status-icon"><?php echo $user['status'] === 'new' ? '✔️' : '✕'; ?></span>
      <div>
        <strong>Decision Closed</strong>
        <p>This student's Post-UTME has already been decided as <strong><?php echo htmlspecialchars(ucfirst($user['status'])); ?></strong>. Further changes are disabled.</p>
      </div>
    </div>
  <?php endif; ?>

  <p><a href="users.php">Back to users</a></p>
<?php include 'footer.php'; ?>
