<?php
require_once 'functions.php';
if(empty($_SESSION['user_id'])) redirect('login.php');
$db = db_connect();
$ann = $db->query('SELECT title, body, created_at FROM announcements ORDER BY created_at DESC');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Announcements - Federal College of Animal Health and Production Technology</title>
  <link rel="stylesheet" href="css/style.css">
  </head>
  <body class="student-full">
  <div class="container">
    <div class="page-header">
      <div>
        <a href="dashboard.php" class="btn btn-outline btn-small">← Back to dashboard</a>
        <h1>Announcements</h1>
        <p class="page-description">Important updates and admission information from Federal College of Animal Health and Production Technology.</p>
      </div>
    </div>

    <?php if($ann && $ann->num_rows > 0): ?>
      <div class="announcement-list">
        <?php while($row = $ann->fetch_assoc()): ?>
          <article class="announcement-card">
            <div class="announcement-meta"><?php echo htmlspecialchars(date('M j, Y', strtotime($row['created_at']))); ?></div>
            <h2><?php echo htmlspecialchars($row['title']); ?></h2>
            <div class="announcement-body"><?php echo nl2br(htmlspecialchars($row['body'])); ?></div>
          </article>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <p>No announcements yet. Check back soon for application updates.</p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
