<?php
require_once 'functions.php';
if(empty($_SESSION['user_id'])) redirect('login.php');
$db = db_connect();
$uid = intval($_SESSION['user_id']);
$email = ''; $status = 'prospective'; $o_path = null; $j_path = null; $post_utme_path = null; $profileComplete = 0; $registered_program = null; $registered_courses = null; $matric_no = null; $applicationProgramId = 0; $applicationDepartment = ''; $applicationLevel = ''; $fullName = ''; $registeredCourseSummaries = [];
$programs = get_programs($db);
$programsByDepartment = get_programs_by_department($db);
$departmentOptions = array_keys($programsByDepartment);
$levelOptions = ['ND', 'HND', 'Pre-Degree'];
$res = $db->query("SELECT email,status,o_level_path,jamb_path,post_utme_path,profile_complete,registered_program,registered_courses,matric_no,full_name,specialization,education_type,application_program_id,application_department,application_level FROM users WHERE id = " . $uid);
if($res && $row = $res->fetch_assoc()){
  $email = $row['email'] ?? '';
  $status = $row['status'] ?? $status;
  $o_path = $row['o_level_path'] ?? null;
  $j_path = $row['jamb_path'] ?? null;
  $post_utme_path = $row['post_utme_path'] ?? null;
  $profileComplete = intval($row['profile_complete'] ?? 0);
  $registered_program = $row['registered_program'] ?? null;
  $specialization = $row['specialization'] ?? null;
  $registered_courses = $row['registered_courses'] ?? null;
  $matric_no = $row['matric_no'] ?? null;
  $fullName = trim((string)($row['full_name'] ?? ''));
  $education_type = ($row['education_type'] ?? 'nd');
  $applicationProgramId = intval($row['application_program_id'] ?? 0);
  $applicationDepartment = trim((string)($row['application_department'] ?? ''));
  $applicationLevel = trim((string)($row['application_level'] ?? ''));

  if($applicationProgramId <= 0 && $applicationDepartment !== ''){
    $resolvedProgramId = resolve_program_id_from_selection($db, $applicationDepartment);
    if($resolvedProgramId > 0){
      $stmtBackfill = $db->prepare('UPDATE users SET application_program_id = ? WHERE id = ?');
      if($stmtBackfill){
        $stmtBackfill->bind_param('ii', $resolvedProgramId, $uid);
        $stmtBackfill->execute();
        $stmtBackfill->close();
      }
      $applicationProgramId = $resolvedProgramId;
    }
  }
}

// load registered course summaries (used by the Registration Summary section)
$summaryStmt = $db->prepare('SELECT scr.course_id, c.name AS course_name, c.course_code FROM student_course_registrations scr LEFT JOIN courses c ON c.id = scr.course_id WHERE scr.user_id = ? ORDER BY c.name, c.course_code');
if($summaryStmt){
  $summaryStmt->bind_param('i', $uid);
  $summaryStmt->execute();
  $summaryResult = $summaryStmt->get_result();
  while($summaryRow = $summaryResult->fetch_assoc()){
    $courseName = trim((string)($summaryRow['course_name'] ?? ''));
    if($courseName === ''){
      $courseName = 'Course #' . intval($summaryRow['course_id'] ?? 0);
    }
    $registeredCourseSummaries[] = [
      'id' => intval($summaryRow['course_id'] ?? 0),
      'name' => $courseName,
      'code' => trim((string)($summaryRow['course_code'] ?? ''))
    ];
  }
  $summaryStmt->close();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_application_details'){
  $applicationDepartment = trim((string)($_POST['application_department'] ?? ''));
  if($applicationDepartment === ''){
    $_SESSION['flash_error'] = 'Please select your department.';
  } else {
    $resolvedProgramId = resolve_program_id_from_selection($db, $applicationDepartment);
    $stmt = $db->prepare('UPDATE users SET application_program_id = ?, application_department = ? WHERE id = ?');
    if($stmt){
      $stmt->bind_param('isi', $resolvedProgramId, $applicationDepartment, $uid);
      if($stmt->execute()){
        $_SESSION['flash_success'] = 'Your application details were updated.';
      } else {
        $_SESSION['flash_error'] = 'Unable to update your application details.';
      }
      $stmt->close();
    }
  }
  redirect('dashboard.php');
}
$postsDir = __DIR__ . '/blog/posts';
$files = glob($postsDir . '/*.html');
usort($files, function($a,$b){ return filemtime($b) - filemtime($a); });
$files = array_slice($files,0,3);
$uploads = 0;
if(!empty($o_path)) $uploads++;
if(!empty($j_path)) $uploads++;
if(!empty($post_utme_path)) $uploads++;
$hasRequiredUploads = !empty($o_path) && !empty($j_path);
$showUploadForm = !in_array($status, ['pending','new','completed','failed'], true) && !$hasRequiredUploads;
$showEnrollmentMessage = in_array($status, ['new','completed'], true) && !$profileComplete;
$showAdmissionAcceptance = $status === 'new' && !$profileComplete && !empty($post_utme_path);

