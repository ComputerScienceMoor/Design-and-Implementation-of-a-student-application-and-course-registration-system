<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();
$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $pass = $_POST['pass'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';
    if($email === '' || $department === '' || $pass === '') $error = 'Provide email, department and password.';
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email address.';
    elseif($pass !== $pass2) $error = 'Passwords do not match.';
    else{
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO admin_users (username,email,department,password_hash) VALUES (?,?,?,?)');
        $username = $email;
        $stmt->bind_param('ssss', $username, $email, $department, $hash);
        if($stmt->execute()){
            redirect('admins.php');
        } else {
            $error = 'Failed to create admin (email may already exist).';
        }
    }
}
?>
<?php include 'header.php'; ?>
  <div class="form-box">
    <h2>Add Admin</h2>
    <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
      <label>Email</label>
      <input type="email" name="email" required>
      <label>Department</label>
      <input type="text" name="department" required>
      <label>Password</label>
      <input type="password" name="pass" required>
      <label>Confirm Password</label>
      <input type="password" name="pass2" required>
      <button type="submit">Create</button>
    </form>
    <p><a href="admins.php">Back</a></p>
  </div>
<?php include 'footer.php'; ?>
