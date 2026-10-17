<?php include 'header.php';
$db = db_connect();
$error = '';

// Create program
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $id = intval($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  if(!$name) $error = 'Program name is required.';
  else {
    if($id > 0){
      $stmt = $db->prepare('UPDATE programs SET name = ?, description = ? WHERE id = ?');
      if($stmt){
        $stmt->bind_param('ssi', $name, $desc, $id);
        if(!$stmt->execute()) $error = 'Failed to update program. Name might already exist.';
        else $_SESSION['flash_success'] = 'Program updated.';
      } else $error = 'DB error.';
    } else {
      $stmt = $db->prepare('INSERT INTO programs (name, description) VALUES (?, ?)');
      if($stmt){
        $stmt->bind_param('ss', $name, $desc);
        if(!$stmt->execute()) $error = 'Failed to add program. It may already exist.';
        else $_SESSION['flash_success'] = 'Program added.';
      } else {
        $error = 'DB error.';
      }
    }
  }
  if(empty($error)) redirect('programs.php');
}

// Delete program
if(!empty($_GET['delete'])){
  $id = intval($_GET['delete']);
  $stmt = $db->prepare('DELETE FROM programs WHERE id = ?');
  if($stmt){
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $_SESSION['flash_success'] = 'Program deleted.';
  }
  redirect('programs.php');
}

// Edit program - load data for form
$editProgram = null;
if(!empty($_GET['edit'])){
  $eid = intval($_GET['edit']);
  $stmt = $db->prepare('SELECT id,name,description FROM programs WHERE id = ? LIMIT 1');
  if($stmt){
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $res = $stmt->get_result();
    $editProgram = $res->fetch_assoc();
  }
}

$res = $db->query('SELECT id,name,description,created_at FROM programs ORDER BY name');
?>

<h2>Programs</h2>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div style="display:flex;gap:20px;align-items:flex-start">
  <div style="flex:1;max-width:540px">
    <form method="post" class="form-box">
      <input type="hidden" name="id" value="<?php echo intval($editProgram['id'] ?? 0); ?>">
      <label>Name</label>
      <input type="text" name="name" required value="<?php echo htmlspecialchars($editProgram['name'] ?? ''); ?>">
      <label>Description</label>
      <textarea name="description" rows="3"><?php echo htmlspecialchars($editProgram['description'] ?? ''); ?></textarea>
      <button class="btn btn-primary" style="margin-top:10px"><?php echo $editProgram ? 'Update Program' : 'Add Program'; ?></button>
      <?php if($editProgram): ?> <a class="btn" href="programs.php" style="background:#6b7280;margin-left:10px">Cancel</a> <?php endif; ?>
    </form>
  </div>
  <div style="flex:1">
    <h3>Existing Programs</h3>
    <table class="table">
      <thead><tr><th>Name</th><th>Created</th><th></th></tr></thead>
      <tbody>
        <?php while($r = $res->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['name']); ?><div style="font-size:0.9em;color:#666"><?php echo htmlspecialchars($r['description']); ?></div></td>
            <td><?php echo $r['created_at']; ?></td>
            <td><a href="programs.php?delete=<?php echo $r['id']; ?>" onclick="return confirm('Delete this program? All courses under it will be removed.')">Delete</a></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php';
