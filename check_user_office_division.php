<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=tesda_calendar', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking user office and division assignments...\n\n";
    
    // Get all users with their office and division
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.email,
            u.office_id,
            u.division_id,
            o.name as office_name,
            o.code as office_code,
            d.name as division_name,
            d.code as division_code,
            c.name as cluster_name,
            c.code as cluster_code
        FROM users u
        LEFT JOIN offices o ON u.office_id = o.id
        LEFT JOIN divisions d ON u.division_id = d.id
        LEFT JOIN office_clusters c ON o.cluster_id = c.id
        ORDER BY u.id
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "User ID: {$user['id']}\n";
        echo "Email: {$user['email']}\n";
        echo "Office ID: " . ($user['office_id'] ?? 'NULL') . "\n";
        echo "Office: " . ($user['office_name'] ?? 'Not assigned') . " (" . ($user['office_code'] ?? 'N/A') . ")\n";
        echo "Cluster: " . ($user['cluster_name'] ?? 'Not assigned') . " (" . ($user['cluster_code'] ?? 'N/A') . ")\n";
        echo "Division ID: " . ($user['division_id'] ?? 'NULL') . "\n";
        echo "Division: " . ($user['division_name'] ?? 'Not assigned') . " (" . ($user['division_code'] ?? 'N/A') . ")\n";
        echo str_repeat('-', 80) . "\n\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
