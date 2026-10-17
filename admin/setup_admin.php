<?php
require_once __DIR__.'/../functions.php';
$db = db_connect();
$res = $db->query('SELECT COUNT(*) as c FROM admin_users');
$row = $res->fetch_assoc();
if(intval($row['c']) > 0){
    // Admin already exists
    redirect('login.php');
}
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
            redirect('login.php');
        } else {
            $error = 'Failed to create admin.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Setup Admin</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <div class="container">
    <div class="form-box">
      <h2>Setup Admin Account</h2>
      <p style="color: var(--muted); margin-bottom: 20px;">Create the initial administrator account for the system.</p>
      <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post">
        <label>Email</label>
        <input type="email" name="email" required placeholder="Enter admin email">
        <label>Department</label>
        <input type="text" name="department" required placeholder="Enter department">
        <label>Password</label>
        <input type="password" name="pass" required placeholder="Enter password (min. 6 characters)">
        <label>Confirm Password</label>
        <input type="password" name="pass2" required placeholder="Confirm password">
        <button type="submit" class="btn btn-primary">Create Admin Account</button>
      </form>
      <p><a href="login.php">Back to login</a></p>
    </div>
  </div>
</body>
</html>
