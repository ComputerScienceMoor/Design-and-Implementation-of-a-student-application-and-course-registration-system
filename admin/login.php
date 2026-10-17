<?php
require_once __DIR__.'/../functions.php';
$error = '';
$signup_error = '';
$signup_success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  if(isset($_POST['signup_submit'])){
    $email = trim($_POST['signup_email'] ?? '');
    $department = trim($_POST['signup_department'] ?? '');
    $pass = $_POST['signup_pass'] ?? '';
    $pass2 = $_POST['signup_pass2'] ?? '';

    if($email === '' || $department === '' || $pass === ''){
      $signup_error = 'Provide email, department and password.';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
      $signup_error = 'Please enter a valid email address.';
    } elseif($pass !== $pass2){
      $signup_error = 'Passwords do not match.';
    } else {
      $db = db_connect();
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $db->prepare('INSERT INTO admin_users (username,email,department,password_hash) VALUES (?,?,?,?)');
      $username = $email;
      $stmt->bind_param('ssss', $username, $email, $department, $hash);
      if($stmt->execute()){
        $signup_success = 'Admin account created successfully. You can sign in now.';
      } else {
        $signup_error = 'Failed to create admin account (email may already exist).';
      }
    }
  } else {
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['pass'] ?? '';
    if($user === '' || $pass === ''){
      $error = 'Enter email and password.';
    } else {
      $db = db_connect();
      $stmt = $db->prepare('SELECT id,password_hash FROM admin_users WHERE email = ? OR username = ? LIMIT 1');
      $stmt->bind_param('ss',$user,$user);
      $stmt->execute();
      $stmt->bind_result($aid,$hash);
      if($stmt->fetch() && password_verify($pass,$hash)){
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_id'] = $aid;
        session_regenerate_id(true);
        redirect('index.php');
      } else {
        $error = 'Invalid admin credentials.';
      }
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <div class="container">
    <div class="auth-grid">
      <div class="auth-side">
        <h1>Admin Access</h1>
        <p class="muted">Sign in to manage users, announcements, and application workflows.</p>
      </div>
      <div class="auth-card">
        <div class="auth-form-panel" data-mode="login">
          <h2>Administrator Login</h2>
          <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
          <form method="post" action="login.php">
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="user" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="pass" required>
            </div>
            <div class="auth-actions">
              <button type="submit" class="btn btn-primary">Login</button>
            </div>
          </form>
        </div>

        <div class="auth-form-panel" data-mode="signup" hidden>
          <h2>Create Admin Account</h2>
          <?php if($signup_error): ?><div class="error"><?php echo htmlspecialchars($signup_error); ?></div><?php endif; ?>
          <?php if($signup_success): ?><div class="success"><?php echo htmlspecialchars($signup_success); ?></div><?php endif; ?>
          <form method="post" action="login.php">
            <input type="hidden" name="signup_submit" value="1">
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="signup_email" required>
            </div>
            <div class="form-group">
              <label>Department</label>
              <input type="text" name="signup_department" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="signup_pass" required>
            </div>
            <div class="form-group">
              <label>Confirm Password</label>
              <input type="password" name="signup_pass2" required>
            </div>
            <div class="auth-actions">
              <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
          </form>
        </div>

        <div class="auth-switch auth-switch-bottom" role="tablist" aria-label="Authentication mode">
          <button type="button" class="auth-switch-btn active" data-auth-toggle="login">Sign In</button>
          <button type="button" class="auth-switch-btn" data-auth-toggle="signup">Create Account</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const buttons = Array.from(document.querySelectorAll('.auth-switch-btn'));
      const panels = Array.from(document.querySelectorAll('.auth-form-panel'));

      buttons.forEach(function (button) {
        button.addEventListener('click', function () {
          const target = button.getAttribute('data-auth-toggle');
          buttons.forEach(function (btn) {
            btn.classList.toggle('active', btn === button);
          });
          panels.forEach(function (panel) {
            const isActive = panel.getAttribute('data-mode') === target;
            panel.hidden = !isActive;
          });
        });
      });
    });
  </script>
</body>
</html>
