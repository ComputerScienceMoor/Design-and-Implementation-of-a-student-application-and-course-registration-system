<?php
$file = $_GET['file'] ?? '';
$path = __DIR__ . '/posts/' . basename($file);
if(!file_exists($path)){
  http_response_code(404);
  echo 'Post not found';
  exit;
}
$content = file_get_contents($path);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Blog Post</title>
  <link rel="stylesheet" href="../css/style.css">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
  <div class="container blog-page">
    <header class="page-header">
      <div>
        <a href="index.php" class="btn btn-outline btn-small">← Back to blog</a>
        <h1>Blog Post</h1>
      </div>
    </header>
    <article class="post-content card">
      <?php echo $content; ?>
    </article>
  </div>
</body>
</html>
