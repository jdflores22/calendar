<?php
// Simple script to fix office color constraint

$host = 'localhost';
$dbname = 'tesda_calendar';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n\n";
    
    // Check if unique constraint exists
    echo "Checking for unique constraint on office color...\n";
    $stmt = $pdo->query("SHOW INDEX FROM offices WHERE Column_name = 'color' AND Non_unique = 0");
    $constraint = $stmt->fetch();
    
    if ($constraint) {
        echo "Found unique constraint: {$constraint['Key_name']}\n";
        echo "Dropping unique constraint...\n";
        
        $pdo->exec("ALTER TABLE offices DROP INDEX {$constraint['Key_name']}");
        echo "✓ Unique constraint dropped successfully!\n\n";
    } else {
        echo "✓ No unique constraint found on color field.\n\n";
    }
    
    // Make color nullable if not already
    echo "Ensuring color field is nullable...\n";
    $pdo->exec("ALTER TABLE offices MODIFY color VARCHAR(7) DEFAULT NULL");
    echo "✓ Color field is now nullable.\n\n";
    
    echo "✓ All fixes applied successfully!\n";
    echo "\nOffices in the same cluster can now share the same color.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
