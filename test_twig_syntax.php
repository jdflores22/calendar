<?php
// Simple test to check if the Twig template compiles without errors

require_once 'vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

try {
    // Create Twig environment
    $loader = new FilesystemLoader('templates');
    $twig = new Environment($loader);
    
    // Try to compile the calendar template
    $template = $twig->load('calendar/index.html.twig');
    
    echo "✅ SUCCESS: Calendar template compiles without errors!\n";
    echo "✅ Template loaded successfully: " . get_class($template) . "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}