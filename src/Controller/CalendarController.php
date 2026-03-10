<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Office;
use App\Repository\EventRepository;
use App\Repository\OfficeRepository;
use App\Repository\HolidayRepository;
use App\Service\RecurrenceService;
use App\Service\ConflictResolverService;
use App\Service\TimezoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
#[IsGranted('ROLE_USER')]
class CalendarController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private OfficeRepository $officeRepository,
        private HolidayRepository $holidayRepository,
        private EntityManagerInterface $entityManager,
        private RecurrenceService $recurrenceService,
        private ConflictResolverService $conflictResolver,
        private TimezoneService $timezoneService
    ) {}

    #[Route('', name: 'app_calendar_index', methods: ['GET'])]
    public function index(): Response
    {
        // Get all offices for color legend
        $offices = $this->officeRepository->findAll();
        
        // Get current user's office and division
        $user = $this->getUser();
        $userOfficeId = $user->getOffice() ? $user->getOffice()->getId() : null;
        $userDivisionId = $user->getDivision() ? $user->getDivision()->getId() : null;
        
        // Debug logging
        error_log('Calendar Index - User: ' . ($user ? $user->getEmail() : 'null'));
        error_log('Calendar Index - Office ID: ' . ($userOfficeId ?? 'null'));
        error_log('Calendar Index - Division ID: ' . ($userDivisionId ?? 'null'));
        
        return $this->render('calendar/index.html.twig', [
            'offices' => $offices,
            'userOfficeId' => $userOfficeId,
            'userDivisionId' => $userDivisionId,
        ]);
    }

    #[Route('/events', name: 'app_calendar_events', methods: ['GET'])]
    public function getEvents(Request $request): JsonResponse
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        
        // Convert to DateTime objects for database query (dates come from frontend in Philippines timezone)
        $startDate = $start ? new \DateTime($start) : new \DateTime('-1 month');
        $endDate = $end ? new \DateTime($end) : new \DateTime('+1 month');
        
        // Get events within the date range
        $events = $this->eventRepository->findEventsInRange($startDate, $endDate);
        
        // Get holidays within the date range
        $holidays = $this->holidayRepository->getHolidaysForCalendar($startDate, $endDate);
        
        // Format events for FullCalendar
        $calendarEvents = [];
        
        foreach ($events as $event) {
            if ($event->isRecurring()) {
                // Generate recurring instances
                try {
                    $instances = $this->recurrenceService->generateRecurringInstances($event, $startDate, $endDate);
                    
                    foreach ($instances as $instance) {
                        // Create proper UTC DateTime objects from database values
                        $startUtc = new \DateTime($instance['start']->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
                        $endUtc = new \DateTime($instance['end']->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
                        
                        $calendarEvents[] = [
                            'id' => $event->getId() . '_' . $instance['start']->format('Y-m-d-H-i'),
                            'title' => $instance['title'],
                            'start' => $startUtc->format('Y-m-d\TH:i:s\Z'), // Pure UTC format
                            'end' => $endUtc->format('Y-m-d\TH:i:s\Z'),     // Pure UTC format
                            'backgroundColor' => $instance['color'],
                            'borderColor' => $instance['color'],
                            'textColor' => $this->getContrastColor($instance['color']),
                            'allDay' => $event->isAllDay(),
                            'extendedProps' => [
                                'type' => 'event',
                                'description' => $instance['description'],
                                'location' => $instance['location'],
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
                                'isRecurring' => true,
                                'masterEventId' => $instance['masterEventId'],
                                'recurrencePattern' => $event->getRecurrencePattern(),
                                'recurrenceDescription' => $this->recurrenceService->getPatternDescription($event->getRecurrencePattern()),
                            ],
                        ];
                    }
                } catch (\Exception $e) {
                    // If recurrence generation fails, show the master event
                    $eventData = $this->formatEventForCalendar($event);
                    $eventData['extendedProps']['type'] = 'event';
                    $calendarEvents[] = $eventData;
                }
            } else {
                // Regular non-recurring event
                $eventData = $this->formatEventForCalendar($event);
                $eventData['extendedProps']['type'] = 'event';
                $calendarEvents[] = $eventData;
            }
        }
        
        // Add holidays to the calendar events
        $calendarEvents = array_merge($calendarEvents, $holidays);
        
        return new JsonResponse($calendarEvents);
    }

    #[Route('/offices', name: 'app_calendar_offices', methods: ['GET'])]
    public function getOffices(): JsonResponse
    {
        $offices = $this->officeRepository->findAll();
        
        $officeData = [];
        foreach ($offices as $office) {
            $officeData[] = [
                'id' => $office->getId(),
                'name' => $office->getName(),
                'code' => $office->getCode(),
                'color' => $office->getColor(),
            ];
        }
        
        return new JsonResponse($officeData);
    }

    #[Route('/offices/{id}/divisions', name: 'app_office_divisions', methods: ['GET'])]
    public function getOfficeDivisions(int $id): JsonResponse
    {
        $office = $this->officeRepository->find($id);
        
        if (!$office) {
            return new JsonResponse(['error' => 'Office not found'], 404);
        }
        
        $divisions = $office->getDivisions();
        $divisionData = [];
        
        foreach ($divisions as $division) {
            $divisionData[] = [
                'id' => $division->getId(),
                'name' => $division->getName(),
                'code' => $division->getCode(),
            ];
        }
        
        return new JsonResponse($divisionData);
    }

    #[Route('/search', name: 'app_calendar_search', methods: ['GET'])]
    public function searchEvents(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $officeIds = $request->query->get('office_ids', []);
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $tags = $request->query->get('tags', []);
        $priority = $request->query->get('priority');
        $status = $request->query->get('status');
        $creatorId = $request->query->get('creator_id');
        $isRecurring = $request->query->get('is_recurring');
        $isAllDay = $request->query->get('is_all_day');

        // Build search criteria
        $criteria = [];
        
        if (!empty($query)) {
            $criteria['query'] = $query;
        }
        
        if (!empty($officeIds)) {
            $criteria['office_ids'] = is_array($officeIds) ? $officeIds : explode(',', $officeIds);
        }
        
        if (!empty($startDate)) {
            $criteria['start_date'] = $startDate;
        }
        
        if (!empty($endDate)) {
            $criteria['end_date'] = $endDate;
        }
        
        if (!empty($tags)) {
            $criteria['tags'] = is_array($tags) ? $tags : explode(',', $tags);
        }
        
        if (!empty($priority)) {
            $criteria['priority'] = $priority;
        }
        
        if (!empty($status)) {
            $criteria['status'] = $status;
        }
        
        if (!empty($creatorId)) {
            $criteria['creator_id'] = $creatorId;
        }
        
        if ($isRecurring !== null) {
            $criteria['is_recurring'] = filter_var($isRecurring, FILTER_VALIDATE_BOOLEAN);
        }
        
        if ($isAllDay !== null) {
            $criteria['is_all_day'] = filter_var($isAllDay, FILTER_VALIDATE_BOOLEAN);
        }

        // Perform search
        $events = $this->eventRepository->searchEventsAdvanced($criteria);
        
        // Format events for response
        $searchResults = [];
        foreach ($events as $event) {
            $eventData = $this->formatEventForCalendar($event);
            
            // Add search-specific metadata
            $eventData['extendedProps']['searchScore'] = $this->calculateSearchScore($event, $query);
            $eventData['extendedProps']['matchedFields'] = $this->getMatchedFields($event, $query);
            
            $searchResults[] = $eventData;
        }

        return new JsonResponse([
            'success' => true,
            'total' => count($searchResults),
            'events' => $searchResults,
            'criteria' => $criteria
        ]);
    }

    #[Route('/filter', name: 'app_calendar_filter', methods: ['POST'])]
    public function filterEvents(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data'
            ], 400);
        }

        // Use the advanced search with the provided criteria
        $events = $this->eventRepository->searchEventsAdvanced($data);
        
        // Format events for calendar
        $filteredEvents = [];
        foreach ($events as $event) {
            if ($event->isRecurring()) {
                // Generate recurring instances if needed
                $rangeStart = isset($data['start_date']) ? new \DateTime($data['start_date']) : new \DateTime('-1 month');
                $rangeEnd = isset($data['end_date']) ? new \DateTime($data['end_date']) : new \DateTime('+1 month');
                
                try {
                    $instances = $this->recurrenceService->generateRecurringInstances($event, $rangeStart, $rangeEnd);
                    
                    foreach ($instances as $instance) {
                        $filteredEvents[] = [
                            'id' => $event->getId() . '_' . $instance['start']->format('Y-m-d-H-i'),
                            'title' => $instance['title'],
                            'start' => $instance['start']->format('c'),
                            'end' => $instance['end']->format('c'),
                            'backgroundColor' => $instance['color'],
                            'borderColor' => $instance['color'],
                            'textColor' => $this->getContrastColor($instance['color']),
                            'allDay' => $event->isAllDay(),
                            'extendedProps' => [
                                'description' => $instance['description'],
                                'location' => $instance['location'],
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
                                'isRecurring' => true,
                                'masterEventId' => $instance['masterEventId'],
                                'recurrencePattern' => $event->getRecurrencePattern(),
                                'recurrenceDescription' => $this->recurrenceService->getPatternDescription($event->getRecurrencePattern()),
                            ],
                        ];
                    }
                } catch (\Exception $e) {
                    // If recurrence generation fails, show the master event
                    $filteredEvents[] = $this->formatEventForCalendar($event);
                }
            } else {
                $filteredEvents[] = $this->formatEventForCalendar($event);
            }
        }

        return new JsonResponse([
            'success' => true,
            'total' => count($filteredEvents),
            'events' => $filteredEvents
        ]);
    }

    private function formatEventForCalendar(Event $event): array
    {
        // Check for conflicts with this event
        $conflicts = $this->conflictResolver->checkConflicts(
            $event->getStartTime(),
            $event->getEndTime(),
            $event
        );
        
        $hasConflicts = !empty($conflicts);
        $conflictCount = count($conflicts);
        
        // IMPORTANT: Database stores UTC values but DateTime objects have server timezone
        // Create proper UTC DateTime objects for FullCalendar
        $startUtc = new \DateTime($event->getStartTime()->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
        $endUtc = new \DateTime($event->getEndTime()->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
        
        $eventData = [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'start' => $startUtc->format('Y-m-d\TH:i:s\Z'), // Pure UTC format
            'end' => $endUtc->format('Y-m-d\TH:i:s\Z'),     // Pure UTC format
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
                'hasConflicts' => $hasConflicts,
                'conflictCount' => $conflictCount,
                'conflicts' => $hasConflicts ? $this->conflictResolver->getConflictDetails($conflicts) : [],
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
        // Remove # if present
        $hex = ltrim($hexColor, '#');
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        
        // Return white for dark colors, black for light colors
        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    /**
     * Calculate search relevance score for an event
     */
    private function calculateSearchScore(Event $event, string $query): float
    {
        if (empty($query)) {
            return 1.0;
        }

        $score = 0.0;
        $query = strtolower($query);

        // Title match (highest weight)
        if (stripos($event->getTitle(), $query) !== false) {
            $score += 3.0;
            if (stripos($event->getTitle(), $query) === 0) {
                $score += 1.0; // Bonus for starting with query
            }
        }

        // Description match (medium weight)
        if ($event->getDescription() && stripos($event->getDescription(), $query) !== false) {
            $score += 2.0;
        }

        // Location match (medium weight)
        if ($event->getLocation() && stripos($event->getLocation(), $query) !== false) {
            $score += 2.0;
        }

        // Tag match (low weight)
        foreach ($event->getTagNames() as $tagName) {
            if (stripos($tagName, $query) !== false) {
                $score += 1.0;
            }
        }

        // Office match (low weight)
        if ($event->getOffice() && stripos($event->getOffice()->getName(), $query) !== false) {
            $score += 1.0;
        }

        return $score;
    }

    /**
     * Get fields that matched the search query
     */
    private function getMatchedFields(Event $event, string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $matchedFields = [];
        $query = strtolower($query);

        if (stripos($event->getTitle(), $query) !== false) {
            $matchedFields[] = 'title';
        }

        if ($event->getDescription() && stripos($event->getDescription(), $query) !== false) {
            $matchedFields[] = 'description';
        }

        if ($event->getLocation() && stripos($event->getLocation(), $query) !== false) {
            $matchedFields[] = 'location';
        }

        foreach ($event->getTagNames() as $tagName) {
            if (stripos($tagName, $query) !== false) {
                $matchedFields[] = 'tags';
                break;
            }
        }

        if ($event->getOffice() && stripos($event->getOffice()->getName(), $query) !== false) {
            $matchedFields[] = 'office';
        }

        return $matchedFields;
    }
}