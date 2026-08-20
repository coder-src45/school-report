<?php
// admin/login.php
require_once '../config/database.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh the page and try again.";
    } elseif ($email && $password) {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $error = "Invalid email address.";
        } else {
            $stmt = $pdo->prepare("SELECT id, name, password FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | School Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: var(--primary-blue); height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 450px; width: 100%; margin: auto; padding: 40px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); background: #fff; }
    </style>
</head>
<body>

<div class="login-card text-center">
    <h2 class="fw-bold mb-1" style="color: var(--primary-blue);">Admin Access</h2>
    <p class="text-muted mb-4">Sign in to manage portal</p>
    
    <?php if($error): ?>
        <div class="alert alert-danger py-2"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="mb-3 text-start">
            <label class="form-label text-muted small fw-bold">Email Address</label>
            <input type="email" name="email" class="form-control form-control-lg" required>
        </div>
        <div class="mb-4 text-start">
            <label class="form-label text-muted small fw-bold">Password</label>
            <input type="password" name="password" class="form-control form-control-lg" required>
        </div>
        <button type="submit" class="btn btn-primary-custom w-100 btn-lg">Secure Login</button>
    </form>
    <a href="../index.php" class="d-block mt-4 text-muted small text-decoration-none">← Back to Public Website</a>
</div>

</body>
</html>