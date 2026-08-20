<?php
if (isset($_GET['download_template'])) {
    require_once '../config/database.php';
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }

    if (($_GET['download_template'] ?? '') === 'xlsx') {
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            exit('The PHP zip extension is not enabled. Enable it in php.ini to download Excel templates.');
        }
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
            http_response_code(500);
            exit('Could not create template.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Students" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>student_id</t></is></c><c r="B1" t="inlineStr"><is><t>roll_number</t></is></c><c r="C1" t="inlineStr"><is><t>name</t></is></c><c r="D1" t="inlineStr"><is><t>dob</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>STU-2025-001</t></is></c><c r="B2"><v>1</v></c><c r="C2" t="inlineStr"><is><t>John Doe</t></is></c><c r="D2" t="inlineStr"><is><t>2008-05-15</t></is></c></row><row r="3"><c r="A3" t="inlineStr"><is><t>STU-2025-002</t></is></c><c r="B3"><v>2</v></c><c r="C3" t="inlineStr"><is><t>Jane Smith</t></is></c><c r="D3" t="inlineStr"><is><t>2009-03-22</t></is></c></row></sheetData></worksheet>');
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="students_template.xlsx"');
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="students_template.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility
    echo "student_id,roll_number,name,dob\n";
    echo "STU-2025-001,1,John Doe,2008-05-15\n";
    echo "STU-2025-002,2,Jane Smith,2009-03-22\n";
    exit;
}

function normalizeStudentRow($studentId, $roll, $name, $dob) {
    return [
        'student_id' => trim($studentId),
        'roll_number' => (int)$roll,
        'name' => trim($name),
        'dob' => trim($dob) !== '' ? trim($dob) : null
    ];
}

function parseStudentsCsv($path) {
    $rows = [];
    $errors = [];
    $fh = fopen($path, 'r');
    if (!$fh) {
        throw new RuntimeException("Could not open the uploaded file.");
    }
    $line = 0;
    $firstLine = true;
    while (($row = fgetcsv($fh)) !== false) {
        $line++;
        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }
        if ($firstLine) {
            $firstLine = false;
            $row[0] = ltrim((string)($row[0] ?? ''), "\xEF\xBB\xBF");
            if (strtolower(trim($row[0])) === 'student_id') {
                continue; // header row
            }
        }
        if (count($row) < 3 || trim((string)($row[0] ?? '')) === '' || trim((string)($row[1] ?? '')) === '' || trim((string)($row[2] ?? '')) === '') {
            $errors[] = "Row $line: skipped (missing student_id, roll_number or name).";
            continue;
        }
        $rows[] = normalizeStudentRow($row[0], $row[1], $row[2], $row[3] ?? '');
    }
    fclose($fh);
    return [$rows, $errors];
}

