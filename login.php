<?php
require_once 'functions.php';

// --- MANUAL PHPMailer INCLUSION ---
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';
$mode = (isset($_GET['type']) && $_GET['type'] === 'returning') ? 'returning_signup' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register_returning'])) {
        $mode = 'returning_signup';
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $pass = $_POST['password'] ?? '';
        $pass2 = $_POST['confirm_password'] ?? '';
        $jamb = trim($_POST['jamb_no'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$email) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($pass !== $pass2) {
            $error = 'Passwords do not match.';
        } else {
            $db = db_connect();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Email already registered. Please log in instead.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $status = 'returning';
                $ins = $db->prepare('INSERT INTO users (email,jamb_no,phone,password_hash,status) VALUES (?,?,?,?,?)');
                $ins->bind_param('sssss', $email, $jamb, $phone, $hash, $status);
                
                if ($ins->execute()) {
                    send_welcome_email($email);
                    $_SESSION['user_id'] = $db->insert_id;
                    redirect('dashboard.php');
                } else {
                    $error = 'Registration failed.';
                }
            }
        }
    } elseif (isset($_POST['forgot_password'])) {
        $identifier = trim($_POST['identifier'] ?? '');
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address to reset your password.';
        } else {
            // ... (Your existing Forgot Password logic remains here)
        }
    } elseif (isset($_POST['login'])) {
        $identifier = trim($_POST['identifier'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($identifier === '') {
            $error = 'Enter your matriculation number or registered email.';
        } else {
            $db = db_connect();
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
            
            $query = $isEmail ? 'SELECT id, password_hash, email FROM users WHERE email = ? LIMIT 1' 
                              : 'SELECT id, password_hash, email FROM users WHERE matric_no = ? LIMIT 1';
            
            $stmt = $db->prepare($query);
            $stmt->bind_param('s', $identifier);
            $stmt->execute();
            $stmt->bind_result($id, $hash, $user_email);
            
            if ($stmt->fetch()) {
                if (password_verify($pass, $hash)) {
                    $_SESSION['user_id'] = $id;

                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'akinwunmipleasant13@gmail.com'; 
                        $mail->Password   = 'hhzw piwb cyay xihm';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = 465;

                        $mail->setFrom('akinwunmipleasant13@gmail.com', 'FCAHPT Portal');
                        $mail->addAddress($user_email);
                        $mail->isHTML(true);
                        $mail->Subject = 'Welcome Back to the Portal';
                        $mail->Body    = '<h1>Welcome Back!</h1><p>You have successfully logged into your student portal.</p>';
                        
                        $mail->send();
                    } catch (Exception $e) {
                        die("Mailer Error: " . $mail->ErrorInfo);
                    }

                    redirect('dashboard.php');
                } else {
                    $error = 'Invalid credentials.';
                }
            } else {
                $error = 'No account found. Please <a href="login.php?type=returning">create a returning student account</a> or <a href="signup.php">apply as prospective</a>.';
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
  <title><?php echo $mode === 'returning_signup' ? 'Returning Student Signup' : 'Returning Student Login'; ?> - Federal College of Animal Health and Production Technology</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <div class="auth-grid">
      <div class="auth-side">
        <h1><?php echo $mode === 'returning_signup' ? 'Returning Student Account' : 'Welcome Back'; ?></h1>
        <p><?php echo $mode === 'returning_signup'
            ? 'Create your returning student account to complete enrollment and register for courses.'
            : 'Access your student portal to track application status, upload documents, and register for courses.';
        ?></p>
        <div class="auth-illustration">📚</div>
      </div>

      <div class="auth-card">
        <h2><?php echo $mode === 'returning_signup' ? 'Returning Student Signup' : 'Returning Student Login'; ?></h2>
        <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
        
        <?php if($mode === 'returning_signup'): ?>
        <form method="post" action="login.php?type=returning">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
          </div>
          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
          </div>
          <div class="form-group">
            <label>JAMB Number</label>
            <input type="text" name="jamb_no" placeholder="Optional">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" placeholder="Optional">
          </div>
          <div class="auth-actions">
            <button type="submit" name="register_returning" class="btn btn-primary">Create Account</button>
          </div>
        </form>
        <p style="margin-top:12px;font-size:0.95rem;">Already have an account? <a href="login.php">Login here</a>.</p>
        <p style="margin-top:6px;font-size:0.95rem;">Not a returning student? <a href="signup.php">Apply as prospective</a>.</p>
        
        <?php else: ?>
        <form method="post" action="login.php">
          <div class="form-group">
            <label>Matriculation Number or Email</label>
            <input type="text" name="identifier" required>
          </div>
          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
          </div>
          <div class="auth-actions">
            <button type="submit" name="login" class="btn btn-primary">Login</button>
          </div>
        </form>
        <p style="margin-top:12px;font-size:0.95rem;">New returning student? <a href="login.php?type=returning">Create your account</a>.</p>
        <p style="margin-top:6px;font-size:0.95rem;">Not a returning student? <a href="signup.php">Apply as prospective</a>.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>