<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();
$programs = get_programs($db);
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id'])){
    $adminId = intval($_POST['admin_id']);
    $programIds = [];
    if(isset($_POST['program_ids']) && is_array($_POST['program_ids'])){
        $programIds = array_values(array_unique(array_map('intval', array_filter($_POST['program_ids'], 'is_numeric'))));
    }
    $deleteStmt = $db->prepare('DELETE FROM admin_program_access WHERE admin_id = ?');
    if($deleteStmt){
        $deleteStmt->bind_param('i', $adminId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    if($programIds){
        $stmt = $db->prepare('INSERT INTO admin_program_access (admin_id, program_id) VALUES (?, ?)');
        foreach($programIds as $programId){
            $stmt->bind_param('ii', $adminId, $programId);
            $stmt->execute();
        }
        $stmt->close();
    }
    redirect('admins.php');
}
$res = $db->query('SELECT id,username,email,department,created_at FROM admin_users ORDER BY id ASC');
?>
<?php include 'header.php'; ?>
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 8px">Admin Access</h2>
      <p class="page-description">Assign or remove programs for each admin. Once saved, the selected programs appear as a clean, read-only summary.</p>
    </div>
    <a class="btn" href="admin_add.php">Add Admin</a>
  </div>
  <div style="display:grid;gap:16px">
    <?php while($r = $res->fetch_assoc()): ?>
      <?php
        $assignedNames = [];
        $assignedStmt = $db->prepare('SELECT p.name FROM admin_program_access apa JOIN programs p ON p.id = apa.program_id WHERE apa.admin_id = ? ORDER BY p.name ASC');
        if($assignedStmt){
          $assignedStmt->bind_param('i', $r['id']);
          $assignedStmt->execute();
          $assignedResult = $assignedStmt->get_result();
          while($assignedRow = $assignedResult->fetch_assoc()){
            $assignedNames[] = $assignedRow['name'] ?? '';
          }
          $assignedStmt->close();
        }
      ?>
      <div class="card" style="padding:18px;border:1px solid rgba(249,115,22,0.12);box-shadow:0 12px 30px rgba(15,23,42,0.06)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px">
          <div>
            <div style="font-weight:800;font-size:1.06rem;color:var(--primary-dark)"><?php echo htmlspecialchars($r['email'] ?: $r['username']); ?></div>
            <div style="color:var(--muted);font-size:0.9rem;margin-top:4px">Department: <?php echo htmlspecialchars($r['department'] ?: '—'); ?></div>
            <div style="color:var(--muted);font-size:0.9rem;margin-top:4px">Created: <?php echo htmlspecialchars($r['created_at']); ?></div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <?php if(!empty($assignedNames)): ?>
              <span style="padding:7px 10px;border-radius:999px;background:rgba(34,197,94,0.12);color:#166534;font-size:0.84rem;font-weight:700">Assigned</span>
            <?php else: ?>
              <span style="padding:7px 10px;border-radius:999px;background:rgba(249,115,22,0.12);color:var(--primary-dark);font-size:0.84rem;font-weight:700">Pending</span>
            <?php endif; ?>
            <?php if($r['id'] != ($_SESSION['admin_id'] ?? 0)): ?>
              <a class="btn btn-danger" href="admin_delete.php?id=<?php echo $r['id']; ?>" onclick="return confirm('Delete admin?')">Delete</a>
            <?php else: ?>
              <span class="btn" style="background:rgba(249,115,22,0.08);color:var(--primary-dark);border-color:transparent">Current Admin</span>
            <?php endif; ?>
          </div>
        </div>
        <div style="margin-bottom:12px;padding:10px 12px;border-radius:12px;background:rgba(15,23,42,0.03);border:1px solid rgba(15,23,42,0.06);font-size:0.94rem;color:var(--primary-dark)">
          <?php if(!empty($assignedNames)): ?>
            <strong>Assigned programs:</strong> <?php echo htmlspecialchars(implode(', ', array_filter($assignedNames))); ?>
          <?php else: ?>
            <strong>No programs assigned yet</strong>
          <?php endif; ?>
        </div>
        <form method="post" style="display:grid;gap:12px">
          <input type="hidden" name="admin_id" value="<?php echo (int)$r['id']; ?>">
          <?php $hasAssignments = !empty($assignedNames); ?>
          <?php if($hasAssignments): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              <?php foreach($assignedNames as $assignedName): ?>
                <span style="display:inline-flex;align-items:center;padding:8px 10px;border:1px solid var(--soft-border);border-radius:999px;background:rgba(249,115,22,0.05);font-size:0.92rem;line-height:1.2;white-space:nowrap">
                  <?php echo htmlspecialchars($assignedName); ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              <?php foreach($programs as $program): ?>
                <?php $isAssigned = admin_can_access_program($db, (int)$r['id'], (int)$program['id']); ?>
                <label style="display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--soft-border);border-radius:999px;background:white;font-size:0.92rem;line-height:1.2;white-space:nowrap;box-shadow:0 2px 6px rgba(15,23,42,0.04)">
                  <input type="checkbox" name="program_ids[]" value="<?php echo (int)$program['id']; ?>" <?php if($isAssigned) echo 'checked'; ?>>
                  <span><?php echo htmlspecialchars($program['name']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if(!$hasAssignments): ?>
            <button type="submit" class="btn btn-primary">Save Access</button>
          <?php endif; ?>
        </form>
      </div>
    <?php endwhile; ?>
  </div>
<?php include 'footer.php'; ?>
