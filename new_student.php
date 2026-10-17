<?php
require_once __DIR__.'/functions.php';
if(empty($_SESSION['user_id'])) redirect('login.php');
$db = db_connect();
$uid = intval($_SESSION['user_id']);

// load user record with expanded fields
$stmt = $db->prepare('SELECT status,profile_complete,full_name,email,gender,dob,state_of_origin,lga,nationality,marital_status,religion,address,passport_path,parent_name,parent_phone,parent_email,birth_certificate_path,indigene_letter_path,nin,admission_letter_path,acceptance_letter_path,undertaking_form_path,education_type,it_letter_path,nd_result_path,cgpa_path,nd_institution,it_place,it_duration,weight,height,blood_group,genotype,allergies,medical_history,xray_path,blood_test_path,next_of_kin_name,next_of_kin_email,next_of_kin_phone,next_of_kin_address,jamb_no FROM users WHERE id = ?');
$stmt->bind_param('i',$uid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if(!$user) redirect('dashboard.php');

if(!in_array($user['status'], ['new','returning'])){
    $_SESSION['flash_error'] = 'Enrollment access is reserved for admitted students. Please check your dashboard for updates.';
    redirect('dashboard.php');
}

$step = intval($_GET['step'] ?? 1);
if($step < 1 || $step > 4) $step = 1;
$error = '';
$success = '';

// load programs for selection
$programs = [];
$programGroups = [];
$requestedPrograms = [
  'National Diploma in Animal Health and Production Technology',
  'National Diploma in Science Laboratory Technology',
  'National Diploma in Fisheries Technology',
  'National Diploma in Computer Science',
  'National Diploma in Statistics',
  'National Diploma in Veterinary Laboratory Technology',
  'Higher National Diploma in Animal Health Technology',
  'Higher National Diploma in Animal Production Technology',
  'Higher National Diploma in Agricultural Extension and Management',
  'Higher National Diploma in Fisheries Technology',
  'Higher National Diploma in Software and Web Development Option',
  'Higher National Diploma in Network and Cloud Computing Option',
  'Higher National Diploma in Agribusiness Management',
  'Higher National Diploma in Pasture and Range Management',
  'Higher National Diploma in Statistics',
  'Higher National Diploma in Biology/Microbiology Option',
  'Higher National Diploma in Chemistry/Biochemistry',
  'Higher National Diploma in Environmental Biology',
  'Higher National Diploma in Physics/Electronics'
];

// ensure the requested programs exist in the database and load them for the form
$selectCols = 'id,name';
if(function_exists('column_exists') && column_exists($db, DB_NAME, 'programs', 'code')){
  $selectCols .= ',code';
}

$resP = $db->query("SELECT $selectCols FROM programs ORDER BY name");
if($resP){
  while($r = $resP->fetch_assoc()){
    $pid = intval($r['id']);
    $pname = $r['name'];
    if(!in_array($pname, $requestedPrograms, true)){
      continue;
    }
    $pcode = array_key_exists('code', $r) ? $r['code'] : null;
    $programs[$pid] = $pname;
    $level = 'nd';
    if(stripos($pname, 'Higher National Diploma') !== false || stripos($pname, 'Higher National Diploma in') !== false || stripos($pname, 'Higher National Diploma') === 0){
      $level = 'hnd';
    } elseif(stripos($pname, 'National Diploma') !== false || stripos($pname, 'National Diploma in') !== false){
      $level = 'nd';
    }
    $programGroups[$pname] = [['id' => $pid, 'name' => $pname, 'label' => $pname, 'code' => $pcode, 'level' => $level]];
  }
}

foreach($requestedPrograms as $programName){
  $stmtCheck = $db->prepare('SELECT id FROM programs WHERE name = ? LIMIT 1');
  if($stmtCheck){
    $stmtCheck->bind_param('s', $programName);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    if($stmtCheck->num_rows === 0){
      $stmtInsert = $db->prepare('INSERT INTO programs (name, code, description) VALUES (?, ?, ?)');
      if($stmtInsert){
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $programName), 0, 10));
        $desc = 'Program added for enrollment';
        $stmtInsert->bind_param('sss', $programName, $code, $desc);
        $stmtInsert->execute();
        $stmtInsert->close();
      }
    }
    $stmtCheck->close();
  }
}

$resP = $db->query("SELECT $selectCols FROM programs ORDER BY name");
if($resP){
  $programGroups = [];
  while($r = $resP->fetch_assoc()){
    $pid = intval($r['id']);
    $pname = $r['name'];
    if(!in_array($pname, $requestedPrograms, true)){
      continue;
    }
    $pcode = array_key_exists('code', $r) ? $r['code'] : null;
    $programs[$pid] = $pname;
    $level = 'nd';
    if(stripos($pname, 'Higher National Diploma') !== false){
      $level = 'hnd';
    }
    $programGroups[$pname] = [['id' => $pid, 'name' => $pname, 'label' => $pname, 'code' => $pcode, 'level' => $level]];
  }
}


function sanitize($value){
    return trim($value);
}

