<?php
// Complete validation test for transfer functionality

function validateTransferData($data) {
    $errors = [];
    
    // Title validation
    $title = trim($data['title'] ?? '');
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    // Color validation
    $color = $data['color'] ?? '#007BFF';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        // Try to fix common color format issues
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#' . $color;
        } else {
            $color = '#007BFF'; // Default blue
        }
    }
    
    // Priority validation with mapping
    $priority = $data['priority'] ?? 'normal';
    $priorityMap = [
        'medium' => 'normal',
        'moderate' => 'normal', 
        'critical' => 'urgent',
        'emergency' => 'urgent'
    ];
    if (isset($priorityMap[$priority])) {
        $priority = $priorityMap[$priority];
    }
    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
        $priority = 'normal';
    }
    
    // Status validation
    $status = $data['status'] ?? 'confirmed';
    if (!in_array($status, ['confirmed', 'tentative', 'cancelled'])) {
        $status = 'confirmed';
    }
    
    // Date validation
    if (!isset($data['start']) || !isset($data['end'])) {
        $errors[] = 'Start and end times are required';
    } else {
        $startTime = new DateTime($data['start']);
        $endTime = new DateTime($data['end']);
        if ($endTime <= $startTime) {
            $errors[] = 'End time must be after start time';
        }
    }
    
    return [
        'isValid' => empty($errors),
        'errors' => $errors,
        'processedData' => [
            'title' => $title,
            'description' => trim($data['description'] ?? ''),
            'location' => trim($data['location'] ?? ''),
            'color' => $color,
            'priority' => $priority,
            'status' => $status,
            'allDay' => isset($data['allDay']) && ($data['allDay'] === true || $data['allDay'] === '1' || $data['allDay'] === 1),
            'office_id' => isset($data['office_id']) && is_numeric($data['office_id']) ? (int)$data['office_id'] : null,
            'isRecurring' => isset($data['isRecurring']) && ($data['isRecurring'] === true || $data['isRecurring'] === '1' || $data['isRecurring'] === 1),
            'start' => $data['start'] ?? null,
            'end' => $data['end'] ?? null
        ]
    ];
}

// Test the problematic data from the user's console log
$problematicData = [
    'title' => 'Staff Training Workshop',
    'description' => 'Professional development workshop',
    'location' => 'Training Center',
    'color' => '#F59E0B',
    'priority' => 'medium',  // This was causing the validation error
    'status' => 'confirmed',
    'allDay' => false,
    'office_id' => null,
    'isRecurring' => false,
    'start' => '2026-02-14T15:51',
    'end' => '2026-02-14T16:51'
];

echo "Transfer Data Validation Test:\n";
echo "=============================\n";
echo "Original problematic data:\n";
echo json_encode($problematicData, JSON_PRETTY_PRINT) . "\n\n";

$result = validateTransferData($problematicData);

echo "Validation Result:\n";
echo "=================\n";
echo "Valid: " . ($result['isValid'] ? '✅ Yes' : '❌ No') . "\n";

if (!empty($result['errors'])) {
    echo "Errors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
} else {
    echo "No validation errors found!\n";
}

echo "\nProcessed Data:\n";
echo "==============\n";
echo json_encode($result['processedData'], JSON_PRETTY_PRINT) . "\n";

// Test other edge cases
echo "\n" . str_repeat("=", 50) . "\n";
echo "Edge Case Tests:\n";
echo str_repeat("=", 50) . "\n";

$edgeCases = [
    'Empty title' => array_merge($problematicData, ['title' => '']),
    'Invalid color' => array_merge($problematicData, ['color' => 'invalid']),
    'Invalid priority' => array_merge($problematicData, ['priority' => 'super-high']),
    'Invalid status' => array_merge($problematicData, ['status' => 'maybe']),
    'End before start' => array_merge($problematicData, ['end' => '2026-02-14T14:51']),
];

foreach ($edgeCases as $caseName => $testData) {
    echo "\nTest: $caseName\n";
    echo str_repeat("-", strlen($caseName) + 6) . "\n";
    
    $result = validateTransferData($testData);
    echo "Valid: " . ($result['isValid'] ? '✅ Yes' : '❌ No') . "\n";
    
    if (!empty($result['errors'])) {
        echo "Errors: " . implode(', ', $result['errors']) . "\n";
    }
    
    // Show key processed values
    echo "Priority: {$testData['priority']} -> {$result['processedData']['priority']}\n";
    if (isset($testData['color'])) {
        echo "Color: {$testData['color']} -> {$result['processedData']['color']}\n";
    }
}
?>