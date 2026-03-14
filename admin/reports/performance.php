<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
require_role(['superadmin', 'analyst', 'viewer']);

$report_type = 'performance';
$error = '';
$success = '';

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Comment submission
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (in_array($_SESSION['role'], ['superadmin', 'analyst'])) {
        $comment = trim($_POST['comment']);
        $author = $_SESSION['username'] ?? 'unknown';

        if ($comment !== '') {
            $stmt = $conn->prepare("
                INSERT INTO report_comments (report_type, author, comment, created)
                VALUES (?, ?, ?, NOW())
            ");
            if ($stmt) {
                $stmt->bind_param("sss", $report_type, $author, $comment);
                if ($stmt->execute()) {
                    $success = "Comment added successfully.";
                } else {
                    $error = "Failed to save comment.";
                }
                $stmt->close();
            } else {
                $error = "Failed to prepare comment insert.";
            }
        } else {
            $error = "Comment cannot be empty.";
        }
    } else {
        $error = "You are not allowed to post comments.";
    }
}

/*
|--------------------------------------------------------------------------
| Summary stats
|--------------------------------------------------------------------------
*/
$avgLoadTime = 0;
$maxLoadTime = 0;
$slowestPage = 'N/A';
$sampleCount = 0;

$result = $conn->query("
    SELECT ROUND(AVG(load_time_ms), 2) AS avg_load
    FROM analytics
    WHERE load_time_ms IS NOT NULL
");
if ($result && $row = $result->fetch_assoc()) {
    $avgLoadTime = $row['avg_load'] ?? 0;
}

$result = $conn->query("
    SELECT MAX(load_time_ms) AS max_load
    FROM analytics
    WHERE load_time_ms IS NOT NULL
");
if ($result && $row = $result->fetch_assoc()) {
    $maxLoadTime = $row['max_load'] ?? 0;
}

$result = $conn->query("
    SELECT page, ROUND(AVG(load_time_ms), 2) AS avg_page_load
    FROM analytics
    WHERE load_time_ms IS NOT NULL AND page IS NOT NULL AND page <> ''
    GROUP BY page
    ORDER BY avg_page_load DESC
    LIMIT 1
");
if ($result && $row = $result->fetch_assoc()) {
    $slowestPage = $row['page'] ?: 'N/A';
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM analytics
    WHERE load_time_ms IS NOT NULL
");
if ($result && $row = $result->fetch_assoc()) {
    $sampleCount = (int)$row['total'];
}

/*
|--------------------------------------------------------------------------
| Chart 1: average load time by page
|--------------------------------------------------------------------------
*/
$pageChartLabels = [];
$pageChartValues = [];

$stmt = $conn->prepare("
    SELECT page, ROUND(AVG(load_time_ms), 2) AS avg_load
    FROM analytics
    WHERE load_time_ms IS NOT NULL AND page IS NOT NULL AND page <> ''
    GROUP BY page
    ORDER BY avg_load DESC
    LIMIT 10
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pageChartLabels[] = $row['page'];
        $pageChartValues[] = (float)$row['avg_load'];
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Chart 2: average load time by day
|--------------------------------------------------------------------------
*/
$dayChartLabels = [];
$dayChartValues = [];

$stmt = $conn->prepare("
    SELECT DATE(created) AS day, ROUND(AVG(load_time_ms), 2) AS avg_load
    FROM analytics
    WHERE load_time_ms IS NOT NULL
    GROUP BY DATE(created)
    ORDER BY day ASC
    LIMIT 14
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $dayChartLabels[] = $row['day'];
        $dayChartValues[] = (float)$row['avg_load'];
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Performance table by page
|--------------------------------------------------------------------------
*/
$performanceRows = [];
$stmt = $conn->prepare("
    SELECT
        page,
        COUNT(*) AS total_samples,
        ROUND(AVG(load_time_ms), 2) AS avg_load_time,
        ROUND(MIN(load_time_ms), 2) AS min_load_time,
        ROUND(MAX(load_time_ms), 2) AS max_load_time
    FROM analytics
    WHERE load_time_ms IS NOT NULL AND page IS NOT NULL AND page <> ''
    GROUP BY page
    ORDER BY avg_load_time DESC
    LIMIT 50
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $performanceRows[] = $row;
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Recent performance samples
|--------------------------------------------------------------------------
*/
$recentSamples = [];
$stmt = $conn->prepare("
    SELECT page, event, load_time_ms, viewport_width, viewport_height, created
    FROM analytics
    WHERE load_time_ms IS NOT NULL
    ORDER BY created DESC
    LIMIT 20
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentSamples[] = $row;
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Comments
|--------------------------------------------------------------------------
*/
$comments = [];
$stmt = $conn->prepare("
    SELECT author, comment, created
    FROM report_comments
    WHERE report_type = ?
    ORDER BY created DESC
");
if ($stmt) {
    $stmt->bind_param("s", $report_type);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f7fb;
            color: #222;
        }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .summary-box {
            background: #fff4ea;
            border-left: 5px solid #d97706;
            border-radius: 8px;
            padding: 16px;
        }
        .summary-box h3 {
            margin: 0 0 8px 0;
            font-size: 1rem;
        }
        .summary-box p {
            margin: 0;
            font-size: 1.6rem;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f1f4f8;
        }
        textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            box-sizing: border-box;
            margin-top: 8px;
        }
        button {
            background: #d97706;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
        }
        .message.success {
            color: green;
            margin-bottom: 10px;
        }
        .message.error {
            color: red;
            margin-bottom: 10px;
        }
        .comment {
            background: #fafafa;
            border-left: 4px solid #d97706;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
        }
        .subtle {
            color: #666;
        }
        .chart-wrap {
            position: relative;
            width: 100%;
            height: 380px;
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/nav.php'; ?>

<div class="container">
    <div class="card">
        <h1>Performance Report</h1>
        <a href="/admin/exports/export_performance.php"><button>Export PDF</button></a>
	<p class="subtle">
            This report focuses on page responsiveness using recorded load-time analytics from the site.
        </p>

        <div class="summary-grid">
            <div class="summary-box">
                <h3>Average Load Time</h3>
                <p><?php echo h($avgLoadTime); ?> ms</p>
            </div>
            <div class="summary-box">
                <h3>Highest Load Time</h3>
                <p><?php echo h($maxLoadTime); ?> ms</p>
            </div>
            <div class="summary-box">
                <h3>Slowest Page</h3>
                <p><?php echo h($slowestPage); ?></p>
            </div>
            <div class="summary-box">
                <h3>Performance Samples</h3>
                <p><?php echo h($sampleCount); ?></p>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Average Load Time by Page</h2>
        <?php if (count($pageChartLabels) > 0): ?>
            <div class="chart-wrap">
                <canvas id="pagePerformanceChart"></canvas>
            </div>
        <?php else: ?>
            <p>No page load data available yet.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Average Load Time by Day</h2>
        <?php if (count($dayChartLabels) > 0): ?>
            <div class="chart-wrap">
                <canvas id="dailyPerformanceChart"></canvas>
            </div>
        <?php else: ?>
            <p>No daily performance trend data available yet.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Page Performance Table</h2>
        <table>
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Samples</th>
                    <th>Avg Load (ms)</th>
                    <th>Min Load (ms)</th>
                    <th>Max Load (ms)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($performanceRows) > 0): ?>
                    <?php foreach ($performanceRows as $row): ?>
                        <tr>
                            <td><?php echo h($row['page']); ?></td>
                            <td><?php echo h($row['total_samples']); ?></td>
                            <td><?php echo h($row['avg_load_time']); ?></td>
                            <td><?php echo h($row['min_load_time']); ?></td>
                            <td><?php echo h($row['max_load_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No performance data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Recent Performance Samples</h2>
        <table>
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Event</th>
                    <th>Load Time (ms)</th>
                    <th>Viewport</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentSamples) > 0): ?>
                    <?php foreach ($recentSamples as $row): ?>
                        <tr>
                            <td><?php echo h($row['page']); ?></td>
                            <td><?php echo h($row['event']); ?></td>
                            <td><?php echo h($row['load_time_ms']); ?></td>
                            <td><?php echo h($row['viewport_width']); ?> × <?php echo h($row['viewport_height']); ?></td>
                            <td><?php echo h($row['created']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No recent performance samples found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Analyst Comments</h2>

        <?php if ($success): ?>
            <div class="message success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['superadmin', 'analyst'])): ?>
            <form method="POST">
                <label for="comment">Add a comment for this performance report:</label>
                <textarea name="comment" id="comment" placeholder="Write your performance analysis here..."></textarea>
                <button type="submit">Save Comment</button>
            </form>
            <hr>
        <?php endif; ?>

        <?php if (count($comments) > 0): ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment">
                    <strong><?php echo h($comment['author']); ?></strong>
                    <em>(<?php echo h($comment['created']); ?>)</em>
                    <p><?php echo nl2br(h($comment['comment'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No comments yet for this report.</p>
        <?php endif; ?>
    </div>
</div>

<script>
const pageLabels = <?php echo json_encode($pageChartLabels); ?>;
const pageValues = <?php echo json_encode($pageChartValues); ?>;

if (document.getElementById('pagePerformanceChart')) {
    new Chart(document.getElementById('pagePerformanceChart'), {
        type: 'bar',
        data: {
            labels: pageLabels,
            datasets: [{
                label: 'Avg Load Time (ms)',
                data: pageValues
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

const dayLabels = <?php echo json_encode($dayChartLabels); ?>;
const dayValues = <?php echo json_encode($dayChartValues); ?>;

if (document.getElementById('dailyPerformanceChart')) {
    new Chart(document.getElementById('dailyPerformanceChart'), {
        type: 'line',
        data: {
            labels: dayLabels,
            datasets: [{
                label: 'Avg Load Time by Day (ms)',
                data: dayValues,
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}
</script>
</body>
</html>
