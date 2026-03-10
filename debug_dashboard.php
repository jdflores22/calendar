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

// Simulate what findTodaysEvents() does
$timezone = new DateTimeZone('Asia/Manila');
$today = new DateTime('today', $timezone);
$tomorrow = new DateTime('tomorrow', $timezone);

echo "=== Simulating findTodaysEvents() ===\n";
echo "Today Manila: " . $today->format('Y-m-d H:i:s T') . "\n";
echo "Tomorrow Manila: " . $tomorrow->format('Y-m-d H:i:s T') . "\n\n";

// The query uses these DateTime objects directly
// Doctrine should convert them to UTC for the database query
echo "Today object timezone: " . $today->getTimezone()->getName() . "\n";
echo "Tomorrow object timezone: " . $tomorrow->getTimezone()->getName() . "\n\n";

// Let's see what the actual SQL query would be
// When Doctrine receives a DateTime with Asia/Manila timezone, it should convert to UTC
$todayForDB = clone $today;
$todayForDB->setTimezone(new DateTimeZone('UTC'));
$tomorrowForDB = clone $tomorrow;
$tomorrowForDB->setTimezone(new DateTimeZone('UTC'));

echo "What Doctrine SHOULD query (UTC):\n";
echo "Start: " . $todayForDB->format('Y-m-d H:i:s') . "\n";
echo "End: " . $tomorrowForDB->format('Y-m-d H:i:s') . "\n\n";

// Query with the UTC times
$sql = 'SELECT id, title, start_time, end_time FROM events WHERE id = 78';
$stmt = $conn->executeQuery($sql);
$event = $stmt->fetchAssociative();

if ($event) {
    echo "=== Event ID 78 Details ===\n";
    echo "Title: {$event['title']}\n";
    echo "Start (UTC): {$event['start_time']}\n";
    echo "End (UTC): {$event['end_time']}\n";
    
    $start = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $start->setTimezone($timezone);
    $end = new DateTime($event['end_time'], new DateTimeZone('UTC'));
    $end->setTimezone($timezone);
    
    echo "Start (Manila): " . $start->format('Y-m-d H:i:s') . "\n";
    echo "End (Manila): " . $end->format('Y-m-d H:i:s') . "\n\n";
}

// Now test the overlap query
$sql = 'SELECT id, title, start_time, end_time FROM events WHERE start_time <= :end AND end_time >= :start ORDER BY start_time';
$stmt = $conn->executeQuery($sql, [
    'start' => $todayForDB->format('Y-m-d H:i:s'),
    'end' => $tomorrowForDB->format('Y-m-d H:i:s')
]);
$events = $stmt->fetchAllAssociative();

echo "=== Events returned by query ===\n";
if (empty($events)) {
    echo "No events found\n";
} else {
    foreach ($events as $event) {
        echo "ID: {$event['id']}, Title: {$event['title']}\n";
        echo "  Start (UTC): {$event['start_time']}\n";
        echo "  End (UTC): {$event['end_time']}\n";
        
        $start = new DateTime($event['start_time'], new DateTimeZone('UTC'));
        $start->setTimezone($timezone);
        $end = new DateTime($event['end_time'], new DateTimeZone('UTC'));
        $end->setTimezone($timezone);
        
        echo "  Start (Manila): " . $start->format('Y-m-d H:i:s') . "\n";
        echo "  End (Manila): " . $end->format('Y-m-d H:i:s') . "\n";
        echo "  Date in Manila: " . $start->format('Y-m-d') . "\n\n";
    }
}
