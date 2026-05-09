<?php
require 'db.php';

// 1. Initialize Redis for cache invalidation
$redis = new Redis();
$redisReady = false;
try {
    if ($redis->connect('redis', 6379, 0.5)) {
        $redisReady = true;
    }
} catch (Exception $e) {
    $redisReady = false;
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();
    
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    // Requirement #10: Specific Validation Algorithm
    $phonePattern = '/^(\+?1\s?)?(\(\d{3}\)|\d{3})[\s\-]?\d{3}[\s\-]?\d{4}$/';

    if (preg_match($phonePattern, $phone) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Insert into Database
        $stmt = $pdo->prepare("INSERT INTO valid_customers (first_name, last_name, email, phone, ip) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([
            $_POST['first_name'], 
            $_POST['last_name'], 
            $email, 
            $phone, 
            $_SERVER['REMOTE_ADDR']
        ]);

        if ($success) {
            // 2. IMPORTANT: Invalidate Redis Cache
            // Since a new record is added, the cached pages and total count are now outdated
            if ($redisReady) {
                $redis->del('total_count'); // Force recount on next dashboard load
                // Invalidate the first few pages of the dashboard where the new record likely appears
                for ($i = 1; $i <= 5; $i++) {
                    $redis->del("customers_p" . $i);
                }
            }

            header("Location: index.php");
            exit;
        }
    } else {
        $message = "Invalid Data Format. Please check the Phone and Email requirements.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer | DataOps Pro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="logo">DataOps Pro</div>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li class="active"><a href="create.php">Add Customer</a></li>
                <li><a href="processor.php?action=export">Export Batches</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <header class="top-bar">
                <h1>Add New Customer</h1>
                <a href="index.php" class="btn-secondary">&larr; Back to List</a>
            </header>

            <?php if ($message): ?>
                <div class="alert error"><?php echo $message; ?></div>
            <?php endif; ?>

            <section class="form-container">
                <form method="POST" class="pro-form">
                    <div class="form-group">
                        <label>First Name</label>
                        <input name="first_name" placeholder="John" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name</label>
                        <input name="last_name" placeholder="Doe" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="john.doe@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input name="phone" placeholder="555-555-5555" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        <small>Requirement: Valid US Format (e.g., 123-456-7890)</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Customer</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>