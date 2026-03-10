<?php
// Simple database check
$host = '127.0.0.1';
$dbname = 'tesda_calendar';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database: $dbname\n\n";
    
    // Show tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables in database:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    if (empty($tables)) {
        echo "No tables found. Database might be empty.\n";
        echo "Try running: php bin/console doctrine:schema:create\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    
    // Try to create the database
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "Attempting to create database: $dbname\n";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
        echo "Database created successfully\n";
        echo "Now run: php bin/console doctrine:migrations:migrate\n";
        
    } catch (PDOException $e2) {
        echo "Failed to create database: " . $e2->getMessage() . "\n";
    }
}
?>