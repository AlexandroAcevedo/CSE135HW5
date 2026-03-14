<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['superadmin','analyst','viewer']);
require_once __DIR__ . '/includes/db.php';

$result = $conn->query("
    SELECT id, cid, page, event, created
    FROM analytics
    ORDER BY id DESC
    LIMIT 100
");

$rows = $result->fetch_all(MYSQLI_ASSOC);
$username = $_SESSION["username"] ?? "user";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f6fa;
        }
        main {
            max-width: 1100px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }
        h1 {
            margin-top: 0;
        }
        .meta {
            margin-bottom: 1rem;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.95rem;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 0.75rem;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #1e66f5;
            color: white;
        }
        tr:nth-child(even) {
            background: #f8f9fc;
        }
        .empty {
            padding: 1rem;
            background: #fff3cd;
            border: 1px solid #ffe69c;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <main>
        <h1>Reports Table</h1>
        <div class="meta">
            Logged in as <strong><?= htmlspecialchars($username) ?></strong>
        </div>

        <?php if (count($rows) === 0): ?>
            <div class="empty">No analytics data found.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client ID</th>
                        <th>Page</th>
                        <th>Event</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['cid']) ?></td>
                            <td><?= htmlspecialchars($row['page']) ?></td>
                            <td><?= htmlspecialchars($row['event']) ?></td>
                            <td><?= htmlspecialchars($row['created']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
