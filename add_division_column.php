<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=tesda_calendar', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Adding division_id column to users table...\n";
    $pdo->exec('ALTER TABLE users ADD division_id INT DEFAULT NULL AFTER office_id');
    echo "✓ Column added successfully\n";
    
    echo "Adding foreign key constraint...\n";
    $pdo->exec('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9C54C8C93 FOREIGN KEY (division_id) REFERENCES divisions (id)');
    echo "✓ Foreign key added successfully\n";
    
    echo "\nAll done! The division_id column has been added to the users table.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
