<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$id = intval($_GET['id'] ?? 0);
if($id <= 0) redirect('users.php');
$db = db_connect();
$msg = '';
$err = '';
// Allow admin to edit matric number only; status remains managed by enrollment workflow.
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $newMatric = trim($_POST['matric'] ?? '');
  // If empty, unset matric (set NULL). Otherwise ensure uniqueness.
  if($newMatric === ''){
    $u = $db->prepare('UPDATE users SET matric_no = NULL WHERE id = ?');
    if($u){
      $u->bind_param('i', $id);
      if($u->execute()){
        $msg = 'Matric number cleared.';
      } else {
        $err = 'Failed to clear matric number.';
      }
      $u->close();
    } else {
      $err = 'Failed to prepare update.';
    }
  } else {
    if(!preg_match('/^(ND|HND)[A-Z0-9]+\d{2}\/\d{3,}$/', strtoupper($newMatric))){
      $err = 'Matric number must follow the format EDUPROGRAMYY/NNN or above, e.g. NDCS26/0001 or HNDSWD26/10000.';
    } else {
      // check uniqueness
      $c = $db->prepare('SELECT id FROM users WHERE matric_no = ? AND id != ? LIMIT 1');
      if($c){
        $c->bind_param('si', $newMatric, $id);
        $c->execute();
        $c->store_result();
        if($c->num_rows > 0){
          $err = 'That matric number is already in use by another user.';
        } else {
          $c->close();
          $u = $db->prepare('UPDATE users SET matric_no = ? WHERE id = ?');
          if($u){
            $u->bind_param('si', $newMatric, $id);
            if($u->execute()){
              $msg = 'Matric number updated.';
            } else {
              $err = 'Failed to update matric number.';
            }
            $u->close();
          } else {
            $err = 'Failed to prepare update.';
          }
        }
      } else {
        $err = 'Failed to validate matric uniqueness.';
      }
    }
  }
}
$stmt = $db->prepare('SELECT email,jamb_no,phone,status,matric_no FROM users WHERE id = ?');
$stmt->bind_param('i',$id);
$stmt->execute();
$stmt->bind_result($email,$jamb,$phone,$status,$matric);
$stmt->fetch();
?>
<?php include 'header.php'; ?>
  <div class="form-box">
    <h2>Edit User #<?php echo $id; ?></h2>
    <?php if($msg): ?><div class="success"><?php echo $msg; ?></div><?php endif; ?>
    <?php if($err): ?><div class="error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <form method="post">
      <label>Email</label>
      <div><?php echo htmlspecialchars($email); ?></div>
      <label>Matric Number</label>
      <input type="text" name="matric" value="<?php echo htmlspecialchars($matric ?? ''); ?>" placeholder="NDCS26/001">
      <label>Status</label>
      <div style="padding:8px;background:#f3fdf6;border-radius:4px;display:inline-block"><?php echo htmlspecialchars(ucfirst($status)); ?></div>
      <p style="margin-top:10px;color:#666;font-size:0.9rem">Status is managed by the enrollment workflow and cannot be changed here.</p>
    </form>
    <p><a href="users.php">Back</a></p>
  </div>
<?php include 'footer.php'; ?>
