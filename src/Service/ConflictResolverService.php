<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ConflictResolverService
{
    public function __construct(
        private EventRepository $eventRepository,
        private AuthorizationCheckerInterface $authorizationChecker
    ) {}

    /**
     * Check for scheduling conflicts for a given time range
     */
    public function checkConflicts(\DateTimeInterface $start, \DateTimeInterface $end, ?Event $excludeEvent = null): array
    {
        return $this->eventRepository->findConflictingEvents($start, $end, $excludeEvent);
    }

    /**
     * Resolve conflicts based on user role and return resolution result
     */
    public function resolveConflict(Event $newEvent, User $user): ConflictResolution
    {
        $conflicts = $this->checkConflicts(
            $newEvent->getStartTime(),
            $newEvent->getEndTime(),
            $newEvent->getId() ? $newEvent : null
        );

        if (empty($conflicts)) {
            return new ConflictResolution(
                ConflictResolution::RESOLUTION_ALLOWED,
                'No conflicts found',
                []
            );
        }

        // Check if user can override conflicts
        if ($this->canUserOverrideConflicts($user)) {
            return new ConflictResolution(
                ConflictResolution::RESOLUTION_WARNING,
                'Conflicts detected but user can override',
                $conflicts
            );
        }

        // Normal users cannot override conflicts
        return new ConflictResolution(
            ConflictResolution::RESOLUTION_BLOCKED,
            'Conflicts detected and user cannot override',
            $conflicts
        );
    }

    /**
     * Check if a user can override scheduling conflicts
     */
    public function canUserOverrideConflicts(User $user): bool
    {
        // All authenticated users can override conflicts
        return $this->authorizationChecker->isGranted('ROLE_USER');
    }

    /**
     * Get conflict warning message for display
     */
    public function getConflictWarning(array $conflicts): string
    {
        $count = count($conflicts);
        
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            $conflict = $conflicts[0];
            return sprintf(
                'This time slot conflicts with "%s" (%s - %s). Do you want to proceed?',
                $conflict->getTitle(),
                $conflict->getStartTime()->format('M j, Y g:i A'),
                $conflict->getEndTime()->format('g:i A')
            );
        }

        return sprintf(
            'This time slot conflicts with %d existing events. Do you want to proceed?',
            $count
        );
    }

    /**
     * Get detailed conflict information for API responses
     */
    public function getConflictDetails(array $conflicts): array
    {
        $details = [];
        
        foreach ($conflicts as $conflict) {
            $details[] = [
                'id' => $conflict->getId(),
                'title' => $conflict->getTitle(),
                'start' => $conflict->getStartTime()->format('c'),
                'end' => $conflict->getEndTime()->format('c'),
                'location' => $conflict->getLocation(),
                'creator' => $conflict->getCreator() ? $conflict->getCreator()->getEmail() : null,
                'office' => $conflict->getOffice() ? [
                    'id' => $conflict->getOffice()->getId(),
                    'name' => $conflict->getOffice()->getName(),
                    'code' => $conflict->getOffice()->getCode(),
                ] : null,
                'priority' => $conflict->getPriority(),
                'status' => $conflict->getStatus(),
            ];
        }
        
        return $details;
    }

    /**
     * Check if an event can be moved to a new time slot
     */
    public function canMoveEvent(Event $event, \DateTimeInterface $newStart, \DateTimeInterface $newEnd, User $user): ConflictResolution
    {
        // Create a temporary event with new times for conflict checking
        $tempEvent = clone $event;
        $tempEvent->setStartTime($newStart);
        $tempEvent->setEndTime($newEnd);
        
        return $this->resolveConflict($tempEvent, $user);
    }

    /**
     * Check if an event can be resized
     */
    public function canResizeEvent(Event $event, \DateTimeInterface $newEnd, User $user): ConflictResolution
    {
        // Create a temporary event with new end time for conflict checking
        $tempEvent = clone $event;
        $tempEvent->setEndTime($newEnd);
        
        return $this->resolveConflict($tempEvent, $user);
    }

    /**
     * Get conflicts for a specific time range (for calendar display)
     */
    public function getConflictsInRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $events = $this->eventRepository->findEventsInRange($start, $end);
        $conflicts = [];
        
        // Group events by time to find conflicts
        $timeSlots = [];
        foreach ($events as $event) {
            $key = $event->getStartTime()->format('Y-m-d H:i') . '-' . $event->getEndTime()->format('Y-m-d H:i');
            if (!isset($timeSlots[$key])) {
                $timeSlots[$key] = [];
            }
            $timeSlots[$key][] = $event;
        }
        
        // Find time slots with multiple events
        foreach ($timeSlots as $slot => $slotEvents) {
            if (count($slotEvents) > 1) {
                $conflicts[$slot] = $slotEvents;
            }
        }
        
        return $conflicts;
    }

    /**
     * Validate event timing
     */
    public function validateEventTiming(Event $event): array
    {
        $errors = [];
        
        if (!$event->getStartTime() || !$event->getEndTime()) {
            $errors[] = 'Start and end times are required';
            return $errors;
        }
        
        if ($event->getEndTime() <= $event->getStartTime()) {
            $errors[] = 'End time must be after start time';
        }
        
        // Check if event is too long (more than 24 hours)
        $duration = $event->getEndTime()->diff($event->getStartTime());
        if ($duration->days >= 1) {
            $errors[] = 'Event duration cannot exceed 24 hours';
        }
        
        // Check if event is in the past (for new events)
        if (!$event->getId() && $event->getStartTime() < new \DateTime()) {
            $errors[] = 'Cannot create events in the past';
        }
        
        return $errors;
    }

    /**
     * Get conflict statistics for reporting
     */
    public function getConflictStatistics(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $conflicts = $this->getConflictsInRange($start, $end);
        
        $totalConflicts = count($conflicts);
        $totalConflictingEvents = 0;
        $maxConflictsInSlot = 0;
        
        foreach ($conflicts as $slotEvents) {
            $eventCount = count($slotEvents);
            $totalConflictingEvents += $eventCount;
            $maxConflictsInSlot = max($maxConflictsInSlot, $eventCount);
        }
        
        return [
            'total_conflict_slots' => $totalConflicts,
            'total_conflicting_events' => $totalConflictingEvents,
            'max_conflicts_in_slot' => $maxConflictsInSlot,
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
        ];
    }
}