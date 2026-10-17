<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$id = intval($_GET['id'] ?? 0);
if($id>0){
    // prevent deleting self
    if(isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $id){
        redirect('admins.php');
    }
    $db = db_connect();
    $stmt = $db->prepare('DELETE FROM admin_users WHERE id = ?');
    $stmt->bind_param('i',$id);
    $stmt->execute();
}
redirect('admins.php');
