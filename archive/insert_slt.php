<?php
// Archived copy of insert_slt.php
require 'config.php';
$db = db_connect();
$queries = [
    ["Science Laboratory Technology", "SLT", "Laboratory technology studies"],
    ["Science Laboratory Technology - Physics", "PHY", "Physics specialization for HND"],
    ["Science Laboratory Technology - Microbiology", "MIC", "Microbiology specialization for HND"]
];
foreach ($queries as $q) {
    list($name, $code, $desc) = $q;
    $stmt = $db->prepare('SELECT id FROM programs WHERE name = ? OR code = ? LIMIT 1');
    $stmt->bind_param('ss', $name, $code);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $ins = $db->prepare('INSERT INTO programs (name, code, description) VALUES (?, ?, ?)');
        $ins->bind_param('sss', $name, $code, $desc);
        if ($ins->execute()) {
            echo "Inserted: $name ($code)\n";
        } else {
            echo "Failed to insert $name: " . $db->error . "\n";
        }
        $ins->close();
    } else {
        echo "Already exists: $name ($code)\n";
    }
    $stmt->close();
}
