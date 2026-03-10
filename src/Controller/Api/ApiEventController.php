<?php

namespace App\Controller\Api;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\OfficeRepository;
use App\Service\ConflictResolverService;
use App\Service\RecurrenceService;
use App\Service\ApiAuthenticationService;
use App\Service\ApiValidationService;
use App\Service\TimezoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/events')]
#[IsGranted('ROLE_USER')]
class ApiEventController extends BaseApiController
{
    public function __construct(
        ApiAuthenticationService $apiAuth,
        ApiValidationService $apiValidation,
        private EventRepository $eventRepository,
        private OfficeRepository $officeRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ConflictResolverService $conflictResolver,
        private RecurrenceService $recurrenceService,
        private TimezoneService $timezoneService
    ) {
        parent::__construct($apiAuth, $apiValidation);
    }

    #[Route('', name: 'api_events_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Validate request format
        if ($errorResponse = $this->handleRequestValidation($request)) {
            return $errorResponse;
        }

        // Validate pagination and search parameters
        $paginationErrors = $this->apiValidation->validatePaginationParams($request);
        $searchErrors = $this->apiValidation->validateSearchParams($request);
        
        $allErrors = $this->apiValidation->combineErrors($paginationErrors, $searchErrors);
        if (!empty($allErrors)) {
            return $this->validationErrorResponse($allErrors);
        }

