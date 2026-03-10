<?php
$pdo = new PDO('mysql:host=localhost;dbname=tesda_calendar;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('DESCRIBE events');
echo "Events table structure:\n";
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

// Also check if event 76 exists
$stmt = $pdo->query('SELECT COUNT(*) as count FROM events WHERE id = 76');
$result = $stmt->fetch();
echo "\nEvent 76 exists: " . ($result['count'] > 0 ? 'Yes' : 'No') . "\n";

if ($result['count'] > 0) {
    $stmt = $pdo->query('SELECT * FROM events WHERE id = 76');
    $event = $stmt->fetch();
    echo "\nEvent 76 data:\n";
    foreach ($event as $key => $value) {
        if (!is_numeric($key)) {
            echo "$key: $value\n";
        }
    }
}
?>