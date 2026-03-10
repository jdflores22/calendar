<?php
// Create a simple Symfony controller test to verify Twig filters are working
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Create a simple test template
$testTemplate = '
<!DOCTYPE html>
<html>
<head><title>Twig Filter Test</title></head>
<body>
<h1>Twig Filter Test</h1>
<p>Testing Event 76 timezone conversion:</p>
<table border="1">
<tr><th>Filter</th><th>Result</th></tr>
<tr><td>Raw startTime</td><td>{{ event.startTime.format("Y-m-d H:i:s") }}</td></tr>
<tr><td>system_date filter</td><td>{{ event.startTime|system_date("Y-m-d H:i:s") }}</td></tr>
<tr><td>system_time filter</td><td>{{ event.startTime|system_time }}</td></tr>
<tr><td>system_date with format</td><td>{{ event.startTime|system_date("l, F j, Y g:i A") }}</td></tr>
</table>
</body>
</html>
';

file_put_contents('templates/test_filters.html.twig', $testTemplate);

echo "<h1>✅ Created Test Template</h1>\n";
echo "<p>Created <code>templates/test_filters.html.twig</code> to test Twig filters.</p>\n";

// Create a simple controller method
$controllerCode = '
<?php

namespace App\Controller;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route(\'/test-filters\', name: \'test_filters\')]
    public function testFilters(EntityManagerInterface $entityManager): Response
    {
        // Get Event 76
        $event = $entityManager->getRepository(Event::class)->find(76);
        
        if (!$event) {
            throw $this->createNotFoundException(\'Event 76 not found\');
        }
        
        return $this->render(\'test_filters.html.twig\', [
            \'event\' => $event,
        ]);
    }
}
';

file_put_contents('src/Controller/TestController.php', $controllerCode);

echo "<h2>✅ Created Test Controller</h2>\n";
echo "<p>Created <code>src/Controller/TestController.php</code> with test route.</p>\n";

echo "<h2>🧪 Test Instructions</h2>\n";
echo "<ol>\n";
echo "<li>Go to <a href='http://127.0.0.4:8000/test-filters' target='_blank'>http://127.0.0.4:8000/test-filters</a></li>\n";
echo "<li>This will show Event 76 with different filter applications</li>\n";
echo "<li>Compare the results:</li>\n";
echo "<ul>\n";
echo "<li><strong>Raw startTime</strong>: Should show <code>2026-02-12 00:00:00</code> (UTC)</li>\n";
echo "<li><strong>system_date filter</strong>: Should show <code>2026-02-12 08:00:00</code> (Philippines)</li>\n";
echo "<li><strong>system_time filter</strong>: Should show <code>2026-02-12T08:00</code> (Philippines)</li>\n";
echo "<li><strong>system_date with format</strong>: Should show <code>Thursday, February 12, 2026 8:00 AM</code></li>\n";
echo "</ul>\n";
echo "</ol>\n";

echo "<h2>🔍 Expected Results</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Filter</th><th>Expected Result</th></tr>\n";
echo "<tr><td>Raw startTime</td><td>2026-02-12 00:00:00</td></tr>\n";
echo "<tr><td>system_date filter</td><td>2026-02-12 08:00:00</td></tr>\n";
echo "<tr><td>system_time filter</td><td>2026-02-12T08:00</td></tr>\n";
echo "<tr><td>system_date with format</td><td>Thursday, February 12, 2026 8:00 AM</td></tr>\n";
echo "</table>\n";

echo "<h2>🚨 If Filters Don't Work</h2>\n";
echo "<p>If the system_date and system_time filters show the same as raw startTime, then:</p>\n";
echo "<ul>\n";
echo "<li>❌ The Twig filters are not working in the Symfony environment</li>\n";
echo "<li>❌ There might be an issue with the TimezoneExtension registration</li>\n";
echo "<li>❌ The filters might be using the wrong timezone</li>\n";
echo "</ul>\n";

echo "<h2>🧹 Cleanup</h2>\n";
echo "<p>After testing, you can delete these test files:</p>\n";
echo "<ul>\n";
echo "<li><code>templates/test_filters.html.twig</code></li>\n";
echo "<li><code>src/Controller/TestController.php</code></li>\n";
echo "</ul>\n";
?>