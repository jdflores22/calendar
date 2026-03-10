<?php

// Simple test to call the calendar events API and check if conflicts are included

$url = 'http://127.0.0.1:8000/calendar/events?start=2026-02-01&end=2026-02-28';

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'X-Requested-With: XMLHttpRequest'
]);

// We need to handle authentication - let's try without first
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Calendar Events API Test ===\n";
echo "URL: {$url}\n";
echo "HTTP Code: {$httpCode}\n";

if ($httpCode === 200) {
    $events = json_decode($response, true);
    
    if ($events) {
        echo "Events found: " . count($events) . "\n\n";
        
        foreach ($events as $event) {
            echo "Event: {$event['title']}\n";
            echo "Start: {$event['start']}\n";
            
            if (isset($event['extendedProps'])) {
                $props = $event['extendedProps'];
                echo "Has conflicts: " . (isset($props['hasConflicts']) && $props['hasConflicts'] ? 'Yes' : 'No') . "\n";
                
                if (isset($props['conflictCount']) && $props['conflictCount'] > 0) {
                    echo "Conflict count: {$props['conflictCount']}\n";
                }
                
                if (isset($props['conflicts']) && is_array($props['conflicts'])) {
                    echo "Conflicts: " . count($props['conflicts']) . " detailed conflicts\n";
                }
            }
            
            echo "---\n";
        }
    } else {
        echo "No events or invalid JSON response\n";
        echo "Response: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "HTTP Error: {$httpCode}\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
    
    if ($httpCode === 302 || $httpCode === 401) {
        echo "\nThis is likely due to authentication required.\n";
        echo "The calendar requires login to access events.\n";
    }
}

echo "\n=== Next Steps ===\n";
echo "1. Login to the calendar manually at http://127.0.0.1:8000/login\n";
echo "2. Check if events on Feb 2nd and 3rd show conflict indicators\n";
echo "3. If conflicts are not showing, the frontend mapping needs to be fixed\n";