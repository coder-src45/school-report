<?php
$active = 'settings';
$pageTitle = 'Settings | Admin Panel';
require_once 'includes/header.php';

$settings = $pdo->query("SELECT * FROM school_settings ORDER BY id LIMIT 1")->fetch();
if (!$settings) {
    $pdo->query("INSERT INTO school_settings (school_name) VALUES ('My School')");
    $settings = $pdo->query("SELECT * FROM school_settings ORDER BY id LIMIT 1")->fetch();
}
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_settings') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh the page and try again.";
    } else {
        $stmt = $pdo->prepare("UPDATE school_settings SET school_name = ?, address = ?, phone = ?, email = ?, website = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['school_name']),
            trim($_POST['address']),
            trim($_POST['phone']),
            trim($_POST['email']),
            trim($_POST['website']),
            (int)$settings['id']
        ]);
        $settings = $pdo->query("SELECT * FROM school_settings ORDER BY id LIMIT 1")->fetch();
        $saved = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh the page and try again.";
    } else {
    $current = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
    $current->execute([(int)$_SESSION['admin_id']]);
    $admin = $current->fetch();

    if (!$admin) {
        $error = "Admin account not found.";
    } elseif (password_verify($_POST['current_password'], $admin['password'])) {
        if (strlen($_POST['new_password']) >= 6 && $_POST['new_password'] === $_POST['confirm_password']) {
            $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([$hash, (int)$_SESSION['admin_id']]);
            header("Location: settings.php?msg=Password changed successfully");
            exit;
        } else {
            $error = "New password must be at least 6 characters and match the confirmation.";
        }
    } else {
        $error = "Current password is incorrect.";
    }
    }
}
?>
<h2 class="fw-bold mb-0">Settings</h2>
<p class="text-muted mb-4">School information shown on result sheets.</p>

<?php if ($saved): ?>
    <div class="alert alert-success">School settings updated successfully.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<div class="card card-custom p-4 mb-4">
    <h5 class="fw-bold mb-3">School Information</h5>
    <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">School Name</label>
                <input type="text" name="school_name" class="form-control" value="<?= escape($settings['school_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Website</label>
                <input type="text" name="website" class="form-control" value="<?= escape($settings['website']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= escape($settings['address']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= escape($settings['phone']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= escape($settings['email']) ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary-custom"><i class="fa-solid fa-floppy-disk me-2"></i>Save Settings</button>
            </div>
        </div>
    </form>
</div>

<div class="card card-custom p-4">
    <h5 class="fw-bold mb-3">Change Password</h5>
    <form method="POST" class="row g-3">
        <input type="hidden" name="action" value="change_password">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary-custom"><i class="fa-solid fa-key me-2"></i>Change Password</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
