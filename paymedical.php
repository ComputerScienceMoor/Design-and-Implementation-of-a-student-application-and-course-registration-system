<?php
require_once __DIR__.'/functions.php';
if(empty($_SESSION['user_id'])) redirect('login.php');

$db = db_connect();
$uid = intval($_SESSION['user_id']);
$amount = floatval($_GET['amount'] ?? 7400.00);
$fee_title = sanitize($_GET['fee'] ?? 'medical');

// Insert or update payment status as 'paid' so it flags on the Bursar dashboard
$stmt = $db->prepare("INSERT INTO payments (student_id, title, amount, status) VALUES (?, ?, ?, 'paid') ON DUPLICATE KEY UPDATE amount=?, status='paid'");
if($stmt){
    $stmt->bind_param('isdd', $uid, $fee_title, $amount, $amount);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['flash_success'] = 'Payment processed successfully! Awaiting Bursar verification.';
redirect('new_student.php?step=3');
?>