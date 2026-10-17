<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$id = intval($_GET['id'] ?? 0);
if($id>0){
    $db = db_connect();
    $stmt = $db->prepare('DELETE FROM announcements WHERE id = ?');
    $stmt->bind_param('i',$id);
    $stmt->execute();
}
redirect('index.php');
