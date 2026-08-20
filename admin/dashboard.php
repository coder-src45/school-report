<?php
// admin/dashboard.php
$active = 'dashboard';
$pageTitle = 'Dashboard | Admin Panel';
require_once 'includes/header.php';

// Fetch basic stats
$students_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$classes_count = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$exams_count = $pdo->query("SELECT COUNT(*) FROM examinations")->fetchColumn();
$subjects_count = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
?>

<h2 class="fw-bold mb-0">Overview</h2>
<p class="text-muted">Welcome back, <?= escape($_SESSION['admin_name']) ?></p>

<div class="d-flex justify-content-end mb-4">
    <a href="../index.php" target="_blank" class="btn btn-outline-primary"><i class="fa-solid fa-globe me-2"></i>View Website</a>
</div>

<div class="row g-4">
    <!-- Stat Cards -->
    <div class="col-md-3">
        <div class="card card-custom stat-card p-4">
            <h6 class="text-muted fw-bold">TOTAL STUDENTS</h6>
            <h2 class="fw-bold mb-0" style="color: var(--primary-blue);"><?= $students_count ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom stat-card p-4">
            <h6 class="text-muted fw-bold">CLASSES</h6>
            <h2 class="fw-bold mb-0" style="color: var(--primary-blue);"><?= $classes_count ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom stat-card p-4">
            <h6 class="text-muted fw-bold">SUBJECTS</h6>
            <h2 class="fw-bold mb-0" style="color: var(--primary-blue);"><?= $subjects_count ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-custom stat-card p-4">
            <h6 class="text-muted fw-bold">EXAMINATIONS</h6>
            <h2 class="fw-bold mb-0" style="color: var(--primary-blue);"><?= $exams_count ?></h2>
        </div>
    </div>
</div>

<div class="card card-custom p-4 mt-5">
    <h5 class="fw-bold mb-4">Quick Setup Guide</h5>
    <ol class="text-muted mb-0 lh-lg">
        <li>Create <strong>Academic Sessions</strong> and <strong>Classes</strong>.</li>
        <li>Add <strong>Subjects</strong> and assign them to respective classes.</li>
        <li>Register <strong>Students</strong> into their respective sessions and classes.</li>
        <li>Create an <strong>Examination</strong> (e.g., Final Exam 2024).</li>
        <li>Go to <strong>Manage Marks</strong> to input marks for students.</li>
        <li>Students can now search their roll number on the public portal to view and print their beautiful marksheet.</li>
    </ol>
</div>

<?php require_once 'includes/footer.php'; ?>