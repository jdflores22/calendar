<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use App\Service\TimezoneService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ApiValidationService
{
    public function __construct(
        private ValidatorInterface $validator,
        private TimezoneService $timezoneService
    ) {}

    /**
     * Validate pagination parameters
     */
    public function validatePaginationParams(Request $request): array
    {
        $errors = [];
        
        $page = $request->query->get('page', 1);
        $limit = $request->query->get('limit', 20);

        // Validate page
        if (!is_numeric($page) || $page < 1) {
            $errors[] = [
                'field' => 'page',
                'message' => 'Page must be a positive integer'
            ];
        }

        // Validate limit
        if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
            $errors[] = [
                'field' => 'limit',
                'message' => 'Limit must be between 1 and 100'
            ];
        }

        return $errors;
    }

    /**
     * Validate date range parameters
     */
    public function validateDateRange(?string $startDate, ?string $endDate): array
    {
        $errors = [];

        if ($startDate) {
            try {
                $start = $this->timezoneService->convertToUtc($startDate);
            } catch (\Exception $e) {
                $errors[] = [
                    'field' => 'start_date',
                    'message' => 'Invalid start date format. Use ISO 8601 format (Y-m-d\TH:i:s)'
                ];
            }
        }

        if ($endDate) {
            try {
                $end = $this->timezoneService->convertToUtc($endDate);
            } catch (\Exception $e) {
                $errors[] = [
                    'field' => 'end_date',
                    'message' => 'Invalid end date format. Use ISO 8601 format (Y-m-d\TH:i:s)'
                ];
            }
        }

        if (isset($start) && isset($end) && $start >= $end) {
            $errors[] = [
                'field' => 'date_range',
                'message' => 'End date must be after start date'
            ];
        }

        return $errors;
    }

    /**
     * Validate search query parameters
     */
    public function validateSearchParams(Request $request): array
    {
        $errors = [];
        
        $search = $request->query->get('search');
        if ($search && strlen($search) < 2) {
            $errors[] = [
                'field' => 'search',
                'message' => 'Search query must be at least 2 characters long'
            ];
        }

        if ($search && strlen($search) > 255) {
            $errors[] = [
                'field' => 'search',
                'message' => 'Search query must not exceed 255 characters'
            ];
        }

        return $errors;
    }

    /**
     * Validate email format
     */
    public function validateEmail(string $email): array
    {
        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(['message' => 'Email is required']),
            new Assert\Email(['message' => 'Invalid email format']),
            new Assert\Length([
                'max' => 255,
                'maxMessage' => 'Email must not exceed 255 characters'
            ])
        ]);

        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => 'email',
                'message' => $violation->getMessage()
            ];
        }

        return $errors;
    }

    /**
     * Validate password strength
     */
    public function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = [
                'field' => 'password',
                'message' => 'Password must be at least 8 characters long'
            ];
        }

        if (strlen($password) > 128) {
            $errors[] = [
                'field' => 'password',
                'message' => 'Password must not exceed 128 characters'
            ];
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = [
                'field' => 'password',
                'message' => 'Password must contain at least one uppercase letter'
            ];
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = [
                'field' => 'password',
                'message' => 'Password must contain at least one lowercase letter'
            ];
        }

        // Check for at least one digit
        if (!preg_match('/\d/', $password)) {
            $errors[] = [
                'field' => 'password',
                'message' => 'Password must contain at least one digit'
            ];
        }

        return $errors;
    }

    /**
     * Validate color format (hex color)
     */
    public function validateColor(string $color): array
    {
        $errors = [];

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $errors[] = [
                'field' => 'color',
                'message' => 'Color must be in hex format (#RRGGBB)'
            ];
        }

        return $errors;
    }

    /**
     * Validate priority value
     */
    public function validatePriority(string $priority): array
    {
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        
        if (!in_array($priority, $validPriorities)) {
            return [[
                'field' => 'priority',
                'message' => 'Priority must be one of: ' . implode(', ', $validPriorities)
            ]];
        }

        return [];
    }

    /**
     * Validate status value
     */
    public function validateStatus(string $status): array
    {
        $validStatuses = ['draft', 'confirmed', 'cancelled', 'completed'];
        
        if (!in_array($status, $validStatuses)) {
            return [[
                'field' => 'status',
                'message' => 'Status must be one of: ' . implode(', ', $validStatuses)
            ]];
        }

        return [];
    }

    /**
     * Validate role value
     */
    public function validateRole(string $role): array
    {
        $validRoles = ['ROLE_USER', 'ROLE_PROVINCE', 'ROLE_DIVISION', 'ROLE_EO', 'ROLE_OSEC', 'ROLE_ADMIN'];
        
        if (!in_array($role, $validRoles)) {
            return [[
                'field' => 'role',
                'message' => 'Role must be one of: ' . implode(', ', $validRoles)
            ]];
        }

        return [];
    }

    /**
     * Validate required fields
     */
    public function validateRequiredFields(array $data, array $requiredFields): array
    {
        $errors = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $errors[] = [
                    'field' => $field,
                    'message' => "Field '{$field}' is required"
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate string length
     */
    public function validateStringLength(string $value, string $fieldName, int $minLength = 0, int $maxLength = 255): array
    {
        $errors = [];
        $length = strlen($value);

        if ($length < $minLength) {
            $errors[] = [
                'field' => $fieldName,
                'message' => "{$fieldName} must be at least {$minLength} characters long"
            ];
        }

        if ($length > $maxLength) {
            $errors[] = [
                'field' => $fieldName,
                'message' => "{$fieldName} must not exceed {$maxLength} characters"
            ];
        }

        return $errors;
    }

    /**
     * Validate numeric range
     */
    public function validateNumericRange($value, string $fieldName, $min = null, $max = null): array
    {
        $errors = [];

        if (!is_numeric($value)) {
            $errors[] = [
                'field' => $fieldName,
                'message' => "{$fieldName} must be a number"
            ];
            return $errors;
        }

        $numValue = (float) $value;

        if ($min !== null && $numValue < $min) {
            $errors[] = [
                'field' => $fieldName,
                'message' => "{$fieldName} must be at least {$min}"
            ];
        }

        if ($max !== null && $numValue > $max) {
            $errors[] = [
                'field' => $fieldName,
                'message' => "{$fieldName} must not exceed {$max}"
            ];
        }

        return $errors;
    }

    /**
     * Combine multiple validation error arrays
     */
    public function combineErrors(array ...$errorArrays): array
    {
        return array_merge(...$errorArrays);
    }
}