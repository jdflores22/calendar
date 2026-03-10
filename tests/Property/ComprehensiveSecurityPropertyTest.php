<?php

namespace App\Tests\Property;

use App\Service\SecurityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Property 14: Comprehensive Security Protection
 * 
 * For any user input or system interaction, CSRF protection must be implemented on forms,
 * XSS attacks must be prevented through proper sanitization, file uploads must be validated
 * for type and size restrictions, and all user actions must be logged for audit purposes.
 * 
 * **Validates: Requirements 10.1, 10.2, 10.4, 10.5, 10.6, 10.8**
 */
class ComprehensiveSecurityPropertyTest extends TestCase
{
    private SecurityService $securityService;
    private CsrfTokenManagerInterface $csrfTokenManager;

    protected function setUp(): void
    {
        // Create mock dependencies
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $logger = new NullLogger();
        $requestStack = new RequestStack();
        $security = $this->createMock(Security::class);
        $auditService = $this->createMock(AuditService::class);
        
        // Create mock CSRF token manager
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        
        // Create SecurityService with mocked dependencies
        $this->securityService = new SecurityService(
            $entityManager,
            $logger,
            $requestStack,
            $security,
            $this->csrfTokenManager,
            $auditService
        );
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 14: Comprehensive Security Protection
     * 
     * Test that XSS prevention works for all types of malicious input
     */
    public function testXSSPreventionForAllInputTypes(): void
    {
        // Generate various XSS attack vectors
        $xssVectors = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(\'XSS\')">',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<svg onload="alert(\'XSS\')">',
            '<body onload="alert(\'XSS\')">',
            '<div onclick="alert(\'XSS\')">Click me</div>',
            '"><script>alert("XSS")</script>',
            '\';alert("XSS");//',
            '<object data="javascript:alert(\'XSS\')"></object>',
        ];

        foreach ($xssVectors as $vector) {
            // Test input sanitization
            $sanitized = $this->securityService->sanitizeInput($vector, false);
            
            // Property: Sanitized input must not contain executable JavaScript
            $this->assertStringNotContainsString('<script', strtolower($sanitized), 
                "XSS vector should be sanitized: {$vector}");
            $this->assertStringNotContainsString('javascript:', strtolower($sanitized), 
                "JavaScript protocol should be removed: {$vector}");
            $this->assertStringNotContainsString('onerror=', strtolower($sanitized), 
                "Event handlers should be removed: {$vector}");
            $this->assertStringNotContainsString('onload=', strtolower($sanitized), 
                "Event handlers should be removed: {$vector}");
            $this->assertStringNotContainsString('onclick=', strtolower($sanitized), 
                "Event handlers should be removed: {$vector}");
            
            // Property: Suspicious content detection must identify XSS attempts
            $isSuspicious = $this->securityService->containsSuspiciousContent($vector);
            $this->assertTrue($isSuspicious, "XSS vector should be detected as suspicious: {$vector}");
        }
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 14: Comprehensive Security Protection
     * 
     * Test that CSRF protection is properly implemented and validated
     */
    public function testCSRFProtectionForAllForms(): void
    {
        $formTypes = ['event_form', 'profile_form', 'directory_form', 'form_builder'];
        
        foreach ($formTypes as $tokenId) {
            // Mock valid CSRF token
            $validToken = 'valid_token_' . $tokenId;
            $this->csrfTokenManager
                ->expects($this->any())
                ->method('isTokenValid')
                ->willReturnCallback(function($token) use ($validToken) {
                    return $token->getValue() === $validToken;
                });
            
            // Property: Valid CSRF tokens must be accepted
            $isValidToken = $this->securityService->validateCsrfToken($tokenId, $validToken);
            $this->assertTrue($isValidToken, "Valid CSRF token should be accepted for {$tokenId}");
            
            // Property: Invalid CSRF tokens must be rejected
            $invalidTokens = [
                'invalid_token',
                '',
                'expired_token_' . time(),
                str_repeat('a', 40), // Wrong length
                $validToken . 'tampered', // Tampered token
            ];
            
            foreach ($invalidTokens as $invalidToken) {
                $isInvalidToken = $this->securityService->validateCsrfToken($tokenId, $invalidToken);
                $this->assertFalse($isInvalidToken, 
                    "Invalid CSRF token should be rejected for {$tokenId}: {$invalidToken}");
            }
        }
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 14: Comprehensive Security Protection
     * 
     * Test that file upload validation prevents malicious files
     */
    public function testFileUploadSecurityValidation(): void
    {
        $tempDir = sys_get_temp_dir();
        
        // Test cases for file upload validation
        $testCases = [
            // Valid image files
            [
                'filename' => 'valid_image.jpg',
                'content' => 'fake_jpeg_content',
                'mime_type' => 'image/jpeg',
                'size' => 1024,
                'should_pass' => true,
                'allowed_types' => ['image/jpeg', 'image/png']
            ],
            // Executable file disguised as image
            [
                'filename' => 'malicious.php.jpg',
                'content' => '<?php system($_GET["cmd"]); ?>',
                'mime_type' => 'application/x-php',
                'size' => 100,
                'should_pass' => false,
                'allowed_types' => ['image/jpeg', 'image/png']
            ],
            // File too large
            [
                'filename' => 'large_image.jpg',
                'content' => str_repeat('x', 6 * 1024 * 1024), // 6MB
                'mime_type' => 'image/jpeg',
                'size' => 6 * 1024 * 1024,
                'should_pass' => false,
                'allowed_types' => ['image/jpeg']
            ],
            // Executable file
            [
                'filename' => 'malware.exe',
                'content' => 'MZ executable content',
                'mime_type' => 'application/x-executable',
                'size' => 1024,
                'should_pass' => false,
                'allowed_types' => ['image/jpeg']
            ],
            // Script file
            [
                'filename' => 'script.js',
                'content' => 'alert("malicious");',
                'mime_type' => 'application/javascript',
                'size' => 100,
                'should_pass' => false,
                'allowed_types' => ['image/jpeg']
            ],
        ];

        foreach ($testCases as $testCase) {
            // Create temporary file with proper permissions
            $tempFile = tempnam($tempDir, 'test_upload_');
            file_put_contents($tempFile, $testCase['content']);
            chmod($tempFile, 0644); // Set proper permissions
            
            $fileArray = [
                'tmp_name' => $tempFile,
                'name' => $testCase['filename'],
                'size' => $testCase['size'],
                'type' => $testCase['mime_type']
            ];
            
            // Test file validation
            $errors = $this->securityService->validateFileUpload(
                $fileArray, 
                $testCase['allowed_types'], 
                5 * 1024 * 1024 // 5MB max
            );
            
            if ($testCase['should_pass']) {
                // Property: Valid files should pass validation
                $this->assertEmpty($errors, 
                    "Valid file should pass validation: {$testCase['filename']} - Errors: " . implode(', ', $errors));
            } else {
                // Property: Invalid/malicious files should be rejected
                $this->assertNotEmpty($errors, 
                    "Invalid file should be rejected: {$testCase['filename']}");
            }
            
            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 14: Comprehensive Security Protection
     * 
     * Test that suspicious activity detection works for various attack patterns
     */
    public function testSuspiciousActivityDetection(): void
    {
        $suspiciousInputs = [
            // SQL Injection attempts
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "UNION SELECT * FROM users",
            "1' AND 1=1 --",
            
            // Path traversal attempts
            "../../../etc/passwd",
            "..\\..\\windows\\system32\\config\\sam",
            "/proc/self/environ",
            
            // Command injection attempts
            "; cat /etc/passwd",
            "| whoami",
            "`id`",
            "$(uname -a)",
            
            // XSS attempts (additional to previous test)
            "<script>document.cookie</script>",
            "<img src=x onerror=alert(1)>",
            "javascript:void(0)",
        ];

        foreach ($suspiciousInputs as $input) {
            // Property: Suspicious content must be detected
            $isSuspicious = $this->securityService->containsSuspiciousContent($input);
            $this->assertTrue($isSuspicious, "Suspicious input should be detected: {$input}");
            
            // Property: Suspicious activity detection should trigger monitoring
            $isDetected = $this->securityService->detectSuspiciousActivity($input, 'test_context');
            $this->assertTrue($isDetected, "Suspicious activity should be detected: {$input}");
        }
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 14: Comprehensive Security Protection
     * 
     * Test that rate limiting prevents abuse
     */
    public function testRateLimitingPreventsAbuse(): void
    {
        $testIP = '192.168.1.100';
        $action = 'test_action';
        $maxAttempts = 5;
        $timeWindow = 60; // 1 minute
        
        // Property: Initial requests should not be rate limited
        for ($i = 0; $i < $maxAttempts; $i++) {
            $isLimited = $this->securityService->isRateLimited($testIP, $action, $maxAttempts, $timeWindow);
            $this->assertFalse($isLimited, "Request {$i} should not be rate limited");
        }
        
        // Property: Requests exceeding limit should be rate limited
        $isLimited = $this->securityService->isRateLimited($testIP, $action, $maxAttempts, $timeWindow);
        $this->assertTrue($isLimited, "Request exceeding limit should be rate limited");
        
        // Property: Rate limit can be cleared
        $this->securityService->clearRateLimit($testIP, $action);
        $isLimitedAfterClear = $this->securityService->isRateLimited($testIP, $action, $maxAttempts, $timeWindow);
        $this->assertFalse($isLimitedAfterClear, "Rate limit should be cleared");
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 14: Comprehensive Security Protection
     * 
     * Test that input sanitization preserves safe content while removing threats
     */
    public function testInputSanitizationPreservesSafeContent(): void
    {
        $testCases = [
            // Safe content should be preserved
            [
                'input' => 'Hello, this is a normal message.',
                'expected_preserved' => true,
                'allow_html' => false
            ],
            [
                'input' => 'User email: user@example.com',
                'expected_preserved' => true,
                'allow_html' => false
            ],
            [
                'input' => 'Meeting at 2:00 PM on 2024-01-15',
                'expected_preserved' => true,
                'allow_html' => false
            ],
            // Safe HTML should be preserved when allowed (but will be entity-encoded)
            [
                'input' => '<p>This is a paragraph</p>',
                'expected_preserved' => true,
                'allow_html' => true
            ],
            [
                'input' => '<strong>Important</strong> message',
                'expected_preserved' => true,
                'allow_html' => true
            ],
            // Unsafe content should be sanitized
            [
                'input' => '<script>alert("xss")</script>Normal text',
                'expected_preserved' => false, // Script should be removed
                'allow_html' => true
            ],
        ];

        foreach ($testCases as $testCase) {
            $sanitized = $this->securityService->sanitizeInput($testCase['input'], $testCase['allow_html']);
            
            if ($testCase['expected_preserved']) {
                // Property: Safe content should be preserved (text content)
                $expectedText = strip_tags($testCase['input']);
                $this->assertStringContainsString($expectedText, html_entity_decode($sanitized), 
                    "Safe text content should be preserved: {$testCase['input']}");
            }
            
            // Property: Sanitized content should never contain dangerous patterns
            $this->assertStringNotContainsString('<script', strtolower($sanitized), 
                "Sanitized content should not contain script tags");
            $this->assertStringNotContainsString('javascript:', strtolower($sanitized), 
                "Sanitized content should not contain javascript protocol");
        }
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 14: Comprehensive Security Protection
     * 
     * Test that secure filename generation prevents directory traversal and injection
     */
    public function testSecureFilenameGeneration(): void
    {
        $dangerousFilenames = [
            '../../../etc/passwd',
            '..\\..\\windows\\system32\\config\\sam',
            'file.php.jpg',
            'script.js',
            'malware.exe',
            'file with spaces.txt',
            'file;with;semicolons.txt',
            'file|with|pipes.txt',
            'file`with`backticks.txt',
            'file$with$dollars.txt',
            'file(with)parentheses.txt',
            'file{with}braces.txt',
            'file[with]brackets.txt',
            'file<with>angles.txt',
            'file"with"quotes.txt',
            "file'with'apostrophes.txt",
            'file&with&ampersands.txt',
            'file%with%percents.txt',
            'file#with#hashes.txt',
            'file@with@ats.txt',
            'file!with!exclamations.txt',
            'file+with+plus.txt',
            'file=with=equals.txt',
            'file?with?questions.txt',
            'file:with:colons.txt',
            'file/with/slashes.txt',
            'file\\with\\backslashes.txt',
        ];

        foreach ($dangerousFilenames as $filename) {
            $secureFilename = $this->securityService->generateSecureFilename($filename);
            
            // Property: Secure filename should not contain directory traversal patterns
            $this->assertStringNotContainsString('..', $secureFilename, 
                "Secure filename should not contain directory traversal: {$filename}");
            $this->assertStringNotContainsString('/', $secureFilename, 
                "Secure filename should not contain forward slashes: {$filename}");
            $this->assertStringNotContainsString('\\', $secureFilename, 
                "Secure filename should not contain backslashes: {$filename}");
            
            // Property: Secure filename should not contain dangerous characters
            $dangerousChars = [';', '|', '`', '$', '(', ')', '{', '}', '[', ']', '<', '>', '"', "'", '&', '%', '#', '@', '!', '+', '=', '?', ':'];
            foreach ($dangerousChars as $char) {
                $this->assertStringNotContainsString($char, $secureFilename, 
                    "Secure filename should not contain dangerous character '{$char}': {$filename}");
            }
            
            // Property: Secure filename should have reasonable length
            $this->assertLessThanOrEqual(100, strlen($secureFilename), 
                "Secure filename should have reasonable length: {$filename}");
            
            // Property: Secure filename should not be empty
            $this->assertNotEmpty($secureFilename, 
                "Secure filename should not be empty: {$filename}");
        }
    }
}