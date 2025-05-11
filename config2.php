<?php
// config.php
$host = "localhost";
$dbname = "artistic";
$username = "artistic_admin";
$password = "StrongPassword123!";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Corrected line below - removed the _EXCEPTION from the attribute name
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Make $pdo available globally
    $GLOBALS['pdo'] = $pdo;
    
    // Initialize permissions if needed
    $stmt = $pdo->query("SELECT COUNT(*) FROM adminpermissions");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO adminpermissions (...) VALUES (...)");
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function getPDO() {
    return $GLOBALS['pdo'];
}