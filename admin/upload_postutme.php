<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$id = intval($_GET['id'] ?? 0);
if($id <= 0) redirect('users.php');

$db = db_connect();
$error = '';
$currentStatus = null;
$q = $db->prepare('SELECT status FROM users WHERE id = ?');
$q->bind_param('i',$id);
$q->execute();
$q->store_result();
$q->bind_result($currentStatus);
$q->fetch();
$q->free_result();
$q->close();

if(in_array($currentStatus,['new','failed','completed'], true)){
    $_SESSION['flash_error'] = 'Post-UTME upload and decision are already complete for this student.';
    redirect('user_files.php?id='.$id);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(empty($_FILES['postutme']) || $_FILES['postutme']['error'] !== UPLOAD_ERR_OK){
        $error = 'File upload failed or no file provided.';
    } else {
        $allowed = ['application/pdf','image/jpeg','image/png'];
        $tmp = $_FILES['postutme']['tmp_name'];
        $type = mime_content_type($tmp);
        if(!in_array($type,$allowed)){
            $error = 'Invalid file type. Allowed: PDF, JPG, PNG.';
        } else {
            $uploadDir = __DIR__.'/../uploads';
            if(!is_dir($uploadDir)){
                if(!safe_mkdir($uploadDir,0755)){
                    $error = 'Failed to create uploads directory.';
                }
            }
            if(empty($error)){
                $name = 'post_'.$id.'_'.time().'_'.basename($_FILES['postutme']['name']);
                $safe = 'uploads/'.preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
                $destPath = __DIR__.'/../'. $safe;
                if(move_uploaded_file($tmp, $destPath)){
                    // Ensure the DB column exists; if not, try to add it.
                    $colExists = false;
                    $res = $db->query("SHOW COLUMNS FROM users LIKE 'post_utme_path'");
                    if($res && $res->num_rows > 0) {
                        $colExists = true;
                    } else {
                        // attempt to add column
                        $alt = $db->query("ALTER TABLE users ADD COLUMN post_utme_path VARCHAR(255) DEFAULT NULL");
                        if($alt) $colExists = true;
                    }

                    $currentStatus = null;
                    $q = $db->prepare('SELECT status FROM users WHERE id = ?');
                    $q->bind_param('i',$id);
                    $q->execute();
                    $q->store_result();
                    $q->bind_result($currentStatus);
                    $q->fetch();
                    $q->free_result();
                    $q->close();

                    // After admin uploads a Post-UTME result, the student should be able to
                    // continue into enrollment by moving them to the next stage.
                    $stat = in_array($currentStatus, ['new','returning','completed'], true) ? $currentStatus : 'new';
                    if($colExists){
                        $stmt = $db->prepare('UPDATE users SET post_utme_path = ? WHERE id = ?');
                        if($stmt){ $stmt->bind_param('si',$safe,$id); $stmt->execute(); $stmt->close(); }
                    }
                    if(!update_user_status($db, $id, $stat, 'postutme')){
                        $error = 'Status update blocked by policy.';
                    } else {
                        redirect('user_files.php?id='.$id);
                    }
                } else {
                    $error = 'Failed to move uploaded file. Check permissions.';
                }
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
  <title>Upload Post-UTME</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <div class="container">
    <div class="form-box">
      <h2>Upload Post-UTME Result</h2>
      <p style="color: var(--muted); margin-bottom: 20px;">Student ID: <strong><?php echo $id; ?></strong></p>
      <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <label>Post-UTME Result (PDF, JPG, or PNG)</label>
        <input type="file" name="postutme" accept=".pdf,.jpg,.jpeg,.png" required>
        <p style="color: var(--muted); font-size: 0.9rem; margin-top: 8px;">Maximum file size: 5 MB</p>
        <button type="submit" class="btn btn-primary">Upload Document</button>
      </form>
      <p><a href="user_files.php?id=<?php echo $id; ?>">Back to user files</a></p>
    </div>
  </div>
</body>
</html>
