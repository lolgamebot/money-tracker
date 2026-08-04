<?php
// Database Configuration
// Copy this file to db.php and fill in your credentials
// For InfinityFree: host is usually sql###.infinityfree.com
$host = "";
$dbname = "";
$dbuser = "";
$dbpass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Error. Please verify database settings and try again.");
}
?>
