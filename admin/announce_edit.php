<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();
$id = intval($_GET['id'] ?? 0);
if($id<=0) redirect('index.php');
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = $_POST['title'] ?? '';
    $body = $_POST['body'] ?? '';
    $stmt = $db->prepare('UPDATE announcements SET title = ?, body = ? WHERE id = ?');
    $stmt->bind_param('ssi',$title,$body,$id);
    $stmt->execute();
    redirect('index.php');
}
$stmt = $db->prepare('SELECT title,body FROM announcements WHERE id = ?');
$stmt->bind_param('i',$id);
$stmt->execute();
$stmt->bind_result($title,$body);
$stmt->fetch();
?>
<?php include 'header.php'; ?>
  <div class="form-box">
    <h2>Edit Announcement</h2>
    <form method="post">
      <label>Title</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
      <label>Body</label>
      <textarea name="body" rows="6" style="width:100%" required><?php echo htmlspecialchars($body); ?></textarea>
      <button type="submit">Save</button>
    </form>
    <p><a href="index.php">Back</a></p>
  </div>
<?php include 'footer.php'; ?>
