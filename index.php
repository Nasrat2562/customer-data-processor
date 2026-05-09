<?php
require 'db.php';

// Initialize Redis
$redis = new Redis();
$isRedisActive = false;
try {
    if ($redis->connect('redis', 6379, 0.5)) {
        $isRedisActive = true;
    }
} catch (Exception $e) {
    $isRedisActive = false;
}

$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$cacheKey = "customers_p" . $page;
$customers = [];
$dataSource = "MySQL Database";

// 1. Try Redis Cache
if ($isRedisActive && $redis->exists($cacheKey)) {
    $customers = json_decode($redis->get($cacheKey), true);
    $dataSource = "Redis Cache (High Performance)";
} else {
    // 2. Fallback to MySQL
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM valid_customers ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($isRedisActive && !empty($customers)) {
        $redis->setex($cacheKey, 60, json_encode($customers));
    }
}

// Get Total Count
$total = 0;
if ($isRedisActive && $redis->exists('total_count')) {
    $total = $redis->get('total_count');
} else {
    $pdo = getDBConnection();
    $total = $pdo->query("SELECT COUNT(*) FROM valid_customers")->fetchColumn();
    if ($isRedisActive) $redis->setex('total_count', 600, $total);
}

$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Data Ops | Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="logo">DataOps Pro</div>
            <ul>
                <li class="active"><a href="index.php">Dashboard</a></li>
                <li><a href="create.php">Add Customer</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <header class="top-bar">
                <div>
                    <h1>System Dashboard</h1>
                    <small>Data Source: <strong style="color:#38bdf8"><?php echo $dataSource; ?></strong></small>
                </div>
                <div class="stats">Total: <?php echo number_format($total); ?></div>
            </header>

            <?php if (isset($_GET['status'])): ?>
                <div class="alert success" style="background:#dcfce7; color:#166534; padding:10px; border-radius:5px; margin-bottom:20px;">
                    Action completed successfully!
                </div>
            <?php endif; ?>

            <section class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px;">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>#<?php echo $c['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['email']); ?></td>
                                <td><?php echo htmlspecialchars($c['phone']); ?></td>
                                <td class="actions">
                                    <a href="edit.php?id=<?php echo $c['id']; ?>" class="btn-edit">Edit</a>
                                    <a href="delete.php?id=<?php echo $c['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <?php if($page > 1): ?> <a href="?page=<?php echo $page-1; ?>">&laquo; Prev</a> <?php endif; ?>
                    <span>Page <?php echo number_format($page); ?> of <?php echo number_format($totalPages); ?></span>
                    <?php if($page < $totalPages): ?> <a href="?page=<?php echo $page+1; ?>">Next &raquo;</a> <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>