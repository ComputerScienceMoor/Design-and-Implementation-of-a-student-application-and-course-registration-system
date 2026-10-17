<?php
require_once __DIR__.'/../config.php';
$db = db_connect();
$res = $db->query("SELECT p.code, p.name, COUNT(c.id) AS course_count FROM programs p LEFT JOIN courses c ON c.program_id = p.id WHERE p.code IN ('AHT','APT','SWD','NCC') GROUP BY p.id ORDER BY p.code");
while ($row = $res->fetch_assoc()) {
    echo $row['code'] . ' | ' . $row['name'] . ' | courses=' . $row['course_count'] . PHP_EOL;
}
$db->close();
