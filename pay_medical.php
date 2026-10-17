<?php
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    redirect('login.php');
}

$reference = trim($_GET['reference'] ?? '');
$fee_title = trim($_GET['fee_title'] ?? 'medical');

if (empty($reference)) {
    $_SESSION['flash_error'] = 'Missing payment reference.';
    redirect('new_student.php?step=3');
}

/*
|--------------------------------------------------------------------------
| Forward to payment verification
|--------------------------------------------------------------------------
*/
$query = http_build_query([
    'reference' => $reference,
    'fee_title' => $fee_title
]);

redirect('verify_payment.php?' . $query);