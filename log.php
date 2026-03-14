<?php

$host = "localhost";
$user = "collector";
$password = "collectorpass";
$dbname = "cse135";

/* show errors (IMPORTANT for debugging) */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function get_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }

    return null;
}

function clean_string($value, $maxLen = 255) {
    if ($value === null) {
        return null;
    }

    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    return substr($value, 0, $maxLen);
}

try {
    $conn = new mysqli($host, $user, $password, $dbname);
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB CONNECTION FAILED: " . $e->getMessage();
    exit();
}

/* required GET parameters */
$cid = clean_string($_GET['cid'] ?? null, 255);
$page = clean_string($_GET['page'] ?? null, 255);
$event = clean_string($_GET['event'] ?? null, 100);

/* optional extra analytics fields */
$referrer = clean_string($_GET['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? null), 1000);
$userAgent = clean_string($_GET['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null), 2000);

$loadTimeMs = (isset($_GET['load_time_ms']) && $_GET['load_time_ms'] !== '')
    ? (float)$_GET['load_time_ms']
    : null;

$viewportWidth = (isset($_GET['viewport_width']) && $_GET['viewport_width'] !== '')
    ? (int)$_GET['viewport_width']
    : null;

$viewportHeight = (isset($_GET['viewport_height']) && $_GET['viewport_height'] !== '')
    ? (int)$_GET['viewport_height']
    : null;

$ipAddress = clean_string(get_client_ip(), 45);

if (!$cid || !$page || !$event) {
    http_response_code(400);
    echo "Missing parameters";
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO analytics
    (cid, page, event, user_agent, referrer, load_time_ms, viewport_width, viewport_height, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssssdiis",
    $cid,
    $page,
    $event,
    $userAgent,
    $referrer,
    $loadTimeMs,
    $viewportWidth,
    $viewportHeight,
    $ipAddress
);

$stmt->execute();

echo "logged";

$stmt->close();
$conn->close();
?>