        try {
            $pagination = $this->getPaginationParams($request);
            $page = $pagination['page'];
            $limit = $pagination['limit'];
            $offset = $this->calculateOffset($page, $limit);

            $start = $request->query->get('start');
            $end = $request->query->get('end');
            $officeId = $request->query->get('office_id');
            $creatorId = $request->query->get('creator_id');
            $search = $request->query->get('search');

            // Validate date range if provided
            if ($start || $end) {
                $dateErrors = $this->apiValidation->validateDateRange($start, $end);
                if (!empty($dateErrors)) {
                    return $this->validationErrorResponse($dateErrors);
                }
            }

            $criteria = [];
            
            if ($start) {
                $criteria['start_date'] = $start;
            }
            
            if ($end) {
                $criteria['end_date'] = $end;
            }
            
            if ($officeId) {
                $criteria['office_ids'] = [$officeId];
            }
            
            if ($creatorId) {
                $criteria['creator_id'] = $creatorId;
            }
            
            if ($search) {
                $criteria['query'] = $search;
            }

            if (!empty($criteria)) {
                $events = $this->eventRepository->searchEventsAdvanced($criteria);
                $total = count($events);
                $events = array_slice($events, $offset, $limit);
            } else {
                $events = $this->eventRepository->findBy([], ['startTime' => 'ASC'], $limit, $offset);
                $total = $this->eventRepository->count([]);
            }

            $data = [];
            foreach ($events as $event) {
                $data[] = $this->formatEventForApi($event);
            }

            $this->logApiAccess($request, 'list_events');

            return $this->paginatedResponse($data, $page, $limit, $total);

        } catch (\Exception $e) {
            $this->logApiAccess($request, 'list_events', false);
            return $this->errorResponse('Internal server error', 'INTERNAL_ERROR', null, 500);
        }
    }

    #[Route('/{id}', name: 'api_events_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $this->formatEventForApi($event)
        ]);
    }

    #[Route('', name: 'api_events_create', methods: ['POST'])]
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

        $event = new Event();
        $result = $this->populateEventFromData($event, $data, true);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatEventForApi($event),
            'message' => 'Event created successfully'
        ], 201);
    }

    #[Route('/{id}', name: 'api_events_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Event $event): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $event);

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data',
                'error_code' => 'INVALID_JSON'
            ], 400);
        }

        $result = $this->populateEventFromData($event, $data, false);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatEventForApi($event),
            'message' => 'Event updated successfully'
        ]);
    }

    #[Route('/{id}', name: 'api_events_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Event $event): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $event);

        try {
            $this->entityManager->remove($event);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting event',
                'error_code' => 'DELETE_FAILED'
            ], 500);
        }
    }

    #[Route('/{id}/conflicts', name: 'api_events_conflicts', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getConflicts(Event $event): JsonResponse
    {
        $conflicts = $this->conflictResolver->checkConflicts(
            $event->getStartTime(),
            $event->getEndTime(),
            $event
        );

        $conflictData = [];
        foreach ($conflicts as $conflict) {
            $conflictData[] = $this->formatEventForApi($conflict);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'event_id' => $event->getId(),
                'has_conflicts' => !empty($conflicts),
                'conflict_count' => count($conflicts),
                'conflicts' => $conflictData,
                'can_override' => $this->conflictResolver->canUserOverrideConflicts($this->getUser())
            ]
        ]);
    }

    private function populateEventFromData(Event $event, array $data, bool $isNew): array
    {
        try {
            // Validate required fields
            $requiredFields = ['title', 'start_time', 'end_time'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required",
                        'error_code' => 'MISSING_REQUIRED_FIELD'
                    ];
                }
            }

            // Set basic fields
            $event->setTitle($data['title']);
            $event->setDescription($data['description'] ?? null);
            $event->setLocation($data['location'] ?? null);
            $event->setColor($data['color'] ?? '#007BFF');
            $event->setPriority($data['priority'] ?? 'normal');
            $event->setStatus($data['status'] ?? 'confirmed');
            $event->setAllDay($data['all_day'] ?? false);

            // Handle dates - convert from Philippines timezone to UTC for database storage
            try {
                $event->setStartTime($this->timezoneService->convertToUtc($data['start_time']));
                $event->setEndTime($this->timezoneService->convertToUtc($data['end_time']));
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Invalid date format. Use ISO 8601 format (Y-m-d\TH:i:s)',
                    'error_code' => 'INVALID_DATE_FORMAT'
                ];
            }

            // Validate date logic
            if ($event->getStartTime() >= $event->getEndTime()) {
                return [
                    'success' => false,
                    'message' => 'End time must be after start time',
                    'error_code' => 'INVALID_DATE_RANGE'
                ];
            }

            // Handle recurring events
            $isRecurring = isset($data['is_recurring']) && $data['is_recurring'];
            $event->setRecurring($isRecurring);

            if ($isRecurring && isset($data['recurrence_pattern'])) {
                if (!$this->recurrenceService->validateRecurrencePattern($data['recurrence_pattern'])) {
                    return [
                        'success' => false,
                        'message' => 'Invalid recurrence pattern',
                        'error_code' => 'INVALID_RECURRENCE_PATTERN'
                    ];
                }
                $event->setRecurrencePattern($data['recurrence_pattern']);
            } else {
                $event->setRecurrencePattern(null);
            }

            // Set office
            if (isset($data['office_id'])) {
                $office = $this->officeRepository->find($data['office_id']);
                if (!$office) {
                    return [
                        'success' => false,
                        'message' => 'Office not found',
                        'error_code' => 'OFFICE_NOT_FOUND'
                    ];
                }
                $event->setOffice($office);
            }

            // Set creator for new events
            if ($isNew) {
                $event->setCreator($this->getUser());
            }

            // Check for conflicts
            $resolution = $this->conflictResolver->resolveConflict($event, $this->getUser());

            if ($resolution->isBlocked()) {
                return [
                    'success' => false,
                    'message' => $resolution->getMessage(),
                    'error_code' => 'SCHEDULING_CONFLICT',
                    'conflicts' => $this->conflictResolver->getConflictDetails($resolution->getConflicts())
                ];
            }

            // Validate the event
            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'error_code' => 'VALIDATION_FAILED',
                    'errors' => $this->formatValidationErrors($errors)
                ];
            }

            // Save the event
            if ($isNew) {
                $this->entityManager->persist($event);
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

    private function formatEventForApi(Event $event): array
    {
        $data = [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'location' => $event->getLocation(),
            'start_time' => $event->getStartTime()->format('c'),
            'end_time' => $event->getEndTime()->format('c'),
            'color' => $event->getEffectiveColor(),
            'priority' => $event->getPriority(),
            'status' => $event->getStatus(),
            'all_day' => $event->isAllDay(),
            'is_recurring' => $event->isRecurring(),
            'created_at' => $event->getCreatedAt()->format('c'),
            'updated_at' => $event->getUpdatedAt()->format('c'),
            'creator' => $event->getCreator() ? [
                'id' => $event->getCreator()->getId(),
                'email' => $event->getCreator()->getEmail(),
                'name' => $event->getCreator()->getProfile() ? 
                    $event->getCreator()->getProfile()->getFullName() : 
                    $event->getCreator()->getEmail()
            ] : null,
            'office' => $event->getOffice() ? [
                'id' => $event->getOffice()->getId(),
                'name' => $event->getOffice()->getName(),
                'code' => $event->getOffice()->getCode(),
                'color' => $event->getOffice()->getColor()
            ] : null,
            'tags' => $event->getTagNames()
        ];

        if ($event->isRecurring() && $event->getRecurrencePattern()) {
            $data['recurrence_pattern'] = $event->getRecurrencePattern();
            $data['recurrence_description'] = $this->recurrenceService->getPatternDescription($event->getRecurrencePattern());
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