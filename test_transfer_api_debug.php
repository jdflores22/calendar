<?php
// Simple test script to debug the transfer API endpoint

// Test data that should be valid
$testData = [
    'title' => 'Test Transfer Event',
    'description' => 'This is a test event for debugging transfer functionality',
    'location' => 'Test Location',
    'color' => '#3B82F6',
    'priority' => 'normal',
    'status' => 'confirmed',
    'allDay' => false,
    'office_id' => null,
    'isRecurring' => false,
    'start' => '2026-02-05T09:00',
    'end' => '2026-02-05T10:00'
];

echo "Test Transfer API Data:\n";
echo "======================\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Validate the data according to our validation rules
$errors = [];

// Title validation
$title = trim($testData['title'] ?? '');
if (empty($title)) {
    $errors[] = 'Title is required';
}

// Color validation
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $testData['color'])) {
    $errors[] = 'Invalid color format (must be #XXXXXX)';
}

// Priority validation
if (!in_array($testData['priority'], ['low', 'normal', 'high', 'urgent'])) {
    $errors[] = 'Invalid priority value';
}

// Status validation
if (!in_array($testData['status'], ['confirmed', 'tentative', 'cancelled'])) {
    $errors[] = 'Invalid status value';
}

// Date validation
$startTime = new DateTime($testData['start']);
$endTime = new DateTime($testData['end']);
if ($endTime <= $startTime) {
    $errors[] = 'End time must be after start time';
}

echo "Validation Results:\n";
echo "==================\n";
if (empty($errors)) {
    echo "✅ All validation checks passed!\n";
} else {
    echo "❌ Validation errors found:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\nDateTime Parsing Test:\n";
echo "=====================\n";
echo "Start: " . $startTime->format('Y-m-d H:i:s') . " (UTC: " . $startTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') . ")\n";
echo "End: " . $endTime->format('Y-m-d H:i:s') . " (UTC: " . $endTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') . ")\n";

echo "\nColor Format Test:\n";
echo "=================\n";
$testColors = ['#FF0000', 'FF0000', '#ff0000', '#FFF', '#GGGGGG', '', '#123456'];
foreach ($testColors as $color) {
    $isValid = preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    echo sprintf("%-10s -> %s\n", $color ?: '(empty)', $isValid ? '✅ Valid' : '❌ Invalid');
}

echo "\nBoolean Conversion Test:\n";
echo "=======================\n";
$testBooleans = [true, false, '1', '0', 1, 0, 'true', 'false', '', null];
foreach ($testBooleans as $value) {
    $converted = $value === true || $value === '1' || $value === 1;
    $display = is_null($value) ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
    echo sprintf("%-8s (%s) -> %s\n", $display, gettype($value), $converted ? 'true' : 'false');
}
?>