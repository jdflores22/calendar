<?php

require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$conn = DriverManager::getConnection(['url' => getenv('DATABASE_URL')]);

try {
    // Drop unique constraint on color
    echo "Dropping unique constraint on office color...\n";
    $conn->executeStatement('ALTER TABLE offices DROP INDEX UNIQ_F574FF4C665648E9');
    echo "✓ Unique constraint dropped\n";
} catch (\Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

try {
    // Make color nullable
    echo "Making office color nullable...\n";
    $conn->executeStatement('ALTER TABLE offices MODIFY color VARCHAR(7) DEFAULT NULL');
    echo "✓ Office color field is now nullable\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✓ Office color field updated successfully!\n";
