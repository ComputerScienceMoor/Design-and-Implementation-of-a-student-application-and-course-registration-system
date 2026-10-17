<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();
$msg = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = $_POST['title'] ?? '';
    $body = $_POST['body'] ?? '';
    $stmt = $db->prepare('INSERT INTO announcements (title,body) VALUES (?,?)');
    $stmt->bind_param('ss',$title,$body);
    $stmt->execute();
    redirect('index.php');
}
?>
<?php include 'header.php'; ?>
  <div class="form-box">
    <h2>Create Announcement</h2>
    <form method="post">
      <label>Title</label>
      <input type="text" name="title" required>
      <label>Body</label>
      <textarea name="body" rows="6" style="width:100%" required></textarea>
      <button type="submit">Create</button>
    </form>
    <p><a href="index.php">Back</a></p>
  </div>
<?php include 'footer.php'; ?>
