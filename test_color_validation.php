<?php
// Test color validation and setColor method behavior

function testSetColor($input) {
    // Simulate the setColor method logic
    $color = $input;
    if (!str_starts_with($color, '#')) {
        $color = '#' . $color;
    }
    $color = strtoupper($color);
    
    // Test validation regex
    $isValid = preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    
    return [
        'input' => $input,
        'processed' => $color,
        'valid' => $isValid
    ];
}

$testColors = [
    '#3B82F6',      // Standard blue
    '#ff0000',      // Lowercase red
    'FF0000',       // No # prefix
    '#123abc',      // Mixed case
    '#GGGGGG',      // Invalid hex
    '#FFF',         // Too short
    '',             // Empty
    '#123456'       // Valid
];

echo "Color Processing and Validation Test:\n";
echo "====================================\n";

foreach ($testColors as $color) {
    $result = testSetColor($color);
    $status = $result['valid'] ? '✅ Valid' : '❌ Invalid';
    echo sprintf("%-10s -> %-10s %s\n", 
        $result['input'] ?: '(empty)', 
        $result['processed'], 
        $status
    );
}

echo "\nRegex Pattern Test:\n";
echo "==================\n";
$pattern = '/^#[0-9A-Fa-f]{6}$/';
echo "Pattern: $pattern\n\n";

$directTests = ['#FF0000', '#ff0000', '#123ABC', '#123abc', '#GGGGGG', '#12345'];
foreach ($directTests as $test) {
    $matches = preg_match($pattern, $test);
    echo sprintf("%-10s -> %s\n", $test, $matches ? '✅ Match' : '❌ No match');
}
?>