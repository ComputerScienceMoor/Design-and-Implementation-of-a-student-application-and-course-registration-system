<?php
// Archived copy of migrate.php
require_once __DIR__ . '/../config.php';

function column_exists($db, $schema, $table, $column){
    $q = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->bind_param('sss', $schema, $table, $column);
    $q->execute();
    $q->bind_result($c);
    $q->fetch();
    $q->close();
    return intval($c) > 0;
}

function table_exists($db, $schema, $table){
    $q = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $q->bind_param('ss', $schema, $table);
    $q->execute();
    $q->bind_result($c);
    $q->fetch();
    $q->close();
    return intval($c) > 0;
}

try{
    $db = db_connect();
    $schema = DB_NAME;

    echo "Migration started...\n";

    // (migration logic archived)
    echo "Migration script archived for reference.\n";
} catch(Exception $e){
    echo "Migration error: " . $e->getMessage() . "\n";
}

if(php_sapi_name() !== 'cli'){
    echo '<pre>-- migration archived --</pre>';
}
