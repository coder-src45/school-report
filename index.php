<?php
// index.php
require_once 'config/database.php';

// Fetch dynamic dropdown data
$sessions = $pdo->query("SELECT * FROM academic_sessions WHERE status = 'active'")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes")->fetchAll();
$exams = $pdo->query("SELECT * FROM examinations WHERE status = 'published'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Result Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="fa-solid fa-graduation-cap fs-3 me-2"></i> Excellence Academy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Notices</a></li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-primary-custom" href="admin/login.php" target="_blank" rel="noopener">Admin Login</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center justify-content-center text-center mb-5">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold text-dark mb-3">Check Your <span class="text-gradient">Academic Results</span></h1>
                <p class="lead text-muted mb-4">Access your examination results quickly, securely, and conveniently through our modern digital portal.</p>
            </div>
        </div>

        <!-- Search Card -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card card-custom p-4 p-md-5">
                    <h3 class="fw-bold mb-4 text-center">Search Result</h3>
                    
                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger rounded-3"><i class="fa-solid fa-circle-exclamation me-2"></i><?= escape($_SESSION['error']) ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form action="result.php" method="POST" target="_blank">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Academic Session</label>
                                <select name="session_id" id="sessionSelect" class="form-select" required>
                                    <option value="">Select Session</option>
                                    <?php foreach($sessions as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= escape($s['session_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Examination</label>
                                <select name="exam_id" id="examSelect" class="form-select" required>
                                    <option value="">Select Examination</option>
                                    <?php foreach($exams as $e): ?>
                                        <option value="<?= $e['id'] ?>" data-session="<?= $e['session_id'] ?>"><?= escape($e['exam_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Class</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php foreach($classes as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= escape($c['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Student Roll Number</label>
                                <input type="number" name="roll_number" class="form-control" placeholder="Enter Roll Number" required>
                            </div>
                        </div>
                        <div class="text-center mt-5">
                            <button type="submit" class="btn btn-primary-custom px-5 py-3 fs-5 w-100">
                                <i class="fa-solid fa-magnifying-glass me-2"></i> VIEW RESULT
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer text-center mt-5">
    <div class="container">
        <h5 class="fw-bold mb-3">Excellence International Academy</h5>
        <p class="mb-0">© <?= date('Y') ?> All Rights Reserved. Built with modern web technologies.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sessionSelect = document.getElementById('sessionSelect');
    const examSelect = document.getElementById('examSelect');
    const examOptions = Array.from(examSelect.options).filter(o => o.value !== '');

    sessionSelect.addEventListener('change', function() {
        const sid = this.value;
        examOptions.forEach(o => {
            o.hidden = (o.getAttribute('data-session') !== sid);
        });
        const selected = examSelect.selectedOptions[0];
        if (!selected || selected.hidden) {
            examSelect.value = '';
        }
    });
});
</script>
</body>
</html>