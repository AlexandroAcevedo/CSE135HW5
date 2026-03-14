<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
require_role(['superadmin', 'analyst', 'viewer']);

$report_type = 'traffic';
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
$totalPageViews = 0;
$totalUniqueVisitors = 0;
$totalTrafficEvents = 0;
$topTrafficPage = 'N/A';

$result = $conn->query("SELECT COUNT(*) AS total FROM analytics WHERE event = 'pageview'");
if ($result && $row = $result->fetch_assoc()) {
    $totalPageViews = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(DISTINCT cid) AS total FROM analytics");
if ($result && $row = $result->fetch_assoc()) {
    $totalUniqueVisitors = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM analytics");
if ($result && $row = $result->fetch_assoc()) {
    $totalTrafficEvents = (int)$row['total'];
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
    $topTrafficPage = $row['page'] ?: 'N/A';
}

/*
|--------------------------------------------------------------------------
| Chart 1: top pages by pageviews
|--------------------------------------------------------------------------
*/
$pageChartLabels = [];
$pageChartValues = [];

$stmt = $conn->prepare("
    SELECT page, COUNT(*) AS views
    FROM analytics
    WHERE event = 'pageview' AND page IS NOT NULL AND page <> ''
    GROUP BY page
    ORDER BY views DESC
    LIMIT 10
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pageChartLabels[] = $row['page'];
        $pageChartValues[] = (int)$row['views'];
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Chart 2: traffic by day
|--------------------------------------------------------------------------
*/
$dayChartLabels = [];
$dayChartValues = [];

$stmt = $conn->prepare("
    SELECT DATE(created) AS day, COUNT(*) AS total
    FROM analytics
    WHERE event = 'pageview'
    GROUP BY DATE(created)
    ORDER BY day ASC
    LIMIT 14
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $dayChartLabels[] = $row['day'];
        $dayChartValues[] = (int)$row['total'];
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Table: page traffic summary
|--------------------------------------------------------------------------
*/
$trafficSummaryRows = [];
$stmt = $conn->prepare("
    SELECT
        page,
        COUNT(*) AS total_events,
        COUNT(DISTINCT cid) AS unique_visitors,
        SUM(CASE WHEN event = 'pageview' THEN 1 ELSE 0 END) AS pageviews
    FROM analytics
    WHERE page IS NOT NULL AND page <> ''
    GROUP BY page
    ORDER BY pageviews DESC, total_events DESC
    LIMIT 50
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $trafficSummaryRows[] = $row;
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Recent traffic records
|--------------------------------------------------------------------------
*/
$recentTrafficRows = [];
$stmt = $conn->prepare("
    SELECT cid, page, event, referrer, created
    FROM analytics
    ORDER BY created DESC
    LIMIT 20
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentTrafficRows[] = $row;
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
    <title>Traffic Report</title>
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
            background: #eef4ff;
            border-left: 5px solid #0077cc;
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
            background: #0077cc;
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
            border-left: 4px solid #0077cc;
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
        <h1>Traffic Report</h1>
        <a href="/admin/exports/export_traffic.php">
	<button>Export PDF</button>
	</a> 
        <p class="subtle">
            This report summarizes where site traffic is concentrated, which pages receive the most pageviews,
            and how recent visitor activity is distributed across the site.
        </p>

        <div class="summary-grid">
            <div class="summary-box">
                <h3>Total Pageviews</h3>
                <p><?php echo h($totalPageViews); ?></p>
            </div>
            <div class="summary-box">
                <h3>Unique Visitors</h3>
                <p><?php echo h($totalUniqueVisitors); ?></p>
            </div>
            <div class="summary-box">
                <h3>Total Logged Events</h3>
                <p><?php echo h($totalTrafficEvents); ?></p>
            </div>
            <div class="summary-box">
                <h3>Top Traffic Page</h3>
                <p><?php echo h($topTrafficPage); ?></p>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Top Pages by Pageviews</h2>
        <p class="subtle">
            This chart highlights which pages account for the largest share of recorded pageview traffic.
        </p>
        <?php if (count($pageChartLabels) > 0): ?>
            <div class="chart-wrap">
                <canvas id="trafficPageChart"></canvas>
            </div>
        <?php else: ?>
            <p>No pageview data available yet.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Traffic Trend by Day</h2>
        <p class="subtle">
            This chart shows how pageview volume changes over time, helping identify traffic peaks or drops.
        </p>
        <?php if (count($dayChartLabels) > 0): ?>
            <div class="chart-wrap">
                <canvas id="trafficDayChart"></canvas>
            </div>
        <?php else: ?>
            <p>No daily traffic trend data available yet.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Page Traffic Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Total Events</th>
                    <th>Unique Visitors</th>
                    <th>Pageviews</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($trafficSummaryRows) > 0): ?>
                    <?php foreach ($trafficSummaryRows as $row): ?>
                        <tr>
                            <td><?php echo h($row['page']); ?></td>
                            <td><?php echo h($row['total_events']); ?></td>
                            <td><?php echo h($row['unique_visitors']); ?></td>
                            <td><?php echo h($row['pageviews']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No traffic summary data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Recent Traffic Activity</h2>
        <table>
            <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Page</th>
                    <th>Event</th>
                    <th>Referrer</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentTrafficRows) > 0): ?>
                    <?php foreach ($recentTrafficRows as $row): ?>
                        <tr>
                            <td><?php echo h($row['cid']); ?></td>
                            <td><?php echo h($row['page']); ?></td>
                            <td><?php echo h($row['event']); ?></td>
                            <td><?php echo h($row['referrer'] ?: '(direct/none)'); ?></td>
                            <td><?php echo h($row['created']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No recent traffic records found.</td></tr>
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
                <label for="comment">Add a comment for this traffic report:</label>
                <textarea name="comment" id="comment" placeholder="Write your traffic analysis here..."></textarea>
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
const trafficPageLabels = <?php echo json_encode($pageChartLabels); ?>;
const trafficPageValues = <?php echo json_encode($pageChartValues); ?>;

if (document.getElementById('trafficPageChart')) {
    new Chart(document.getElementById('trafficPageChart'), {
        type: 'bar',
        data: {
            labels: trafficPageLabels,
            datasets: [{
                label: 'Pageviews',
                data: trafficPageValues
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

const trafficDayLabels = <?php echo json_encode($dayChartLabels); ?>;
const trafficDayValues = <?php echo json_encode($dayChartValues); ?>;

if (document.getElementById('trafficDayChart')) {
    new Chart(document.getElementById('trafficDayChart'), {
        type: 'line',
        data: {
            labels: trafficDayLabels,
            datasets: [{
                label: 'Pageviews Per Day',
                data: trafficDayValues,
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
