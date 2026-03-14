<?php

require_once __DIR__ . '/includes/auth.php';
require_role(['superadmin', 'analyst', 'viewer']);

$username = $_SESSION["username"] ?? "user";
$role = $_SESSION["role"] ?? "viewer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HW5 Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f6fa;
        }
       
        main {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }
        .card {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <main>
        <h1>Analytics Dashboard</h1>
        <p>Logged in as <strong><?= htmlspecialchars($username) ?></strong></p>
        <p>Role: <strong><?= htmlspecialchars($role) ?></strong></p>

        <div class="card">
            <h2>Access Summary</h2>
            <?php if ($role === 'superadmin'): ?>
                <p>You can manage users, view all reports, charts, and export data.</p>
            <?php elseif ($role === 'analyst'): ?>
                <p>You can view analytics reports, charts, and create report insights.</p>
            <?php else: ?>
                <p>You can only view report pages.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
