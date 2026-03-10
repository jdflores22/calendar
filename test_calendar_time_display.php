<?php
// Test to check what time data is being returned by the calendar API

$url = 'http://127.0.0.4:8000/calendar/events';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "Failed to fetch events from API\n";
    exit(1);
}

$events = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Failed to decode JSON response: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "=== Calendar Events Time Analysis ===\n\n";
echo "Total events returned: " . count($events) . "\n\n";

foreach ($events as $index => $event) {
    if ($event['extendedProps']['type'] === 'event') {
        echo "Event #" . ($index + 1) . ": " . $event['title'] . "\n";
        echo "  ID: " . $event['id'] . "\n";
        echo "  All Day: " . ($event['allDay'] ? 'Yes' : 'No') . "\n";
        echo "  Start (Raw): " . $event['start'] . "\n";
        echo "  End (Raw): " . $event['end'] . "\n";
        
        // Parse and display the times
        $startTime = new DateTime($event['start']);
        $endTime = new DateTime($event['end']);
        
        echo "  Start (Parsed): " . $startTime->format('Y-m-d H:i:s T') . "\n";
        echo "  End (Parsed): " . $endTime->format('Y-m-d H:i:s T') . "\n";
        echo "  Duration: " . $startTime->diff($endTime)->format('%h hours %i minutes') . "\n";
        
        if (isset($event['extendedProps']['office'])) {
            echo "  Primary Office: " . $event['extendedProps']['office']['name'] . "\n";
        }
        
        if (!empty($event['extendedProps']['taggedOffices'])) {
            echo "  Tagged Offices: ";
            $officeNames = array_map(function($office) {
                return $office['name'];
            }, $event['extendedProps']['taggedOffices']);
            echo implode(', ', $officeNames) . "\n";
        }
        
        echo "\n";
    }
}

echo "=== Current Server Time ===\n";
echo "Server Time (UTC): " . gmdate('Y-m-d H:i:s T') . "\n";
echo "Server Time (Local): " . date('Y-m-d H:i:s T') . "\n";

// Test Philippines timezone
$philippinesTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
echo "Philippines Time: " . $philippinesTime->format('Y-m-d H:i:s T') . "\n";

echo "\nTest completed.\n";
?>