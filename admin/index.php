<?php include 'header.php'; ?>
<?php
// populate counts and announcements
$db = db_connect();
$admin_id = intval($_SESSION['admin_id'] ?? 0);
$accessibleProgramIds = get_admin_program_ids($db, $admin_id);
$hasFullAccess = false;
$counts = [];
$res = $db->query('SELECT id,status,application_program_id,application_department,registered_program FROM users');
while($r = $res->fetch_assoc()){
  if(!$hasFullAccess && !empty($accessibleProgramIds) && !user_matches_admin_program_access($db, $r, $admin_id, $accessibleProgramIds)) continue;
  $status = $r['status'] ?? 'prospective';
  $counts[$status] = ($counts[$status] ?? 0) + 1;
}
$ann = $db->query('SELECT id,title,created_at FROM announcements ORDER BY created_at DESC');
?>
  <h2>Overview</h2>
  <div style="display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap">
    <div class="card"><strong>Prospective:</strong> <?php echo $counts['prospective'] ?? 0; ?></div>
    <div class="card"><strong>New:</strong> <?php echo (($counts['new'] ?? 0) + ($counts['completed'] ?? 0)); ?></div>
    <div class="card"><strong>Returning:</strong> <?php echo $counts['returning'] ?? 0; ?></div>
    <div class="card"><strong>Pending:</strong> <?php echo $counts['pending'] ?? 0; ?></div>
    <div class="card"><strong>Failed:</strong> <?php echo $counts['failed'] ?? 0; ?></div>
  </div>

  <h3>Quick Actions</h3>
  <div style="margin-bottom:14px">
    <a class="btn" href="users.php">Manage Users</a>
    <a class="btn" href="admins.php">Manage Admins</a>
    <a class="btn" href="announce_create.php">New Announcement</a>
    <a class="btn" href="cleanup_stray_courses.php">Cleanup Stray Courses</a>
  </div>

  <h3>Announcements</h3>
  <ul>
    <?php while($a = $ann->fetch_assoc()): ?>
      <li><?php echo htmlspecialchars($a['title']); ?> — <a href="announce_edit.php?id=<?php echo $a['id']; ?>">Edit</a> | <a href="announce_delete.php?id=<?php echo $a['id']; ?>" onclick="return confirm('Delete?')">Delete</a></li>
    <?php endwhile; ?>
  </ul>
<?php include 'footer.php'; ?>
