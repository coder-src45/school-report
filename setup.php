<?php
// setup.php
// One-time installer for Wasmer Edge: creates the schema + seed data on the
// freshly provisioned managed MySQL database. Safe to re-run; it is skipped
// once the tables already exist. Delete this file after deployment.

require_once __DIR__ . '/config/database.php';

function tables_exist(PDO $pdo): bool {
    try {
        $pdo->query("SELECT 1 FROM school_settings LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

if (tables_exist($pdo)) {
    die("Setup already complete. Delete setup.php for security.");
}

$sql = file_get_contents(__DIR__ . '/database.sql');
if ($sql === false) {
    die("database.sql not found.");
}

// The Wasmer database is pre-created and we are already connected to it,
// so drop the CREATE DATABASE / USE statements.
$sql = preg_replace('/CREATE DATABASE[^;]*;/i', '', $sql);
$sql = preg_replace('/USE[^;]*;/i', '', $sql);

$statements = array_filter(array_map('trim', explode(';', $sql)));
$executed = 0;

foreach ($statements as $stmt) {
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Setup failed on statement: " . htmlspecialchars(substr($stmt, 0, 120)) . "<br>";
        echo "Error: " . htmlspecialchars($e->getMessage());
        exit;
    }
}

echo "Setup complete. $executed statements executed.<br>";
echo '<a href="index.php">Go to public portal</a> &bull; <a href="admin/login.php">Admin login</a><br>';
echo "Default admin: admin@school.com / password<br>";
echo "Delete setup.php now for security.";
