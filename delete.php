<?php
require 'db.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $pdo = getDBConnection();
    
    // 1. Perform Delete
    $stmt = $pdo->prepare("DELETE FROM valid_customers WHERE id = ?");
    $stmt->execute([$id]);

    // 2. Clear Redis Cache
    // Since the total record count and page content have changed, 
    // we must wipe the cache to force a fresh MySQL read.
    $redis = new Redis();
    try {
        if ($redis->connect('redis', 6379, 0.5)) {
            $redis->flushAll();
        }
    } catch (Exception $e) {
        // Continue even if Redis fails
    }
}

// Redirect back to dashboard
header("Location: index.php?status=deleted");
exit;