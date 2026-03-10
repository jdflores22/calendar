<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=tesda_calendar', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking if foreign key exists...\n";
    
    // Drop if exists
    try {
        $pdo->exec('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9C54C8C93');
        echo "✓ Existing foreign key dropped\n";
    } catch (PDOException $e) {
        echo "No existing foreign key found (this is OK)\n";
    }
    
    echo "Adding foreign key constraint...\n";
    $pdo->exec('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9C54C8C93 FOREIGN KEY (division_id) REFERENCES divisions (id)');
    echo "✓ Foreign key added successfully\n";
    
    echo "\nAll done!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
