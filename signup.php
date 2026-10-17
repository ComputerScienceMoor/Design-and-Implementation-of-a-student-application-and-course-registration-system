<?php
require_once 'functions.php';


$signupType = (isset($_GET['type']) && $_GET['type'] === 'returning') ? 'returning' : 'prospective';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $db = db_connect();
    $signupType = ($_POST['status'] ?? 'prospective') === 'returning' ? 'returning' : 'prospective';
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $jamb = trim($_POST['jamb_no'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';

    if(!$email) $error = 'Enter a valid email.';
    elseif(strlen($pass) < 6) $error = 'Password must be at least 6 characters.';
    elseif($pass !== $pass2) $error = 'Passwords do not match.';
    else{
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0){
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins = $db->prepare('INSERT INTO users (email,jamb_no,phone,password_hash,status) VALUES (?,?,?,?,?)');
            $ins->bind_param('sssss',$email,$jamb,$phone,$hash,$signupType);
            
            if($ins->execute()){
                send_welcome_email($email);
                if($signupType === 'returning'){
                    $_SESSION['user_id'] = $db->insert_id;
                    redirect('new_student.php?step=1');
                }
                redirect('login.php?msg=welcome_sent');
            } else {
                $error = 'Registration failed.';
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
  <title><?php echo $signupType === 'returning' ? 'Returning Student Signup' : 'Prospective Signup'; ?> - Federal College of Animal Health and Production Technology</title>
  <link rel="stylesheet" href="css/style.css">
 </head>
<body>
  <div class="container">
    <div class="auth-grid">
      <div class="auth-side">
        <div class="page-heading-logo">
          <img src="images/logo.png.jpg" alt="College Logo" class="site-logo signup-logo">
          <h1>Join Federal College of Animal Health and Production Technology</h1>
        </div>
        <p><?php echo $signupType === 'returning'
            ? 'Returning students can sign up with an email and password, then continue through the same enrollment process as a prospective student.'
            : 'Sign up as a prospective student to receive updates, upload required documents, and track your application progress.';
        ?></p>
        <div class="auth-illustration">🧑‍🎓</div>
      </div>

      <div class="auth-card">
        <h2><?php echo $signupType === 'returning' ? 'Returning Student Signup' : 'Prospective Student Signup'; ?></h2>
        <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" action="signup.php<?php echo $signupType === 'returning' ? '?type=returning' : ''; ?>">
          <label>Email</label>
          <input type="email" name="email" required>
          <label>JAMB Number</label>
          <input type="text" name="jamb_no">
          <label>Phone</label>
          <input type="text" name="phone">
          <label>Password</label>
          <input type="password" name="password" required>
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required>
          <input type="hidden" name="status" value="<?php echo $signupType; ?>">
          <div class="auth-actions">
            <button type="submit">Create Account</button>
            <a href="login.php" class="small">Already have an account?</a>
          </div>
        </form>
        <div class="auth-legal">By signing up you agree to the terms and privacy policy.</div>
      </div>
    </div>
  </div>
</body>
</html>
