<?php

namespace App\Controller\Api;

use App\Service\ApiAuthenticationService;
use App\Service\ApiValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseApiController extends AbstractController
{
    public function __construct(
        protected ApiAuthenticationService $apiAuth,
        protected ApiValidationService $apiValidation
    ) {}

    /**
     * Create a successful API response
     */
    protected function successResponse($data = null, string $message = null, int $status = 200): JsonResponse
    {
        $response = ['success' => true];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        return new JsonResponse($response, $status);
    }

    /**
     * Create an error API response
     */
    protected function errorResponse(string $message, string $errorCode = null, $details = null, int $status = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        return new JsonResponse($response, $status);
    }

    /**
     * Create a paginated response
     */
    protected function paginatedResponse(array $data, int $page, int $limit, int $total, string $message = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        return new JsonResponse($response);
    }

    /**
     * Create a validation error response
     */
    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 'VALIDATION_FAILED', ['errors' => $errors], 400);
    }

    /**
     * Format validation errors for API response
     */
    protected function formatValidationErrors($errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $formattedErrors[] = [
                'field' => $error->getPropertyPath(),
                'message' => $error->getMessage()
            ];
        }
        return $formattedErrors;
    }

    /**
     * Parse and validate JSON request data
     */
    protected function getJsonData(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON data: ' . json_last_error_msg());
            }
            
            return $this->apiAuth->sanitizeApiInput($data ?? []);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        $errors = $this->apiValidation->validatePaginationParams($request);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid pagination parameters');
        }

        return [
            'page' => max(1, $request->query->getInt('page', 1)),
            'limit' => min(100, max(1, $request->query->getInt('limit', 20)))
        ];
    }

    /**
     * Calculate offset from page and limit
     */
    protected function calculateOffset(int $page, int $limit): int
    {
        return ($page - 1) * $limit;
    }

    /**
     * Validate API request format
     */
    protected function validateApiRequest(Request $request): array
    {
        return $this->apiAuth->validateApiRequest($request);
    }

    /**
     * Handle API request validation and return error response if invalid
     */
    protected function handleRequestValidation(Request $request): ?JsonResponse
    {
        $errors = $this->validateApiRequest($request);
        
        if (!empty($errors)) {
            return $this->validationErrorResponse($errors, 'Invalid request format');
        }

        return null;
    }

    /**
     * Log API access for audit purposes
     */
    protected function logApiAccess(Request $request, string $action, bool $success = true): void
    {
        $this->apiAuth->logApiAccess($request, $this->getUser(), $action, $success);
    }
}