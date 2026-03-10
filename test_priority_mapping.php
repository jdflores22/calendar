<?php
// Test priority mapping logic

function mapPriority($priority) {
    // Map common invalid priority values to valid ones
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
    
    return $priority;
}

$testPriorities = [
    'low',
    'normal', 
    'high',
    'urgent',
    'medium',      // Should map to 'normal'
    'moderate',    // Should map to 'normal'
    'critical',    // Should map to 'urgent'
    'emergency',   // Should map to 'urgent'
    'invalid',     // Should default to 'normal'
    '',            // Should default to 'normal'
    null           // Should default to 'normal'
];

echo "Priority Mapping Test:\n";
echo "=====================\n";

foreach ($testPriorities as $priority) {
    $mapped = mapPriority($priority ?? 'normal');
    $display = $priority === null ? 'null' : ($priority === '' ? '(empty)' : $priority);
    echo sprintf("%-12s -> %s\n", $display, $mapped);
}

echo "\nValidation Test:\n";
echo "===============\n";

$validPriorities = ['low', 'normal', 'high', 'urgent'];
foreach ($testPriorities as $priority) {
    $mapped = mapPriority($priority ?? 'normal');
    $isValid = in_array($mapped, $validPriorities);
    $display = $priority === null ? 'null' : ($priority === '' ? '(empty)' : $priority);
    echo sprintf("%-12s -> %-8s %s\n", $display, $mapped, $isValid ? '✅' : '❌');
}
?>