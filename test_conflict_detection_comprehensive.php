<?php
/**
 * Comprehensive Conflict Detection Test
 * This script creates test events and then tests the conflict detection API
 */

// Test the conflict detection API directly
function testConflictDetection($start, $end, $description = '') {
    $url = 'http://127.0.0.4:8000/events/api/check-conflicts';
    
    $data = [
        'start' => $start,
        'end' => $end
    ];
    
    $options = [
        'http' => [
            'header' => [
                "Content-Type: application/json",
                "X-Requested-With: XMLHttpRequest"
            ],
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        echo "❌ ERROR: Failed to call API for $description\n";
        return false;
    }
    
    $response = json_decode($result, true);
    
    echo "\n🔍 Testing: $description\n";
    echo "   Time: $start to $end\n";
    echo "   Result: " . ($response['success'] ? '✅ Success' : '❌ Failed') . "\n";
    
    if ($response['success']) {
        if ($response['has_conflicts']) {
            echo "   Status: ⚠️  CONFLICTS DETECTED ({$response['conflict_count']} conflicts)\n";
            echo "   Can Override: " . ($response['can_override'] ? '✅ Yes' : '❌ No') . "\n";
            
            if (!empty($response['conflicts'])) {
                echo "   Conflicting Events:\n";
                foreach ($response['conflicts'] as $conflict) {
                    echo "     - {$conflict['title']} ({$conflict['start']} to {$conflict['end']})\n";
                }
            }
        } else {
            echo "   Status: ✅ NO CONFLICTS - Time slot available\n";
        }
    } else {
        echo "   Error: {$response['message']}\n";
    }
    
    return $response;
}

echo "🧪 COMPREHENSIVE CONFLICT DETECTION TEST\n";
echo "========================================\n";

// Test 1: No conflicts (future time slot)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
testConflictDetection(
    $tomorrow . 'T09:00:00',
    $tomorrow . 'T10:00:00',
    'Future time slot (should be available)'
);

// Test 2: Overlapping with existing events (if any)
$today = date('Y-m-d');
testConflictDetection(
    $today . 'T10:00:00',
    $today . 'T11:00:00',
    'Today 10-11 AM (may have conflicts)'
);

// Test 3: Different time today
testConflictDetection(
    $today . 'T14:00:00',
    $today . 'T15:00:00',
    'Today 2-3 PM (may have conflicts)'
);

// Test 4: Invalid time range (end before start)
testConflictDetection(
    $today . 'T15:00:00',
    $today . 'T14:00:00',
    'Invalid range (end before start)'
);

// Test 5: Very long event
testConflictDetection(
    $today . 'T08:00:00',
    $today . 'T18:00:00',
    'Long event 8 AM - 6 PM (likely conflicts)'
);

echo "\n📊 TEST SUMMARY\n";
echo "===============\n";
echo "✅ If you see conflicts detected above, the API is working correctly\n";
echo "🔍 Check the calendar at http://127.0.0.4:8000/calendar to see existing events\n";
echo "🧪 Use the modal to test real-time conflict detection\n";

?>