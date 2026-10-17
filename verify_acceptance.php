<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    redirect('login.php');
}

$db = db_connect();
$user_id = intval($_SESSION['user_id']);

/*
|--------------------------------------------------------------------------
| Paystack Secret Key
|--------------------------------------------------------------------------
*/
$secretKey = "sk_test_ba230611e7d9060e8a0e4da69a85be3e36adcbfc";

/*
|--------------------------------------------------------------------------
| Acceptance Fee
|--------------------------------------------------------------------------
*/
$expectedAmount = 25000 * 100; // Kobo

/*
|--------------------------------------------------------------------------
| Get Payment Reference
|--------------------------------------------------------------------------
*/
$reference = trim($_GET['reference'] ?? $_GET['trxref'] ?? '');

if (empty($reference)) {

    $_SESSION['flash_error'] = "Payment reference not found.";

    redirect('pay_acceptance.php');
}

/*
|--------------------------------------------------------------------------
| Verify Transaction With Paystack
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

    $_SESSION['flash_error'] = curl_error($curl);

    curl_close($curl);

    redirect('pay_acceptance.php');
}

$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

$result = json_decode($response, true);

if (
    $httpCode != 200 ||
    !$result ||
    empty($result['status']) ||
    empty($result['data'])
) {

    $_SESSION['flash_error'] = "Unable to verify payment.";

    redirect('pay_acceptance.php');
}

$data = $result['data'];

/*
|--------------------------------------------------------------------------
| Validate Transaction
|--------------------------------------------------------------------------
*/
if ($data['status'] !== "success") {

    $_SESSION['flash_error'] = "Payment was not successful.";

    redirect('pay_acceptance.php');
}

if ($data['currency'] !== "NGN") {

    $_SESSION['flash_error'] = "Invalid currency.";

    redirect('pay_acceptance.php');
}

if (intval($data['amount']) !== $expectedAmount) {

    $_SESSION['flash_error'] = "Incorrect payment amount.";

    redirect('pay_acceptance.php');
}

/*
|--------------------------------------------------------------------------
| Prevent Duplicate Payment
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("SELECT id FROM payments WHERE reference=? LIMIT 1");
$stmt->bind_param("s", $reference);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($exists) {

    $_SESSION['flash_success'] = "Acceptance fee already verified.";

    redirect('new_student.php');
}

/*
|--------------------------------------------------------------------------
| Save Payment
|--------------------------------------------------------------------------
*/
$db->begin_transaction();

try {

    $amount = 25000.00;

    $title = "acceptance";

    $status = "verified";

    $stmt = $db->prepare("
        INSERT INTO payments
        (student_id,title,amount,reference,status)
        VALUES (?,?,?,?,?)
    ");

    if (!$stmt) {
        throw new Exception($db->error);
    }

    $stmt->bind_param(
        "isdss",
        $user_id,
        $title,
        $amount,
        $reference,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Update User (Only if column exists)
    |--------------------------------------------------------------------------
    */

    $check = $db->query("SHOW COLUMNS FROM users LIKE 'acceptance_fee_paid'");

    if ($check && $check->num_rows > 0) {

        $stmt = $db->prepare("
            UPDATE users
            SET acceptance_fee_paid = 1
            WHERE id = ?
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    $db->commit();

    $_SESSION['flash_success'] =
        "Acceptance fee payment verified successfully.";

    redirect('new_student.php');

} catch (Exception $e) {

    $db->rollback();

    error_log($e->getMessage());

    $_SESSION['flash_error'] =
        "Unable to save payment.";

    redirect('pay_acceptance.php');
}