<?php
$active = 'subjects';
$pageTitle = 'Subjects | Admin Panel';
require_once 'includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh the page and try again.";
    } else {

    try {
        if ($action === 'add_class') {
            $stmt = $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)");
            $stmt->execute([trim($_POST['class_name'])]);
            header("Location: subjects.php?msg=Class added successfully");
            exit;
        }
        if ($action === 'delete_class') {
            $classId = (int)$_POST['id'];
            $check = $pdo->prepare("SELECT
                (SELECT COUNT(*) FROM students WHERE class_id = ?) +
                (SELECT COUNT(*) FROM subjects WHERE class_id = ?)");
            $check->execute([$classId, $classId]);
            if ((int)$check->fetchColumn() > 0) {
                throw new RuntimeException("Cannot delete this class because it still has students or subjects assigned to it.");
            }
            $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$classId]);
            header("Location: subjects.php?msg=Class deleted successfully");
            exit;
        }
        if ($action === 'add_subject') {
            $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, full_marks, pass_marks, class_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['subject_name']),
                trim($_POST['subject_code']),
                (int)$_POST['full_marks'],
                (int)$_POST['pass_marks'],
                (int)$_POST['class_id']
            ]);
            header("Location: subjects.php?msg=Subject added successfully");
            exit;
        }
        if ($action === 'edit_subject') {
            $stmt = $pdo->prepare("UPDATE subjects SET subject_name = ?, subject_code = ?, full_marks = ?, pass_marks = ?, class_id = ? WHERE id = ?");
            $stmt->execute([
                trim($_POST['subject_name']),
                trim($_POST['subject_code']),
                (int)$_POST['full_marks'],
                (int)$_POST['pass_marks'],
                (int)$_POST['class_id'],
                (int)$_POST['id']
            ]);
            header("Location: subjects.php?msg=Subject updated successfully");
            exit;
        }
        if ($action === 'delete_subject') {
            $stmt = $pdo->prepare("DELETE FROM marks WHERE subject_id = ?");
            $stmt->execute([(int)$_POST['id']]);
            $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([(int)$_POST['id']]);
            header("Location: subjects.php?msg=Subject deleted successfully");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
    }
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$subjects = $pdo->query("SELECT sub.*, c.class_name
                         FROM subjects sub
                         JOIN classes c ON sub.class_id = c.id
                         ORDER BY c.class_name, sub.subject_name")->fetchAll();

$editSubject = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSubject = $stmt->fetch();
}
?>
<h2 class="fw-bold mb-0">Subjects</h2>
<p class="text-muted mb-4">Manage classes and subjects assigned to them.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Classes panel -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 h-100">
            <h5 class="fw-bold mb-3">Classes</h5>
            <form method="POST" class="d-flex mb-3">
                <input type="hidden" name="action" value="add_class">
                <?= csrf_field() ?>
                <input type="text" name="class_name" class="form-control me-2" placeholder="e.g. Class 7" required>
                <button type="submit" class="btn btn-primary-custom text-nowrap"><i class="fa-solid fa-plus"></i></button>
            </form>
            <ul class="list-group">
                <?php foreach ($classes as $c): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= escape($c['class_name']) ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this class?');">
                        <input type="hidden" name="action" value="delete_class">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </li>
                <?php endforeach; ?>
                <?php if (count($classes) === 0): ?>
                <li class="list-group-item text-muted text-center">No classes yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Subjects panel -->
    <div class="col-lg-8">
        <div class="card card-custom p-4 mb-4">
            <h5 class="fw-bold mb-3"><?= $editSubject ? 'Edit Subject' : 'Add New Subject' ?></h5>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editSubject ? 'edit_subject' : 'add_subject' ?>">
                <input type="hidden" name="id" value="<?= $editSubject['id'] ?? '' ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control" value="<?= escape($editSubject['subject_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Code</label>
                        <input type="text" name="subject_code" class="form-control" value="<?= escape($editSubject['subject_code'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Full Marks</label>
                        <input type="number" name="full_marks" class="form-control" value="<?= $editSubject['full_marks'] ?? 100 ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Pass Marks</label>
                        <input type="number" name="pass_marks" class="form-control" value="<?= $editSubject['pass_marks'] ?? 33 ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= isset($editSubject) && $editSubject['class_id'] == $c['id'] ? 'selected' : '' ?>><?= escape($c['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary-custom"><?= $editSubject ? 'Update Subject' : 'Add Subject' ?></button>
                        <?php if ($editSubject): ?>
                            <a href="subjects.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-3">Subject List (<?= count($subjects) ?>)</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Code</th>
                            <th>Full</th>
                            <th>Pass</th>
                            <th>Class</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $sub): ?>
                        <tr>
                            <td class="fw-bold"><?= escape($sub['subject_name']) ?></td>
                            <td><?= escape($sub['subject_code']) ?></td>
                            <td><?= $sub['full_marks'] ?></td>
                            <td><?= $sub['pass_marks'] ?></td>
                            <td><?= escape($sub['class_name']) ?></td>
                            <td class="text-center">
                                <a href="subjects.php?edit=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this subject?');">
                                    <input type="hidden" name="action" value="delete_subject">
                                    <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($subjects) === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No subjects found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
