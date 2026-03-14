<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
require_role(['superadmin', 'analyst', 'viewer']);

$report_type = 'behavior';
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
$totalClicks = 0;
$totalDistinctEvents = 0;
$mostCommonEvent = 'N/A';
$mostActivePage = 'N/A';

$result = $conn->query("SELECT COUNT(*) AS total FROM analytics WHERE event = 'click'");
if ($result && $row = $result->fetch_assoc()) {
    $totalClicks = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(DISTINCT event) AS total FROM analytics");
if ($result && $row = $result->fetch_assoc()) {
    $totalDistinctEvents = (int)$row['total'];
}

$result = $conn->query("
    SELECT event, COUNT(*) AS total
    FROM analytics
    WHERE event IS NOT NULL AND event <> ''
    GROUP BY event
    ORDER BY total DESC
    LIMIT 1
");
if ($result && $row = $result->fetch_assoc()) {
    $mostCommonEvent = $row['event'] ?: 'N/A';
}

$result = $conn->query("
    SELECT page, COUNT(*) AS total
    FROM analytics
    WHERE page IS NOT NULL AND page <> ''
    GROUP BY page
    ORDER BY total DESC
    LIMIT 1
");
if ($result && $row = $result->fetch_assoc()) {
    $mostActivePage = $row['page'] ?: 'N/A';
}

/*
|--------------------------------------------------------------------------
| Chart 1: event distribution
|--------------------------------------------------------------------------
*/
$eventChartLabels = [];
$eventChartValues = [];

$stmt = $conn->prepare("
    SELECT event, COUNT(*) AS total
    FROM analytics
    WHERE event IS NOT NULL AND event <> ''
    GROUP BY event
    ORDER BY total DESC
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $eventChartLabels[] = $row['event'];
        $eventChartValues[] = (int)$row['total'];
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Chart 2: most active pages by events
|--------------------------------------------------------------------------
*/
$pageChartLabels = [];
$pageChartValues = [];

$stmt = $conn->prepare("
    SELECT page, COUNT(*) AS total
    FROM analytics
    WHERE page IS NOT NULL AND page <> ''
    GROUP BY page
    ORDER BY total DESC
    LIMIT 10
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pageChartLabels[] = $row['page'];
        $pageChartValues[] = (int)$row['total'];
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Table: page/event breakdown
|--------------------------------------------------------------------------
*/
$behaviorRows = [];
$stmt = $conn->prepare("
    SELECT page, event, COUNT(*) AS total_events, COUNT(DISTINCT cid) AS unique_visitors
    FROM analytics
    WHERE page IS NOT NULL AND page <> '' AND event IS NOT NULL AND event <> ''
    GROUP BY page, event
    ORDER BY total_events DESC
    LIMIT 50
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $behaviorRows[] = $row;
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Recent interaction samples
|--------------------------------------------------------------------------
*/
$recentBehaviorRows = [];
$stmt = $conn->prepare("
    SELECT cid, page, event, user_agent, created
    FROM analytics
    ORDER BY created DESC
    LIMIT 20
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentBehaviorRows[] = $row;
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
    <title>Behavior Report</title>
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
            background: #eefaf1;
            border-left: 5px solid #2e8b57;
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
            background: #2e8b57;
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
            border-left: 4px solid #2e8b57;
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
        <h1>Behavior Report</h1>
        <a href="/admin/exports/export_behavior.php"><button>Export PDF</button></a>
	<p class="subtle">
            This report focuses on user interaction patterns by comparing event types, showing which behaviors
            are most common, and identifying which page/event combinations are most frequent.
        </p>

        <div class="summary-grid">
            <div class="summary-box">
                <h3>Total Click Events</h3>
                <p><?php echo h($totalClicks); ?></p>
            </div>
            <div class="summary-box">
                <h3>Distinct Event Types</h3>
                <p><?php echo h($totalDistinctEvents); ?></p>
            </div>
            <div class="summary-box">
                <h3>Most Common Event</h3>
                <p><?php echo h($mostCommonEvent); ?></p>
            </div>
            <div class="summary-box">
                <h3>Most Active Page</h3>
                <p><?php echo h($mostActivePage); ?></p>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Event Distribution</h2>
        <p class="subtle">
            This chart compares event types to show which user actions are most common across the site.
        </p>
        <?php if (count($eventChartLabels) > 0): ?>
            <div class="chart-wrap">
                <canvas id="behaviorEventChart"></canvas>
            </div>
        <?php else: ?>
            <p>No event distribution data available yet.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Most Active Pages by Event Volume</h2>
        <p class="subtle">
            This chart shows which pages generate the highest amount of total interaction activity.
        </p>
        <?php if (count($pageChartLabels) > 0): ?>
            <div class="chart-wrap">
                <canvas id="behaviorPageChart"></canvas>
            </div>
        <?php else: ?>
            <p>No page activity data available yet.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Page and Event Breakdown</h2>
        <table>
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Event</th>
                    <th>Total Events</th>
                    <th>Unique Visitors</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($behaviorRows) > 0): ?>
                    <?php foreach ($behaviorRows as $row): ?>
                        <tr>
                            <td><?php echo h($row['page']); ?></td>
                            <td><?php echo h($row['event']); ?></td>
                            <td><?php echo h($row['total_events']); ?></td>
                            <td><?php echo h($row['unique_visitors']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No behavior analytics data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Recent Interaction Samples</h2>
        <table>
            <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Page</th>
                    <th>Event</th>
                    <th>User Agent</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentBehaviorRows) > 0): ?>
                    <?php foreach ($recentBehaviorRows as $row): ?>
                        <tr>
                            <td><?php echo h($row['cid']); ?></td>
                            <td><?php echo h($row['page']); ?></td>
                            <td><?php echo h($row['event']); ?></td>
                            <td><?php echo h($row['user_agent'] ?: '(unknown)'); ?></td>
                            <td><?php echo h($row['created']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No recent behavior samples found.</td></tr>
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
                <label for="comment">Add a comment for this behavior report:</label>
                <textarea name="comment" id="comment" placeholder="Write your behavioral analysis here..."></textarea>
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
const behaviorEventLabels = <?php echo json_encode($eventChartLabels); ?>;
const behaviorEventValues = <?php echo json_encode($eventChartValues); ?>;

if (document.getElementById('behaviorEventChart')) {
    new Chart(document.getElementById('behaviorEventChart'), {
        type: 'pie',
        data: {
            labels: behaviorEventLabels,
            datasets: [{
                label: 'Event Count',
                data: behaviorEventValues
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

const behaviorPageLabels = <?php echo json_encode($pageChartLabels); ?>;
const behaviorPageValues = <?php echo json_encode($pageChartValues); ?>;

if (document.getElementById('behaviorPageChart')) {
    new Chart(document.getElementById('behaviorPageChart'), {
        type: 'bar',
        data: {
            labels: behaviorPageLabels,
            datasets: [{
                label: 'Total Events',
                data: behaviorPageValues
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
