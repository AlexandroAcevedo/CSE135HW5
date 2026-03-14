<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

use Dompdf\Dompdf;

$report_type = "traffic";

$result = $conn->query("
SELECT page, COUNT(*) as visits
FROM analytics
GROUP BY page
ORDER BY visits DESC
LIMIT 20
");

$rows = $result->fetch_all(MYSQLI_ASSOC);

$comments = $conn->query("
SELECT author, comment, created
FROM report_comments
WHERE report_type='traffic'
ORDER BY created DESC
");

$commentRows = $comments->fetch_all(MYSQLI_ASSOC);

$html = "<h1>Traffic Report</h1>";
$html .= "<p>Generated on ".date("Y-m-d H:i:s")."</p>";

$html .= "<h2>Top Pages</h2>";
$html .= "<table border='1' cellpadding='6'>";
$html .= "<tr><th>Page</th><th>Visits</th></tr>";

foreach($rows as $row){
    $html .= "<tr>
        <td>{$row['page']}</td>
        <td>{$row['visits']}</td>
    </tr>";
}

$html .= "</table>";

$html .= "<h2>Analyst Comments</h2>";

foreach($commentRows as $c){
    $html .= "<p><strong>{$c['author']}</strong> ({$c['created']}):<br>{$c['comment']}</p>";
}

$dompdf = new Dompdf();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("traffic_report.pdf");
?>
