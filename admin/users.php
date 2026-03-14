<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_role(['superadmin']);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

$currentUsername = $_SESSION['username'] ?? 'unknown';

/*
|--------------------------------------------------------------------------
| Create user
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    $allowedRoles = ['superadmin', 'analyst', 'viewer'];

    if ($username === '' || $password === '' || $role === '') {
        $error = 'All fields are required.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = 'Invalid role selected.';
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $error = 'That username already exists.';
        } else {
            $insertStmt = $conn->prepare("
                INSERT INTO users (username, password, role)
                VALUES (?, ?, ?)
            ");
            $insertStmt->bind_param("sss", $username, $password, $role);

            if ($insertStmt->execute()) {
                $message = 'User created successfully.';
            } else {
                $error = 'Failed to create user.';
            }

            $insertStmt->close();
        }

        $checkStmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Delete user
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = (int)($_POST['user_id'] ?? 0);
    $deleteUsername = trim($_POST['delete_username'] ?? '');

    if ($deleteId <= 0) {
        $error = 'Invalid user selected for deletion.';
    } elseif ($deleteUsername === $currentUsername) {
        $error = 'You cannot delete your own currently logged-in account.';
    } else {
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
        $deleteStmt->bind_param("i", $deleteId);

        if ($deleteStmt->execute()) {
            $message = 'User deleted successfully.';
        } else {
            $error = 'Failed to delete user.';
        }

        $deleteStmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Fetch all users
|--------------------------------------------------------------------------
*/
$users = [];
$result = $conn->query("
    SELECT id, username, role
    FROM users
    ORDER BY id ASC
");

if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f6fa;
            color: #222;
        }

        main {
            max-width: 1100px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        h1, h2 {
            margin-top: 0;
        }

        .card {
            margin-top: 1.5rem;
            padding: 1.25rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
        }

        .message {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .message.success {
            background: #e8f7e8;
            border: 1px solid #b7e0b7;
            color: #1f6b1f;
        }

        .message.error {
            background: #fdecec;
            border: 1px solid #efb3b3;
            color: #a12626;
        }

        form {
            margin-top: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.4rem;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button {
            background: #1e66f5;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background: #1553cc;
        }

        .delete-btn {
            background: #c62828;
        }

        .delete-btn:hover {
            background: #9f1f1f;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 0.85rem;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #1e66f5;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8f9fc;
        }

        .inline-form {
            display: inline;
        }

        .muted {
            color: #666;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/nav.php'; ?>

    <main>
        <h1>Manage Users</h1>
        <p class="muted">Superadmin-only page for managing user accounts and roles.</p>

        <?php if ($message !== ''): ?>
            <div class="message success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="message error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Create New User</h2>

            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <div>
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div>
                        <label for="password">Password</label>
                        <input type="text" id="password" name="password" required>
                    </div>

                    <div>
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="superadmin">superadmin</option>
                            <option value="analyst">analyst</option>
                            <option value="viewer">viewer</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <button type="submit">Create User</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Existing Users</h2>

            <?php if (count($users) === 0): ?>
                <p>No users found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= h($user['id']) ?></td>
                                <td>
                                    <?= h($user['username']) ?>
                                    <?php if ($user['username'] === $currentUsername): ?>
                                        <span class="muted">(you)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($user['role']) ?></td>
                                <td>
                                    <?php if ($user['username'] === $currentUsername): ?>
                                        <span class="muted">Cannot delete current account</span>
                                    <?php else: ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= h($user['id']) ?>">
                                            <input type="hidden" name="delete_username" value="<?= h($user['username']) ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
