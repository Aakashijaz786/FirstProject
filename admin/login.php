<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}
require_once '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $conn->prepare('SELECT id, password FROM admin_users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $hashed_password);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $id;
                $_SESSION['admin_username'] = $username;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #fff;
            padding: 2.5rem 2rem 2rem 2rem;
            border-radius: 1.25rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            max-width: 400px;
            width: 100%;
        }
        .login-title {
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
        }
        .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42, 82, 152, 0.15);
        }
        .btn-primary {
            background: linear-gradient(90deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #2a5298 0%, #1e3c72 100%);
        }
        .brand {
            font-size: 2rem;
            font-weight: 800;
            color: #2a5298;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }
        .login-footer {
            margin-top: 1.5rem;
            font-size: 0.95rem;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <div class="brand">Admin</div>
            <div class="login-title">Sign in to your account</div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?>All rights reserved.
        </div>
    </div>
</body>
</html> 