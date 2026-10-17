<?php include 'header.php';
$db = db_connect();
$error = '';

// Load programs
$programs = [];
$resP = $db->query('SELECT id,name FROM programs ORDER BY name');
while($p = $resP->fetch_assoc()) $programs[$p['id']] = $p['name'];

$selected_program = intval($_GET['program_id'] ?? 0);

// Handle admin seat adjustments
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_seats'){
  $cid = intval($_POST['course_id'] ?? 0);
  $add = intval($_POST['add_seats'] ?? 0);
  $also_total = isset($_POST['also_total']) ? 1 : 0;
  @mkdir(__DIR__ . '/../logs', 0755, true);
  $logfile = __DIR__ . '/../logs/admin_courses.log';
  if($cid > 0 && $add > 0){
    // Ensure columns exist using column_exists when available
    try{
      if(function_exists('column_exists') && !column_exists($db, DB_NAME, 'courses', 'seats_available')){
        $db->query('ALTER TABLE courses ADD COLUMN seats_available INT DEFAULT 50');
        @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Added seats_available column\n", FILE_APPEND);
      }
      if(function_exists('column_exists') && !column_exists($db, DB_NAME, 'courses', 'seats_total')){
        $db->query('ALTER TABLE courses ADD COLUMN seats_total INT DEFAULT 50');
        @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Added seats_total column\n", FILE_APPEND);
      }
    } catch(Exception $e){ @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Column ensure error: {$e->getMessage()}\n", FILE_APPEND); }

    $sql = 'UPDATE courses SET seats_available = COALESCE(seats_available,0) + ?'.($also_total? ', seats_total = COALESCE(seats_total,0) + ?' : '').' WHERE id = ?';
    $stmt = $db->prepare($sql);
    if($stmt){
      if($also_total) $stmt->bind_param('iii', $add, $add, $cid); else $stmt->bind_param('ii', $add, $cid);
      $ok = $stmt->execute();
      if($ok){
        $_SESSION['flash_success'] = 'Seats updated.';
        @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Seats updated: course={$cid} add={$add} also_total={$also_total}\n", FILE_APPEND);
        // record audit
        try{ $ip = $_SERVER['REMOTE_ADDR'] ?? null; $a = $db->prepare('INSERT INTO registration_audit (user_id, course_id, action, actor_ip) VALUES (NULL, ?, ?, ?)'); if($a){ $act = 'admin_increase_seats:+' . $add; $a->bind_param('iss', $cid, $act, $ip); $a->execute(); } }catch(Exception $e){ @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Audit insert error: {$e->getMessage()}\n", FILE_APPEND); }
      } else {
        $err = $db->error;
        $error = 'Failed to update seats.';
        @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Seats update failed: {$err} SQL={$sql} course={$cid} add={$add}\n", FILE_APPEND);
      }
      $stmt->close();
    } else {
      $err = $db->error;
      $error = 'DB error.';
      @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Prepare failed: {$err} SQL={$sql}\n", FILE_APPEND);
    }
  } else {
    $error = 'Invalid course or seat value.';
    @file_put_contents($logfile, date('[Y-m-d H:i:s] ')."Invalid seat change input: course={$cid} add={$add}\n", FILE_APPEND);
  }
  // redirect back to manage view
  $redir = 'courses.php?program_id=' . intval($_POST['program_id'] ?? 0);
  if(!empty($cid)) $redir .= '&manage=' . $cid;
  redirect($redir);
}

// Add course
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $id = intval($_POST['id'] ?? 0);
  $program_id = intval($_POST['program_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  if($program_id <= 0 || !isset($programs[$program_id])) $error = 'Select a valid program.';
  elseif(!$name) $error = 'Course name is required.';
  else {
    if($id > 0){
      $stmt = $db->prepare('UPDATE courses SET program_id = ?, name = ?, description = ? WHERE id = ?');
      if($stmt){
        $stmt->bind_param('issi',$program_id,$name,$desc,$id);
        if(!$stmt->execute()) $error = 'Failed to update course. Name might already exist.';
        else { $_SESSION['flash_success'] = 'Course updated.'; $selected_program = $program_id; }
      } else $error = 'DB error.';
    } else {
      $stmt = $db->prepare('INSERT INTO courses (program_id, name, description) VALUES (?, ?, ?)');
      if($stmt){
        $stmt->bind_param('iss',$program_id,$name,$desc);
        if(!$stmt->execute()) $error = 'Failed to add course. It may already exist.';
        else { $_SESSION['flash_success'] = 'Course added.'; $selected_program = $program_id; }
      } else $error = 'DB error.';
    }
  }
  if(empty($error)) redirect('courses.php?program_id=' . $selected_program);
}

// Delete course
if(!empty($_GET['delete'])){
  $id = intval($_GET['delete']);
  $stmt = $db->prepare('DELETE FROM courses WHERE id = ?');
  if($stmt){ $stmt->bind_param('i',$id); $stmt->execute(); $_SESSION['flash_success'] = 'Course deleted.'; }
  redirect('courses.php?program_id=' . intval($_GET['program_id'] ?? 0));
}

// Edit course - load for form
$editCourse = null;
if(!empty($_GET['edit'])){
  $eid = intval($_GET['edit']);
  $stmt = $db->prepare('SELECT id,program_id,name,description FROM courses WHERE id = ? LIMIT 1');
  if($stmt){
    $stmt->bind_param('i',$eid);
    $stmt->execute();
    $res = $stmt->get_result();
    $editCourse = $res->fetch_assoc();
    if($editCourse) $selected_program = intval($editCourse['program_id']);
  }
}

$courses = [];
if($selected_program > 0){
    $stmt = $db->prepare('SELECT id,name,description,created_at FROM courses WHERE program_id = ? ORDER BY name');
    $stmt->bind_param('i',$selected_program);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) $courses[] = $r;
}

?>

<h2>Courses</h2>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div style="display:flex;gap:20px;align-items:flex-start">
  <div style="flex:1;max-width:420px">
    <form method="post" class="form-box">
      <input type="hidden" name="id" value="<?php echo intval($editCourse['id'] ?? 0); ?>">
      <label>Program</label>
      <select name="program_id" required>
        <option value="">Choose program</option>
        <?php foreach($programs as $pid => $pname): ?>
          <option value="<?php echo $pid; ?>" <?php if($selected_program===$pid) echo 'selected'; ?>><?php echo htmlspecialchars($pname); ?></option>
        <?php endforeach; ?>
      </select>
      <label>Course Name</label>
      <input type="text" name="name" required value="<?php echo htmlspecialchars($editCourse['name'] ?? ''); ?>">
      <label>Description</label>
      <textarea name="description" rows="3"><?php echo htmlspecialchars($editCourse['description'] ?? ''); ?></textarea>
      <button class="btn btn-primary" style="margin-top:10px"><?php echo $editCourse ? 'Update Course' : 'Add Course'; ?></button>
      <?php if($editCourse): ?> <a class="btn" href="courses.php?program_id=<?php echo $selected_program; ?>" style="background:#6b7280;margin-left:10px">Cancel</a> <?php endif; ?>
    </form>
  </div>
  <div style="flex:1">
    <h3>Courses<?php echo $selected_program ? ' — ' . htmlspecialchars($programs[$selected_program]) : ''; ?></h3>
    <div style="margin-bottom:10px">
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <label style="margin:0">Show:</label>
        <select name="program_id" onchange="this.form.submit()">
          <option value="">Choose program</option>
          <?php foreach($programs as $pid => $pname): ?>
            <option value="<?php echo $pid; ?>" <?php if($selected_program===$pid) echo 'selected'; ?>><?php echo htmlspecialchars($pname); ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if($selected_program && empty($courses)): ?>
      <p>No courses found for this program.</p>
    <?php elseif(!empty($courses)): ?>
      <table class="table">
        <thead><tr><th>Name</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach($courses as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['name']); ?><div style="font-size:0.9em;color:#666"><?php echo htmlspecialchars($c['description']); ?></div></td>
              <td><?php echo $c['created_at']; ?></td>
              <td style="white-space:nowrap">
                <a href="courses.php?edit=<?php echo $c['id']; ?>&program_id=<?php echo $selected_program; ?>">Edit</a>
                &nbsp;|&nbsp;
                <a href="courses.php?manage=<?php echo $c['id']; ?>&program_id=<?php echo $selected_program; ?>">Manage</a>
                &nbsp;|&nbsp;
                <a href="courses.php?delete=<?php echo $c['id']; ?>&program_id=<?php echo $selected_program; ?>" onclick="return confirm('Delete this course?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    
    <?php
      // If admin selected manage view, load course details and registered students
      if(!empty($_GET['manage'])){
        $manageId = intval($_GET['manage']);
        $courseInfo = null;
        // Build SELECT defensively in case seats columns are missing on some environments
        $cols = ['c.id','c.name','c.description'];
        if(function_exists('column_exists') && column_exists($db, DB_NAME, 'courses', 'seats_available')) $cols[] = 'c.seats_available';
        if(function_exists('column_exists') && column_exists($db, DB_NAME, 'courses', 'seats_total')) $cols[] = 'c.seats_total';
        $cols[] = 'p.name as program_name';
        $select = 'SELECT ' . implode(', ', $cols) . ' FROM courses c JOIN programs p ON c.program_id = p.id WHERE c.id = ? LIMIT 1';
        $s = $db->prepare($select);
        if($s){ $s->bind_param('i',$manageId); $s->execute(); $courseInfo = $s->get_result()->fetch_assoc(); }
        $registered = [];
        // Build student registration select defensively (some installs may use registered_at)
        $regCols = ['scr.user_id', 'u.full_name', 'u.email', 'u.matric_no'];
        if(function_exists('column_exists') && column_exists($db, DB_NAME, 'student_course_registrations', 'registered_at')){
          array_unshift($regCols, 'scr.registered_at');
        }
        $regSelect = 'SELECT ' . implode(', ', $regCols) . ' FROM student_course_registrations scr LEFT JOIN users u ON scr.user_id = u.id WHERE scr.course_id = ? ORDER BY u.full_name';
        $r = $db->prepare($regSelect);
        if($r){ $r->bind_param('i',$manageId); $r->execute(); $res = $r->get_result(); while($row = $res->fetch_assoc()) $registered[] = $row; }
        if($courseInfo){
    ?>
    <div style="margin-top:20px;padding:12px;background:var(--card-bg);border:1px solid var(--soft-border);border-radius:8px">
      <h3>Manage Course: <?php echo htmlspecialchars($courseInfo['name']); ?> <small style="color:var(--muted);font-size:0.9rem">(<?php echo htmlspecialchars($courseInfo['program_name']); ?>)</small></h3>
      <div style="display:flex;gap:20px;align-items:flex-start;margin-top:8px">
        <div style="flex:1;max-width:360px">
          <form method="post" class="form-box">
            <input type="hidden" name="action" value="change_seats">
            <input type="hidden" name="course_id" value="<?php echo intval($courseInfo['id']); ?>">
            <input type="hidden" name="program_id" value="<?php echo intval($selected_program); ?>">
            <label>Seats Available</label>
            <div style="font-weight:700;font-size:1.1rem"><?php echo intval($courseInfo['seats_available'] ?? 0); ?> available / <?php echo intval($courseInfo['seats_total'] ?? 0); ?> total</div>
              <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="number" name="add_seats" min="1" value="1" required style="width:90px;padding:6px;border:1px solid #ccd;">
                <select name="add_preset" onchange="this.form.add_seats.value=this.value" style="padding:6px;border:1px solid #ccd;">
                  <option value="">Presets</option>
                  <option value="1">+1</option>
                  <option value="5">+5</option>
                  <option value="10">+10</option>
                </select>
                <label style="display:inline-flex;align-items:center;gap:6px;margin:0;">
                  <input type="checkbox" name="also_total" value="1" checked>
                  <span>Also increase seats total</span>
                </label>
                <button type="submit" class="btn btn-success" style="margin-left:6px;">Apply</button>
              </div>
          </form>
        </div>
        <div style="flex:1">
          <h4 style="margin:0 0 8px 0">Registered Students (<?php echo count($registered); ?>)</h4>
          <?php if(empty($registered)): ?>
            <div style="color:var(--muted)">No students registered for this course.</div>
          <?php else: ?>
            <table class="table"><thead><tr><th>Name</th><th>Matric/Email</th><th>Registered At</th></tr></thead><tbody>
              <?php foreach($registered as $rr): ?>
                <tr>
                  <td><?php echo htmlspecialchars($rr['full_name'] ?? 'Unknown'); ?></td>
                  <td><?php echo htmlspecialchars($rr['matric_no'] ?: $rr['email']); ?></td>
                  <td><?php echo htmlspecialchars($rr['registered_at'] ?? $rr['created_at'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php }
      }
    ?>
  </div>
</div>

<?php include 'footer.php';
