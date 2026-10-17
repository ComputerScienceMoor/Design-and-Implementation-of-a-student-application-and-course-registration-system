<?php
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    redirect('login.php');
}

$db = db_connect();
$uid = intval($_SESSION['user_id']);

$reference = trim($_GET['reference'] ?? '');
$fee_title = trim($_GET['fee_title'] ?? 'medical');

if (empty($reference)) {
    $_SESSION['flash_error'] = 'Missing payment reference.';
    redirect('new_student.php?step=3');
}

$fee_schedule = [
    'medical' => 7400.00
];

if (!array_key_exists($fee_title, $fee_schedule)) {
    $_SESSION['flash_error'] = 'Unknown fee.';
    redirect('new_student.php?step=3');
}

$expectedAmount = intval($fee_schedule[$fee_title] * 100);

// Prevent duplicate verification
$stmt = $db->prepare("SELECT id,status FROM payments WHERE reference=? LIMIT 1");
$stmt->bind_param("s", $reference);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && $existing['status'] == 'verified') {
    $_SESSION['flash_success'] = "Payment already verified.";
    redirect('new_student.php?step=3');
}

/*
|--------------------------------------------------------------------------
| PAYSTACK SECRET KEY
|--------------------------------------------------------------------------
*/
$secretKey = "sk_test_ba230611e7d9060e8a0e4da69a85be3e36adcbfc";

/*
|--------------------------------------------------------------------------
| VERIFY PAYMENT
|--------------------------------------------------------------------------
*/
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$secretKey}",
        "Cache-Control: no-cache"
    ]
]);

$response = curl_exec($curl);

if (curl_errno($curl)) {

    $_SESSION['flash_error'] = "Unable to connect to Paystack.";

    curl_close($curl);

    redirect('new_student.php?step=3');
}

$http = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

$result = json_decode($response, true);

if (
    $http != 200 ||
    !$result ||
    empty($result['status']) ||
    empty($result['data'])
) {

    $_SESSION['flash_error'] = "Payment verification failed.";

    redirect('new_student.php?step=3');
}

$data = $result['data'];

if ($data['status'] != "success") {

    $_SESSION['flash_error'] = "Payment was not successful.";

    redirect('new_student.php?step=3');
}

if ($data['currency'] != "NGN") {

    $_SESSION['flash_error'] = "Invalid payment currency.";

    redirect('new_student.php?step=3');
}

if (intval($data['amount']) != $expectedAmount) {

    $_SESSION['flash_error'] = "Incorrect payment amount.";

    redirect('new_student.php?step=3');
}

/*
|--------------------------------------------------------------------------
| SAVE PAYMENT
|--------------------------------------------------------------------------
*/

$db->begin_transaction();

try {

    $amount = $expectedAmount / 100;

    $status = "verified";

    $stmt = $db->prepare("
        INSERT INTO payments
        (student_id,title,amount,reference,status)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            amount=VALUES(amount),
            reference=VALUES(reference),
            status='verified'
    ");

    $stmt->bind_param(
        "isdss",
        $uid,
        $fee_title,
        $amount,
        $reference,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    $db->commit();

    $_SESSION['flash_success'] =
        "Payment verified successfully. You can now complete your medical form.";

} catch (Exception $e) {

    $db->rollback();

    error_log($e->getMessage());

    $_SESSION['flash_error'] =
        "Unable to save payment.";

}

redirect('new_student.php?step=3');