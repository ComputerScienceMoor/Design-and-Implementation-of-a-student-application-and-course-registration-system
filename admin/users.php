<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();
$status = $_GET['status'] ?? '';

function safe_prepare($db, $sql){
    try{
        return $db->prepare($sql);
    } catch (mysqli_sql_exception $e){
        return false;
    }
}

$params = [];
$admin_id = intval($_SESSION['admin_id'] ?? 0);
$accessibleProgramIds = get_admin_program_ids($db, $admin_id);
$hasFullAccess = false;
$sql = "SELECT u.id,u.email,u.matric_no,u.jamb_no,u.phone,u.status,u.o_level_path,u.jamb_path,u.post_utme_path,u.registered_program, u.specialization, u.application_program_id, u.application_department, u.application_level, up.name AS user_program_name, GROUP_CONCAT(CONCAT_WS('::',c.id,c.name) SEPARATOR '||') AS registered_courses, GROUP_CONCAT(DISTINCT(CONCAT_WS('::',p.id,p.name)) SEPARATOR '||') AS registered_programs FROM users u LEFT JOIN student_course_registrations r ON r.user_id = u.id LEFT JOIN courses c ON r.course_id = c.id LEFT JOIN programs p ON r.program_id = p.id LEFT JOIN programs up ON u.registered_program = up.id";
$where = [];
if($status){
  $where[] = 'u.status = ?';
  $params[] = $status;
}
if($where){
  $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' GROUP BY u.id ORDER BY u.id DESC';

$stmt = safe_prepare($db, $sql);
if($stmt){
  if($params){
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  // fallback: simple select without registered courses
  $stmt = safe_prepare($db, 'SELECT id,email,matric_no,jamb_no,phone,status,o_level_path,jamb_path,post_utme_path FROM users');
  $stmt->execute();
  $res = $stmt->get_result();
}
?>
<?php include 'header.php'; ?>
  <h2>Users</h2>
  <form method="get" action="users.php" style="margin-bottom:12px">
    <label>Filter by status</label>
    <select name="status"><option value="">-- All --</option>
      <option value="prospective" <?php if($status==='prospective') echo 'selected'; ?>>Prospective</option>
      <option value="new" <?php if($status==='new') echo 'selected'; ?>>New</option>
      <option value="returning" <?php if($status==='returning') echo 'selected'; ?>>Returning</option>
      <option value="pending" <?php if($status==='pending') echo 'selected'; ?>>Pending</option>
      <option value="failed" <?php if($status==='failed') echo 'selected'; ?>>Failed</option>
    </select>
    <button type="submit">Filter</button>
  </form>

  <table>
    <tr><th>ID</th><th>Email</th><th>Matric</th><th>JAMB</th><th>Phone</th><th>Status</th><th>Program</th><th>Files</th><th>Registered Courses</th><th>Actions</th></tr>
    <?php while($u = $res->fetch_assoc()): ?>
      <?php if(!$hasFullAccess && !empty($accessibleProgramIds) && !user_matches_admin_program_access($db, $u, $admin_id, $accessibleProgramIds)) continue; ?>
      <tr>
        <td><?php echo $u['id']; ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><?php echo htmlspecialchars($u['matric_no'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($u['jamb_no']); ?></td>
        <td><?php echo htmlspecialchars($u['phone']); ?></td>
        <td><?php echo htmlspecialchars($u['status']); ?></td>
        <td>
          <?php
            $displayProgram = '';
            if(!empty($u['application_program_id'])) {
              $displayProgram = get_program_name($db, $u['application_program_id']);
            }
            if(!empty($u['user_program_name'])){
              $displayProgram = $u['user_program_name'];
            } elseif(!empty($u['registered_program'])) {
              $rpVal = trim($u['registered_program']);
              // try numeric id first
              if(preg_match('/^\d+$/', $rpVal)){
                $q = $db->prepare('SELECT name FROM programs WHERE id = ? LIMIT 1');
                if($q){ $q->bind_param('i', $rpVal); $q->execute(); $q->bind_result($pn); if($q->fetch()) $displayProgram = $pn; $q->close(); }
              }
              // fallback: try matching by code or exact name
              if(empty($displayProgram)){
                $q2 = $db->prepare('SELECT name FROM programs WHERE code = ? OR name = ? LIMIT 1');
                if($q2){ $q2->bind_param('ss', $rpVal, $rpVal); $q2->execute(); $q2->bind_result($pn2); if($q2->fetch()) $displayProgram = $pn2; $q2->close(); }
              }
            }

            if(!empty($displayProgram)){
              echo '<div>'.htmlspecialchars($displayProgram).'</div>';
            } elseif(!empty($u['specialization'])){
              $spec = strtoupper(trim($u['specialization']));
              $ps = $db->prepare('SELECT name FROM programs WHERE code = ? LIMIT 1');
              $psname = '';
              if($ps){ $ps->bind_param('s', $spec); $ps->execute(); $ps->bind_result($psname); if($ps->fetch()) echo '<div>'.htmlspecialchars($psname).'</div>'; $ps->close(); }
              if(empty($psname)) echo '<div>'.htmlspecialchars(strtoupper($u['specialization'])).'</div>';
            } elseif(!empty($u['registered_programs'])){
              foreach(explode('||',$u['registered_programs']) as $rp): if(trim($rp)==='') continue; $pp = explode('::',$rp); $pid = $pp[0] ?? ''; $pname = $pp[1] ?? '';
                echo '<div>'.htmlspecialchars($pname ?: ('#'.$pid)).'</div>';
              endforeach;
            } else {
              echo '<span style="color:var(--muted)">Not selected</span>';
            }
          ?>
        </td>
        <td>
          <?php if(!empty($u['application_department']) || !empty($u['application_level'])): ?>
            <div style="font-size:0.85rem;color:var(--muted);margin-bottom:4px">Dept: <?php echo htmlspecialchars($u['application_department'] ?? ''); ?> | Level: <?php echo htmlspecialchars($u['application_level'] ?? ''); ?></div>
          <?php endif; ?>
          <?php if(!empty($u['o_level_path'])): ?><a href="../<?php echo htmlspecialchars($u['o_level_path']); ?>" target="_blank">O-Level</a><?php endif; ?>
          <?php if(!empty($u['jamb_path'])): ?> | <a href="../<?php echo htmlspecialchars($u['jamb_path']); ?>" target="_blank">JAMB</a><?php endif; ?>
          <?php if(!empty($u['post_utme_path'] ?? null)): ?> | <a href="../<?php echo htmlspecialchars($u['post_utme_path'] ?? ''); ?>" target="_blank">Post-UTME</a><?php endif; ?>
        </td>
        <td>
          
          <?php if(!empty($u['registered_courses'])): ?>
            <ul style="list-style:none;padding:0;margin:0">
              <?php foreach(explode('||',$u['registered_courses']) as $rc): if(trim($rc)==='') continue; $parts = explode('::',$rc); $cid = $parts[0] ?? ''; $cname = $parts[1] ?? ''; ?>
                <li><?php echo htmlspecialchars($cname ?: ('#'.$cid)); ?> <?php if(!empty($cid)) echo '(<a href="user_edit.php?id='.$u['id'].'">view</a>)'; ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <span style="color:var(--muted)">None</span>
          <?php endif; ?>
        </td>
        <td>
          <a class="btn" href="user_files.php?id=<?php echo $u['id']; ?>">Review</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
<?php include 'footer.php'; ?>
