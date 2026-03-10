<?php

namespace App\Controller\Api;

use App\Entity\Office;
use App\Repository\OfficeRepository;
use App\Repository\DirectoryContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/offices')]
#[IsGranted('ROLE_USER')]
class ApiOfficeController extends AbstractController
{
    public function __construct(
        private OfficeRepository $officeRepository,
        private DirectoryContactRepository $contactRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_offices_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));
        $offset = ($page - 1) * $limit;

        $search = $request->query->get('search');
        $parentId = $request->query->get('parent_id');
        $includeChildren = $request->query->getBoolean('include_children', false);
        $includeStats = $request->query->getBoolean('include_stats', false);

        $criteria = [];
        $orderBy = ['name' => 'ASC'];

        if ($parentId !== null) {
            if ($parentId === '0' || $parentId === 'null') {
                $criteria['parent'] = null;
            } else {
                $parent = $this->officeRepository->find($parentId);
                if ($parent) {
                    $criteria['parent'] = $parent;
                }
            }
        }

        if ($search) {
            $offices = $this->officeRepository->searchOffices($search);
            $total = count($offices);
            $offices = array_slice($offices, $offset, $limit);
        } else {
            $offices = $this->officeRepository->findBy($criteria, $orderBy, $limit, $offset);
            $total = $this->officeRepository->count($criteria);
        }

        $data = [];
        foreach ($offices as $office) {
            $data[] = $this->formatOfficeForApi($office, $includeChildren, $includeStats);
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

    #[Route('/{id}', name: 'api_offices_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Office $office, Request $request): JsonResponse
    {
        $includeChildren = $request->query->getBoolean('include_children', true);
        $includeStats = $request->query->getBoolean('include_stats', true);

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatOfficeForApi($office, $includeChildren, $includeStats)
        ]);
    }

    #[Route('', name: 'api_offices_create', methods: ['POST'])]
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

        $office = new Office();
        $result = $this->populateOfficeFromData($office, $data, true);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatOfficeForApi($office),
            'message' => 'Office created successfully'
        ], 201);
    }

    #[Route('/{id}', name: 'api_offices_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request, Office $office): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data',
                'error_code' => 'INVALID_JSON'
            ], 400);
        }

        $result = $this->populateOfficeFromData($office, $data, false);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatOfficeForApi($office),
            'message' => 'Office updated successfully'
        ]);
    }

    #[Route('/{id}', name: 'api_offices_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Office $office): JsonResponse
    {
        // Check if office has contacts or child offices
        $contactCount = $this->contactRepository->countByOffice($office);
        $childOffices = $office->getChildren();

        if ($contactCount > 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cannot delete office with existing contacts',
                'error_code' => 'OFFICE_HAS_CONTACTS',
                'details' => [
                    'contact_count' => $contactCount
                ]
            ], 400);
        }

        if (count($childOffices) > 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cannot delete office with child offices',
                'error_code' => 'OFFICE_HAS_CHILDREN',
                'details' => [
                    'child_count' => count($childOffices)
                ]
            ], 400);
        }

        try {
            $this->entityManager->remove($office);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Office deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting office',
                'error_code' => 'DELETE_FAILED'
            ], 500);
        }
    }

    #[Route('/{id}/children', name: 'api_offices_children', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getChildren(Office $office, Request $request): JsonResponse
    {
        $includeStats = $request->query->getBoolean('include_stats', false);
        
        $children = $office->getChildren();
        $data = [];

        foreach ($children as $child) {
            $data[] = $this->formatOfficeForApi($child, false, $includeStats);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'total' => count($data)
        ]);
    }

    #[Route('/{id}/hierarchy', name: 'api_offices_hierarchy', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getHierarchy(Office $office): JsonResponse
    {
        $hierarchy = [];
        $current = $office;

        // Build hierarchy from current office to root
        while ($current) {
            array_unshift($hierarchy, [
                'id' => $current->getId(),
                'name' => $current->getName(),
                'code' => $current->getCode(),
                'color' => $current->getColor()
            ]);
            $current = $current->getParent();
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'office_id' => $office->getId(),
                'hierarchy' => $hierarchy,
                'depth' => count($hierarchy)
            ]
        ]);
    }

    #[Route('/colors', name: 'api_offices_colors', methods: ['GET'])]
    public function getColors(): JsonResponse
    {
        $offices = $this->officeRepository->findAll();
        $colors = [];

        foreach ($offices as $office) {
            $colors[] = [
                'office_id' => $office->getId(),
                'office_name' => $office->getName(),
                'office_code' => $office->getCode(),
                'color' => $office->getColor()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $colors
        ]);
    }

    private function populateOfficeFromData(Office $office, array $data, bool $isNew): array
    {
        try {
            // Validate required fields
            $requiredFields = ['name', 'code'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required",
                        'error_code' => 'MISSING_REQUIRED_FIELD'
                    ];
                }
            }

            // Check for unique code
            $existingOffice = $this->officeRepository->findByCode($data['code']);
            if ($existingOffice && $existingOffice !== $office) {
                return [
                    'success' => false,
                    'message' => 'Office code already exists',
                    'error_code' => 'CODE_EXISTS'
                ];
            }

            // Set basic fields
            $office->setName($data['name']);
            $office->setCode($data['code']);
            $office->setColor($data['color'] ?? '#3B82F6');
            $office->setDescription($data['description'] ?? null);

            // Validate color format
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $office->getColor())) {
                return [
                    'success' => false,
                    'message' => 'Invalid color format. Use hex format (#RRGGBB)',
                    'error_code' => 'INVALID_COLOR_FORMAT'
                ];
            }

            // Check for unique color
            $existingOfficeWithColor = $this->officeRepository->findByColor($office->getColor());
            if ($existingOfficeWithColor && $existingOfficeWithColor !== $office) {
                return [
                    'success' => false,
                    'message' => 'Color already used by another office',
                    'error_code' => 'COLOR_EXISTS'
                ];
            }

            // Set parent office
            if (isset($data['parent_id'])) {
                if ($data['parent_id'] === null || $data['parent_id'] === 0) {
                    $office->setParent(null);
                } else {
                    $parent = $this->officeRepository->find($data['parent_id']);
                    if (!$parent) {
                        return [
                            'success' => false,
                            'message' => 'Parent office not found',
                            'error_code' => 'PARENT_NOT_FOUND'
                        ];
                    }

                    // Prevent circular references
                    if ($parent === $office) {
                        return [
                            'success' => false,
                            'message' => 'Office cannot be its own parent',
                            'error_code' => 'CIRCULAR_REFERENCE'
                        ];
                    }

                    // Check if the parent would create a circular reference
                    $current = $parent;
                    while ($current) {
                        if ($current === $office) {
                            return [
                                'success' => false,
                                'message' => 'Circular reference detected in office hierarchy',
                                'error_code' => 'CIRCULAR_REFERENCE'
                            ];
                        }
                        $current = $current->getParent();
                    }

                    $office->setParent($parent);
                }
            }

            // Validate the office
            $errors = $this->validator->validate($office);
            if (count($errors) > 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error_code' => 'VALIDATION_FAILED',
                    'errors' => $this->formatValidationErrors($errors)
                ];
            }

            // Save the office
            if ($isNew) {
                $this->entityManager->persist($office);
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

    private function formatOfficeForApi(Office $office, bool $includeChildren = false, bool $includeStats = false): array
    {
        $data = [
            'id' => $office->getId(),
            'name' => $office->getName(),
            'code' => $office->getCode(),
            'color' => $office->getColor(),
            'description' => $office->getDescription(),
            'parent' => $office->getParent() ? [
                'id' => $office->getParent()->getId(),
                'name' => $office->getParent()->getName(),
                'code' => $office->getParent()->getCode()
            ] : null
        ];

        if ($includeChildren) {
            $children = [];
            foreach ($office->getChildren() as $child) {
                $children[] = [
                    'id' => $child->getId(),
                    'name' => $child->getName(),
                    'code' => $child->getCode(),
                    'color' => $child->getColor()
                ];
            }
            $data['children'] = $children;
            $data['children_count'] = count($children);
        }

        if ($includeStats) {
            $data['stats'] = [
                'user_count' => $office->getUsers()->count(),
                'event_count' => $office->getEvents()->count(),
                'contact_count' => $this->contactRepository->countByOffice($office)
            ];
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