// load other uploaded documents (to surface additional O-Level copies)
$otherDocs = [];
$stmtOther = $db->prepare('SELECT id, file_path, label, created_at FROM student_other_documents WHERE user_id = ? ORDER BY created_at DESC');
if($stmtOther){
  $stmtOther->bind_param('i', $uid);
  $stmtOther->execute();
  $resO = $stmtOther->get_result();
  while($r = $resO->fetch_assoc()) $otherDocs[] = $r;
}

$additionalOlevels = array_filter($otherDocs, function($d){
  return preg_match('/o[-\s]*level|waec|neco|ssce|gce/i', $d['label']);
});

$coursesByProgram = [];
$coursesFullData = [];
$userCourses = [];
$programIdToName = [];
$programLookup = [];
$initial_program_id = 0;
if($profileComplete && in_array($status, ['new','completed'])){
  if(function_exists('column_exists') && column_exists($db, DB_NAME, 'programs', 'id') && column_exists($db, DB_NAME, 'courses', 'id')){
    $stmt = $db->prepare('SELECT id, name FROM programs ORDER BY name ASC');
    if($stmt){
      $stmt->execute();
      $result = $stmt->get_result();
      while($row = $result->fetch_assoc()){
        $programLookup[$row['name']] = $row['id'];
        $programIdToName[intval($row['id'])] = $row['name'];
      }
    }

    if(!empty($registered_program)){
      $rpVal = trim($registered_program);
      if(preg_match('/^\d+$/', $rpVal)){
        $initial_program_id = intval($rpVal);
        if(empty($programIdToName[$initial_program_id])) $initial_program_id = 0;
      }
      if(empty($initial_program_id)){
        $q = $db->prepare('SELECT id,name FROM programs WHERE code = ? OR name = ? LIMIT 1');
        if($q){ $q->bind_param('ss', $rpVal, $rpVal); $q->execute(); $q->bind_result($pid,$pname); if($q->fetch()) { $initial_program_id = intval($pid); if(!isset($programIdToName[$initial_program_id])) $programIdToName[$initial_program_id] = $pname; } $q->close(); }
      }
    }
    if(empty($initial_program_id) && !empty($specialization)){
      $specCode = strtoupper(trim($specialization));
      $p = $db->prepare('SELECT id,name FROM programs WHERE code = ? LIMIT 1');
      if($p){ $p->bind_param('s', $specCode); $p->execute(); $r = $p->get_result(); if($rowp = $r->fetch_assoc()){ $initial_program_id = intval($rowp['id']); if(!isset($programIdToName[$initial_program_id])) $programIdToName[$initial_program_id] = $rowp['name']; } $p->close(); }
    }
    if(empty($initial_program_id) && !empty($programIdToName)){
      reset($programIdToName);
      $initial_program_id = intval(key($programIdToName));
    }

    if($initial_program_id > 0){
      $courseCols = ['c.id', 'c.name'];
      $colChecks = ['course_code','credits','instructor','schedule','room','semester','seats_available','seats_total'];
      foreach($colChecks as $col){
        if(function_exists('column_exists') && column_exists($db, DB_NAME, 'courses', $col)){
          $courseCols[] = "c.$col";
        }
      }
      $select = 'SELECT p.name as program_name, p.id as program_id, '.implode(', ', $courseCols).' FROM courses c JOIN programs p ON c.program_id = p.id WHERE c.program_id = ? ORDER BY p.name, c.name';
      $stmt = $db->prepare($select);
      if($stmt){
        $stmt->bind_param('i', $initial_program_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
          if(!isset($coursesByProgram[$row['program_name']])){
            $coursesByProgram[$row['program_name']] = [];
          }
          $coursesByProgram[$row['program_name']][$row['id']] = $row['name'];
          $coursesFullData[$row['id']] = [
            'id' => $row['id'],
            'program_name' => $row['program_name'],
            'program_id' => $row['program_id'] ?? null,
            'name' => $row['name'],
            'course_code' => array_key_exists('course_code', $row) ? ($row['course_code'] ?? 'N/A') : 'N/A',
            'credits' => array_key_exists('credits', $row) ? ($row['credits'] ?? 3) : 3,
            'instructor' => array_key_exists('instructor', $row) ? ($row['instructor'] ?? 'TBA') : 'TBA',
            'schedule' => array_key_exists('schedule', $row) ? ($row['schedule'] ?? 'TBA') : 'TBA',
            'room' => array_key_exists('room', $row) ? ($row['room'] ?? 'TBA') : 'TBA',
            'semester' => array_key_exists('semester', $row) ? ($row['semester'] ?? 'TBA') : 'TBA',
            'seats_available' => array_key_exists('seats_available', $row) ? ($row['seats_available'] ?? 50) : 50,
            'seats_total' => array_key_exists('seats_total', $row) ? ($row['seats_total'] ?? 50) : 50
          ];
        }
      }
    }
  }

  // load user's registered courses
  $stmt = $db->prepare('SELECT course_id FROM student_course_registrations WHERE user_id = ?');
  if($stmt){
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
      $userCourses[] = intval($row['course_id']);
    }
  }
  }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard - Federal College of Animal Health and Production Technology</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* make sure the [hidden] attribute always wins over .card display rules */
    [hidden]{display:none !important;}
  </style>
