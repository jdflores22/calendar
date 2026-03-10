<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\UserRepository;
use App\Repository\OfficeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users')]
#[IsGranted('ROLE_USER')]
class ApiUserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private OfficeRepository $officeRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('', name: 'api_users_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $offset = ($page - 1) * $limit;

        $search = $request->query->get('search');
        $officeId = $request->query->get('office_id');
        $role = $request->query->get('role');
        $isVerified = $request->query->get('is_verified');

        $criteria = [];
        $orderBy = ['id' => 'ASC'];

        if ($officeId) {
            $criteria['office'] = $officeId;
        }

        if ($isVerified !== null) {
            $criteria['isVerified'] = filter_var($isVerified, FILTER_VALIDATE_BOOLEAN);
        }

        if ($search || $role) {
            // Use custom repository method for complex queries
            $users = $this->userRepository->findBySearchCriteria($search, $role, $officeId, $isVerified);
            $total = count($users);
            $users = array_slice($users, $offset, $limit);
        } else {
            $users = $this->userRepository->findBy($criteria, $orderBy, $limit, $offset);
            $total = $this->userRepository->count($criteria);
        }

        $data = [];
        foreach ($users as $user) {
            $data[] = $this->formatUserForApi($user);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/me', name: 'api_users_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatUserForApi($user, true)
        ]);
    }

    #[Route('/{id}', name: 'api_users_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(User $user): JsonResponse
    {
        // Users can view their own profile, admins can view any profile
        if ($user !== $this->getUser()) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatUserForApi($user, $user === $this->getUser())
        ]);
    }

    #[Route('', name: 'api_users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data',
                'error_code' => 'INVALID_JSON'
            ], 400);
        }

        $user = new User();
        $result = $this->populateUserFromData($user, $data, true);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatUserForApi($user),
            'message' => 'User created successfully'
        ], 201);
    }

    #[Route('/{id}', name: 'api_users_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, User $user): JsonResponse
    {
        // Users can update their own profile, admins can update any profile
        if ($user !== $this->getUser()) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data',
                'error_code' => 'INVALID_JSON'
            ], 400);
        }

        $result = $this->populateUserFromData($user, $data, false);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatUserForApi($user),
            'message' => 'User updated successfully'
        ]);
    }

    #[Route('/{id}', name: 'api_users_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(User $user): JsonResponse
    {
        // Prevent deletion of the current user
        if ($user === $this->getUser()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cannot delete your own account',
                'error_code' => 'CANNOT_DELETE_SELF'
            ], 400);
        }

        try {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting user',
                'error_code' => 'DELETE_FAILED'
            ], 500);
        }
    }

    #[Route('/{id}/profile', name: 'api_users_update_profile', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateProfile(Request $request, User $user): JsonResponse
    {
        // Users can update their own profile, admins can update any profile
        if ($user !== $this->getUser()) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data',
                'error_code' => 'INVALID_JSON'
            ], 400);
        }

        if (!$user->getProfile()) {
            $profile = new UserProfile();
            $user->setProfile($profile);
            $this->entityManager->persist($profile);
        }

        $profile = $user->getProfile();
        $result = $this->populateProfileFromData($profile, $data);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        // Update office if provided
        if (isset($data['office_id'])) {
            $office = $this->officeRepository->find($data['office_id']);
            if (!$office) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Office not found',
                    'error_code' => 'OFFICE_NOT_FOUND'
                ], 400);
            }
            $user->setOffice($office);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatUserForApi($user, $user === $this->getUser()),
            'message' => 'Profile updated successfully'
        ]);
    }

    private function populateUserFromData(User $user, array $data, bool $isNew): array
    {
        try {
            // Validate required fields for new users
            if ($isNew) {
                $requiredFields = ['email', 'password'];
                foreach ($requiredFields as $field) {
                    if (!isset($data[$field]) || empty($data[$field])) {
                        return [
                            'success' => false,
                            'message' => "Field '{$field}' is required",
                            'error_code' => 'MISSING_REQUIRED_FIELD'
                        ];
                    }
                }

                // Check if email already exists
                $existingUser = $this->userRepository->findByEmail($data['email']);
                if ($existingUser) {
                    return [
                        'success' => false,
                        'message' => 'Email already exists',
                        'error_code' => 'EMAIL_EXISTS'
                    ];
                }

                $user->setEmail($data['email']);
                $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            }

            // Update email for existing users (admin only)
            if (!$isNew && isset($data['email']) && $this->isGranted('ROLE_ADMIN')) {
                $existingUser = $this->userRepository->findByEmail($data['email']);
                if ($existingUser && $existingUser !== $user) {
                    return [
                        'success' => false,
                        'message' => 'Email already exists',
                        'error_code' => 'EMAIL_EXISTS'
                    ];
                }
                $user->setEmail($data['email']);
            }

            // Update password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                if (strlen($data['password']) < 8) {
                    return [
                        'success' => false,
                        'message' => 'Password must be at least 8 characters long',
                        'error_code' => 'PASSWORD_TOO_SHORT'
                    ];
                }
                $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            }

            // Update roles (admin only)
            if (isset($data['roles']) && $this->isGranted('ROLE_ADMIN')) {
                $validRoles = ['ROLE_USER', 'ROLE_PROVINCE', 'ROLE_DIVISION', 'ROLE_EO', 'ROLE_OSEC', 'ROLE_ADMIN'];
                $roles = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
                
                foreach ($roles as $role) {
                    if (!in_array($role, $validRoles)) {
                        return [
                            'success' => false,
                            'message' => "Invalid role: {$role}",
                            'error_code' => 'INVALID_ROLE'
                        ];
                    }
                }
                
                $user->setRoles($roles);
            }

            // Update verification status (admin only)
            if (isset($data['is_verified']) && $this->isGranted('ROLE_ADMIN')) {
                $user->setVerified(filter_var($data['is_verified'], FILTER_VALIDATE_BOOLEAN));
            }

            // Update office
            if (isset($data['office_id'])) {
                $office = $this->officeRepository->find($data['office_id']);
                if (!$office) {
                    return [
                        'success' => false,
                        'message' => 'Office not found',
                        'error_code' => 'OFFICE_NOT_FOUND'
                    ];
                }
                $user->setOffice($office);
            }

            // Handle profile data
            if (isset($data['profile'])) {
                if (!$user->getProfile()) {
                    $profile = new UserProfile();
                    $user->setProfile($profile);
                    $this->entityManager->persist($profile);
                }

                $profileResult = $this->populateProfileFromData($user->getProfile(), $data['profile']);
                if (!$profileResult['success']) {
                    return $profileResult;
                }
            }

            // Validate the user
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error_code' => 'VALIDATION_FAILED',
                    'errors' => $this->formatValidationErrors($errors)
                ];
            }

            // Save the user
            if ($isNew) {
                $this->entityManager->persist($user);
            }
            $this->entityManager->flush();

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Internal server error',
                'error_code' => 'INTERNAL_ERROR'
            ];
        }
    }

    private function populateProfileFromData(UserProfile $profile, array $data): array
    {
        if (isset($data['first_name'])) {
            $profile->setFirstName($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $profile->setLastName($data['last_name']);
        }

        if (isset($data['middle_name'])) {
            $profile->setMiddleName($data['middle_name']);
        }

        if (isset($data['phone'])) {
            $profile->setPhone($data['phone']);
        }

        if (isset($data['address'])) {
            $profile->setAddress($data['address']);
        }

        // Update completion status
        $profile->checkCompletionStatus();

        // Validate the profile
        $errors = $this->validator->validate($profile);
        if (count($errors) > 0) {
            return [
                'success' => false,
                'message' => 'Profile validation failed',
                'error_code' => 'PROFILE_VALIDATION_FAILED',
                'errors' => $this->formatValidationErrors($errors)
            ];
        }

        return ['success' => true];
    }

    private function formatUserForApi(User $user, bool $includePrivateData = false): array
    {
        $data = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'is_verified' => $user->isVerified(),
            'last_login' => $user->getLastLogin()?->format('c'),
            'created_at' => $user->getCreatedAt()?->format('c'),
            'office' => $user->getOffice() ? [
                'id' => $user->getOffice()->getId(),
                'name' => $user->getOffice()->getName(),
                'code' => $user->getOffice()->getCode(),
                'color' => $user->getOffice()->getColor()
            ] : null,
            'profile' => $user->getProfile() ? [
                'first_name' => $user->getProfile()->getFirstName(),
                'last_name' => $user->getProfile()->getLastName(),
                'middle_name' => $user->getProfile()->getMiddleName(),
                'full_name' => $user->getProfile()->getFullName(),
                'is_complete' => $user->getProfile()->isComplete(),
                'avatar' => $user->getProfile()->getAvatar()
            ] : null
        ];

        // Include private data for the user themselves or admins
        if ($includePrivateData) {
            if ($user->getProfile()) {
                $data['profile']['phone'] = $user->getProfile()->getPhone();
                $data['profile']['address'] = $user->getProfile()->getAddress();
            }
        }

        return $data;
    }

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
}