<?php
session_start();

function require_login() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: /admin/login.php");
        exit();
    }
}

function require_role(array $allowed_roles) {
    require_login();

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1>";
        echo "<p>You do not have permission to access this page.</p>";
        exit();
    }
}
?>
