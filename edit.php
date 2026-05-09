<?php
require 'db.php';

// 1. Redis Connection for Cache Clearing
$redis = new Redis();
$redisReady = false;
try {
    if ($redis->connect('redis', 6379, 0.5)) $redisReady = true;
} catch (Exception $e) { $redisReady = false; }

$pdo = getDBConnection();
$message = "";
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM valid_customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $phonePattern = '/^(\+?1\s?)?(\(\d{3}\)|\d{3})[\s\-]?\d{3}[\s\-]?\d{4}$/';

    if (preg_match($phonePattern, $phone) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update = $pdo->prepare("UPDATE valid_customers SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
        if ($update->execute([$_POST['first_name'], $_POST['last_name'], $email, $phone, $id])) {
            
            // 2. Clear Redis Cache so changes show immediately
            if ($redisReady) {
                $redis->flushAll(); 
            }

            header("Location: index.php?status=updated");
            exit;
        }
    } else {
        $message = "Invalid Data Format. Changes not saved.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Customer | DataOps Pro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="logo">DataOps Pro</div>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="create.php">Add Customer</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <header class="top-bar">
                <h1>Edit Customer #<?php echo $id; ?></h1>
                <a href="index.php" class="btn-secondary">&larr; Cancel</a>
            </header>

            <?php if ($message): ?>
                <div class="alert error"><?php echo $message; ?></div>
            <?php endif; ?>

            <section class="form-container">
                <form method="POST" class="pro-form">
                    <div class="form-group">
                        <label>First Name</label>
                        <input name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                        <small>Requirement: Valid US Format</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Update Customer Record</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>