function parseStudentsXlsx($path) {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException("The PHP zip extension is not enabled. Enable it in php.ini to import Excel files.");
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Could not open the Excel file. Make sure it is a valid .xlsx workbook.");
    }

    $shared = [];
    if (($content = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sst = simplexml_load_string($content);
        foreach ($sst->si as $si) {
            $shared[] = (string)$si->t;
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    if (($wb = $zip->getFromName('xl/workbook.xml')) !== false) {
        $wxml = simplexml_load_string($wb);
        if (isset($wxml->sheets->sheet[0]['sheetId'])) {
            $sheetPath = 'xl/worksheets/sheet1.xml';
        }
    }
    $content = $zip->getFromName($sheetPath);
    $zip->close();
    if ($content === false) {
        throw new RuntimeException("Could not read the first worksheet from the Excel file.");
    }

    $xml = simplexml_load_string($content);
    $rows = [];
    $errors = [];
    $rowIndex = 0;
    foreach ($xml->sheetData->row as $r) {
        $rowIndex++;
        $cells = [];
        foreach ($r->c as $c) {
            $ref = (string)$c['r'];
            $col = preg_replace('/\d+/', '', $ref);
            $type = (string)$c['t'];
            $value = '';
            if ($type === 's') {
                $value = $shared[(int)$c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)$c->is->t;
            } else {
                $value = trim((string)$c->v);
            }
            $cells[$col] = trim((string)$value);
        }
        $sid = $cells['A'] ?? '';
        $roll = $cells['B'] ?? '';
        $name = $cells['C'] ?? '';
        $dob = $cells['D'] ?? '';

        if ($sid === '' && $roll === '' && $name === '') {
            continue; // blank row
        }
        if ($rowIndex === 1 && strtolower($sid) === 'student_id') {
            continue; // header row
        }
        if ($sid === '' || $roll === '' || $name === '') {
            $errors[] = "Row $rowIndex: skipped (missing student_id, roll_number or name).";
            continue;
        }
        $rows[] = normalizeStudentRow($sid, $roll, $name, $dob);
    }
    return [$rows, $errors];
}

function decodePdfString($s) {
    $s = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
    $s = preg_replace_callback('/\\\\(\d{1,3})/', fn($m) => chr(octdec($m[1])), $s);
    $s = str_replace(['\\n', '\\r', '\\t', '\\b', '\\f'], ["\n", "\r", "\t", "\x08", "\x0C"], $s);
    return $s;
}

function parseStudentsPdf($path) {
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Could not read the PDF file.");
    }

    // Extract text runs from all BT...ET blocks (best-effort, works for simple text PDFs)
    preg_match_all('/BT(.*?)ET/s', $content, $blocks);
    $lines = [];
    foreach ($blocks[1] as $block) {
        $block = preg_replace('/\/[A-Za-z0-9_.]+\s+[\d.]+\s+Tf\b/', '', $block);
        $current = '';
        preg_match_all('/\((?:(?:\\\\.)|[^\\\\()])*\)\s*Tj|\[[^\]]*\]\s*TJ|Td|TD|T\*|[\d.\-]+\s+[\d.\-]+\s+[\d.\-]+\s+[\d.\-]+\s+[\d.\-]+\s+[\d.\-]+\s*Tm/', $block, $ops);
        foreach ($ops[0] as $op) {
            if (preg_match('/^\(/', $op)) {
                $current .= decodePdfString(substr($op, 1, strpos($op, ')') - 1));
            } elseif (preg_match('/^\[/', $op)) {
                preg_match_all('/\((?:(?:\\\\.)|[^\\\\()])*\)/', $op, $parts);
                foreach ($parts[0] as $p) {
                    $current .= decodePdfString(substr($p, 1, -1));
                }
            } else {
                if (trim($current) !== '') {
                    $lines[] = trim($current);
                }
                $current = '';
            }
        }
        if (trim($current) !== '') {
            $lines[] = trim($current);
        }
    }

    $rows = [];
    $errors = [];
    $lineNo = 0;
    foreach ($lines as $raw) {
        $lineNo++;
        $line = preg_replace('/\s+/', ' ', trim($raw));
        if ($line === '') {
            continue;
        }
        $tokens = explode(' ', $line);
        if (count($tokens) < 2) {
            $errors[] = "PDF line $lineNo: skipped (too few fields).";
            continue;
        }
        $dob = '';
        $last = end($tokens);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) {
            $dob = array_pop($tokens);
        }
        if (count($tokens) < 2) {
            $errors[] = "PDF line $lineNo: skipped (too few fields).";
            continue;
        }
        if (is_numeric($tokens[0])) {
            // Layout B: roll number first, no student ID → generate one
            $roll = (int)array_shift($tokens);
            $studentId = 'STU-' . date('Y') . '-' . str_pad($roll, 4, '0', STR_PAD_LEFT);
        } else {
            $studentId = array_shift($tokens);
            if (!isset($tokens[0]) || !is_numeric($tokens[0])) {
                $errors[] = "PDF line $lineNo: skipped (roll number missing).";
                continue;
            }
            $roll = (int)array_shift($tokens);
        }
        $name = trim(implode(' ', $tokens));
        if ($name === '') {
            $errors[] = "PDF line $lineNo: skipped (name missing).";
            continue;
        }
        $rows[] = normalizeStudentRow($studentId, $roll, $name, $dob);
    }
    return [$rows, $errors];
}

