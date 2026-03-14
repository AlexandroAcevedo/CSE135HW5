<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['superadmin','analyst']);
require_once __DIR__ . '/includes/db.php';

$username = $_SESSION["username"] ?? "user";

/* Query 1: count visits by page in last 24 hours */
$pageResult = $conn->query("
    SELECT page, COUNT(*) AS total
    FROM analytics
    WHERE created >= NOW() - INTERVAL 24 HOUR
    GROUP BY page
    ORDER BY total DESC
");

/* Query 2: count events by type in last 24 hours */
$eventResult = $conn->query("
    SELECT event, COUNT(*) AS total
    FROM analytics
    WHERE created >= NOW() - INTERVAL 24 HOUR
    GROUP BY event
    ORDER BY total DESC
");

$pageLabels = [];
$pageData = [];

while ($row = $pageResult->fetch_assoc()) {
    $pageLabels[] = $row['page'];
    $pageData[] = (int)$row['total'];
}

$eventLabels = [];
$eventData = [];

while ($row = $eventResult->fetch_assoc()) {
    $eventLabels[] = $row['event'];
    $eventData[] = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Charts</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .chart-box {
            margin-top: 2rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
        }
        canvas {
            max-width: 100%;
            margin-top: 1rem;
        }
        .empty-note {
            margin-top: 1rem;
            padding: 1rem;
            background: #fff3cd;
            border: 1px solid #ffe69c;
            border-radius: 6px;
            color: #664d03;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <main>
        <h1>Analytics Charts</h1>
        <div class="meta">
            Logged in as <strong><?= htmlspecialchars($username) ?></strong>
        </div>

        <?php if (count($pageLabels) === 0 && count($eventLabels) === 0): ?>
            <div class="empty-note">
                No analytics data has been collected in the last 24 hours.
            </div>
        <?php endif; ?>

        <div class="chart-box">
            <h2>Visits by Page (Last 24 Hours)</h2>
            <canvas id="pageChart"></canvas>
        </div>

        <div class="chart-box">
            <h2>Events by Type (Last 24 Hours)</h2>
            <canvas id="eventChart"></canvas>
        </div>
    </main>

    <script>
        const pageLabels = <?= json_encode($pageLabels) ?>;
        const pageData = <?= json_encode($pageData) ?>;

        const eventLabels = <?= json_encode($eventLabels) ?>;
        const eventData = <?= json_encode($eventData) ?>;

        new Chart(document.getElementById('pageChart'), {
            type: 'bar',
            data: {
                labels: pageLabels,
                datasets: [{
                    label: 'Visits',
                    data: pageData
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('eventChart'), {
            type: 'pie',
            data: {
                labels: eventLabels,
                datasets: [{
                    label: 'Events',
                    data: eventData
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
</body>
</html>
