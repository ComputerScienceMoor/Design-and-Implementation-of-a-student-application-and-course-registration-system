<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();
$admin_id = intval($_SESSION['admin_id'] ?? 0);
$accessibleProgramIds = get_admin_program_ids($db, $admin_id);
$hasFullAccess = false;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin — Federal College of Animal Health and Production Technology</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <img src="../images/logo.png.jpg" alt="College Logo" class="site-logo">
      <div class="brand">Federal College of Animal Health and Production Technology</div>
      <nav>
        <a href="index.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="programs.php">Programs</a>
        <a href="courses.php">Courses</a>
        <a href="cleanup_stray_courses.php">Cleanup Courses</a>
        <a href="admins.php">Admins</a>
        <a href="announce_create.php">Create Announcement</a>
        <a href="../index.php">Site</a>
        <a href="logout.php" class="danger">Logout</a>
      </nav>
    </aside>
    <main class="admin-main">
      <header class="admin-header">
        <h1>Admin Panel</h1>
      </header>
      <section class="admin-content">