$active = 'students';
$pageTitle = 'Students | Admin Panel';
require_once 'includes/header.php';

$error = '';
$importOk = 0;
$importFailed = 0;
$importMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh the page and try again.";
    } else {

    try {
        if ($action === 'add' || $action === 'edit') {
            $classId = (int)$_POST['class_id'];
            $sessionId = (int)$_POST['session_id'];
            $roll = (int)$_POST['roll_number'];
            $excludeId = $action === 'edit' ? (int)($_POST['id'] ?? 0) : 0;

            $dupRoll = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND session_id = ? AND roll_number = ? AND id <> ?");
            $dupRoll->execute([$classId, $sessionId, $roll, $excludeId]);
            if ((int)$dupRoll->fetchColumn() > 0) {
                throw new RuntimeException("Roll number $roll already exists in this class and session.");
            }

            $dupId = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ? AND id <> ?");
            $dupId->execute([trim($_POST['student_id']), $excludeId]);
            if ((int)$dupId->fetchColumn() > 0) {
                throw new RuntimeException("Student ID already exists.");
            }
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO students (student_id, roll_number, name, class_id, session_id, dob) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['student_id']),
                (int)$_POST['roll_number'],
                trim($_POST['name']),
                (int)$_POST['class_id'],
                (int)$_POST['session_id'],
                $_POST['dob'] !== '' ? $_POST['dob'] : null
            ]);
            header("Location: students.php?msg=Student added successfully");
            exit;
        }

        if ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE students SET student_id = ?, roll_number = ?, name = ?, class_id = ?, session_id = ?, dob = ? WHERE id = ?");
            $stmt->execute([
                trim($_POST['student_id']),
                (int)$_POST['roll_number'],
                trim($_POST['name']),
                (int)$_POST['class_id'],
                (int)$_POST['session_id'],
                $_POST['dob'] !== '' ? $_POST['dob'] : null,
                (int)$_POST['id']
            ]);
            header("Location: students.php?msg=Student updated successfully");
            exit;
        }

        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM marks WHERE student_id = ?");
            $stmt->execute([(int)$_POST['id']]);
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([(int)$_POST['id']]);
            header("Location: students.php?msg=Student deleted successfully");
            exit;
        }

        if ($action === 'import_csv') {
            $batchClassId = (int)($_POST['class_id'] ?? 0);
            $batchSessionId = (int)($_POST['session_id'] ?? 0);

            if (!$batchClassId || !$batchSessionId) {
                throw new RuntimeException("Please select a Class and Session for the batch.");
            }
            if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException("Please choose a CSV, Excel or PDF file to upload.");
            }

            $file = $_FILES['import_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext === 'csv') {
                [$rows, $parseErrors] = parseStudentsCsv($file['tmp_name']);
            } elseif ($ext === 'xlsx') {
                [$rows, $parseErrors] = parseStudentsXlsx($file['tmp_name']);
            } elseif ($ext === 'pdf') {
                [$rows, $parseErrors] = parseStudentsPdf($file['tmp_name']);
            } else {
                throw new RuntimeException("Unsupported file type: .$ext. Please upload a CSV, XLSX or PDF file.");
            }

            $insert = $pdo->prepare("INSERT INTO students (student_id, roll_number, name, class_id, session_id, dob) VALUES (?, ?, ?, ?, ?, ?)");
            $importMessages = array_merge($importMessages, $parseErrors);
            foreach ($rows as $i => $r) {
                try {
                    $insert->execute([$r['student_id'], $r['roll_number'], $r['name'], $batchClassId, $batchSessionId, $r['dob']]);
                    $importOk++;
                } catch (PDOException $e) {
                    $importFailed++;
                    $importMessages[] = "Entry " . ($i + 1) . ": " . $r['student_id'] . " skipped (" . ($e->getCode() == 23000 ? 'duplicate student ID' : 'database error') . ").";
                }
            }

            $note = $importFailed > 0 ? " ($importFailed failed)" : "";
            if ($importOk > 0) {
                header("Location: students.php?msg=$importOk student(s) imported successfully$note");
                exit;
            }
            if ($importFailed > 0 || $importMessages) {
                $error = "Import failed: 0 students imported. " . count($importMessages) . " issue(s) found.";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
    }
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name")->fetchAll();
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY session_name")->fetchAll();
$students = $pdo->query("SELECT s.*, c.class_name, sess.session_name
                         FROM students s
                         JOIN classes c ON s.class_id = c.id
                         JOIN academic_sessions sess ON s.session_id = sess.id
                         ORDER BY s.class_id, s.roll_number")->fetchAll();

$editStudent = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editStudent = $stmt->fetch();
}
?>
<h2 class="fw-bold mb-0">Students</h2>
<p class="text-muted mb-4">Register and manage students.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<div class="card card-custom p-4 mb-4">
    <h5 class="fw-bold mb-3"><?= $editStudent ? 'Edit Student' : 'Add New Student' ?></h5>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editStudent ? 'edit' : 'add' ?>">
        <input type="hidden" name="id" value="<?= $editStudent['id'] ?? '' ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Student ID</label>
                <input type="text" name="student_id" class="form-control" value="<?= escape($editStudent['student_id'] ?? '') ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Roll Number</label>
                <input type="number" name="roll_number" class="form-control" value="<?= $editStudent['roll_number'] ?? '' ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= escape($editStudent['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= isset($editStudent) && $editStudent['class_id'] == $c['id'] ? 'selected' : '' ?>><?= escape($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= isset($editStudent) && $editStudent['session_id'] == $s['id'] ? 'selected' : '' ?>><?= escape($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-control" value="<?= $editStudent['dob'] ?? '' ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary-custom"><?= $editStudent ? 'Update Student' : 'Add Student' ?></button>
                <?php if ($editStudent): ?>
                    <a href="students.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card card-custom p-4 mb-4 border-primary">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-import me-2"></i>Bulk Upload Students (CSV / Excel / PDF)</h5>
        <div>
            <a href="students.php?download_template=csv" class="btn btn-sm btn-outline-primary me-1"><i class="fa-solid fa-download me-1"></i>CSV Template</a>
            <a href="students.php?download_template=xlsx" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download me-1"></i>Excel Template</a>
        </div>
    </div>
    <p class="text-muted mb-3">
        Upload a <strong>CSV</strong> or <strong>Excel</strong> file with columns:
        <code>student_id, roll_number, name, dob</code> (dob optional). All students in the batch are assigned to the
        Class and Session selected below.
    </p>
    <div class="alert alert-info py-2 small mb-3">
        <strong>PDF (best-effort):</strong> each line of text in the PDF is read as one student. Format per line:
        <code>student_id roll_number name dob</code> — or <code>roll_number name</code> if no student IDs are listed
        (IDs are then generated automatically). Complex/layout-heavy PDFs may not parse reliably.
    </div>
    <?php if ($importFailed > 0 && $importMessages): ?>
        <div class="alert alert-warning">
            <strong><?= $importFailed ?> entry(ies) could not be imported:</strong>
            <ul class="mb-0 mt-2"><?php foreach ($importMessages as $m): ?><li><?= escape($m) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_csv">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= escape($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= escape($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">File (CSV, XLSX or PDF)</label>
                <input type="file" name="import_file" class="form-control" accept=".csv,.xlsx,.pdf" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary-custom w-100"><i class="fa-solid fa-upload me-2"></i>Upload Batch</button>
            </div>
        </div>
    </form>
</div>

<div class="card card-custom p-4">
    <h5 class="fw-bold mb-3">Student List (<?= count($students) ?>)</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Roll</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Session</th>
                    <th>DOB</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $st): ?>
                <tr>
                    <td><?= escape($st['student_id']) ?></td>
                    <td><?= $st['roll_number'] ?></td>
                    <td class="fw-bold"><?= escape($st['name']) ?></td>
                    <td><?= escape($st['class_name']) ?></td>
                    <td><?= escape($st['session_name']) ?></td>
                    <td><?= $st['dob'] ?></td>
                    <td class="text-center">
                        <a href="students.php?edit=<?= $st['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $st['id'] ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($students) === 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No students found. Add your first student above.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
