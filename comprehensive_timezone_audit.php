<?php
// Comprehensive timezone audit across all pages
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');
$dotenv->load('.env');

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'dev';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '1';

$kernel = new \App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "<h1>🔍 Comprehensive Timezone Audit</h1>\n";

// Test 1: Database Direct Query
echo "<h2>1. Database Direct Query</h2>\n";
try {
    $host = $_ENV['DATABASE_HOST'] ?? 'localhost';
    $port = $_ENV['DATABASE_PORT'] ?? '3306';
    $dbname = $_ENV['DATABASE_NAME'] ?? 'tesda_calendar';
    $username = $_ENV['DATABASE_USER'] ?? 'root';
    $password = $_ENV['DATABASE_PASSWORD'] ?? '';

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT id, title, start_time, end_time FROM events WHERE id = 76");
    $stmt->execute();
    $dbEvent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Source</th><th>Start Time</th><th>End Time</th></tr>\n";
    echo "<tr><td><strong>Raw Database</strong></td><td>{$dbEvent['start_time']}</td><td>{$dbEvent['end_time']}</td></tr>\n";
    echo "</table>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: {$e->getMessage()}</p>\n";
}

// Test 2: Doctrine Entity
echo "<h2>2. Doctrine Entity (Event Repository)</h2>\n";
try {
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $event = $entityManager->getRepository('App\Entity\Event')->find(76);
    
    if ($event) {
        $startTime = $event->getStartTime();
        $endTime = $event->getEndTime();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Source</th><th>Start Time</th><th>Timezone</th><th>End Time</th><th>Timezone</th></tr>\n";
        echo "<tr><td><strong>Doctrine Entity</strong></td><td>{$startTime->format('Y-m-d H:i:s')}</td><td>{$startTime->getTimezone()->getName()}</td><td>{$endTime->format('Y-m-d H:i:s')}</td><td>{$endTime->getTimezone()->getName()}</td></tr>\n";
        echo "</table>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Doctrine error: {$e->getMessage()}</p>\n";
}

// Test 3: TimezoneService Methods
echo "<h2>3. TimezoneService Methods</h2>\n";
try {
    $timezoneService = $container->get('App\Service\TimezoneService');
    
    if ($event) {
        $convertedStart = $timezoneService->convertFromDatabase($event->getStartTime());
        $frontendFormat = $timezoneService->formatForFrontend($event->getStartTime());
        $displayFormat = $timezoneService->formatForDisplay($event->getStartTime(), 'g:i A');
        $isoFormat = $convertedStart->format('c');
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Method</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
        
        $convertedStatus = (strpos($convertedStart->format('H:i'), '00:00') !== false) ? '✅ CORRECT' : '❌ WRONG';
        $frontendStatus = ($frontendFormat === '2026-02-12T00:00') ? '✅ CORRECT' : '❌ WRONG';
        $displayStatus = ($displayFormat === '12:00 AM') ? '✅ CORRECT' : '❌ WRONG';
        
        echo "<tr><td>convertFromDatabase</td><td><strong>{$convertedStart->format('Y-m-d H:i:s T')}</strong></td><td>2026-02-12 00:00:00 PST</td><td>$convertedStatus</td></tr>\n";
        echo "<tr><td>formatForFrontend</td><td><strong>$frontendFormat</strong></td><td>2026-02-12T00:00</td><td>$frontendStatus</td></tr>\n";
        echo "<tr><td>formatForDisplay</td><td><strong>$displayFormat</strong></td><td>12:00 AM</td><td>$displayStatus</td></tr>\n";
        echo "<tr><td>ISO format (for API)</td><td><strong>$isoFormat</strong></td><td>2026-02-12T00:00:00+08:00</td><td>" . (strpos($isoFormat, 'T00:00:00') !== false ? '✅ CORRECT' : '❌ WRONG') . "</td></tr>\n";
        echo "</table>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>TimezoneService error: {$e->getMessage()}</p>\n";
}

// Test 4: Calendar API Simulation
echo "<h2>4. Calendar API Simulation</h2>\n";
try {
    // Simulate what CalendarController.formatEventForCalendar does
    if ($event) {
        $startTime = $timezoneService->convertFromDatabase($event->getStartTime());
        $endTime = $timezoneService->convertFromDatabase($event->getEndTime());
        
        $apiData = [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'start' => $startTime->format('c'), // ISO 8601 format
            'end' => $endTime->format('c'),
        ];
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>API Field</th><th>Value</th><th>Expected</th><th>Status</th></tr>\n";
        
        $startStatus = (strpos($apiData['start'], 'T00:00:00') !== false) ? '✅ CORRECT' : '❌ WRONG';
        $endStatus = (strpos($apiData['end'], 'T02:00:00') !== false) ? '✅ CORRECT' : '❌ WRONG';
        
        echo "<tr><td>start</td><td><strong>{$apiData['start']}</strong></td><td>2026-02-12T00:00:00+08:00</td><td>$startStatus</td></tr>\n";
        echo "<tr><td>end</td><td><strong>{$apiData['end']}</strong></td><td>2026-02-12T02:00:00+08:00</td><td>$endStatus</td></tr>\n";
        echo "</table>\n";
        
        echo "<h3>JavaScript Processing Simulation</h3>\n";
        echo "<p>When JavaScript receives this data and processes it:</p>\n";
        echo "<pre>\n";
        echo "// JavaScript code in calendar:\n";
        echo "const startTime = new Date('{$apiData['start']}').toLocaleTimeString('en-US', {\n";
        echo "    hour: 'numeric',\n";
        echo "    minute: '2-digit',\n";
        echo "    hour12: true,\n";
        echo "    timeZone: 'Asia/Manila'\n";
        echo "});\n";
        echo "// Should result in: '12:00 AM'\n";
        echo "</pre>\n";
        
        // Test this JavaScript logic in PHP
        $jsDate = new DateTime($apiData['start']);
        $jsResult = $jsDate->format('g:i A');
        $jsStatus = ($jsResult === '12:00 AM') ? '✅ CORRECT' : '❌ WRONG';
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>JavaScript Simulation</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
        echo "<tr><td>toLocaleTimeString equivalent</td><td><strong>$jsResult</strong></td><td>12:00 AM</td><td>$jsStatus</td></tr>\n";
        echo "</table>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>API simulation error: {$e->getMessage()}</p>\n";
}

// Test 5: Twig Filters
echo "<h2>5. Twig Filters Test</h2>\n";
try {
    if ($event) {
        $twig = $container->get('twig');
        $template = $twig->createTemplate('{{ startTime|system_time }} | {{ startTime|system_date("g:i A") }}');
        $result = $template->render(['startTime' => $event->getStartTime()]);
        
        $parts = explode(' | ', $result);
        $systemTimeResult = trim($parts[0] ?? '');
        $systemDateResult = trim($parts[1] ?? '');
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Twig Filter</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
        
        $twigTimeStatus = ($systemTimeResult === '2026-02-12T00:00') ? '✅ CORRECT' : '❌ WRONG';
        $twigDateStatus = ($systemDateResult === '12:00 AM') ? '✅ CORRECT' : '❌ WRONG';
        
        echo "<tr><td>system_time</td><td><strong>$systemTimeResult</strong></td><td>2026-02-12T00:00</td><td>$twigTimeStatus</td></tr>\n";
        echo "<tr><td>system_date</td><td><strong>$systemDateResult</strong></td><td>12:00 AM</td><td>$twigDateStatus</td></tr>\n";
        echo "</table>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Twig error: {$e->getMessage()}</p>\n";
}

// Test 6: System Configuration
echo "<h2>6. System Configuration</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Setting</th><th>Value</th><th>Expected</th><th>Status</th></tr>\n";

$phpTz = date_default_timezone_get();
$phpStatus = ($phpTz === 'Asia/Manila') ? '✅ CORRECT' : '❌ WRONG';

echo "<tr><td>PHP Default Timezone</td><td><strong>$phpTz</strong></td><td>Asia/Manila</td><td>$phpStatus</td></tr>\n";
echo "<tr><td>Current PHP Time</td><td>" . date('Y-m-d H:i:s T') . "</td><td>Philippines time</td><td>ℹ️ INFO</td></tr>\n";

$nowPhilippines = new DateTime('now', new DateTimeZone('Asia/Manila'));
echo "<tr><td>Philippines Time Now</td><td>" . $nowPhilippines->format('Y-m-d H:i:s T') . "</td><td>Current time</td><td>ℹ️ INFO</td></tr>\n";

echo "</table>\n";

// Summary
echo "<h2>🎯 DIAGNOSIS SUMMARY</h2>\n";
echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #007bff;'>\n";
echo "<h3>Key Findings:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Database</strong>: Event 76 stored as <code>{$dbEvent['start_time']}</code></li>\n";
echo "<li><strong>Doctrine</strong>: DateTime timezone is <code>" . ($event ? $event->getStartTime()->getTimezone()->getName() : 'N/A') . "</code></li>\n";
echo "<li><strong>TimezoneService</strong>: " . ($convertedStatus === '✅ CORRECT' ? 'Working correctly' : 'Has issues') . "</li>\n";
echo "<li><strong>Calendar API</strong>: " . ($startStatus === '✅ CORRECT' ? 'Sending correct data' : 'Sending wrong data') . "</li>\n";
echo "<li><strong>Twig Filters</strong>: " . ($twigTimeStatus === '✅ CORRECT' ? 'Working correctly' : 'Has issues') . "</li>\n";
echo "</ol>\n";

if ($startStatus === '✅ CORRECT' && $convertedStatus === '✅ CORRECT') {
    echo "<h3 style='color: green;'>✅ BACKEND IS CORRECT</h3>\n";
    echo "<p>The backend is sending the correct time data. The issue is likely:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Browser caching</strong> - Try hard refresh (Ctrl+F5)</li>\n";
    echo "<li><strong>JavaScript timezone handling</strong> - Check browser console for errors</li>\n";
    echo "<li><strong>FullCalendar library</strong> - May be doing additional timezone conversion</li>\n";
    echo "</ul>\n";
} else {
    echo "<h3 style='color: red;'>❌ BACKEND HAS ISSUES</h3>\n";
    echo "<p>The backend is not sending the correct time data. Issues found in:</p>\n";
    echo "<ul>\n";
    if ($convertedStatus !== '✅ CORRECT') echo "<li>TimezoneService methods</li>\n";
    if ($startStatus !== '✅ CORRECT') echo "<li>Calendar API data formatting</li>\n";
    if ($twigTimeStatus !== '✅ CORRECT') echo "<li>Twig filter implementation</li>\n";
    echo "</ul>\n";
}

echo "</div>\n";

$kernel->shutdown();
?>