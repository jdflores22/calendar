<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiAuthenticationService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /**
     * Authenticate API request using Bearer token or session
     */
    public function authenticateRequest(Request $request): ?User
    {
        // Try Bearer token authentication first
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $this->authenticateByToken($token);
        }

        // Try API key authentication
        $apiKey = $request->headers->get('X-API-Key') ?? $request->query->get('api_key');
        if ($apiKey) {
            return $this->authenticateByApiKey($apiKey);
        }

        // For now, we'll rely on Symfony's session-based authentication
        // In a production API, you would implement proper token-based auth
        return null;
    }

    /**
     * Authenticate using Bearer token (placeholder for JWT implementation)
     */
    private function authenticateByToken(string $token): ?User
    {
        // This is a placeholder for JWT token validation
        // In a real implementation, you would:
        // 1. Validate the JWT token
        // 2. Extract user information from the token
        // 3. Return the authenticated user
        
        // For now, return null to fall back to session authentication
        return null;
    }

    /**
     * Authenticate using API key
     */
    private function authenticateByApiKey(string $apiKey): ?User
    {
        // This is a placeholder for API key authentication
        // In a real implementation, you would:
        // 1. Look up the API key in the database
        // 2. Validate the key is active and not expired
        // 3. Return the associated user
        
        // For now, return null to fall back to session authentication
        return null;
    }

    /**
     * Generate API response for authentication errors
     */
    public function createAuthenticationErrorResponse(string $message = 'Authentication required'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => 'AUTHENTICATION_REQUIRED'
        ];
    }

    /**
     * Generate API response for authorization errors
     */
    public function createAuthorizationErrorResponse(string $message = 'Insufficient permissions'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_code' => 'INSUFFICIENT_PERMISSIONS'
        ];
    }

    /**
     * Validate API request format and content type
     */
    public function validateApiRequest(Request $request): array
    {
        $errors = [];

        // Check content type for POST/PUT requests
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->headers->get('Content-Type');
            if (!$contentType || !str_contains($contentType, 'application/json')) {
                $errors[] = 'Content-Type must be application/json for ' . $request->getMethod() . ' requests';
            }
        }

        // Validate JSON content for requests with body
        if ($request->getContent() && in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            json_decode($request->getContent());
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON format: ' . json_last_error_msg();
            }
        }

        return $errors;
    }

    /**
     * Sanitize and validate API input data
     */
    public function sanitizeApiInput(array $data): array
    {
        return $this->recursiveSanitize($data);
    }

    /**
     * Recursively sanitize input data
     */
    private function recursiveSanitize($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'recursiveSanitize'], $data);
        }

        if (is_string($data)) {
            // Remove potential XSS vectors
            $data = strip_tags($data);
            // Trim whitespace
            $data = trim($data);
            // Convert special characters to HTML entities
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $data;
    }

    /**
     * Log API access for audit purposes
     */
    public function logApiAccess(Request $request, ?User $user, string $action, bool $success = true): void
    {
        // This would typically log to a dedicated API access log
        // For now, we'll use error_log as a placeholder
        
        $logData = [
            'timestamp' => date('c'),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'user_id' => $user?->getId(),
            'user_email' => $user?->getEmail(),
            'action' => $action,
            'success' => $success
        ];

        error_log('API_ACCESS: ' . json_encode($logData));
    }
}