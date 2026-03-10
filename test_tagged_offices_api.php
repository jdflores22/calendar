<?php
// Simple test to check if tagged offices are being returned by the calendar API

// Test the calendar events API endpoint
$url = 'http://127.0.0.4:8000/calendar/events';

// Make a request to get events
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

echo "Total events returned: " . count($events) . "\n\n";

// Check each event for tagged offices
foreach ($events as $event) {
    if (isset($event['extendedProps']['taggedOffices']) && !empty($event['extendedProps']['taggedOffices'])) {
        echo "Event: " . $event['title'] . "\n";
        echo "Primary Office: " . ($event['extendedProps']['office']['name'] ?? 'None') . "\n";
        echo "Tagged Offices:\n";
        
        foreach ($event['extendedProps']['taggedOffices'] as $office) {
            echo "  - " . $office['name'] . " (" . $office['code'] . ") - Color: " . $office['color'] . "\n";
        }
        echo "\n";
    }
}

echo "Test completed.\n";
?>