<?php
$host = "localhost";
$user = "collector";
$pass = "collectorpass";
$db = "cse135";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

?>
