<?php
/**
 * Direct test of the conflict detection API
 */

// Test scenarios for February 5th, 2026
$testCases = [
    [
        'name' => 'Overlap with Test Event (10:15-10:45)',
        'start' => '2026-02-05T10:15:00',
        'end' => '2026-02-05T10:45:00',
        'expected' => 'CONFLICT'
    ],
    [
        'name' => 'Overlap with both events (10:45-11:30)',
        'start' => '2026-02-05T10:45:00',
        'end' => '2026-02-05T11:30:00',
        'expected' => 'CONFLICT'
    ],
    [
        'name' => 'Clear morning slot (9:00-9:30)',
        'start' => '2026-02-05T09:00:00',
        'end' => '2026-02-05T09:30:00',
        'expected' => 'CLEAR'
    ],
    [
        'name' => 'Clear afternoon slot (12:30-13:30)',
        'start' => '2026-02-05T12:30:00',
        'end' => '2026-02-05T13:30:00',
        'expected' => 'CLEAR'
    ],
    [
        'name' => 'Overlap with Policy Review (14:30-15:30)',
        'start' => '2026-02-05T14:30:00',
        'end' => '2026-02-05T15:30:00',
        'expected' => 'CONFLICT'
    ]
];

echo "🧪 DIRECT CONFLICT DETECTION API TEST\n";
echo "=====================================\n\n";

foreach ($testCases as $i => $testCase) {
    echo "Test " . ($i + 1) . ": {$testCase['name']}\n";
    echo "Time: {$testCase['start']} to {$testCase['end']}\n";
    echo "Expected: {$testCase['expected']}\n";
    
    // Prepare the request data
    $data = [
        'start' => $testCase['start'],
        'end' => $testCase['end']
    ];
    
    // Make the API request using curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.4:8000/events/api/check-conflicts');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ CURL Error: $error\n";
    } elseif ($httpCode === 302 || $httpCode === 401) {
        echo "🔐 Authentication required (HTTP $httpCode)\n";
        echo "   Please log in at http://127.0.0.4:8000/login first\n";
    } elseif ($httpCode !== 200) {
        echo "❌ HTTP Error: $httpCode\n";
        echo "   Response: " . substr($response, 0, 200) . "...\n";
    } else {
        $result = json_decode($response, true);
        
        if ($result === null) {
            echo "❌ Invalid JSON response\n";
            echo "   Raw response: " . substr($response, 0, 200) . "...\n";
        } else {
            if ($result['success']) {
                if ($result['has_conflicts']) {
                    echo "⚠️  CONFLICTS DETECTED ({$result['conflict_count']} conflicts)\n";
                    echo "   Can override: " . ($result['can_override'] ? 'Yes' : 'No') . "\n";
                    
                    if (!empty($result['conflicts'])) {
                        echo "   Conflicting events:\n";
                        foreach ($result['conflicts'] as $conflict) {
                            echo "     - {$conflict['title']} ({$conflict['start']} to {$conflict['end']})\n";
                        }
                    }
                    
                    $actualResult = 'CONFLICT';
                } else {
                    echo "✅ NO CONFLICTS - Time slot available\n";
                    $actualResult = 'CLEAR';
                }
                
                // Check if result matches expectation
                if ($actualResult === $testCase['expected']) {
                    echo "✅ Result matches expectation\n";
                } else {
                    echo "❌ Result mismatch! Expected {$testCase['expected']}, got $actualResult\n";
                }
            } else {
                echo "❌ API Error: {$result['message']}\n";
            }
        }
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

echo "📋 SUMMARY\n";
echo "==========\n";
echo "If you see authentication errors, please:\n";
echo "1. Go to http://127.0.0.4:8000/login\n";
echo "2. Log in with your credentials\n";
echo "3. Run this test again\n\n";
echo "If conflicts are detected correctly, the modal should work too!\n";
?>