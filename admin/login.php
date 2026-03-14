<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s",$username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $password === $user["password"]) {

        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];

        header("Location: /admin/index.php");
        exit();

    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HW4 Admin Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
            width: 320px;
        }
        h1 {
            margin-top: 0;
            font-size: 1.5rem;
            text-align: center;
        }
        label {
            display: block;
            margin-top: 1rem;
            margin-bottom: 0.3rem;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 0.7rem;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            margin-top: 1.2rem;
            padding: 0.8rem;
            background: #1e66f5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background: #174fc1;
        }
        .error {
            color: #c62828;
            margin-top: 1rem;
            text-align: center;
        }
        .note {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #555;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Admin Login</h1>

        <form method="POST" action="/admin/login.php">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>

            <button type="submit">Login</button>
        </form>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="note">
            Protected backend for HW4 checkpoint.
        </div>
    </div>
</body>
</html>
