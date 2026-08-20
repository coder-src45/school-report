<?php
$active = 'marks';
$pageTitle = 'Manage Marks | Admin Panel';
require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_marks') {
    $student_id = (int)$_POST['student_id'];
    $exam_id = (int)$_POST['exam_id'];

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh the page and try again.";
    } else {

    try {
        $pdo->beginTransaction();
        $upsert = $pdo->prepare("INSERT INTO marks (student_id, exam_id, subject_id, obtained_marks)
                                 VALUES (?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE obtained_marks = VALUES(obtained_marks)");
        $delete = $pdo->prepare("DELETE FROM marks WHERE student_id = ? AND exam_id = ? AND subject_id = ?");
        $fullMarks = $pdo->prepare("SELECT id, full_marks FROM subjects WHERE id = ?");
        $validationError = '';
        foreach (($_POST['marks'] ?? []) as $subject_id => $obtained) {
            $subject_id = (int)$subject_id;
            $fullMarks->execute([$subject_id]);
            $subject = $fullMarks->fetch();
            if (!$subject) {
                continue;
            }
            if ($obtained === '' || $obtained === null) {
                $delete->execute([$student_id, $exam_id, $subject_id]);
                continue;
            }
            $obtained = (int)$obtained;
            if ($obtained < 0 || $obtained > (int)$subject['full_marks']) {
                $validationError = "Marks must be between 0 and " . $subject['full_marks'] . ".";
                break;
            }
            $upsert->execute([$student_id, $exam_id, $subject_id, $obtained]);
        }
        if ($validationError !== '') {
            $pdo->rollBack();
            $error = $validationError;
        } else {
            $pdo->commit();
            header("Location: marks.php?exam_id=$exam_id&session_id=" . (int)($_POST['session_id'] ?? 0) . "&class_id=" . (int)($_POST['class_id'] ?? 0) . "&student_id=$student_id&msg=Marks saved successfully");
            exit;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Database error: " . $e->getMessage();
    }
    }
}

$exams = $pdo->query("SELECT e.*, s.session_name FROM examinations e JOIN academic_sessions s ON e.session_id = s.id ORDER BY e.id DESC")->fetchAll();
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY session_name")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();

$exam_id = (int)($_GET['exam_id'] ?? 0);
$session_id = (int)($_GET['session_id'] ?? 0);
$class_id = (int)($_GET['class_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

$students = [];
if ($session_id && $class_id) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE session_id = ? AND class_id = ? ORDER BY roll_number");
    $stmt->execute([$session_id, $class_id]);
    $students = $stmt->fetchAll();
}

$subjects = [];
$existingMarks = [];
$selectedStudent = null;
if ($student_id && $exam_id) {
    $stmt = $pdo->prepare("SELECT s.*, c.class_name, sess.session_name
                           FROM students s
                           JOIN classes c ON s.class_id = c.id
                           JOIN academic_sessions sess ON s.session_id = sess.id
                           WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $selectedStudent = $stmt->fetch();

    if ($selectedStudent) {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE class_id = ? ORDER BY subject_name");
        $stmt->execute([$selectedStudent['class_id']]);
        $subjects = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM marks WHERE student_id = ? AND exam_id = ?");
        $stmt->execute([$student_id, $exam_id]);
        foreach ($stmt->fetchAll() as $m) {
            $existingMarks[$m['subject_id']] = $m['obtained_marks'];
        }
    }
}
?>
<h2 class="fw-bold mb-0">Manage Marks</h2>
<p class="text-muted mb-4">Select an examination and a student to enter marks.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<div class="card card-custom p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Examination</label>
            <select name="exam_id" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($exams as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $exam_id === (int)$e['id'] ? 'selected' : '' ?>><?= escape($e['exam_name']) ?> (<?= escape($e['session_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Session</label>
            <select name="session_id" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $session_id === (int)$s['id'] ? 'selected' : '' ?>><?= escape($s['session_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Class</label>
            <select name="class_id" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $class_id === (int)$c['id'] ? 'selected' : '' ?>><?= escape($c['class_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary-custom w-100"><i class="fa-solid fa-filter me-2"></i>Show Students</button>
        </div>
    </form>
</div>

<?php if ($students): ?>
<div class="card card-custom p-4 mb-4">
    <h5 class="fw-bold mb-3">Select Student (<?= count($students) ?>)</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>Roll</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $st): ?>
                <tr class="<?= $student_id === (int)$st['id'] ? 'table-primary' : '' ?>">
                    <td><?= $st['roll_number'] ?></td>
                    <td><?= escape($st['student_id']) ?></td>
                    <td class="fw-bold"><?= escape($st['name']) ?></td>
                    <td class="text-center">
                        <a href="marks.php?exam_id=<?= $exam_id ?>&session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&student_id=<?= $st['id'] ?>" class="btn btn-sm btn-primary-custom"><?= $student_id === (int)$st['id'] ? 'Selected' : 'Enter Marks' ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($selectedStudent && $subjects): ?>
<div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Enter Marks for <?= escape($selectedStudent['name']) ?></h5>
        <span class="text-muted"><?= escape($selectedStudent['class_name']) ?> | <?= escape($selectedStudent['session_name']) ?></span>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save_marks">
        <input type="hidden" name="student_id" value="<?= $selectedStudent['id'] ?>">
        <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <input type="hidden" name="class_id" value="<?= $class_id ?>">
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Code</th>
                        <th>Full Marks</th>
                        <th>Pass Marks</th>
                        <th>Obtained Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $sub): ?>
                    <tr>
                        <td class="fw-bold"><?= escape($sub['subject_name']) ?></td>
                        <td><?= escape($sub['subject_code']) ?></td>
                        <td><?= $sub['full_marks'] ?></td>
                        <td><?= $sub['pass_marks'] ?></td>
                        <td style="max-width: 180px;">
                            <input type="number" name="marks[<?= $sub['id'] ?>]" class="form-control" min="0" max="<?= $sub['full_marks'] ?>" value="<?= $existingMarks[$sub['id']] ?? '' ?>" placeholder="--">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-primary-custom"><i class="fa-solid fa-floppy-disk me-2"></i>Save Marks</button>
    </form>
</div>
<?php endif; ?>

<?php if ($student_id && !$selectedStudent): ?>
<div class="alert alert-warning">Selected student was not found.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
