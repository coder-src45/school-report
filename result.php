<?php
// result.php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
$exam_id = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
$class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
$roll_number = filter_input(INPUT_POST, 'roll_number', FILTER_VALIDATE_INT);

if (!$session_id || !$exam_id || !$class_id || !$roll_number) {
    $_SESSION['error'] = "Invalid input data. Please try again.";
    header("Location: index.php");
    exit;
}

// 1. Verify Student Exists
$stmt = $pdo->prepare("SELECT s.*, c.class_name, sess.session_name 
                       FROM students s 
                       JOIN classes c ON s.class_id = c.id 
                       JOIN academic_sessions sess ON s.session_id = sess.id 
                       WHERE s.class_id = ? AND s.session_id = ? AND s.roll_number = ?");
$stmt->execute([$class_id, $session_id, $roll_number]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = "Result not found. Please check your information and try again.";
    header("Location: index.php");
    exit;
}

// 2. Fetch School Info & Exam Info
$school = $pdo->query("SELECT * FROM school_settings LIMIT 1")->fetch();
$exam = $pdo->prepare("SELECT exam_name, status, session_id FROM examinations WHERE id = ?");
$exam->execute([$exam_id]);
$examData = $exam->fetch();

if (!$examData || $examData['status'] !== 'published') {
    $_SESSION['error'] = "Result for this examination has not been published yet.";
    header("Location: index.php");
    exit;
}

if ((int)$examData['session_id'] !== (int)$student['session_id']) {
    $_SESSION['error'] = "The selected examination does not belong to the selected session.";
    header("Location: index.php");
    exit;
}

// 3. Fetch Marks
$stmt = $pdo->prepare("SELECT m.obtained_marks, sub.subject_name, sub.full_marks, sub.pass_marks 
                       FROM marks m 
                       JOIN subjects sub ON m.subject_id = sub.id 
                       WHERE m.student_id = ? AND m.exam_id = ?");
$stmt->execute([$student['id'], $exam_id]);
$marks = $stmt->fetchAll();

if (count($marks) === 0) {
    $_SESSION['error'] = "No marks found for this examination.";
    header("Location: index.php");
    exit;
}

// 4. Fetch Grading System for calculations
$grades = $pdo->query("SELECT * FROM grades ORDER BY min_marks DESC")->fetchAll();

function getGradeData($marks, $grades) {
    foreach ($grades as $g) {
        if ($marks >= $g['min_marks'] && $marks <= $g['max_marks']) {
            return $g;
        }
    }
    return ['grade_name' => 'F', 'gpa' => 0.00];
}

// Calculations
$total_full_marks = 0;
$total_obtained = 0;
$total_gpa = 0;
$subject_count = count($marks);
$has_failed = false;

foreach ($marks as &$mark) {
    $total_full_marks += $mark['full_marks'];
    $total_obtained += $mark['obtained_marks'];
    
    // Check if failed a specific subject
    if ($mark['obtained_marks'] < $mark['pass_marks']) {
        $has_failed = true;
    }
    
    $gradeInfo = getGradeData($mark['obtained_marks'], $grades);
    $mark['grade'] = $gradeInfo['grade_name'];
    $mark['gpa'] = $gradeInfo['gpa'];
    $total_gpa += $gradeInfo['gpa'];
}

$percentage = $total_full_marks > 0 ? ($total_obtained / $total_full_marks) * 100 : 0;
$final_gpa = $has_failed ? 0.00 : number_format($total_gpa / $subject_count, 2);
$final_grade = $has_failed ? 'F' : getGradeData($percentage, $grades)['grade_name'];
$result_status = $has_failed ? 'FAILED' : 'PASSED';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result | <?= escape($student['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<audio id="result-sound" src="assets/<?= $result_status === 'PASSED' ? 'Pass' : 'Fail' ?>/<?= rawurlencode($result_status === 'PASSED' ? 'shabas beta' : 'harami') ?>.m4a" autoplay class="no-print"></audio>

<div class="container py-5">
    <!-- Controls (Hidden on print) -->
    <div class="d-flex justify-content-between mb-4 no-print">
        <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Search</a>
        <div>
            <button onclick="window.print()" class="btn btn-primary-custom me-2"><i class="fa-solid fa-print"></i> Print Result</button>
        </div>
    </div>

    <!-- Marksheet -->
    <div class="marksheet-wrapper bg-white">
        <!-- Header -->
        <div class="text-center border-bottom pb-4 mb-4">
            <h2 class="fw-bold text-uppercase mb-1" style="color: var(--primary-blue);"><?= escape($school['school_name']) ?></h2>
            <p class="mb-1 text-muted"><?= escape($school['address']) ?> | Phone: <?= escape($school['phone']) ?></p>
            <h4 class="mt-3 text-dark fw-bold bg-light d-inline-block px-4 py-2 rounded">STATEMENT OF ACADEMIC RESULT</h4>
        </div>

        <!-- Student Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><th width="150" class="text-muted">Student Name:</th><td class="fw-bold fs-5"><?= escape($student['name']) ?></td></tr>
                    <tr><th class="text-muted">Student ID:</th><td><?= escape($student['student_id']) ?></td></tr>
                    <tr><th class="text-muted">Roll Number:</th><td><?= escape($student['roll_number']) ?></td></tr>
                </table>
            </div>
            <div class="col-md-6 text-md-end">
                <table class="table table-borderless table-sm mb-0">
                    <tr><th width="150" class="text-muted">Class:</th><td class="fw-bold"><?= escape($student['class_name']) ?></td></tr>
                    <tr><th class="text-muted">Session:</th><td><?= escape($student['session_name']) ?></td></tr>
                    <tr><th class="text-muted">Examination:</th><td><?= escape($examData['exam_name']) ?></td></tr>
                    <tr><th class="text-muted">Final Grade:</th><td class="fw-bold" style="color: var(--primary-blue); font-size: 3rem; line-height: 1;"><span id="final-grade" class="d-inline-block" style="cursor: pointer;" title="Click to play sound"><?= $final_grade ?></span></td></tr>
                </table>
            </div>
        </div>

        <!-- Marks Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-custom text-center">
                <thead>
                    <tr>
                        <th class="text-start">Subject Name</th>
                        <th>Full Marks</th>
                        <th>Pass Marks</th>
                        <th>Obtained Marks</th>
                        <th>Grade</th>
                        <th>GPA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marks as $m): ?>
                    <tr>
                        <td class="text-start fw-bold"><?= escape($m['subject_name']) ?></td>
                        <td><?= $m['full_marks'] ?></td>
                        <td><?= $m['pass_marks'] ?></td>
                        <td class="fw-bold <?= $m['obtained_marks'] < $m['pass_marks'] ? 'text-danger' : '' ?>"><?= $m['obtained_marks'] ?></td>
                        <td class="fw-bold"><?= $m['grade'] ?></td>
                        <td><?= $m['gpa'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary & Status -->
        <div class="row align-items-center bg-light rounded p-4 border border-info mb-5">
            <div class="col-md-8">
                <div class="row text-center text-md-start">
                    <div class="col-6 col-md-3 mb-3 mb-md-0">
                        <span class="d-block text-muted small fw-bold">TOTAL MARKS</span>
                        <span class="fs-4 fw-bold"><?= $total_obtained ?> / <?= $total_full_marks ?></span>
                    </div>
                    <div class="col-6 col-md-3 mb-3 mb-md-0">
                        <span class="d-block text-muted small fw-bold">PERCENTAGE</span>
                        <span class="fs-4 fw-bold"><?= number_format($percentage, 2) ?>%</span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="d-block text-muted small fw-bold">FINAL GPA</span>
                        <span class="fs-4 fw-bold text-gradient"><?= $final_gpa ?></span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="d-block text-muted small fw-bold">GRADE</span>
                        <span class="fs-4 fw-bold" style="color: var(--primary-blue);"><?= $final_grade ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0 border-start">
                <span class="d-block text-muted small fw-bold mb-2">RESULT STATUS</span>
                <span class="status-badge <?= $result_status === 'PASSED' ? 'status-passed' : 'status-failed' ?>">
                    <?= $result_status ?>
                </span>
            </div>
        </div>

        <!-- Signatures -->
        <div class="row mt-5 pt-5 text-center">
            <div class="col-4">
                <hr class="w-75 mx-auto border-dark">
                <p class="fw-bold mt-2">Class Teacher</p>
            </div>
            <div class="col-4">
                <!-- Seal Area -->
                <div class="rounded-circle border border-2 border-dark d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; opacity: 0.1;">
                    <small>Official<br>Seal</small>
                </div>
            </div>
            <div class="col-4">
                <hr class="w-75 mx-auto border-dark">
                <p class="fw-bold mt-2">Principal</p>
            </div>
        </div>
        <div class="text-center mt-4 pt-3 border-top text-muted small">
            Date of Publication: <?= date('F d, Y') ?> | Generated electronically from School Result Portal
        </div>
    </div>
</div>

<script>
document.getElementById('final-grade').addEventListener('click', function() {
    var sound = document.getElementById('result-sound');
    sound.currentTime = 0;
    sound.play();
});
</script>

</body>
</html>