function uploadDocument($name, $uid, &$error){
    if(empty($_FILES[$name]) || $_FILES[$name]['error'] === UPLOAD_ERR_NO_FILE){
        return null;
    }
    if($_FILES[$name]['error'] !== UPLOAD_ERR_OK){
    $error = 'Upload failed for '.htmlspecialchars($name).'. (upload error code: '.intval($_FILES[$name]['error']).')';
        return null;
    }
  // server-side validation
  define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB
  $allowed_mimes = ['application/pdf','image/jpeg','image/png'];
  $allowed_exts = ['pdf','jpg','jpeg','png'];
  $tmp = $_FILES[$name]['tmp_name'];
  $size = $_FILES[$name]['size'] ?? 0;
  $orig = $_FILES[$name]['name'] ?? '';
  $type = mime_content_type($tmp);
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if($size > MAX_UPLOAD_BYTES){
    $error = 'File too large for '.htmlspecialchars($name)." (max 5MB).";
    return null;
  }
  if(!in_array($type,$allowed_mimes) || !in_array($ext,$allowed_exts)){
    $error = 'Invalid file type for '.htmlspecialchars($name).'. Allowed: PDF, JPG, PNG.';
    return null;
  }
    $uploadDir = __DIR__.'/uploads';
    if(!is_dir($uploadDir) && !safe_mkdir($uploadDir,0755)){
        $error = 'Cannot create uploads directory.';
        return null;
    }
    $filename = $name.'_user_'.$uid.'_'.time().'_'.basename($_FILES[$name]['name']);
    $safe = 'uploads/'.preg_replace('/[^a-zA-Z0-9._-]/','_', $filename);
    $destPath = __DIR__.'/'. $safe;
    if(move_uploaded_file($tmp, $destPath)){
    @chmod($destPath, 0644);
        return $safe;
    }
  $error = 'Unable to save uploaded file for '.htmlspecialchars($name).'.';
    return null;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  if($step === 1){
    $education_type = in_array($_POST['education_type'] ?? 'nd',['nd','hnd']) ? $_POST['education_type'] : 'nd';
    $registered_program = intval($_POST['registered_program'] ?? 0);
    $full_name = sanitize($_POST['full_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $dob = sanitize($_POST['dob'] ?? '');
    $state_of_origin = sanitize($_POST['state_of_origin'] ?? '');
    $lga = sanitize($_POST['lga'] ?? '');
    $nationality = sanitize($_POST['nationality'] ?? '');
    $marital_status = sanitize($_POST['marital_status'] ?? '');
    $religion = sanitize($_POST['religion'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $jamb_no = sanitize($_POST['jamb_no'] ?? '');

    if(!$full_name || !$gender || !$dob || !$state_of_origin || !$lga || !$nationality || !$marital_status || !$religion || !$address){
      $error = 'Please complete all fields for step 1.';
    } else {
      // validate jamb number format if provided
      if(!empty($jamb_no) && !preg_match('/^[A-Za-z0-9\-]{5,30}$/', $jamb_no)){
        $error = 'Invalid JAMB number format.';
      }
    }
    $specialization = sanitize($_POST['specialization'] ?? '');
    if(empty($error)){
      // prepare HND fields and uploads if applicable
      $nd_institution = sanitize($_POST['nd_institution'] ?? $user['nd_institution']);
      $it_place = sanitize($_POST['it_place'] ?? $user['it_place']);
      $it_duration = sanitize($_POST['it_duration'] ?? $user['it_duration']);
      $it_letter_path = $user['it_letter_path'] ?? null;
      $nd_result_path = $user['nd_result_path'] ?? null;
      $cgpa_path = $user['cgpa_path'] ?? null;
        if($education_type === 'hnd'){
        if(empty($nd_institution) || empty($it_place) || empty($it_duration)){
          $error = 'Please provide ND institution, place of IT and duration for HND applicants.';
        }
        if(empty($error)) $it_letter_path = uploadDocument('it_letter',$uid,$error) ?: $it_letter_path;
        if(empty($error)) $nd_result_path = uploadDocument('nd_result',$uid,$error) ?: $nd_result_path;
        if(empty($error)){
          if(empty($it_letter_path) || empty($nd_result_path)){
            $error = 'HND applicants must upload IT letter and ND result.';
          }
        }
      }
      if(empty($error)){
        // ensure specialization column exists
        try{
          if(function_exists('column_exists') && !column_exists($db, DB_NAME, 'users', 'specialization')){
            $db->query("ALTER TABLE users ADD COLUMN specialization VARCHAR(255) DEFAULT NULL");
          }
        } catch(Exception $e){ /* ignore */ }

        $stmt = $db->prepare('UPDATE users SET education_type=?, registered_program=?, jamb_no=?, full_name=?, gender=?, dob=?, state_of_origin=?, lga=?, nationality=?, marital_status=?, religion=?, address=?, nd_institution=?, it_place=?, it_duration=?, it_letter_path=?, nd_result_path=?, cgpa_path=? WHERE id = ?');
        // Explicit type map — do not infer types from position; registered_program and id
        // are integers, everything else here is a string. A generic "last N chars are ii"
        // scheme breaks the moment a column gets reordered.
        $types = 'sisssssssssssssssi';
        // education_type(s) registered_program(i) jamb_no(s) full_name(s) gender(s) dob(s)
        // state_of_origin(s) lga(s) nationality(s) marital_status(s) religion(s) address(s)
        // nd_institution(s) it_place(s) it_duration(s) it_letter_path(s) nd_result_path(s)
        // cgpa_path(s) id(i)
        $params = [$education_type, $registered_program, $jamb_no, $full_name, $gender, $dob, $state_of_origin, $lga, $nationality, $marital_status, $religion, $address, $nd_institution, $it_place, $it_duration, $it_letter_path, $nd_result_path, $cgpa_path, $uid];
        // bind_param requires references
        $refs = [];
        foreach($params as $k => $v) $refs[$k] = &$params[$k];
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
      if($stmt->execute()){
        // persist specialization separately (if provided)
        if(!empty($specialization)){
          try{
            $u2 = $db->prepare('UPDATE users SET specialization = ? WHERE id = ?');
            if($u2){ $u2->bind_param('si', $specialization, $uid); $u2->execute(); $u2->close(); }
          } catch(Exception $e){ /* ignore */ }
        }
        redirect('new_student.php?step=2');
      }
        $error = 'Unable to save biodata. Please try again.';
      }
    }
  } elseif($step === 2){
    $parent_name = sanitize($_POST['parent_name'] ?? '');
    $parent_phone = sanitize($_POST['parent_phone'] ?? '');
    $parent_email = filter_var($_POST['parent_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $nin = sanitize($_POST['nin'] ?? '');
    $next_of_kin_name = sanitize($_POST['next_of_kin_name'] ?? '');
    $next_of_kin_email = filter_var($_POST['next_of_kin_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $next_of_kin_phone = sanitize($_POST['next_of_kin_phone'] ?? '');
    $next_of_kin_address = sanitize($_POST['next_of_kin_address'] ?? '');

    if(!$parent_name || !$parent_phone || !$parent_email || !$nin){
      $error = 'Please provide parent/guardian details and NIN.';
    } else {
      $passport_path = uploadDocument('passport_photo',$uid,$error) ?: $user['passport_path'];
      if(empty($error)) $birth_certificate_path = uploadDocument('birth_certificate',$uid,$error) ?: $user['birth_certificate_path'];
      if(empty($error)) $indigene_letter_path = uploadDocument('indigene_letter',$uid,$error) ?: $user['indigene_letter_path'];

      if(empty($error)){
        $stmt = $db->prepare('UPDATE users SET parent_name=?, parent_phone=?, parent_email=?, nin=?, passport_path=?, birth_certificate_path=?, indigene_letter_path=?, next_of_kin_name=?, next_of_kin_email=?, next_of_kin_phone=?, next_of_kin_address=? WHERE id = ?');
        $stmt->bind_param('sssssssssssi', $parent_name, $parent_phone, $parent_email, $nin, $passport_path, $birth_certificate_path, $indigene_letter_path, $next_of_kin_name, $next_of_kin_email, $next_of_kin_phone, $next_of_kin_address, $uid);
        if($stmt->execute()){
          redirect('new_student.php?step=3');
        }
        $error = 'Unable to save parent and document details.';
      }
    }
  } elseif($step === 3) {
    // Medical form submission is gated on a Bursar-verified Paystack payment
    // recorded in the `payments` table — never on anything the client posts here.
    // NOTE: the bank-receipt upload (action=upload_receipt) is handled separately,
    // further down in the render block, and is intentionally allowed through even
    // when unverified — that's the whole point of it. We skip this gate for it here
    // so it doesn't get stamped with a misleading "must be verified" error first.
    if(!(isset($_POST['action']) && $_POST['action'] === 'upload_receipt')){
        $medStmt = $db->prepare("SELECT status FROM payments WHERE student_id = ? AND title = 'medical' LIMIT 1");
        $medStmt->bind_param('i', $uid);
        $medStmt->execute();
        $medRow = $medStmt->get_result()->fetch_assoc();
        $medStmt->close();
        $medical_fee_verified = $medRow && $medRow['status'] === 'verified';

        if (!$medical_fee_verified) {
            $error = 'Medical clearance requires a Bursar-verified payment. Please complete payment before continuing.';
        } else {
            // Process Medical Data
            $weight = sanitize($_POST['weight'] ?? '');
            $height = sanitize($_POST['height'] ?? '');
            $blood_group = sanitize($_POST['blood_group'] ?? '');
            $genotype = sanitize($_POST['genotype'] ?? '');
            $allergies = sanitize($_POST['allergies'] ?? '');
            $medical_history = sanitize($_POST['medical_history'] ?? '');

            if(!$weight || !$height || !$blood_group || !$genotype){
                $error = 'Please provide your medical basics: weight, height, blood group and genotype.';
            }

            if(empty($error)){
                $xray_path = uploadDocument('xray_scan', $uid, $error) ?: ($user['xray_path'] ?? null);
            }

            if(empty($error)){
                $blood_test_path = uploadDocument('blood_test', $uid, $error) ?: ($user['blood_test_path'] ?? null);
            }

            // Final check: verification could theoretically lapse between page load and submission
            $medStmt2 = $db->prepare("SELECT status FROM payments WHERE student_id = ? AND title = 'medical' LIMIT 1");
            $medStmt2->bind_param('i', $uid);
            $medStmt2->execute();
            $medRow2 = $medStmt2->get_result()->fetch_assoc();
            $medStmt2->close();
            $still_verified = $medRow2 && $medRow2['status'] === 'verified';

            if(empty($error) && $still_verified){
                $stmt = $db->prepare('UPDATE users SET weight=?, height=?, blood_group=?, genotype=?, allergies=?, medical_history=?, xray_path=?, blood_test_path=? WHERE id = ?');
                $stmt->bind_param('ssssssssi', $weight, $height, $blood_group, $genotype, $allergies, $medical_history, $xray_path, $blood_test_path, $uid);

                if($stmt->execute()){
                    redirect('new_student.php?step=4');
                } else {
                    $error = 'Unable to save medical information. Please try again.';
                }
            } elseif(empty($error)) {
                $error = 'Payment verification could not be confirmed. Please try again.';
            }
        }
    }
} elseif($step === 4) {
    $admission_letter_path = uploadDocument('admission_letter',$uid,$error) ?: $user['admission_letter_path'];
    if(empty($admission_letter_path)){
      $error = 'Please upload your admission letter.';
    }
    if(empty($error)){
      $acceptance_letter_path = uploadDocument('acceptance_letter',$uid,$error) ?: $user['acceptance_letter_path'];
    }
    if(empty($error)){
      $undertaking_form_path = uploadDocument('undertaking_form',$uid,$error) ?: $user['undertaking_form_path'];
    }
    if(empty($error) && empty($acceptance_letter_path)){
      $error = 'Please upload your acceptance letter.';
    }
    if(empty($error) && empty($undertaking_form_path)){
      $error = 'Please upload your student undertaking form.';
    }
    // handle optional labeled other document upload (moved to step 4)
    if(empty($error)){
      $other_label = sanitize($_POST['other_label'] ?? '');
      if(!empty($_FILES['other_file']) && $_FILES['other_file']['error'] !== UPLOAD_ERR_NO_FILE){
        $other_path = uploadDocument('other_file', $uid, $error);
        if(empty($error) && $other_path){
          try{
            $stmtO = $db->prepare('INSERT INTO student_other_documents (user_id, file_path, label) VALUES (?, ?, ?)');
            if($stmtO){
              $lbl = $other_label ?: 'Other Document';
              $stmtO->bind_param('iss', $uid, $other_path, $lbl);
              $stmtO->execute();
            }
          } catch(Exception $e){ /* ignore */ }
        }
      }
    }
    if(empty($error)){
      $stmt = $db->prepare('UPDATE users SET admission_letter_path=?, acceptance_letter_path=?, undertaking_form_path=?, profile_complete = 1 WHERE id = ?');
      $stmt->bind_param('sssi', $admission_letter_path, $acceptance_letter_path, $undertaking_form_path, $uid);
      if($stmt->execute()){
        // Generate program-aware matric number and update user record
        try{
          // fetch the user's education type, registered program and specialization (fresh)
          $q = $db->prepare('SELECT education_type, registered_program, specialization FROM users WHERE id = ? LIMIT 1');
          $edu_type = 'nd'; $prog_id = null; $spec = '';
          if($q){
            $q->bind_param('i', $uid);
            $q->execute();
            $q->bind_result($edu_type, $prog_id, $spec);
            $q->fetch();
            $q->close();
          }
          $prog_code = '';
          if(!empty($prog_id)){
            // prefer explicit program code if present in programs table
            if(function_exists('column_exists') && column_exists($db, DB_NAME, 'programs', 'code')){
              $p = $db->prepare('SELECT code FROM programs WHERE id = ? LIMIT 1');
              if($p){
                $p->bind_param('i', $prog_id);
                $p->execute();
                $p->bind_result($pc);
                if($p->fetch()) $prog_code = $pc;
                $p->close();
              }
            }
            if(empty($prog_code)){
              $p = $db->prepare('SELECT name FROM programs WHERE id = ? LIMIT 1');
              if($p){
                $p->bind_param('i', $prog_id);
                $p->execute();
                $p->bind_result($pname);
                if($p->fetch()){
                  $derived = preg_replace('/[^A-Za-z]/','',strtoupper($pname));
                  $prog_code = substr($derived,0,3);
                }
                $p->close();
              }
            }
          }
          $matric = generate_program_matric($db, $prog_code, $edu_type, $spec);
          if($matric){
            // persist matric, set role to student, and set status via guarded updater
            $u2 = $db->prepare("UPDATE users SET matric_no = ?, role = 'student' WHERE id = ?");
            if($u2){
              $u2->bind_param('si', $matric, $uid);
              $u2->execute();
              $u2->close();
            }
            update_user_status($db, $uid, 'new', 'enrollment');

            $_SESSION['flash_success'] = 'Enrollment biodata complete. Your matric number: ' . $matric;
          } else {
            $_SESSION['flash_success'] = 'Enrollment biodata complete. (Matric number generation failed — please contact admin)';
          }
        } catch(Exception $e){
          $_SESSION['flash_success'] = 'Enrollment biodata complete. (Matric assignment error)';
        }
        redirect('dashboard.php');
      }
      $error = 'Unable to complete enrollment. Please try again.';
    }
  } else {
    $error = 'Invalid enrollment step.';
  }
}

$stmt = $db->prepare('SELECT status,profile_complete,full_name,email,gender,dob,state_of_origin,lga,nationality,marital_status,religion,address,passport_path,parent_name,parent_phone,parent_email,birth_certificate_path,indigene_letter_path,nin,admission_letter_path,acceptance_letter_path,undertaking_form_path,education_type,it_letter_path,nd_result_path,cgpa_path,nd_institution,it_place,it_duration,weight,height,blood_group,genotype,allergies,medical_history,xray_path,blood_test_path,next_of_kin_name,next_of_kin_email,next_of_kin_phone,next_of_kin_address,jamb_no FROM users WHERE id = ?');
$stmt->bind_param('i',$uid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stepTitles = [1 => 'Basic Biodata', 2 => 'Parent & Supporting Documents', 3 => 'Medical Information', 4 => 'Admission Documents & Undertaking'];
$stepHeadings = [
    1 => 'Step 1 — Personal Biodata',
    2 => 'Step 2 — Parent / Guardian and Supporting Documentation',
    3 => 'Step 3 — Medical Information',
    4 => 'Step 4 — Admission Documents & Student Undertaking'
];
$stepDescription = [
    1 => 'Complete your core biodata fields so your admission record is updated.',
    2 => 'Provide guardian contact details and upload your identity documents.',
    3 => 'Provide medical details and upload your medical clearance documentation.',
    4 => 'Upload your admission documents and student undertaking form to finish enrollment.'
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>New Student Enrollment — Federal College of Animal Health and Production Technology</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <div class="page-header">
      <div>
        <a href="dashboard.php" class="btn btn-outline btn-small">← Back to dashboard</a>
        <h1>New Student Enrollment</h1>
        <p class="page-description">Follow the four-step enrollment workflow to complete your new student registration.</p>
      </div>
    </div>

    <?php if(!empty($error)): ?>
      <div class="alert alert-error"><span class="alert-icon">✕</span> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if(!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success"><span class="alert-icon">✓</span> <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if(!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-error"><span class="alert-icon">✕</span> <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="wizard-bar">
      <?php for($i=1;$i<=4;$i++): ?>
        <div class="wizard-step <?php echo $i === $step ? 'active' : ($i < $step ? 'completed' : ''); ?>">
          <span class="wizard-step-number"><?php echo $i; ?></span>
          <span><?php echo htmlspecialchars($stepTitles[$i]); ?></span>
        </div>
      <?php endfor; ?>
    </div>

    <?php if($user['profile_complete']): ?>
      <div class="card card-success" style="max-width: 800px; margin: 0 auto;">
        <div class="card-body">
          <h2>Enrollment Complete</h2>
          <p>Your biodata is complete. You can now return to the dashboard or review your profile.</p>
          <a href="profile.php" class="btn btn-primary">View Profile</a>
        </div>
      </div>
    <?php else: ?>
      <div class="card" style="max-width: 800px; margin: 0 auto;">
        <div class="card-header"><h2><?php echo htmlspecialchars($stepHeadings[$step]); ?></h2></div>
        <div class="card-body">
          <p><?php echo htmlspecialchars($stepDescription[$step]); ?></p>
          <div id="form-errors" class="form-errors" style="display:none;color:#b00020;margin-bottom:10px;"></div>
          <?php if($step === 1): ?>
            <form method="post" enctype="multipart/form-data">
              <label>Education Type</label>
              <select name="education_type" required>
                <option value="nd" <?php if(($user['education_type'] ?? 'nd')==='nd') echo 'selected'; ?>>National Diploma (ND)</option>
                <option value="hnd" <?php if(($user['education_type'] ?? '')==='hnd') echo 'selected'; ?>>Higher National Diploma (HND)</option>
              </select>

              <label>Program</label>
              <select name="registered_program" id="registered_program" required>
                <option value="">-- Choose program --</option>
                <?php foreach($programGroups as $programName => $items): ?>
                  <?php foreach($items as $item): $level = $item['level'] ?? 'nd'; ?>
                    <option value="<?php echo intval($item['id']); ?>" data-level="<?php echo htmlspecialchars($level); ?>" <?php if(intval($user['registered_program'] ?? 0) === intval($item['id'])) echo 'selected'; ?>><?php echo htmlspecialchars($programName); ?></option>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </select>

              <script>
                var programGroups = <?php echo json_encode($programGroups, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
                var regSel = document.getElementById('registered_program');
                var eduSel = document.querySelector('select[name="education_type"]');
                var allProgramOptions = [];
                if(regSel){
                  Array.from(regSel.options).forEach(function(o){
                    if(!o.value) return;
                    allProgramOptions.push({ value: o.value, text: o.textContent, level: (o.getAttribute('data-level')||'nd').toLowerCase(), attrs: (function(){ var a={}; for(var i=0;i<o.attributes.length;i++){ a[o.attributes[i].name]=o.attributes[i].value;} return a; })() });
                  });
                }

                function rebuildProgramOptions(){
                  if(!regSel || !eduSel) return;
                  var edu = (eduSel.value || 'nd').toLowerCase();
                  var current = regSel.value;
                  while(regSel.options.length > 1) regSel.remove(1);
                  allProgramOptions.forEach(function(o){
                    if(o.level === edu){
                      var opt = document.createElement('option');
                      opt.value = o.value; opt.text = o.text; if(o.attrs){ for(var k in o.attrs){ if(k === 'value' || k === 'label') continue; opt.setAttribute(k, o.attrs[k]); } }
                      regSel.appendChild(opt);
                    }
                  });
                  try{ if(current && Array.from(regSel.options).some(function(x){ return x.value === current;})) regSel.value = current; else regSel.value = ''; } catch(e){ regSel.value=''; }
                }

                if(eduSel){ eduSel.addEventListener('change', rebuildProgramOptions); }
                rebuildProgramOptions();
              </script>

              <label>Full Name</label>
              <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
              <label>Gender</label>
              <select name="gender" required>
                <option value="">Select gender</option>
                <option value="Male" <?php if($user['gender']==='Male') echo 'selected'; ?>>Male</option>
                <option value="Female" <?php if($user['gender']==='Female') echo 'selected'; ?>>Female</option>
                <option value="Other" <?php if($user['gender']==='Other') echo 'selected'; ?>>Other</option>
              </select>
              <label>Date of Birth</label>
              <input type="date" name="dob" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>" required>
              <label>State of Origin</label>
              <input type="text" name="state_of_origin" value="<?php echo htmlspecialchars($user['state_of_origin'] ?? ''); ?>" required>
              <label>LGA</label>
              <input type="text" name="lga" value="<?php echo htmlspecialchars($user['lga'] ?? ''); ?>" required>
              <label>Nationality</label>
              <input type="text" name="nationality" value="<?php echo htmlspecialchars($user['nationality'] ?? ''); ?>" required>
              <label>Marital Status</label>
              <select name="marital_status" required>
                <option value="">Select status</option>
                <option value="Single" <?php if($user['marital_status']==='Single') echo 'selected'; ?>>Single</option>
                <option value="Married" <?php if($user['marital_status']==='Married') echo 'selected'; ?>>Married</option>
                <option value="Other" <?php if($user['marital_status']==='Other') echo 'selected'; ?>>Other</option>
              </select>
              <label>Religion</label>
              <select name="religion" required>
                <option value="">Select religion</option>
                <option value="Muslim" <?php if(($user['religion'] ?? '')==='Muslim') echo 'selected'; ?>>Muslim</option>
                <option value="Christianity" <?php if(($user['religion'] ?? '')==='Christianity') echo 'selected'; ?>>Christianity</option>
                <option value="Other" <?php if(($user['religion'] ?? '')==='Other') echo 'selected'; ?>>Other</option>
              </select>
              <label>JAMB Number</label>
              <input type="text" name="jamb_no" value="<?php echo htmlspecialchars($user['jamb_no'] ?? ''); ?>">
              <label>Address</label>
              <textarea name="address" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
              <div id="hnd-section" style="display:none;">
                <hr>
                <h4>HND Additional Requirements</h4>
                <label>Institution where ND was obtained</label>
                <input type="text" name="nd_institution" value="<?php echo htmlspecialchars($user['nd_institution'] ?? ''); ?>">
                <label>Place of Industrial Training (IT)</label>
                <input type="text" name="it_place" value="<?php echo htmlspecialchars($user['it_place'] ?? ''); ?>">
                <label>Duration of IT</label>
                <input type="text" name="it_duration" value="<?php echo htmlspecialchars($user['it_duration'] ?? ''); ?>">
                <label>Industrial Training Letter (PDF/JPG/PNG)</label>
                <input type="file" name="it_letter" accept=".pdf,.jpg,.jpeg,.png">
                <?php if(!empty($user['it_letter_path'])): ?>
                  <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['it_letter_path']); ?>" target="_blank">View</a></p>
                <?php endif; ?>
                <label>ND Result (PDF/JPG/PNG)</label>
                <input type="file" name="nd_result" accept=".pdf,.jpg,.jpeg,.png">
                <?php if(!empty($user['nd_result_path'])): ?>
                  <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['nd_result_path']); ?>" target="_blank">View</a></p>
                <?php endif; ?>
              </div>
              <button type="submit" class="btn btn-primary">Continue to Step 2</button>
            </form>
          <?php elseif($step === 2): ?>
            <form method="post" enctype="multipart/form-data">
              <label>Parent / Guardian Name</label>
              <input type="text" name="parent_name" value="<?php echo htmlspecialchars($user['parent_name'] ?? ''); ?>" required>
              <label>Parent / Guardian Phone</label>
              <input type="text" name="parent_phone" value="<?php echo htmlspecialchars($user['parent_phone'] ?? ''); ?>" required>
              <label>Parent / Guardian Email</label>
              <input type="email" name="parent_email" value="<?php echo htmlspecialchars($user['parent_email'] ?? ''); ?>" required>
              <label>NIN</label>
              <input type="text" name="nin" value="<?php echo htmlspecialchars($user['nin'] ?? ''); ?>" required>
              <label>Next of Kin Full Name</label>
              <input type="text" name="next_of_kin_name" value="<?php echo htmlspecialchars($user['next_of_kin_name'] ?? ''); ?>">
              <label>Next of Kin Email</label>
              <input type="email" name="next_of_kin_email" value="<?php echo htmlspecialchars($user['next_of_kin_email'] ?? ''); ?>">
              <label>Next of Kin Phone</label>
              <input type="text" name="next_of_kin_phone" value="<?php echo htmlspecialchars($user['next_of_kin_phone'] ?? ''); ?>">
              <label>Next of Kin Residential Address</label>
              <input type="text" name="next_of_kin_address" value="<?php echo htmlspecialchars($user['next_of_kin_address'] ?? ''); ?>">
              <label>Passport Photo (PDF/JPG/PNG)</label>
              <input type="file" name="passport_photo" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['passport_path']) ? 'required' : ''; ?>>
              <?php if(!empty($user['passport_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['passport_path']); ?>" target="_blank">View</a></p>
              <?php endif; ?>
              <label>Birth Certificate / Age Declaration (PDF/JPG/PNG)</label>
              <input type="file" name="birth_certificate" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['birth_certificate_path']) ? 'required' : ''; ?> >
              <?php if(!empty($user['birth_certificate_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['birth_certificate_path']); ?>" target="_blank">View</a></p>
              <?php endif; ?>
              <label>Indigene Letter (PDF/JPG/PNG)</label>
              <input type="file" name="indigene_letter" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['indigene_letter_path']) ? 'required' : ''; ?>>
              <?php if(!empty($user['indigene_letter_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['indigene_letter_path']); ?>" target="_blank">View</a></p>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary">Continue to Step 3</button>
            </form>
          <?php elseif ($step === 3): ?>
    <?php
    // Fetch fee schedule for the student's level
    $student_level = $user['education_type'] ?? 'nd';
    $fee_schedule = [
        'medical' => 7400.00
    ];

    // Fetch existing payments for this user to check status
    $payments_lookup = [];
    $stmt_p = $db->prepare("SELECT title, status FROM payments WHERE student_id = ?");
    if ($stmt_p) {
        $stmt_p->bind_param('i', $uid);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        while ($row = $res_p->fetch_assoc()) {
            $payments_lookup[$row['title']] = $row;
        }
        $stmt_p->close();
    }

    // Handle bank receipt upload submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_receipt') {
        $fee_title = sanitize($_POST['fee_title'] ?? '');
        $fee_amount = floatval($_POST['fee_amount'] ?? 0);
        $receipt_path = uploadDocument('receipt_file', $uid, $error);

        if (empty($error) && $receipt_path) {
            $stmt_ins = $db->prepare("INSERT INTO payments (student_id, title, amount, receipt_path, status) VALUES (?, ?, ?, ?, 'paid') ON DUPLICATE KEY UPDATE receipt_path=?, status='paid'");
            if ($stmt_ins) {
                $stmt_ins->bind_param('isdss', $uid, $fee_title, $fee_amount, $receipt_path, $receipt_path);
                $stmt_ins->execute();
                $stmt_ins->close();
            }
            $_SESSION['flash_success'] = 'Bank receipt uploaded successfully. Awaiting Bursar verification.';
            redirect('new_student.php?step=3');
        }
    }

    // Check if the medical fee is verified to unlock the form
    $medical_info = $payments_lookup['medical'] ?? null;
    $is_verified  = $medical_info && $medical_info['status'] === 'verified';
    $is_paid      = $medical_info && $medical_info['status'] === 'paid';

    // Check pending status
    $is_pending = false;
    $pending_check = $db->prepare("SELECT id FROM payments WHERE student_id=? AND title='medical' AND status='pending' LIMIT 1");
    if($pending_check){
        $pending_check->bind_param('i', $uid);
        $pending_check->execute();
        $pending_check->store_result();
        $is_pending = ($pending_check->num_rows > 0);
        $pending_check->close();
    }
    ?>

    <div id="pane-bursary" class="portal-pane">
        <h3>Bursary Fee Management System</h3>
        <p style="color:#64748b; font-size:0.85rem; margin-top:0.25rem; margin-bottom:1rem;">
            Fees shown for your current level: <strong><?php echo htmlspecialchars(strtoupper($student_level)); ?></strong>
        </p>

        <table class="data-table" style="width:100%; border-collapse:collapse; margin-bottom:1.5rem;">
            <thead>
                <tr style="border-bottom:2px solid #f1f5f9; text-align:left; color:#64748b; font-size:0.9rem;">
                    <th style="padding:10px;">Fee Particular Title</th>
                    <th style="padding:10px;">Pricing</th>
                    <th style="padding:10px;">Action / Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:12px; font-weight:600; color:#334155;">Medical</td>
                    <td style="padding:12px; color:#334155;">₦7,400.00</td>
                    <td style="padding:12px;">
                        <?php if ($is_verified): ?>
                            <span class="status-pill verified" style="color: #10b981; font-weight:bold;">✅ VERIFIED</span>
                        <?php elseif ($is_paid): ?>
                            <span class="status-pill paid" style="color: #f59e0b; font-weight:bold;">💳 PAID — Awaiting Bursar Verification</span>
                        <?php elseif ($is_pending): ?>
                            <span class="status-pill pending" style="color: #3b82f6; font-weight:bold;">🕐 Under Bursar Review</span>
                        <?php else: ?>
                            <div class="fee-action-cell" style="display:flex; flex-direction:column; gap:8px;">
                                <form action="new_student.php?step=3" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_receipt">
                                    <input type="hidden" name="fee_title" value="medical">
                                    <input type="hidden" name="fee_amount" value="7400.00">
                                    <input type="file" name="receipt_file" required accept=".jpg,.jpeg,.png,.pdf" style="font-size:0.8rem; display:block; margin-bottom:0.3rem;">
                                    <button type="submit" class="btn btn-primary" style="font-size:0.78rem; padding:0.35rem 0.7rem; background:#f97316; border:none; color:white; border-radius:4px; cursor:pointer;">📤 Upload Bank Receipt</button>
                                </form>
                                <!-- Real Paystack popup — server independently verifies the transaction
                                     with Paystack before this ever shows as paid. See pay_medical.php. -->
                                <button type="button" class="btn btn-paystack" style="font-size:0.78rem; padding:0.35rem 0.7rem; background:#06b6d4; border:none; color:white; border-radius:4px; cursor:pointer;"
                                        onclick="payWithPaystack('<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES); ?>', 7400.00, 'medical')">
                                    💳 Pay Online
                                </button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <hr style="margin: 2rem 0; border:0; border-top:1px solid #e2e8f0;">

    <!-- Medical Details Form Unlocked Only When Verified -->
    <?php if (!$is_verified): ?>
        <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:1rem; border-radius:6px; color:#1e40af; font-size:0.9rem;">
            ℹ️ Your medical fee must be marked as <strong>VERIFIED</strong> by the Bursar before you can fill out and submit your medical details form.
        </div>
    <?php else: ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem; background:#ecfdf5; color:#065f46; padding:10px; border-radius:6px;">
            <span>✓</span> Medical fee verified! Please proceed with your medical information details below.
        </div>

        <form method="post" enctype="multipart/form-data">
            <label>Weight</label>
            <input type="text" name="weight" value="<?php echo htmlspecialchars($user['weight'] ?? ''); ?>" required>

            <label>Height</label>
            <input type="text" name="height" value="<?php echo htmlspecialchars($user['height'] ?? ''); ?>" required>

            <label>Blood Group</label>
            <input type="text" name="blood_group" value="<?php echo htmlspecialchars($user['blood_group'] ?? ''); ?>" required>

            <label>Genotype</label>
            <input type="text" name="genotype" value="<?php echo htmlspecialchars($user['genotype'] ?? ''); ?>" required>

            <label>Allergies</label>
            <textarea name="allergies"><?php echo htmlspecialchars($user['allergies'] ?? ''); ?></textarea>

            <label>History of Illness / Medical Notes</label>
            <textarea name="medical_history"><?php echo htmlspecialchars($user['medical_history'] ?? ''); ?></textarea>

            <label>X-ray Scan (PDF/JPG/PNG)</label>
            <input type="file" name="xray_scan" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['xray_path']) ? 'required' : ''; ?>>
            <?php if(!empty($user['xray_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['xray_path']); ?>" target="_blank">View</a></p>
            <?php endif; ?>

            <label>Blood Test (PDF/JPG/PNG)</label>
            <input type="file" name="blood_test" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['blood_test_path']) ? 'required' : ''; ?>>
            <?php if(!empty($user['blood_test_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['blood_test_path']); ?>" target="_blank">View</a></p>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Continue to Step 4</button>
        </form>
    <?php endif; ?>


          <?php else: ?>
            <form method="post" enctype="multipart/form-data">
              <label>Admission Letter (PDF/JPG/PNG)</label>
              <input type="file" name="admission_letter" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['admission_letter_path']) ? 'required' : ''; ?> >
              <?php if(!empty($user['admission_letter_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['admission_letter_path']); ?>" target="_blank">View</a></p>
              <?php endif; ?>
              <label>Acceptance Letter (PDF/JPG/PNG)</label>
              <input type="file" name="acceptance_letter" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['acceptance_letter_path']) ? 'required' : ''; ?>>
              <?php if(!empty($user['acceptance_letter_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['acceptance_letter_path']); ?>" target="_blank">View</a></p>
              <?php endif; ?>
              <label>Student Undertaking Form (PDF/JPG/PNG)</label>
              <input type="file" name="undertaking_form" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($user['undertaking_form_path']) ? 'required' : ''; ?>>
              <?php if(!empty($user['undertaking_form_path'])): ?>
                <p class="file-note">Current file: <a href="<?php echo htmlspecialchars($user['undertaking_form_path']); ?>" target="_blank">View</a></p>
              <?php endif; ?>
              <label>Other Documents (label + file)</label>
              <input type="text" name="other_label" placeholder="Document label (e.g. Medical Certificate)">
              <input type="file" name="other_file" accept=".pdf,.jpg,.jpeg,.png">
              <button type="submit" class="btn btn-primary">Finish Enrollment</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
<script src="js/new_student.js"></script>
<!-- Paystack inline JS SDK -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<script>
function payWithPaystack(email, amount, feeKey) {
    if (!email) {
        alert('No email on file for this account — please contact the registry before paying online.');
        return;
    }
    let handler = PaystackPop.setup({
        key: 'pk_test_2dc165be039fe0b87ad69f4b96aac1deb3dfff50', // Public key — safe to expose client-side
        email: email,
        amount: amount * 100, // Paystack expects amount in kobo (e.g. 740000 for ₦7,400)
        currency: 'NGN',
        ref: '' + Math.floor((Math.random() * 1000000000) + 1),
        callback: function(response){
            // Only the reference is sent back — the server independently re-verifies
            // the transaction and amount with Paystack before recording anything.
            window.location.href = 'pay_medical.php?reference=' + encodeURIComponent(response.reference) + '&fee_title=' + encodeURIComponent(feeKey);
        },
        onClose: function(){
            alert('Transaction window closed.');
        }
    });
    handler.openIframe();
}
</script>
</html>