</head>
<body class="page-dashboard student-full">
  <div class="container">
    <div class="app-root">
      <!-- Sidebar Navigation -->
      <aside class="sidebar">
        <div class="sidebar-header">
          <img src="images/logo.png.jpg" alt="College Logo" class="site-logo">
          <div class="logo">🌿 Federal College of Animal Health and Production Technology</div>
          <div class="logo-subtitle">Student Portal</div>
        </div>
        <nav class="sidebar-nav">
          <ul class="nav-list">
            <li class="nav-item">
              <a href="#" id="registrationSummaryLink" class="nav-link">
                <span class="nav-icon">🧾</span>
                <span class="nav-label">Registration Summary</span>
                <span class="badge" id="registrationSummaryCountDash"><?php echo count($registeredCourseSummaries); ?></span>
              </a>
            </li>
            <li class="nav-item">
              <a href="dashboard.php" class="nav-link nav-link-active">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="#uploads" class="nav-link">
                <span class="nav-icon">📂</span>
                <span class="nav-label">Uploads</span>
                <?php if(!empty($o_path) || !empty($j_path)): ?>
                  <span class="badge">Files</span>
                <?php endif; ?>
              </a>
            </li>
            <!-- Blog merged with Announcements; link removed -->
            <li class="nav-item">
              <a href="announcements.php" class="nav-link">
                <span class="nav-icon">📣</span>
                <span class="nav-label">Announcements</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="profile.php" class="nav-link">
                <span class="nav-icon">👤</span>
                <span class="nav-label">Profile</span>
              </a>
            </li>
            <li class="nav-item nav-separator"></li>
            <li class="nav-item">
              <a href="logout.php" class="nav-link nav-link-danger">
                <span class="nav-icon">🚪</span>
                <span class="nav-label">Logout</span>
              </a>
            </li>
          </ul>
        </nav>
      </aside>

      <!-- Main Content -->
      <main class="main">
        <!-- Top Bar -->
        <div class="topbar">
          <div class="topbar-left">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome to Federal College of Animal Health and Production Technology Student Portal</p>
          </div>
          <div class="topbar-right">
            <div class="user-info">
              <div class="avatar-circle"><?php echo strtoupper(substr($email,0,1)); ?></div>
              <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars(explode('@', $email)[0]); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Flash Messages -->
        <?php if(!empty($_SESSION['flash_success'])): ?>
          <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <span class="alert-text"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></span>
          </div>
        <?php endif; ?>
        <?php if(!empty($_SESSION['flash_error'])): ?>
          <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <span class="alert-text"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></span>
          </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
          <div class="welcome-content">
            <h2 class="welcome-title">Welcome back, <?php echo htmlspecialchars(explode('@', $email)[0]); ?>! 👋</h2>
            <p class="welcome-subtitle">Track your application status and upload documents</p>
          </div>
          <div class="welcome-action">
            <a href="profile.php" class="btn btn-outline">View Profile</a>
          </div>
        </div>

        <!-- Registration Summary (hidden until the sidebar link is clicked) -->
        <section id="registrationSummarySection" class="card registration-summary-card" hidden>
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <h3 class="card-title" style="margin:0">🧾 Registration Summary</h3>
            <a href="export_registration_summary.php" class="btn btn-primary btn-small" download="course-registration-summary.pdf">Download PDF</a>
          </div>
          <div class="card-body">
            <div style="display:grid;gap:16px">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px">
                <div style="padding:14px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:12px">
                  <div style="font-size:0.8rem;color:var(--muted);margin-bottom:6px">Name</div>
                  <div style="font-weight:700;color:var(--text);"><?php echo htmlspecialchars($fullName !== '' ? $fullName : explode('@', $email)[0]); ?></div>
                </div>
                <div style="padding:14px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:12px">
                  <div style="font-size:0.8rem;color:var(--muted);margin-bottom:6px">Department</div>
                  <div style="font-weight:700;color:var(--text);"><?php echo htmlspecialchars($applicationDepartment !== '' ? $applicationDepartment : 'Not set'); ?></div>
                </div>
                <div style="padding:14px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:12px">
                  <div style="font-size:0.8rem;color:var(--muted);margin-bottom:6px">Matric Number</div>
                  <div style="font-weight:700;color:var(--text);"><?php echo htmlspecialchars($matric_no ?: 'Not assigned'); ?></div>
                </div>
              </div>
              <div>
                <div style="font-size:0.85rem;color:var(--muted);margin-bottom:10px">Registered Courses</div>
                <ul id="registrationSummaryListDash" style="list-style:none;padding:0;margin:0;display:grid;gap:10px">
                  <?php if(empty($registeredCourseSummaries)): ?>
                    <li style="padding:12px;background:rgba(249,115,22,0.05);border:1px solid var(--soft-border);border-radius:12px;color:var(--muted)">No courses registered yet.</li>
                  <?php else: foreach($registeredCourseSummaries as $summaryItem): ?>
                    <li style="padding:12px;background:rgba(255,255,255,0.9);border:1px solid var(--soft-border);border-radius:12px;color:var(--text)"><?php echo htmlspecialchars($summaryItem['name']); ?></li>
                  <?php endforeach; endif; ?>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <!-- Compact Stats Grid -->
        <div style="display:grid;grid-template-columns:1fr;gap:12px;margin:24px 0">
          <!-- Status Stat -->
          <div style="padding:14px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:10px;display:flex;align-items:center;gap:12px">
            <div style="font-size:2rem">📋</div>
            <div style="flex:1;min-width:0">
              <div style="font-size:0.85rem;color:var(--muted);margin-bottom:4px">Status</div>
              <div style="font-weight:700;font-size:1rem;word-break:break-word"><?php echo ucfirst(htmlspecialchars($status)); ?></div>
            </div>
          </div>
          <!-- Files Stat -->
          <div style="padding:14px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:10px;display:flex;align-items:center;gap:12px">
            <div style="font-size:2rem">📁</div>
            <div style="flex:1;min-width:0">
              <div style="font-size:0.85rem;color:var(--muted);margin-bottom:4px">Files Uploaded</div>
              <div style="font-weight:700;font-size:1rem"><?php echo $uploads; ?> <span style="color:var(--muted);font-weight:400">/ 3</span></div>
            </div>
          </div>
          <!-- Next Step Stat -->
          <div style="padding:14px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:10px;display:flex;align-items:center;gap:12px">
            <div style="font-size:2rem">🎯</div>
            <div style="flex:1;min-width:0">
              <div style="font-size:0.85rem;color:var(--muted);margin-bottom:4px">Next Step</div>
              <div style="font-weight:700;font-size:0.9rem;word-break:break-word;display:flex;flex-direction:column;align-items:flex-start;gap:8px">
                <div>
                  <?php
                    if($status === 'pending'){
                      echo 'Await POST-UTME, check latest news & updates consecutively for the date and venue and other news in the school';
                    } elseif($showAdmissionAcceptance){
                      echo 'Accept admission';
                    } elseif($status === 'new' && !$profileComplete){
                      echo 'Complete enrollment';
                    } elseif($status === 'new' && $profileComplete && empty($matric_no)){
                      echo 'Register for courses';
                    } elseif(($status === 'new' && $profileComplete && !empty($matric_no)) || $status === 'completed'){
                      echo 'Registration complete';
                    } elseif($status === 'failed'){
                      echo 'Application not admitted';
                    } elseif($status === 'completed' && !empty($post_utme_path)){
                      echo 'View your result';
                    } elseif($hasRequiredUploads && !empty($o_path) && !empty($j_path)){
                      echo 'Await admin review';
                    } else {
                      echo 'Upload required documents';
                    }
                  ?>
                </div>
                <?php if($showAdmissionAcceptance): ?>
                <button type="button"
        class="btn btn-primary btn-small"
        onclick="showAcceptancePopup()">
    Accept Admission
