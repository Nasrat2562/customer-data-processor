<?php
require 'db.php';

// 1. Connect to DB and Redis
$pdo = getDBConnection();
$redis = new Redis();
$redis->connect('redis', 6379);

$file = fopen('1M-customers.csv', 'r');
$batchSize = 5000; // Insert in chunks for speed
$count = 0;

echo "Starting import...\n";

$pdo->beginTransaction();

while (($row = fgetcsv($file)) !== FALSE) {
    // Basic validation to separate valid/invalid (Requirement #4)
    if (filter_var($row[6], FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("INSERT INTO valid_customers (first_name, last_name, email, phone, ip) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$row[0], $row[1], $row[6], $row[5], $row[7]]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO invalid_customers (first_name, last_name, email, phone, ip, error_message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$row[0], $row[1], $row[6], $row[5], $row[7], 'Invalid Email Format']);
    }

    $count++;

    // Commit every 5000 rows to keep memory low
    if ($count % $batchSize == 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "Imported $count rows...\n";
    }
}

$pdo->commit();
fclose($file);

// 2. CLEAR REDIS so the dashboard updates
$redis->flushAll();

echo "Finished! $count rows processed. Dashboard updated.\n";