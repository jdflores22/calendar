<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Office;
use App\Entity\EventAttachment;
use App\Repository\EventRepository;
use App\Repository\OfficeRepository;
use App\Repository\EventTagRepository;
use App\Repository\EventAttachmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\ConflictResolverService;
use App\Service\RecurrenceService;
use App\Service\SecurityService;
use App\Service\TimezoneService;

#[Route('/events')]
#[IsGranted('ROLE_USER')]
class EventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private OfficeRepository $officeRepository,
        private EventTagRepository $eventTagRepository,
        private EventAttachmentRepository $eventAttachmentRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ConflictResolverService $conflictResolver,
        private RecurrenceService $recurrenceService,
        private SecurityService $securityService,
        private TimezoneService $timezoneService,
        private string $projectDir
    ) {}

    #[Route('', name: 'app_event_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get filter parameters
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'date-desc');
        
        // Build query
        $queryBuilder = $this->eventRepository->createQueryBuilder('e')
            ->leftJoin('e.office', 'o')
            ->leftJoin('e.creator', 'c');
        
        // Apply search filter
        if (!empty($search)) {
            $queryBuilder->andWhere('e.title LIKE :search OR e.description LIKE :search OR e.location LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // Apply status filter
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        switch ($status) {
            case 'today':
                $tomorrow = clone $today;
                $tomorrow->add(new \DateInterval('P1D'));
                $queryBuilder->andWhere('e.startTime >= :today AND e.startTime < :tomorrow')
                    ->setParameter('today', $today)
                    ->setParameter('tomorrow', $tomorrow);
                break;
            case 'upcoming':
                $queryBuilder->andWhere('e.startTime >= :today')
                    ->setParameter('today', $today);
                break;
            case 'past':
                $queryBuilder->andWhere('e.startTime < :today')
                    ->setParameter('today', $today);
                break;
        }
        
        // Apply sorting
        switch ($sort) {
            case 'date-asc':
                $queryBuilder->orderBy('e.startTime', 'ASC');
                break;
            case 'date-desc':
                $queryBuilder->orderBy('e.startTime', 'DESC');
                break;
            case 'title-asc':
                $queryBuilder->orderBy('e.title', 'ASC');
                break;
            case 'title-desc':
                $queryBuilder->orderBy('e.title', 'DESC');
                break;
            case 'priority':
                $queryBuilder->addSelect('CASE 
                    WHEN e.priority = \'high\' THEN 4 
                    WHEN e.priority = \'medium\' THEN 3 
                    WHEN e.priority = \'normal\' THEN 2 
                    WHEN e.priority = \'low\' THEN 1 
                    ELSE 0 END as HIDDEN priority_order')
                    ->orderBy('priority_order', 'DESC')
                    ->addOrderBy('e.startTime', 'ASC');
                break;
            default:
                $queryBuilder->orderBy('e.startTime', 'DESC');
        }
        
        $events = $queryBuilder->getQuery()->getResult();
        
        // Get statistics for dashboard
        $stats = [
            'total' => count($events),
            'today' => $this->eventRepository->countEventsForDate($today),
            'upcoming' => $this->eventRepository->countUpcomingEvents($today),
            'past' => $this->eventRepository->countPastEvents($today),
        ];
        
        return $this->render('event/index.html.twig', [
            'events' => $events,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentSearch' => $search,
            'currentSort' => $sort,
        ]);
    }

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $event = new Event();
        
        if ($request->isMethod('POST')) {
            // Handle AJAX requests
            if ($request->isXmlHttpRequest()) {
                return $this->handleAjaxEventSubmission($request, $event, true);
            }
            return $this->handleEventSubmission($request, $event, true);
        }
        
        $offices = $this->officeRepository->findAll();
        $popularTags = $this->eventTagRepository->findPopularTags(10);
        
        return $this->render('event/new.html.twig', [
            'event' => $event,
            'offices' => $offices,
            'popularTags' => $popularTags,
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST', 'HEAD'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Event $event): Response
    {
        // Check if user can edit this event
        $this->denyAccessUnlessGranted('EVENT_EDIT', $event);
        
        // Handle HEAD requests for permission checking
        if ($request->isMethod('HEAD')) {
            return new Response('', 200);
        }
        
        if ($request->isMethod('POST')) {
            return $this->handleEventSubmission($request, $event, false);
        }
        
        $offices = $this->officeRepository->findAll();
        $popularTags = $this->eventTagRepository->findPopularTags(10);
        
        // Auto-assign creator's office if event has no primary office
        if (!$event->getOffice() && $event->getCreator() && $event->getCreator()->getOffice()) {
            $event->setOffice($event->getCreator()->getOffice());
            $this->entityManager->flush();
        }
        
        // Prepare event data with proper timezone conversion for form inputs
        $eventData = [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'location' => $event->getLocation(),
            'priority' => $event->getPriority(),
            'status' => $event->getStatus(),
            'allDay' => $event->isAllDay(),
            'isRecurring' => $event->isRecurring(),
            'color' => $event->getColor(),
            'office' => $event->getOffice(),
            'taggedOffices' => $event->getTaggedOffices(),
            'recurrencePattern' => $event->getRecurrencePattern(),
        ];
        
        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'eventData' => $eventData,
            'offices' => $offices,
            'popularTags' => $popularTags,
        ]);
    }

    #[Route('/{id}', name: 'app_event_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Event $event): JsonResponse
    {
        // Check if user can delete this event
        $this->denyAccessUnlessGranted('EVENT_DELETE', $event);
        
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
                'message' => 'Error deleting event: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/create', name: 'app_event_api_create', methods: ['POST'])]
    public function apiCreate(Request $request): JsonResponse
    {
        // Check if this is a multipart/form-data request (with files)
        $contentType = $request->headers->get('Content-Type', '');
        $isMultipart = str_contains($contentType, 'multipart/form-data');
        
        if ($isMultipart) {
            // Handle multipart/form-data (with file uploads)
            $data = $request->request->all();
            $files = $request->files->get('attachments', []);
        } else {
            // Handle JSON data (no files)
            $data = json_decode($request->getContent(), true);
            $files = [];
            
            if (!$data) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ], 400);
            }
        }
        
        $event = new Event();
        $result = $this->populateEventFromData($event, $data, true);
        
        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }
        
        // Handle file attachments if present
        if (!empty($files)) {
            $uploadResult = $this->handleFileUploads($event, $files);
            if (!$uploadResult['success']) {
                return new JsonResponse($uploadResult, 400);
            }
        }
        
        return new JsonResponse([
            'success' => true,
            'event' => $this->formatEventForCalendar($event),
            'message' => 'Event created successfully'
        ]);
    }

    #[Route('/api/{id}/update', name: 'app_event_api_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function apiUpdate(Request $request, Event $event): JsonResponse
    {
        // Check if user can edit this event
        $this->denyAccessUnlessGranted('EVENT_EDIT', $event);
        
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data'
            ], 400);
        }
        
        $result = $this->populateEventFromData($event, $data, false);
        
        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }
        
        return new JsonResponse([
            'success' => true,
            'event' => $this->formatEventForCalendar($event),
            'message' => 'Event updated successfully'
        ]);
    }

    #[Route('/api/{id}/move', name: 'app_event_api_move', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function apiMove(Request $request, Event $event): JsonResponse
    {
        // Check if user can edit this event
        $this->denyAccessUnlessGranted('EVENT_EDIT', $event);
        
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['start']) || !isset($data['end'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Start and end times are required'
            ], 400);
        }
        
        try {
            $startTime = $this->timezoneService->convertToUtc($data['start']);
            $endTime = $this->timezoneService->convertToUtc($data['end']);
            
            // Use conflict resolver service
            $resolution = $this->conflictResolver->canMoveEvent($event, $startTime, $endTime, $this->getUser());
            
            if ($resolution->isBlocked()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $resolution->getMessage(),
                    'conflicts' => $this->conflictResolver->getConflictDetails($resolution->getConflicts())
                ], 409);
            }
            
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            
            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->formatValidationErrors($errors)
                ], 400);
            }
            
            $this->entityManager->flush();
            
            $response = [
                'success' => true,
                'event' => $this->formatEventForCalendar($event),
                'message' => 'Event moved successfully'
            ];
            
            if ($resolution->isWarning()) {
                $response['warning'] = $resolution->getMessage();
                $response['conflicts'] = $this->conflictResolver->getConflictDetails($resolution->getConflicts());
            }
            
            return new JsonResponse($response);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error moving event: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/{id}/resize', name: 'app_event_api_resize', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function apiResize(Request $request, Event $event): JsonResponse
    {
        // Check if user can edit this event
        $this->denyAccessUnlessGranted('EVENT_EDIT', $event);
        
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['end'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'End time is required'
            ], 400);
        }
        
        try {
            $endTime = $this->timezoneService->convertToUtc($data['end']);
            
            // Use conflict resolver service
            $resolution = $this->conflictResolver->canResizeEvent($event, $endTime, $this->getUser());
            
            if ($resolution->isBlocked()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $resolution->getMessage(),
                    'conflicts' => $this->conflictResolver->getConflictDetails($resolution->getConflicts())
                ], 409);
            }
            
            $event->setEndTime($endTime);
            
            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $this->formatValidationErrors($errors)
                ], 400);
            }
            
            $this->entityManager->flush();
            
            $response = [
                'success' => true,
                'event' => $this->formatEventForCalendar($event),
                'message' => 'Event resized successfully'
            ];
            
            if ($resolution->isWarning()) {
                $response['warning'] = $resolution->getMessage();
                $response['conflicts'] = $this->conflictResolver->getConflictDetails($resolution->getConflicts());
            }
            
            return new JsonResponse($response);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error resizing event: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/export', name: 'app_event_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        // Get filter parameters
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');
        
        // Use the same logic as index to get filtered events
        $queryBuilder = $this->eventRepository->createQueryBuilder('e')
            ->leftJoin('e.office', 'o')
            ->leftJoin('e.creator', 'c');
        
        // Apply search filter
        if (!empty($search)) {
            $queryBuilder->andWhere('e.title LIKE :search OR e.description LIKE :search OR e.location LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // Apply status filter
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        switch ($status) {
            case 'today':
                $tomorrow = clone $today;
                $tomorrow->add(new \DateInterval('P1D'));
                $queryBuilder->andWhere('e.startTime >= :today AND e.startTime < :tomorrow')
                    ->setParameter('today', $today)
                    ->setParameter('tomorrow', $tomorrow);
                break;
            case 'upcoming':
                $queryBuilder->andWhere('e.startTime >= :today')
                    ->setParameter('today', $today);
                break;
            case 'past':
                $queryBuilder->andWhere('e.startTime < :today')
                    ->setParameter('today', $today);
                break;
        }
        
        $events = $queryBuilder->orderBy('e.startTime', 'ASC')->getQuery()->getResult();
        
        // Create CSV content
        $csvContent = "Title,Description,Start Date,End Date,Location,Office,Priority,Status,Creator,All Day,Recurring\n";
        
        foreach ($events as $event) {
            $csvContent .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $event->getTitle()),
                str_replace('"', '""', $event->getDescription() ?? ''),
                $event->getStartTime()->format('Y-m-d H:i:s'),
                $event->getEndTime()->format('Y-m-d H:i:s'),
                str_replace('"', '""', $event->getLocation() ?? ''),
                $event->getOffice() ? str_replace('"', '""', $event->getOffice()->getName()) : '',
                $event->getPriority(),
                $event->getStatus(),
                $event->getCreator() ? str_replace('"', '""', $event->getCreator()->getEmail()) : '',
                $event->isAllDay() ? 'Yes' : 'No',
                $event->isRecurring() ? 'Yes' : 'No'
            );
        }
        
        // Create response
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="events_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        return $response;
    }

    #[Route('/api/check-conflicts', name: 'app_event_check_conflicts', methods: ['POST'])]
    public function apiCheckConflicts(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['start']) || !isset($data['end'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Start and end times are required'
            ], 400);
        }
        
        try {
            $startTime = $this->timezoneService->convertToUtc($data['start']);
            $endTime = $this->timezoneService->convertToUtc($data['end']);
            $excludeEventId = $data['exclude_event_id'] ?? null;
            
            $excludeEvent = null;
            if ($excludeEventId) {
                $excludeEvent = $this->eventRepository->find($excludeEventId);
            }
            
            $conflicts = $this->conflictResolver->checkConflicts($startTime, $endTime, $excludeEvent);
            
            $canOverride = $this->conflictResolver->canUserOverrideConflicts($this->getUser());
            
            return new JsonResponse([
                'success' => true,
                'has_conflicts' => !empty($conflicts),
                'conflict_count' => count($conflicts),
                'can_override' => $canOverride,
                'conflicts' => $this->conflictResolver->getConflictDetails($conflicts),
                'warning_message' => $this->conflictResolver->getConflictWarning($conflicts)
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error checking conflicts: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/tags/popular', name: 'app_event_api_popular_tags', methods: ['GET'])]
    public function apiGetPopularTags(Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 10);
            $popularTags = $this->eventTagRepository->findPopularTags($limit);
            
            $formattedTags = array_map(function($tagData) {
                return [
                    'name' => $tagData[0]->getName(),
                    'count' => $tagData['eventCount'],
                    'color' => $tagData[0]->getColor()
                ];
            }, $popularTags);
            
            return new JsonResponse([
                'success' => true,
                'tags' => $formattedTags
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error fetching popular tags: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/tags/search', name: 'app_event_api_search_tags', methods: ['GET'])]
    public function apiSearchTags(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');
            
            if (strlen($query) < 2) {
                return new JsonResponse([
                    'success' => true,
                    'tags' => []
                ]);
            }
            
            $tags = $this->eventTagRepository->searchByName($query);
            
            $formattedTags = array_map(function($tag) {
                return [
                    'name' => $tag->getName(),
                    'color' => $tag->getColor(),
                    'eventCount' => $tag->getEventCount()
                ];
            }, $tags);
            
            return new JsonResponse([
                'success' => true,
                'tags' => $formattedTags
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error searching tags: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleAjaxEventSubmission(Request $request, Event $event, bool $isNew): JsonResponse
    {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->securityService->validateCsrfToken('event_form', $token)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid security token. Please try again.'
            ], 400);
        }

        // Sanitize input data
        $data = $this->securityService->sanitizeArray($request->request->all());
        
        // Check for suspicious content
        foreach ($data as $key => $value) {
            if (is_string($value) && $this->securityService->detectSuspiciousActivity($value, "event_{$key}")) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Request blocked due to security policy.'
                ], 400);
            }
        }
        
        $result = $this->populateEventFromData($event, $data, $isNew);
        
        if ($result['success']) {
            return new JsonResponse([
                'success' => true,
                'message' => $isNew ? 'Event created successfully!' : 'Event updated successfully!',
                'event' => [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'start' => $event->getStartTime()->format('c'),
                    'end' => $event->getEndTime()->format('c')
                ]
            ]);
        } else {
            return new JsonResponse([
                'success' => false,
                'message' => $result['message'],
                'errors' => $result['errors'] ?? []
            ], 400);
        }
    }

    private function handleEventSubmission(Request $request, Event $event, bool $isNew): Response
    {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->securityService->validateCsrfToken('event_form', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute($isNew ? 'app_event_new' : 'app_event_edit', 
                $isNew ? [] : ['id' => $event->getId()]);
        }

        // Sanitize input data
        $data = $this->securityService->sanitizeArray($request->request->all());
        
        // Check for suspicious content
        foreach ($data as $key => $value) {
            if (is_string($value) && $this->securityService->detectSuspiciousActivity($value, "event_{$key}")) {
                $this->addFlash('error', 'Request blocked due to security policy.');
                return $this->redirectToRoute($isNew ? 'app_event_new' : 'app_event_edit', 
                    $isNew ? [] : ['id' => $event->getId()]);
            }
        }
        
        $result = $this->populateEventFromData($event, $data, $isNew);
        
        if ($result['success']) {
            if ($isNew) {
                $this->addFlash('success', 'Event "' . $event->getTitle() . '" has been created successfully!');
            } else {
                $this->addFlash('success', 'Event "' . $event->getTitle() . '" has been updated successfully!');
            }
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        } else {
            $this->addFlash('error', $result['message']);
            $offices = $this->officeRepository->findAll();
            
            return $this->render($isNew ? 'event/new.html.twig' : 'event/edit.html.twig', [
                'event' => $event,
                'offices' => $offices,
                'errors' => $result['errors'] ?? []
            ]);
        }
    }

    private function populateEventFromData(Event $event, array $data, bool $isNew): array
    {
        try {
            // Log the incoming data for debugging
            error_log('populateEventFromData called with data: ' . json_encode($data));
            error_log('Event ID: ' . ($event->getId() ?? 'new') . ', isNew: ' . ($isNew ? 'true' : 'false'));
            
            // Validate and set title (required field)
            $title = trim($data['title'] ?? '');
            if (empty($title)) {
                return [
                    'success' => false,
                    'message' => 'Event title is required'
                ];
            }
            $event->setTitle($title);
            
            // Set optional text fields
            $event->setDescription(!empty($data['description']) ? trim($data['description']) : null);
            $event->setLocation(!empty($data['location']) ? trim($data['location']) : null);
            
            // Validate and set color
            $color = $data['color'] ?? '#007BFF';
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                // Try to fix common color format issues
                if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
                    $color = '#' . $color;
                } else {
                    $color = '#007BFF'; // Default blue
                }
            }
            $event->setColor($color);
            
            // Set choice fields with validation
            $priority = $data['priority'] ?? 'normal';
            // Map common invalid priority values to valid ones
            $priorityMap = [
                'medium' => 'normal',
                'moderate' => 'normal', 
                'critical' => 'urgent',
                'emergency' => 'urgent'
            ];
            if (isset($priorityMap[$priority])) {
                $priority = $priorityMap[$priority];
            }
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                $priority = 'normal';
            }
            $event->setPriority($priority);
            
            $status = $data['status'] ?? 'confirmed';
            if (!in_array($status, ['confirmed', 'tentative', 'cancelled'])) {
                $status = 'confirmed';
            }
            $event->setStatus($status);
            
            // Set meeting type
            $meetingType = $data['meeting_type'] ?? 'in-person';
            if (!in_array($meetingType, ['in-person', 'zoom', 'hybrid', 'other'])) {
                $meetingType = 'in-person';
            }
            $event->setMeetingType($meetingType);
            
            // Set zoom link (only if meeting type is zoom or hybrid)
            if (($meetingType === 'zoom' || $meetingType === 'hybrid') && !empty($data['zoom_link'])) {
                $event->setZoomLink(trim($data['zoom_link']));
            } else {
                $event->setZoomLink(null);
            }
            
            // Set boolean fields
            $event->setAllDay(isset($data['allDay']) && ($data['allDay'] === true || $data['allDay'] === '1' || $data['allDay'] === 1));
            
            // Handle recurring events
            $isRecurring = isset($data['isRecurring']) && ($data['isRecurring'] === true || $data['isRecurring'] === '1' || $data['isRecurring'] === 1);
            $event->setRecurring($isRecurring);
            
            if ($isRecurring && isset($data['recurrencePattern'])) {
                $pattern = $data['recurrencePattern'];
                
                // Validate recurrence pattern
                if (!$this->recurrenceService->validateRecurrencePattern($pattern)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid recurrence pattern provided'
                    ];
                }
                
                $event->setRecurrencePattern($pattern);
            } else {
                $event->setRecurrencePattern(null);
            }
            
            // Set dates - convert from Philippines timezone to UTC for database storage
            if (isset($data['start'])) {
                $event->setStartTime($this->timezoneService->convertToUtc($data['start']));
            }
            if (isset($data['end'])) {
                $event->setEndTime($this->timezoneService->convertToUtc($data['end']));
            }
            
            // Set office
            if (isset($data['office_id']) && !empty($data['office_id'])) {
                $officeId = is_numeric($data['office_id']) ? (int)$data['office_id'] : null;
                if ($officeId) {
                    $office = $this->officeRepository->find($officeId);
                    if ($office) {
                        $event->setOffice($office);
                    }
                }
            }
            
            // Set tagged offices (multiple office selection)
            if (isset($data['tagged_office_ids']) && is_array($data['tagged_office_ids'])) {
                $event->clearTaggedOffices();
                foreach ($data['tagged_office_ids'] as $officeId) {
                    if (is_numeric($officeId)) {
                        $office = $this->officeRepository->find((int)$officeId);
                        if ($office) {
                            $event->addTaggedOffice($office);
                        }
                    }
                }
            }
            
            // Handle event tags
            if (isset($data['tags']) && is_array($data['tags'])) {
                // Clear existing tags
                foreach ($event->getTags() as $tag) {
                    $event->removeTag($tag);
                }
                
                // Add new tags
                foreach ($data['tags'] as $tagName) {
                    $tagName = trim($tagName);
                    if (!empty($tagName)) {
                        // Find or create the tag
                        $tag = $this->eventTagRepository->findOrCreateByName($tagName);
                        $event->addTag($tag);
                    }
                }
            }
            
            // Set creator for new events
            if ($isNew) {
                $event->setCreator($this->getUser());
                
                // Auto-assign creator's office if no office is selected and creator has an office
                if (!$event->getOffice() && $this->getUser() && $this->getUser()->getOffice()) {
                    $event->setOffice($this->getUser()->getOffice());
                }
            }
            
            // Check for conflicts using conflict resolver
            if ($event->getStartTime() && $event->getEndTime()) {
                $resolution = $this->conflictResolver->resolveConflict($event, $this->getUser());
                
                if ($resolution->isBlocked()) {
                    return [
                        'success' => false,
                        'message' => $resolution->getMessage(),
                        'conflicts' => $resolution->getConflicts()
                    ];
                }
                
                // Store warning for later display if needed
                if ($resolution->isWarning()) {
                    // Could store this in session or return it for display
                }
            }
            
            // Validate the event
            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                // Log validation errors for debugging
                error_log('Event validation failed for event ID: ' . ($event->getId() ?? 'new'));
                foreach ($errors as $error) {
                    error_log('Validation error - ' . $error->getPropertyPath() . ': ' . $error->getMessage());
                }
                
                return [
                    'success' => false,
                    'message' => 'Validation failed',
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
                'message' => 'Error saving event: ' . $e->getMessage()
            ];
        }
    }

    private function formatEventForCalendar(Event $event): array
    {
        // Convert times from UTC to Philippines timezone for display
        $startTime = $this->timezoneService->convertFromUtc($event->getStartTime());
        $endTime = $this->timezoneService->convertFromUtc($event->getEndTime());
        
        $eventData = [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'start' => $startTime->format('c'),
            'end' => $endTime->format('c'),
            'backgroundColor' => $event->getEffectiveColor(),
            'borderColor' => $event->getEffectiveColor(),
            'textColor' => $this->getContrastColor($event->getEffectiveColor()),
            'allDay' => $event->isAllDay(),
            'extendedProps' => [
                'description' => $event->getDescription(),
                'location' => $event->getLocation(),
                'creator' => $event->getCreator() ? $event->getCreator()->getEmail() : null,
                'office' => $event->getOffice() ? [
                    'id' => $event->getOffice()->getId(),
                    'name' => $event->getOffice()->getName(),
                    'code' => $event->getOffice()->getCode(),
                ] : null,
                'taggedOffices' => $event->getTaggedOffices()->map(function($office) {
                    return [
                        'id' => $office->getId(),
                        'name' => $office->getName(),
                        'code' => $office->getCode(),
                        'color' => $office->getColor(),
                    ];
                })->toArray(),
                'priority' => $event->getPriority(),
                'status' => $event->getStatus(),
                'tags' => $event->getTagNames(),
                'isRecurring' => $event->isRecurring(),
            ],
        ];
        
        // Add recurrence information if event is recurring
        if ($event->isRecurring() && $event->getRecurrencePattern()) {
            $eventData['extendedProps']['recurrencePattern'] = $event->getRecurrencePattern();
            $eventData['extendedProps']['recurrenceDescription'] = $this->recurrenceService->getPatternDescription($event->getRecurrencePattern());
        }
        
        return $eventData;
    }

    private function getContrastColor(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    private function formatValidationErrors($errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $formattedErrors[] = [
                'property' => $error->getPropertyPath(),
                'message' => $error->getMessage()
            ];
        }
        return $formattedErrors;
    }

    /**
     * Handle file uploads for event attachments
     */
    private function handleFileUploads(Event $event, array $files): array
    {
        try {
            // Ensure the event has been persisted and has an ID
            if (!$event->getId()) {
                return [
                    'success' => false,
                    'message' => 'Event must be saved before uploading attachments'
                ];
            }

            // Create upload directory if it doesn't exist
            $uploadDir = $this->projectDir . '/public/uploads/events/' . $event->getId();
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadedFiles = [];
            $allowedMimeTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'image/jpeg',
                'image/png',
                'image/gif',
                'text/plain',
                'text/csv',
            ];

            $maxFileSize = 10 * 1024 * 1024; // 10MB

            foreach ($files as $uploadedFile) {
                if (!$uploadedFile instanceof UploadedFile) {
                    continue;
                }

                // Get file info BEFORE moving (important!)
                $originalFilename = $uploadedFile->getClientOriginalName();
                $fileSize = $uploadedFile->getSize();
                $mimeType = $uploadedFile->getMimeType();

                // Validate file size
                if ($fileSize > $maxFileSize) {
                    return [
                        'success' => false,
                        'message' => 'File ' . $originalFilename . ' exceeds maximum size of 10MB'
                    ];
                }

                // Validate MIME type
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    return [
                        'success' => false,
                        'message' => 'File type not allowed: ' . $originalFilename
                    ];
                }

                // Generate unique filename
                $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalFilename, PATHINFO_FILENAME));
                $extension = $uploadedFile->guessExtension() ?: pathinfo($originalFilename, PATHINFO_EXTENSION);
                $newFilename = $safeFilename . '_' . uniqid() . '.' . $extension;

                // Move file to upload directory
                try {
                    $uploadedFile->move($uploadDir, $newFilename);
                } catch (FileException $e) {
                    return [
                        'success' => false,
                        'message' => 'Failed to upload file: ' . $e->getMessage()
                    ];
                }

                // Create EventAttachment entity (using saved file info)
                $attachment = new EventAttachment();
                $attachment->setOriginalName($originalFilename);
                $attachment->setFilename($newFilename);
                $attachment->setMimeType($mimeType);
                $attachment->setFileSize($fileSize);
                $attachment->setEvent($event);
                $attachment->setUploadedBy($this->getUser());

                $this->entityManager->persist($attachment);
                $uploadedFiles[] = $attachment;
            }

            // Flush all attachments to database
            if (!empty($uploadedFiles)) {
                $this->entityManager->flush();
            }

            return [
                'success' => true,
                'uploaded_count' => count($uploadedFiles),
                'files' => array_map(function($attachment) {
                    return [
                        'id' => $attachment->getId(),
                        'original_name' => $attachment->getOriginalName(),
                        'file_size' => $attachment->getFormattedFileSize(),
                        'mime_type' => $attachment->getMimeType(),
                    ];
                }, $uploadedFiles)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error uploading files: ' . $e->getMessage()
            ];
        }
    }
}