</button>

                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content Grid -->
        <div style="margin-top:24px">
          <!-- News Section - Full Width -->
          <section class="card" style="margin-bottom:24px">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
              <h3 class="card-title" style="margin:0">📰 Latest News & Updates</h3>
              <a href="blog/index.php" style="font-size:0.9rem;color:var(--primary);text-decoration:none;font-weight:600">View All →</a>
            </div>
            <div class="card-body">
              <?php if(empty($files)): ?>
                <div style="text-align:center;padding:40px 20px;color:var(--muted)">
                  <div style="font-size:3rem;margin-bottom:12px">📭</div>
                  <p>No updates yet. Check back soon!</p>
                </div>
              <?php else: ?>
                <div style="display:grid;gap:12px">
                  <?php foreach($files as $f):
                      $raw = file_get_contents($f);
                      if(preg_match('/<h1[^>]*>(.*?)<\/h1>/is',$raw,$m)) $title = strip_tags($m[1]); else $title = basename($f, '.html');
                      if(preg_match('/<p[^>]*>(.*?)<\/p>/is',$raw,$p)) $excerpt = strip_tags($p[1]); else $excerpt = trim(strip_tags($raw));
                      $excerpt = mb_strlen($excerpt) > 150 ? mb_substr($excerpt,0,147).'...' : $excerpt;
                      $mtime = date('M j, Y', filemtime($f));
                  ?>
                    <article style="padding:12px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:8px;transition:all 200ms ease">
                      <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:8px">
                        <h4 style="margin:0;font-size:0.95rem;font-weight:700;color:var(--primary-dark)"><?php echo htmlspecialchars($title); ?></h4>
                        <span style="font-size:0.85rem;color:var(--muted);white-space:nowrap"><?php echo htmlspecialchars($mtime); ?></span>
                      </div>
                      <p style="margin:0;font-size:0.9rem;color:var(--muted)"><?php echo htmlspecialchars($excerpt); ?></p>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <!-- Content Grid: Uploads & Programs -->
          <div style="display:grid;grid-template-columns:1fr;gap:24px">
            <div class="card" style="margin-bottom:24px">
              <div class="card-header">
                <h3 class="card-title" style="margin:0">🧭 Application Details & Documents</h3>
              </div>
              <div class="card-body">
                <div style="display:grid;gap:16px">
                  <?php if($showAdmissionAcceptance): ?>
                    <div style="padding:16px;border:1px solid rgba(249,115,22,0.22);border-radius:10px;background:rgba(249,115,22,0.06)">
                      <div style="font-size:1.05rem;font-weight:700;margin-bottom:6px">Your post-utme result is here</div>
                      <p style="margin:0;color:var(--muted)">Your post-utme result is here, check it below and accept admission.</p>
                      <div style="margin-top:12px">
                      <button type="button"
        class="btn btn-primary"
        onclick="showAcceptancePopup()">
    Accept Admission
