<?php
// Simple static blog index: lists HTML files in posts/
require_once __DIR__ . '/../functions.php';

$postsDir = __DIR__ . '/posts';
$files = glob($postsDir . '/*.html');
usort($files, function($a,$b){ return filemtime($b) - filemtime($a); });

$db = db_connect();
$annRes = null;
try{
  $annRes = $db->query('SELECT title, body, created_at FROM announcements ORDER BY created_at DESC LIMIT 5');
} catch(Exception $e){ $annRes = null; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Prospective Student Blog</title>
  <link rel="stylesheet" href="../css/style.css">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
  <div class="container blog-page">
    <header class="page-header">
      <div>
        <?php
          // Use web-relative URLs to avoid file:// paths in the browser.
          // blog/index.php is in /blog, so ../dashboard.php points to the dashboard.
          $backHref = (!empty($_SESSION['user_id'])) ? '../dashboard.php' : '../index.php';
        ?>
        <a href="<?php echo htmlspecialchars($backHref); ?>" class="btn btn-outline btn-small">← Back to portal</a>
        <h1>Prospective Student Blog</h1>
        <p class="page-description">Read the latest guidance, application updates, and admission news.</p>
      </div>
    </header>

    <?php if($annRes && $annRes->num_rows > 0): ?>
      <section class="announcement-list">
        <?php while($a = $annRes->fetch_assoc()): ?>
          <article class="announcement-card">
            <div class="announcement-meta"><?php echo htmlspecialchars(date('M j, Y', strtotime($a['created_at']))); ?></div>
            <h2><?php echo htmlspecialchars($a['title']); ?></h2>
            <div class="announcement-body"><?php echo nl2br(htmlspecialchars($a['body'])); ?></div>
          </article>
        <?php endwhile; ?>
      </section>
    <?php endif; ?>

    <?php if(empty($files)): ?>
      <div class="empty-state">
        <div class="empty-icon">📰</div>
        <p>No posts yet. Check back soon.</p>
      </div>
    <?php else: ?>
      <div class="news-list">
        <?php foreach($files as $f):
          $raw = file_get_contents($f);
          if(preg_match('/<h1[^>]*>(.*?)<\/h1>/is',$raw,$m)) $title = strip_tags($m[1]);
          else $title = basename($f, '.html');
          if(preg_match('/<p[^>]*>(.*?)<\/p>/is',$raw,$p)) $excerpt = strip_tags($p[1]);
          else $excerpt = trim(strip_tags($raw));
          $excerpt = mb_strlen($excerpt) > 200 ? mb_substr($excerpt,0,197).'...' : $excerpt;
          $slug = basename($f);
          $mtime = date('M j, Y', filemtime($f));
        ?>
          <article class="news-card">
            <div class="news-card-body">
              <h3 class="news-title"><a href="post.php?file=<?php echo urlencode($slug); ?>"><?php echo htmlspecialchars($title); ?></a></h3>
              <div class="news-date"><?php echo htmlspecialchars($mtime); ?></div>
              <p class="news-excerpt"><?php echo htmlspecialchars($excerpt); ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
