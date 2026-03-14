<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentRole = $_SESSION['role'] ?? null;
$currentUser = $_SESSION['username'] ?? 'Guest';
?>

<style>
    .admin-nav {
        background: #1f2937;
        padding: 14px 20px;
        color: white;
        margin-bottom: 24px;
    }

    .admin-nav .nav-wrap {
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .admin-nav .nav-left,
    .admin-nav .nav-right {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .admin-nav a {
        color: white;
        text-decoration: none;
        background: #374151;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.95rem;
    }

    .admin-nav a:hover {
        background: #4b5563;
    }

    .admin-nav .brand {
        font-weight: bold;
        background: transparent;
        padding-left: 0;
    }

    .admin-nav .user-info {
        font-size: 0.95rem;
        color: #d1d5db;
    }
</style>

<nav class="admin-nav">
    <div class="nav-wrap">
        <div class="nav-left">
            <a class="brand" href="/admin/index.php">Analytics Admin</a>
            <a href="/admin/index.php">Dashboard</a>
            <a href="/admin/reports.php">Reports Overview</a>

            <?php if (in_array($currentRole, ['superadmin', 'analyst', 'viewer'])): ?>
                <a href="/admin/reports/traffic.php">Traffic Report</a>
                <a href="/admin/reports/behavior.php">Behavior Report</a>
                <a href="/admin/reports/performance.php">Performance Report</a>
            <?php endif; ?>

            <?php if (in_array($currentRole, ['superadmin', 'analyst'])): ?>
                <a href="/admin/charts.php">Charts</a>
            <?php endif; ?>

            <?php if ($currentRole === 'superadmin'): ?>
                <a href="/admin/users.php">Manage Users</a>
            <?php endif; ?>
        </div>

        <div class="nav-right">
            <span class="user-info">
                Logged in as: <strong><?php echo htmlspecialchars($currentUser); ?></strong>
                (<?php echo htmlspecialchars($currentRole ?? 'none'); ?>)
            </span>
            <a href="/admin/logout.php">Logout</a>
        </div>
    </div>
</nav>
