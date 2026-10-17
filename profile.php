<?php
require_once 'functions.php';
if(empty($_SESSION['user_id'])) redirect('login.php');
$db = db_connect();
$uid = intval($_SESSION['user_id']);
$stmt = $db->prepare('SELECT email,jamb_no,phone,status,o_level_path,jamb_path,post_utme_path,profile_complete,full_name,gender,dob,state_of_origin,lga,nationality,marital_status,religion,address,passport_path,parent_name,parent_phone,parent_email,birth_certificate_path,indigene_letter_path,nin,admission_letter_path,acceptance_letter_path,education_type,it_letter_path,nd_result_path,cgpa_path,nd_institution,it_place,it_duration,next_of_kin_name,next_of_kin_email,next_of_kin_phone,next_of_kin_address,registered_program,registered_courses,matric_no FROM users WHERE id = ?');
$stmt->bind_param('i',$uid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if(!$user) redirect('dashboard.php');
// load other uploaded documents
$otherDocs = [];
$stmt2 = $db->prepare('SELECT id, file_path, label, created_at FROM student_other_documents WHERE user_id = ? ORDER BY created_at DESC');
if($stmt2){
  $stmt2->bind_param('i', $uid);
  $stmt2->execute();
  $res2 = $stmt2->get_result();
  while($r = $res2->fetch_assoc()) $otherDocs[] = $r;
}

// find additional O-Level documents (students may upload multiple O-Level results)
$additionalOlevels = array_filter($otherDocs, function($d){
  return preg_match('/o[-\s]*level|waec|neco|ssce|gce/i', $d['label']);
});
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profile - Federal College of Animal Health and Production Technology</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="student-full">
  <div class="container">
    <div class="page-header">
      <div>
        <a href="dashboard.php" class="btn btn-outline btn-small">← Back to dashboard</a>
        <h1>My Profile</h1>
        <p class="page-description">Review your contact details, application status, and uploaded documents.</p>
      </div>
    </div>

    <div class="profile-grid">
      <div class="card profile-card">
        <div class="card-header"><h3 class="card-title">Account Details</h3></div>
        <div class="card-body">
          <div class="profile-row"><span>Email</span><strong><?php echo htmlspecialchars($user['email']); ?></strong></div>
          <div class="profile-row"><span>JAMB Number</span><strong><?php echo htmlspecialchars($user['jamb_no'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Phone</span><strong><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Application Status</span><strong><?php echo htmlspecialchars(ucfirst($user['status'] ?? 'prospective')); ?></strong></div>
          <div class="profile-row"><span>Enrollment Progress</span><strong><?php echo $user['profile_complete'] ? 'Complete' : 'Incomplete'; ?></strong></div>
          <div class="profile-row"><span>Program</span><strong><?php
            $rp = $user['registered_program'] ?? null;
            // if numeric id, try to resolve program name
            if($rp && is_numeric($rp)){
              $pstmt = $db->prepare('SELECT name FROM programs WHERE id = ? LIMIT 1');
              if($pstmt){ $pstmt->bind_param('i', $rp); $pstmt->execute(); $pstmt->bind_result($pname); $pstmt->fetch(); $pstmt->close(); }
              echo htmlspecialchars($pname ?: $rp);
            } else {
              echo htmlspecialchars($rp ?: 'Not registered');
            }
          ?></strong></div>
          <div class="profile-row"><span>Matric Number</span><strong><?php echo htmlspecialchars($user['matric_no'] ?: 'Not assigned'); ?></strong></div>
          <?php if($user['status'] === 'new' && !$user['profile_complete']): ?>
            <div class="profile-note">
              <p>Your account has been admitted. Continue to <a href="new_student.php">complete enrollment</a> and upload the required biodata documents.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card profile-card">
        <div class="card-header"><h3 class="card-title">Enrollment Biodata</h3></div>
        <div class="card-body">
          <div class="profile-row"><span>Full Name</span><strong><?php echo htmlspecialchars($user['full_name'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Gender</span><strong><?php echo htmlspecialchars($user['gender'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Date of Birth</span><strong><?php echo htmlspecialchars($user['dob'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>State of Origin</span><strong><?php echo htmlspecialchars($user['state_of_origin'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>LGA</span><strong><?php echo htmlspecialchars($user['lga'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Nationality</span><strong><?php echo htmlspecialchars($user['nationality'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Marital Status</span><strong><?php echo htmlspecialchars($user['marital_status'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Religion</span><strong><?php echo htmlspecialchars($user['religion'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Education</span><strong><?php echo htmlspecialchars(strtoupper($user['education_type'] ?? 'ND')); ?></strong></div>
          <div class="profile-row"><span>ND Institution</span><strong><?php echo htmlspecialchars($user['nd_institution'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>IT Place</span><strong><?php echo htmlspecialchars($user['it_place'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>IT Duration</span><strong><?php echo htmlspecialchars($user['it_duration'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Address</span><strong><?php echo htmlspecialchars($user['address'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Next of Kin</span><strong><?php echo htmlspecialchars($user['next_of_kin_name'] ?: 'Not provided'); ?></strong></div>
          <div class="profile-row"><span>Next of Kin Contact</span><strong><?php echo htmlspecialchars($user['next_of_kin_phone'] ?: 'Not provided'); ?> / <?php echo htmlspecialchars($user['next_of_kin_email'] ?: 'Not provided'); ?></strong></div>
        </div>
      </div>

      <div class="card profile-card">
        <div class="card-header"><h3 class="card-title">Uploaded Documents</h3></div>
        <div class="card-body">
          <div class="file-summary">
            <div class="file-summary-item">
              <span>O-Level Result</span>
              <?php if(!empty($user['o_level_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['o_level_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
              <?php if(!empty($additionalOlevels)): ?>
                <div style="margin-top:8px">
                  <strong style="font-size:0.95rem">Additional O-Level result(s):</strong>
                  <ul style="margin:6px 0 0 14px">
                    <?php foreach($additionalOlevels as $a): ?>
                      <li><a href="<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($a['label'] ?: 'O-Level'); ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>JAMB Result</span>
              <?php if(!empty($user['jamb_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['jamb_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>Post-UTME Result</span>
              <?php if(!empty($user['post_utme_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['post_utme_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not available</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>Passport Photo</span>
              <?php if(!empty($user['passport_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['passport_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>Birth Certificate / Age Declaration</span>
              <?php if(!empty($user['birth_certificate_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['birth_certificate_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>Indigene Letter</span>
              <?php if(!empty($user['indigene_letter_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['indigene_letter_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>Admission Letter</span>
              <?php if(!empty($user['admission_letter_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['admission_letter_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>Acceptance Letter</span>
              <?php if(!empty($user['acceptance_letter_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['acceptance_letter_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>ND Result</span>
              <?php if(!empty($user['nd_result_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['nd_result_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <div class="file-summary-item">
              <span>IT Letter</span>
              <?php if(!empty($user['it_letter_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['it_letter_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <?php if(strtolower(preg_replace('/\s+/','', $user['education_type'] ?? '')) !== 'hnd'): ?>
            <div class="file-summary-item">
              <span>CGPA / Result Evidence</span>
              <?php if(!empty($user['cgpa_path'])): ?>
                <a class="file-link" href="<?php echo htmlspecialchars($user['cgpa_path']); ?>" target="_blank">View</a>
              <?php else: ?>
                <span class="file-missing">Not uploaded</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <hr style="margin:14px 0">
          <div>
            <h4 style="margin:8px 0">Other Documents</h4>
            <div id="otherDocsList">
              <?php if(empty($otherDocs)): ?>
                <div style="color:var(--muted);font-size:0.95rem">No additional documents uploaded.</div>
              <?php else: ?>
                <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px">
                  <?php foreach($otherDocs as $od): ?>
                    <li style="display:flex;justify-content:space-between;align-items:center;padding:8px;border:1px solid var(--soft-border);border-radius:6px;background:var(--card-bg)">
                      <div>
                        <div style="font-weight:600"><?php echo htmlspecialchars($od['label']); ?></div>
                        <div style="font-size:0.85rem;color:var(--muted)"><a href="<?php echo htmlspecialchars($od['file_path']); ?>" target="_blank">View</a> • Uploaded <?php echo htmlspecialchars($od['created_at']); ?></div>
                      </div>
                      <div>
                        <a href="<?php echo htmlspecialchars($od['file_path']); ?>" class="btn btn-outline btn-small" target="_blank">Download</a>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>

            <form id="otherUploadForm" style="margin-top:12px;display:flex;flex-direction:column;gap:8px;align-items:stretch">
              <label for="otherLabel" style="font-weight:600;margin-bottom:4px">Document label</label>
              <textarea name="label" id="otherLabel" placeholder="Document label (e.g. Medical Certificate)" class="form-input" style="width:100%;min-height:44px;padding:8px" rows="2" required></textarea>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="file" name="other_file" id="otherFile" accept=".pdf,.jpg,.jpeg,.png" required style="flex:1">
                <button type="submit" class="btn btn-primary">Upload</button>
              </div>
            </form>
            <div id="otherUploadMsg" style="margin-top:8px"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
<script>
  (function(){
    const form = document.getElementById('otherUploadForm');
    const fileEl = document.getElementById('otherFile');
    const labelEl = document.getElementById('otherLabel');
    const msg = document.getElementById('otherUploadMsg');
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      if(!fileEl.files.length) return;
      const fd = new FormData();
      fd.append('other_file', fileEl.files[0]);
      fd.append('label', labelEl.value || 'Other Document');
      fd.append('ajax','1');
      msg.textContent = 'Uploading...';
      try{
        const res = await fetch('upload.php', { method: 'POST', credentials: 'same-origin', body: fd });
        const data = await res.json();
        if(data.success){
          msg.style.color = 'green';
          msg.textContent = 'Uploaded successfully.';
          // refresh page to show new doc
          setTimeout(()=> location.reload(), 800);
        } else {
          msg.style.color = 'crimson';
          msg.textContent = (data.errors && data.errors.join) ? data.errors.join(' | ') : (data.messages && data.messages.join ? data.messages.join(' | ') : 'Upload failed');
        }
      }catch(err){
        msg.style.color = 'crimson';
        msg.textContent = 'Error: '+err.message;
      }
    });
  })();
</script>
</html>
