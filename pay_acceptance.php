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
| Get Student Details
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ? LIMIT 1");

if (!$stmt) {
    die("Database Error: " . $db->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Student record not found.");
}

$name  = $user['full_name'];
$email = $user['email'];

/*
|--------------------------------------------------------------------------
| Check if Acceptance Fee Already Paid
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT id
    FROM payments
    WHERE student_id = ?
    AND title = 'acceptance'
    AND status = 'verified'
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$paid = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($paid) {
    $_SESSION['flash_success'] = "Acceptance fee has already been paid.";
    redirect("new_student.php");
}

/*
|--------------------------------------------------------------------------
| Payment Settings
|--------------------------------------------------------------------------
*/
$amount = 25000.00;
$reference = "ACPT_" . strtoupper(uniqid()) . "_" . $user_id;

/*
|--------------------------------------------------------------------------
| Paystack Secret Key
|--------------------------------------------------------------------------
*/
$secretKey = "sk_test_ba230611e7d9060e8a0e4da69a85be3e36adcbfc";

/*
|--------------------------------------------------------------------------
| Callback URL
|--------------------------------------------------------------------------
*/
$callback_url = "http://localhost/student-course-registration/verify_acceptance.php";

/*
|--------------------------------------------------------------------------
| Initialize Payment
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === "POST") {

    $fields = [

        "email" => $email,

        "amount" => intval($amount * 100),

        "reference" => $reference,

        "callback_url" => $callback_url,

        "metadata" => [

            "student_id"   => $user_id,
            "payment_type" => "acceptance",
            "student_name" => $name

        ]

    ];

    $curl = curl_init();

    curl_setopt_array($curl, [

        CURLOPT_URL => "https://api.paystack.co/transaction/initialize",

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS => json_encode($fields),

        CURLOPT_HTTPHEADER => [

            "Authorization: Bearer {$secretKey}",

            "Content-Type: application/json"

        ]

    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {

        die("Paystack Error: " . curl_error($curl));

    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    $result = json_decode($response, true);

    if ($httpCode != 200) {

        echo "<pre>";
        print_r($result);
        exit;

    }

    if (!$result || !isset($result['status'])) {

        die("Invalid response received from Paystack.");

    }

    if ($result['status'] !== true) {

        die($result['message']);

    }

    header("Location: " . $result['data']['authorization_url']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Acceptance Fee Payment</title>

<style>

body{
    background:#f4f6f9;
    font-family:Arial,Helvetica,sans-serif;
}

.card{
    width:600px;
    margin:60px auto;
    background:#fff;
    padding:35px;
    border-radius:12px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
}

h2{
    text-align:center;
    margin-bottom:15px;
}

.notice{
    text-align:center;
    color:#666;
    margin-bottom:25px;
}

.amount{
    text-align:center;
    font-size:45px;
    color:#0d6efd;
    font-weight:bold;
    margin-bottom:30px;
}

.info{
    background:#f8f9fa;
    padding:20px;
    border-radius:8px;
    margin-bottom:25px;
}

.info p{
    margin:12px 0;
}

button{
    width:100%;
    background:#198754;
    color:#fff;
    border:none;
    padding:15px;
    font-size:17px;
    border-radius:8px;
    cursor:pointer;
}

button:hover{
    background:#157347;
}

</style>

</head>

<body>

<div class="card">

<h2>Acceptance Fee Payment</h2>

<p class="notice">
Congratulations! You have been offered admission.<br>
Kindly pay your Acceptance Fee to continue your registration.
</p>

<div class="amount">
₦<?= number_format($amount,2); ?>
</div>

<div class="info">

<p><strong>Student:</strong> <?= htmlspecialchars($name); ?></p>

<p><strong>Email:</strong> <?= htmlspecialchars($email); ?></p>

<p><strong>Reference:</strong> <?= htmlspecialchars($reference); ?></p>

</div>

<form method="POST">

<button type="submit">

Proceed to Pay ₦<?= number_format($amount,2); ?>

</button>

</form>

</div>

</body>
</html>