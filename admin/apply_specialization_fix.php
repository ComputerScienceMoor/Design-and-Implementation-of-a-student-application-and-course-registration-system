<?php
// Map users with specialization codes (swd/ncc) to the corresponding program id
require_once __DIR__ . '/../functions.php';
$db = db_connect();
$sql = "UPDATE users u JOIN programs p ON UPPER(p.code) = UPPER(u.specialization) SET u.registered_program = p.id WHERE (u.registered_program IS NULL OR u.registered_program = '' OR u.registered_program = '0') AND u.specialization IS NOT NULL AND u.specialization <> ''";
$db->query($sql);
echo "Updated users: " . $db->affected_rows . "\n";
