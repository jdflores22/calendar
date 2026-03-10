<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env');

// Database connection
$connectionParams = [
    'dbname' => $_ENV['DATABASE_NAME'] ?? 'tesda_calendar',
    'user' => $_ENV['DATABASE_USER'] ?? 'root',
    'password' => $_ENV['DATABASE_PASSWORD'] ?? '',
    'host' => $_ENV['DATABASE_HOST'] ?? 'localhost',
    'driver' => 'pdo_mysql',
];

try {
    $connection = DriverManager::getConnection($connectionParams);
    
    echo "Testing Event Tags Database Structure...\n\n";
    
    // Check if event_tags table exists
    $sql = "SHOW TABLES LIKE 'event_tags'";
    $result = $connection->executeQuery($sql);
    
    if ($result->rowCount() > 0) {
        echo "✓ event_tags table exists\n";
        
        // Check table structure
        $sql = "DESCRIBE event_tags";
        $result = $connection->executeQuery($sql);
        $columns = $result->fetchAllAssociative();
        
        echo "\nEvent Tags table structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']}\n";
        }
        
        // Check if there are any existing tags
        $sql = "SELECT COUNT(*) as count FROM event_tags";
        $result = $connection->executeQuery($sql);
        $count = $result->fetchAssociative()['count'];
        
        echo "\nExisting tags count: {$count}\n";
        
        if ($count > 0) {
            $sql = "SELECT * FROM event_tags LIMIT 5";
            $result = $connection->executeQuery($sql);
            $tags = $result->fetchAllAssociative();
            
            echo "\nSample tags:\n";
            foreach ($tags as $tag) {
                echo "  - ID: {$tag['id']}, Name: {$tag['name']}, Color: " . ($tag['color'] ?? 'null') . "\n";
            }
        }
    } else {
        echo "✗ event_tags table does not exist\n";
    }
    
    // Check if event_event_tags junction table exists
    $sql = "SHOW TABLES LIKE 'event_event_tags'";
    $result = $connection->executeQuery($sql);
    
    if ($result->rowCount() > 0) {
        echo "\n✓ event_event_tags junction table exists\n";
        
        // Check table structure
        $sql = "DESCRIBE event_event_tags";
        $result = $connection->executeQuery($sql);
        $columns = $result->fetchAllAssociative();
        
        echo "\nEvent-EventTags junction table structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']}\n";
        }
        
        // Check if there are any existing relationships
        $sql = "SELECT COUNT(*) as count FROM event_event_tags";
        $result = $connection->executeQuery($sql);
        $count = $result->fetchAssociative()['count'];
        
        echo "\nExisting event-tag relationships: {$count}\n";
    } else {
        echo "\n✗ event_event_tags junction table does not exist\n";
        echo "You may need to run: php bin/console doctrine:migrations:migrate\n";
    }
    
    // Test creating some sample tags
    echo "\nTesting tag creation...\n";
    
    $sampleTags = ['meeting', 'training', 'workshop', 'conference', 'seminar'];
    
    foreach ($sampleTags as $tagName) {
        // Check if tag exists
        $sql = "SELECT id FROM event_tags WHERE name = ?";
        $result = $connection->executeQuery($sql, [$tagName]);
        
        if ($result->rowCount() == 0) {
            // Create the tag
            $sql = "INSERT INTO event_tags (name, created_at) VALUES (?, NOW())";
            $connection->executeStatement($sql, [$tagName]);
            echo "  ✓ Created tag: {$tagName}\n";
        } else {
            echo "  - Tag already exists: {$tagName}\n";
        }
    }
    
    echo "\n✓ Event tags functionality test completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), "event_tags") !== false) {
        echo "\nIt looks like the event_tags table doesn't exist yet.\n";
        echo "Please run the following commands:\n";
        echo "1. php bin/console make:migration\n";
        echo "2. php bin/console doctrine:migrations:migrate\n";
    }
}