</button>
                      </div>
                    </div>
                  <?php elseif($status === 'pending' || ($hasRequiredUploads && !empty($o_path) && !empty($j_path))): ?>
                    <div style="padding:16px;border:1px solid rgba(59,130,246,0.2);border-radius:10px;background:rgba(59,130,246,0.06)">
                      <div style="font-size:1.05rem;font-weight:700;margin-bottom:6px">Application received</div>
                      <p style="margin:0;color:var(--muted)">Your documents have been submitted successfully. Please wait for your Post-UTME result.</p>
                    </div>
                  <?php elseif($showUploadForm): ?>
                    <form method="post" action="upload.php" enctype="multipart/form-data" style="display:grid;gap:14px">
                      <input type="hidden" name="action" value="update_application_details">
                      <div>
                        <label for="application_department" style="display:block;font-weight:600;margin-bottom:6px;font-size:0.95rem">Choose Department</label>
                        <select id="application_department" name="application_department" required class="form-input">
                          <option value="">Select department</option>
                          <?php foreach($departmentOptions as $department): ?>
                            <option value="<?php echo htmlspecialchars($department); ?>" <?php if($applicationDepartment === $department) echo 'selected'; ?>><?php echo htmlspecialchars($department); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div style="border-top:1px solid var(--soft-border);padding-top:12px">
                        <label for="o_level" style="display:block;font-weight:600;margin-bottom:6px;font-size:0.95rem">Upload First O-Level Result</label>
                        <div style="font-size:0.85rem;color:var(--muted);margin-bottom:6px">PDF, JPG, or PNG</div>
                        <?php if(!empty($o_path)): ?>
                          <div style="margin-bottom:8px"><a href="<?php echo htmlspecialchars($o_path); ?>" target="_blank">View current O-Level result</a></div>
                        <?php endif; ?>
                        <input type="file" id="o_level" name="o_level" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                      </div>
                      <div>
                        <label for="o_level_2" style="display:block;font-weight:600;margin-bottom:6px;font-size:0.95rem">Upload Second O-Level Result</label>
                        <div style="font-size:0.85rem;color:var(--muted);margin-bottom:6px">PDF, JPG, or PNG</div>
                        <div style="margin-bottom:8px;font-size:0.85rem;color:var(--muted)">You can leave this blank and still continue.</div>
                        <input type="file" id="o_level_2" name="o_level_2" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                      </div>
                      <div>
                        <label for="jamb_result" style="display:block;font-weight:600;margin-bottom:6px;font-size:0.95rem">Upload JAMB Result</label>
                        <div style="font-size:0.85rem;color:var(--muted);margin-bottom:6px">PDF, JPG, or PNG</div>
                        <input type="file" id="jamb_result" name="jamb_result" accept=".pdf,.jpg,.jpeg,.png" class="form-input">
                      </div>
                      <button type="submit" class="btn btn-primary" style="padding:10px 16px;font-weight:600">Save Application & Upload Files</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Right Column: Programs & Files -->
          <div style="display:flex;flex-direction:column;gap:24px">
              <?php if($status === 'new' && $profileComplete && empty($matric_no)): ?>
              <!-- Programs card removed: users already selected programs during enrollment -->
              <?php elseif(($status === 'new' || $status === 'completed') && $profileComplete && !empty($matric_no)): ?>
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title" style="margin:0">📚 Available Courses</h3>
                </div>
                <div class="card-body">
                  <?php if(!empty($registered_program) && !empty($programIdToName[$registered_program])): ?>
                    <div style="font-weight:700;margin-bottom:6px">Program: <?php echo htmlspecialchars($programIdToName[$registered_program]); ?></div>
                  <?php endif; ?>
                  <select id="programSelectDash" class="form-input" style="display:none">
                    <option value="">Choose a program...</option>
                    <?php if(!empty($programIdToName)): foreach($programIdToName as $pid => $pname): 
                      $level = 'nd';
                      if(stripos($pname, 'Higher National Diploma') !== false) $level = 'hnd';
                      elseif(stripos($pname, 'National Diploma') !== false) $level = 'nd';
                      // if education_type is set, only show matching level; otherwise show all
                      if(isset($education_type) && $education_type !== '' && strtolower($education_type) !== trim($level)) continue;
                    ?>
                      <option value="<?php echo intval($pid); ?>" <?php if(!empty($registered_program) && intval($registered_program) === intval($pid)) echo 'selected'; ?>><?php echo htmlspecialchars($pname); ?></option>
                    <?php endforeach; endif; ?>
                  </select>
                  <input type="text" id="courseSearchDash" placeholder="🔍 Search courses..." class="form-input" style="margin-top:12px;background:var(--card-bg);border:2px dashed var(--soft-border);padding:10px;font-size:1rem;min-height:44px;">
                  <div id="coursesGridDash" style="margin-top:12px;display:grid;gap:12px">
                    <?php
                      if($initial_program_id > 0 && !empty($coursesByProgram)){
                        $pname_init = $programIdToName[$initial_program_id] ?? null;
                        if($pname_init && !empty($coursesByProgram[$pname_init])){
                          foreach($coursesByProgram[$pname_init] as $cid => $cname){
                            $cdata = $coursesFullData[$cid] ?? null;
                            $isReg = in_array(intval($cid), $userCourses);
                            if(!$cdata) continue;
                            $seats = intval($cdata['seats_available'] ?? 0);
                            $total = intval($cdata['seats_total'] ?? 0);
                    ?>
                    <div class="course-card">
                      <div class="course-card-inner">
                        <div class="course-card-header"><h4 class="course-title"><?php echo htmlspecialchars($cdata['name']); ?></h4><div class="course-code"><?php echo htmlspecialchars($cdata['course_code'] ?? ''); ?></div></div>
                        <div class="course-seats"><span class="seats-available"><?php echo $seats; ?></span> / <?php echo $total; ?> Seats</div>
                        <button class="btn <?php echo $isReg ? 'btn-success' : ($seats <= 0 ? 'btn-outline' : 'btn-primary'); ?> btn-block" onclick="handleCourseActionDash(<?php echo intval($cdata['id']); ?>, <?php echo intval($cdata['program_id'] ?? 0); ?>, this, <?php echo $isReg ? 'true' : 'false'; ?>)"><?php echo $isReg ? '✓ Registered' : ($seats <= 0 ? '❌ Full' : 'Register'); ?></button>
                      </div>
                    </div>
                    <?php } }
                        else {
                    ?>
                    <div style="padding:12px;background:var(--card-bg);border:1px dashed var(--soft-border);border-radius:8px;color:var(--muted)">No courses are available for your program yet.</div>
                    <?php }
                      } else {
                    ?>
                    <div style="padding:12px;background:var(--card-bg);border:1px dashed var(--soft-border);border-radius:8px;color:var(--muted)">Complete your program registration to view available courses.</div>
                    <?php }
                    ?>
                  </div>
                </div>
              </div>
              <script>
                const coursesFullDataDash = <?php echo json_encode($coursesFullData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
                const coursesByProgramDash = <?php echo json_encode($coursesByProgram, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
                const programIdToNameDash = <?php echo json_encode($programIdToName ?? [], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
                const userCoursesDash = <?php echo json_encode($userCourses); ?>;

                const programSelectDash = document.getElementById('programSelectDash');
                const courseSearchDash = document.getElementById('courseSearchDash');
                const coursesGridDash = document.getElementById('coursesGridDash');

                function escapeHtmlDash(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

                function renderCoursesForProgramDash(programId){
                  coursesGridDash.innerHTML = '';
                  if(!programId){
                    coursesGridDash.innerHTML = '<div style="padding:12px;background:var(--card-bg);border:1px dashed var(--soft-border);border-radius:8px;color:var(--muted)">Complete your program registration to view available courses.</div>';
                    return;
                  }
                  const pname = programIdToNameDash[programId];
                  if(!pname || !coursesByProgramDash[pname]){
                    coursesGridDash.innerHTML = '<div style="padding:12px;background:var(--card-bg);border:1px dashed var(--soft-border);border-radius:8px;color:var(--muted)">No courses are available for your program yet.</div>';
                    return;
                  }
                  const courseIds = Object.keys(coursesByProgramDash[pname]);
                  if(!courseIds.length){
                    coursesGridDash.innerHTML = '<div style="padding:12px;background:var(--card-bg);border:1px dashed var(--soft-border);border-radius:8px;color:var(--muted)">No courses are available for your program yet.</div>';
                    return;
                  }
                  courseIds.forEach(cid => {
                    if(coursesFullDataDash[cid]){
                      const c = coursesFullDataDash[cid];
                      const isRegistered = userCoursesDash.includes(parseInt(cid));
                      const seatsAvail = c.seats_available || 0;
                      const card = document.createElement('div');
                      card.className = 'course-card';
                      card.innerHTML = `
                        <div class="course-card-inner">
                          <div class="course-card-header"><h4 class="course-title">${escapeHtmlDash(c.name)}</h4><div class="course-code">${escapeHtmlDash(c.course_code)}</div></div>
                          <div class="course-seats"><span class="seats-available">${seatsAvail}</span> / ${c.seats_total} Seats</div>
                          <button class="btn ${isRegistered ? 'btn-success' : seatsAvail <= 0 ? 'btn-outline' : 'btn-primary'} btn-block" onclick="handleCourseActionDash(${c.id}, ${c.program_id}, this, ${isRegistered ? 'true' : 'false'})">${isRegistered ? '✓ Registered' : seatsAvail <= 0 ? '❌ Full' : 'Register'}</button>
                        </div>`;
                      coursesGridDash.appendChild(card);
                    }
                  });
                }
                // when program changes, render courses for that program
                // (listener kept once, below)

                function filterCoursesDash(){
                  const q = courseSearchDash.value.toLowerCase();
                  document.querySelectorAll('#coursesGridDash .course-card').forEach(card => {
                    card.style.display = card.textContent.toLowerCase().includes(q) ? 'block' : 'none';
                  });
                }

                async function registerDash(courseId, programId, btn){
                  if(btn.disabled) return;
                  btn.disabled = true; const orig = btn.textContent; btn.textContent = '⏳';
                  try{
                    const res = await fetch('course_registration.php', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=register_course&course_id='+courseId+'&program_id='+programId});
                    const text = await res.text();
                    let data;
                    try{ data = JSON.parse(text); } catch(e){ alert('Server error:\n'+text); btn.disabled = false; btn.textContent = orig; return; }
                    if(data && data.success){
                      userCoursesDash.push(courseId);
                      if(coursesFullDataDash[courseId]){
                        if(typeof data.seats_available !== 'undefined' && data.seats_available !== null){
                          coursesFullDataDash[courseId].seats_available = parseInt(data.seats_available);
                        } else {
                          coursesFullDataDash[courseId].seats_available = (parseInt(coursesFullDataDash[courseId].seats_available)||0)-1;
                        }
                      }
                      btn.textContent = '✓ Registered'; btn.className = 'btn btn-success btn-block';
                      const courseName = (coursesFullDataDash[courseId] && coursesFullDataDash[courseId].name) ? coursesFullDataDash[courseId].name : 'Registered course';
                      if(typeof updateRegistrationSummaryDash === 'function') updateRegistrationSummaryDash(courseId, courseName, 'add');
                      renderCoursesForProgramDash(programSelectDash.value);
                    } else { alert(data.message || 'Registration failed'); btn.disabled = false; btn.textContent = orig; }
                  }catch(e){ alert('Error: '+e.message); btn.disabled = false; btn.textContent = orig; }
                }

                async function deregisterDash(courseId, btn){
                  if(btn.disabled) return; if(!confirm('Remove this course from your registration?')) return;
                  btn.disabled = true; const orig = btn.textContent; btn.textContent = '⏳';
                  try{
                    const res = await fetch('course_registration.php', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=deregister_course&course_id='+courseId+'&program_id=0'});
                    const text = await res.text();
                    let data;
                    try{ data = JSON.parse(text); } catch(e){ alert('Server error:\n'+text); btn.disabled = false; btn.textContent = orig; return; }
                    if(data && data.success){
                      if(coursesFullDataDash[courseId]){
                        if(typeof data.seats_available !== 'undefined' && data.seats_available !== null){
                          coursesFullDataDash[courseId].seats_available = parseInt(data.seats_available);
                        } else {
                          coursesFullDataDash[courseId].seats_available = (parseInt(coursesFullDataDash[courseId].seats_available)||0)+1;
                        }
                      }
                      const idx = userCoursesDash.indexOf(courseId); if(idx !== -1) userCoursesDash.splice(idx,1);
                      const courseName = (coursesFullDataDash[courseId] && coursesFullDataDash[courseId].name) ? coursesFullDataDash[courseId].name : 'Registered course';
                      if(typeof updateRegistrationSummaryDash === 'function') updateRegistrationSummaryDash(courseId, courseName, 'remove');
                      renderCoursesForProgramDash(programSelectDash.value);
                    } else { alert(data.message || 'Deregistration failed'); btn.disabled = false; btn.textContent = orig; }
                  }catch(e){ alert('Error: '+e.message); btn.disabled = false; btn.textContent = orig; }
                }

                function handleCourseActionDash(courseId, programId, btn, isRegistered){ if(isRegistered) deregisterDash(courseId, btn); else registerDash(courseId, programId, btn); }

                programSelectDash.addEventListener('change', function(){ renderCoursesForProgramDash(this.value); });
                courseSearchDash.addEventListener('input', filterCoursesDash);
                // Ensure program select reflects the user's registered program (or fallback) and render its courses
                programSelectDash.value = '<?php echo intval($initial_program_id ?? 0); ?>';
                if(programSelectDash.value){ renderCoursesForProgramDash(programSelectDash.value); }
                
              </script>
              <?php endif; ?>

              <!-- Files Card -->
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title" style="margin:0">📦 Your Files</h3>
                </div>
                <div class="card-body">
                  <?php if(!empty($o_path) || !empty($j_path) || !empty($post_utme_path)): ?>
                    <div style="display:flex;flex-direction:column;gap:10px">
                      <?php if(!empty($o_path)): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:8px">
                          <div style="display:flex;align-items:center;gap:10px">
                            <span style="font-size:1.3rem">📃</span>
                            <div>
                              <div style="font-weight:600;font-size:0.9rem">O-Level Result</div>
                              <div style="font-size:0.85rem;color:var(--muted)">Uploaded</div>
                            </div>
                          </div>
                          <a href="<?php echo htmlspecialchars($o_path); ?>" target="_blank" style="font-size:0.85rem;color:var(--primary);text-decoration:none;font-weight:600">View</a>
                        </div>
                      <?php endif; ?>
                      <?php if(!empty($j_path)): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:8px">
                          <div style="display:flex;align-items:center;gap:10px">
                            <span style="font-size:1.3rem">📃</span>
                            <div>
                              <div style="font-weight:600;font-size:0.9rem">JAMB Result</div>
                              <div style="font-size:0.85rem;color:var(--muted)">Uploaded</div>
                            </div>
                          </div>
                          <a href="<?php echo htmlspecialchars($j_path); ?>" target="_blank" style="font-size:0.85rem;color:var(--primary);text-decoration:none;font-weight:600">View</a>
                        </div>
                      <?php endif; ?>
                      <?php if(!empty($post_utme_path)): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:8px">
                          <div style="display:flex;align-items:center;gap:10px">
                            <span style="font-size:1.3rem">📃</span>
                            <div>
                              <div style="font-weight:600;font-size:0.9rem">Post-UTME Result</div>
                              <div style="font-size:0.85rem;color:var(--muted)">Uploaded</div>
                            </div>
                          </div>
                          <a href="<?php echo htmlspecialchars($post_utme_path); ?>" target="_blank" style="font-size:0.85rem;color:var(--primary);text-decoration:none;font-weight:600">View</a>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div style="text-align:center;padding:30px 20px;color:var(--muted)">
                      <div style="font-size:2rem;margin-bottom:8px">📭</div>
                      <p style="margin:0;font-size:0.9rem">No documents uploaded yet.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <!-- Acceptance Fee Popup -->

<div id="acceptancePopup" class="popup-overlay">

    <div class="popup-box">

        <h2>Admission Acceptance</h2>

        <p>
            Congratulations!

            <br><br>

            Before continuing your registration,
            you must pay the Acceptance Fee.

        </p>

        <div class="fee-box">

            Acceptance Fee

            <h1>₦25,000</h1>

        </div>

        <div class="popup-buttons">

            <button onclick="closeAcceptancePopup()" class="btn btn-secondary">
                Cancel
            </button>

            <a href="pay_acceptance.php" class="btn btn-primary">
                Pay Acceptance Fee
            </a>

        </div>

    </div>

</div>
  <script src="js/upload.js"></script>

  <script>

function showAcceptancePopup(){

    document.getElementById("acceptancePopup").style.display="flex";

}

function closeAcceptancePopup(){

    document.getElementById("acceptancePopup").style.display="none";

}

</script>

  <!-- Registration Summary logic (always loaded, independent of the course-registration block) -->
  <script>
    const registrationSummaryItemsDash = <?php echo json_encode($registeredCourseSummaries, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    const registrationSummaryListDash = document.getElementById('registrationSummaryListDash');
    const registrationSummaryCountDash = document.getElementById('registrationSummaryCountDash');
    const registrationSummaryLink = document.getElementById('registrationSummaryLink');
    const registrationSummarySection = document.getElementById('registrationSummarySection');

    function escapeHtmlSummary(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function renderRegistrationSummaryDash(){
      if(registrationSummaryCountDash){ registrationSummaryCountDash.textContent = registrationSummaryItemsDash.length; }
      if(!registrationSummaryListDash) return;
      if(!registrationSummaryItemsDash.length){
        registrationSummaryListDash.innerHTML = '<li style="padding:12px;background:rgba(249,115,22,0.05);border:1px solid var(--soft-border);border-radius:12px;color:var(--muted)">No courses registered yet.</li>';
        return;
      }
      registrationSummaryListDash.innerHTML = registrationSummaryItemsDash.map(item => '<li style="padding:12px;background:rgba(255,255,255,0.9);border:1px solid var(--soft-border);border-radius:12px;color:var(--text)">' + escapeHtmlSummary(item.name || 'Registered course') + '</li>').join('');
    }

    function updateRegistrationSummaryDash(courseId, courseName, action){
      const resolvedName = courseName || 'Registered course';
      if(action === 'add'){
        const exists = registrationSummaryItemsDash.some(item => parseInt(item.id) === parseInt(courseId));
        if(!exists){ registrationSummaryItemsDash.push({id: parseInt(courseId), name: resolvedName}); }
      } else {
        const index = registrationSummaryItemsDash.findIndex(item => parseInt(item.id) === parseInt(courseId));
        if(index !== -1){ registrationSummaryItemsDash.splice(index, 1); }
      }
      renderRegistrationSummaryDash();
    }

    if(registrationSummaryLink && registrationSummarySection){
      registrationSummaryLink.addEventListener('click', function(event){
        event.preventDefault();
        registrationSummarySection.hidden = false;
        registrationSummarySection.scrollIntoView({behavior:'smooth', block:'start'});
      });
    }

    renderRegistrationSummaryDash();
  </script>
</body>
</html>