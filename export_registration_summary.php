<?php
require_once 'functions.php';
if(empty($_SESSION['user_id'])) {
    redirect('login.php');
}

$db = db_connect();
$uid = intval($_SESSION['user_id']);

$stmt = $db->prepare('SELECT full_name, email, application_department, matric_no FROM users WHERE id = ?');
if(!$stmt){
    http_response_code(500);
    exit('Unable to prepare student record.');
}
$stmt->bind_param('i', $uid);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$studentName = trim((string)($userRow['full_name'] ?? ''));
$studentDepartment = trim((string)($userRow['application_department'] ?? ''));
$matricNumber = trim((string)($userRow['matric_no'] ?? ''));
$email = trim((string)($userRow['email'] ?? ''));

$courseStmt = $db->prepare('SELECT c.name, c.course_code FROM student_course_registrations scr LEFT JOIN courses c ON c.id = scr.course_id WHERE scr.user_id = ? ORDER BY c.name, c.course_code');
$courses = [];
if($courseStmt){
    $courseStmt->bind_param('i', $uid);
    $courseStmt->execute();
    $courseResult = $courseStmt->get_result();
    while($courseRow = $courseResult->fetch_assoc()){
        $courseName = trim((string)($courseRow['name'] ?? ''));
        if($courseName === '') {
            continue;
        }
        $courses[] = $courseName . (!empty($courseRow['course_code']) ? ' (' . $courseRow['course_code'] . ')' : '');
    }
    $courseStmt->close();
}

function pdf_escape_text($text) {
    $text = (string)$text;
    if(function_exists('utf8_decode')) {
        $text = utf8_decode($text);
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$lines = [];
$lines[] = 'Student Course Registration Summary';
$lines[] = '';
$lines[] = 'Name: ' . ($studentName !== '' ? $studentName : 'Not provided');
$lines[] = 'Department: ' . ($studentDepartment !== '' ? $studentDepartment : 'Not set');
$lines[] = 'Matric Number: ' . ($matricNumber !== '' ? $matricNumber : 'Not assigned');
$lines[] = 'Email: ' . ($email !== '' ? $email : 'Not provided');
$lines[] = '';
$lines[] = 'Registered Courses:';
if(empty($courses)) {
    $lines[] = '- No courses registered yet.';
} else {
    foreach($courses as $course){
        $lines[] = '- ' . $course;
    }
}

$contentStream = "BT\n/F1 18 Tf\n50 760 Td\n(" . pdf_escape_text($lines[0]) . ") Tj\n0 -18 Td\n/F1 10 Tf\n";
for($i = 1; $i < count($lines); $i++) {
    $lineText = $lines[$i];
    $contentStream .= '(' . pdf_escape_text($lineText) . ") Tj\n0 -12 Td\n";
}
$contentStream .= "ET";

$objects = [];
$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj";
$objects[] = "4 0 obj\n<< /Length 6 0 R >>\nstream\n" . $contentStream . "\nendstream\nendobj";
$objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";
$objects[] = "6 0 obj\n" . strlen($contentStream) . "\nendobj";

$offsets = [];
$pdf = "%PDF-1.4\n";
$offsets[1] = strlen($pdf);
foreach($objects as $index => $obj){
    $offsets[$index + 1] = strlen($pdf);
    $pdf .= $obj . "\n";
}
$startxref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $startxref . "\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="student-course-registration-summary.pdf"');
echo $pdf;
exit;
