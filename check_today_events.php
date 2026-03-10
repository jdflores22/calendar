<?php

require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$conn = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'tesda_calendar',
    'user' => 'root',
    'password' => ''
]);

// Get current Manila time
$manilaTimezone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $manilaTimezone);
$today = new DateTime('today', $manilaTimezone);
$tomorrow = new DateTime('tomorrow', $manilaTimezone);

echo "=== Current Time Analysis ===\n";
echo "Current Manila Time: " . $now->format('Y-m-d H:i:s') . "\n";
echo "Today Manila (start): " . $today->format('Y-m-d H:i:s') . "\n";
echo "Tomorrow Manila (start): " . $tomorrow->format('Y-m-d H:i:s') . "\n\n";

// Convert to UTC for database query
$todayUTC = clone $today;
$todayUTC->setTimezone(new DateTimeZone('UTC'));
$tomorrowUTC = clone $tomorrow;
$tomorrowUTC->setTimezone(new DateTimeZone('UTC'));

echo "Today UTC (for DB query): " . $todayUTC->format('Y-m-d H:i:s') . "\n";
echo "Tomorrow UTC (for DB query): " . $tomorrowUTC->format('Y-m-d H:i:s') . "\n\n";

// Query events
$sql = 'SELECT id, title, start_time, end_time FROM events WHERE start_time >= ? AND start_time < ? ORDER BY start_time';
$stmt = $conn->executeQuery($sql, [$todayUTC->format('Y-m-d H:i:s'), $tomorrowUTC->format('Y-m-d H:i:s')]);
$events = $stmt->fetchAllAssociative();

echo "=== Events for TODAY (March 3, 2026 Manila time) ===\n";
if (empty($events)) {
    echo "No events found for today\n\n";
} else {
    foreach ($events as $event) {
        echo "ID: {$event['id']}, Title: {$event['title']}\n";
        echo "  Start (UTC in DB): {$event['start_time']}\n";
        
        $start = new DateTime($event['start_time'], new DateTimeZone('UTC'));
        $start->setTimezone($manilaTimezone);
        echo "  Start (Manila): " . $start->format('Y-m-d H:i:s (g:i A)') . "\n\n";
    }
}

// Also check what events are showing as "tomorrow"
$tomorrowEnd = new DateTime('tomorrow', $manilaTimezone);
$tomorrowEnd->modify('+1 day');
$tomorrowEndUTC = clone $tomorrowEnd;
$tomorrowEndUTC->setTimezone(new DateTimeZone('UTC'));

$sql2 = 'SELECT id, title, start_time, end_time FROM events WHERE start_time >= ? AND start_time < ? ORDER BY start_time';
$stmt2 = $conn->executeQuery($sql2, [$tomorrowUTC->format('Y-m-d H:i:s'), $tomorrowEndUTC->format('Y-m-d H:i:s')]);
$tomorrowEvents = $stmt2->fetchAllAssociative();

echo "=== Events for TOMORROW (March 4, 2026 Manila time) ===\n";
if (empty($tomorrowEvents)) {
    echo "No events found for tomorrow\n";
} else {
    foreach ($tomorrowEvents as $event) {
        echo "ID: {$event['id']}, Title: {$event['title']}\n";
        echo "  Start (UTC in DB): {$event['start_time']}\n";
        
        $start = new DateTime($event['start_time'], new DateTimeZone('UTC'));
        $start->setTimezone($manilaTimezone);
        echo "  Start (Manila): " . $start->format('Y-m-d H:i:s (g:i A)') . "\n\n";
    }
}
