<?php
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$active = $active ?? '';
$pageTitle = $pageTitle ?? 'Admin Panel';
$flash = isset($_GET['msg']) ? htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | School Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { min-height: 100vh; background: var(--primary-blue); color: white; padding-top: 20px;}
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 20px; display: block; border-radius: 8px; margin: 5px 15px; font-weight: 500;}
        .sidebar a:hover, .sidebar a.active { background: var(--secondary-cyan); color: white; }
        .stat-card { border-left: 4px solid var(--secondary-cyan); }
        .table-custom thead th { background: var(--primary-blue); color: #fff; }
        .badge-pub { background: #198754; }
        .badge-unpub { background: #6c757d; }
        .badge-draft { background: #fd7e14; }
    </style>
</head>
<body class="bg-light">

<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar" style="width: 260px; flex-shrink: 0;">
        <h4 class="text-center fw-bold mb-4 px-3 text-white"><i class="fa-solid fa-school me-2"></i>SchoolAdmin</h4>
        <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie me-2"></i> Dashboard</a>
        <a href="students.php" class="<?= $active === 'students' ? 'active' : '' ?>"><i class="fa-solid fa-users me-2"></i> Students</a>
        <a href="subjects.php" class="<?= $active === 'subjects' ? 'active' : '' ?>"><i class="fa-solid fa-book me-2"></i> Subjects</a>
        <a href="examinations.php" class="<?= $active === 'examinations' ? 'active' : '' ?>"><i class="fa-solid fa-file-pen me-2"></i> Examinations</a>
        <a href="marks.php" class="<?= $active === 'marks' ? 'active' : '' ?>"><i class="fa-solid fa-square-poll-vertical me-2"></i> Manage Marks</a>
        <a href="settings.php" class="<?= $active === 'settings' ? 'active' : '' ?>"><i class="fa-solid fa-gear me-2"></i> Settings</a>
        <a href="logout.php" class="text-danger mt-5"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4 p-md-5" style="min-width: 0;">

<?php if ($flash): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $flash ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
<?php endif; ?>
