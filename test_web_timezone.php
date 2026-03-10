<?php
// Test the timezone fix via web request
echo "<h1>🌐 Web Timezone Test</h1>\n";

$url = 'http://127.0.0.4:8000/test-timezone';

echo "<p>Testing URL: <a href='$url' target='_blank'>$url</a></p>\n";

// Use cURL to fetch the test page
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p style='color: red;'>❌ cURL Error: $error</p>\n";
} elseif ($httpCode !== 200) {
    echo "<p style='color: red;'>❌ HTTP Error: $httpCode</p>\n";
    echo "<pre>$response</pre>\n";
} else {
    echo "<p style='color: green;'>✅ Successfully fetched test page</p>\n";
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>\n";
    echo $response;
    echo "</div>\n";
}

echo "<h2>🔗 Manual Test Links</h2>\n";
echo "<p>If the server is running, you can also test these pages manually:</p>\n";
echo "<ul>\n";
echo "<li><a href='http://127.0.0.4:8000/test-timezone' target='_blank'>Timezone Test Page</a></li>\n";
echo "<li><a href='http://127.0.0.4:8000/events/76' target='_blank'>Event 76 Details</a></li>\n";
echo "<li><a href='http://127.0.0.4:8000/events/76/edit' target='_blank'>Event 76 Edit</a></li>\n";
echo "<li><a href='http://127.0.0.4:8000/calendar' target='_blank'>Calendar</a></li>\n";
echo "</ul>\n";
?>