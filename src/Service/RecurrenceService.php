<?php

namespace App\Service;

use App\Entity\Event;
use App\Service\TimezoneService;
use DateTime;
use DateInterval;
use DatePeriod;
use InvalidArgumentException;

class RecurrenceService
{
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_YEARLY = 'yearly';

    public const VALID_FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_YEARLY,
    ];

    public const WEEKDAYS = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 0,
    ];

    public function __construct(
        private TimezoneService $timezoneService
    ) {}

    /**
     * Validate a recurrence pattern
     */
    public function validateRecurrencePattern(array $pattern): bool
    {
        // Check required fields
        if (!isset($pattern['frequency'])) {
            return false;
        }

        if (!in_array($pattern['frequency'], self::VALID_FREQUENCIES, true)) {
            return false;
        }

        // Validate interval
        if (isset($pattern['interval'])) {
            if (!is_int($pattern['interval']) || $pattern['interval'] < 1) {
                return false;
            }
        }

        // Validate count
        if (isset($pattern['count'])) {
            if (!is_int($pattern['count']) || $pattern['count'] < 1) {
                return false;
            }
        }

        // Validate until date
        if (isset($pattern['until'])) {
            if (!$this->isValidDateString($pattern['until'])) {
                return false;
            }
        }

        // Validate weekdays for weekly frequency
        if ($pattern['frequency'] === self::FREQUENCY_WEEKLY && isset($pattern['weekdays'])) {
            if (!is_array($pattern['weekdays'])) {
                return false;
            }
            
            foreach ($pattern['weekdays'] as $weekday) {
                if (!array_key_exists($weekday, self::WEEKDAYS)) {
                    return false;
                }
            }
        }

        // Validate monthly pattern
        if ($pattern['frequency'] === self::FREQUENCY_MONTHLY && isset($pattern['monthlyType'])) {
            if (!in_array($pattern['monthlyType'], ['dayOfMonth', 'dayOfWeek'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate recurring event instances based on pattern
     */
    public function generateRecurringInstances(Event $masterEvent, DateTime $rangeStart, DateTime $rangeEnd): array
    {
        if (!$masterEvent->isRecurring() || !$masterEvent->getRecurrencePattern()) {
            return [];
        }

        $pattern = $masterEvent->getRecurrencePattern();
        
        if (!$this->validateRecurrencePattern($pattern)) {
            throw new InvalidArgumentException('Invalid recurrence pattern');
        }

        $instances = [];
        $frequency = $pattern['frequency'];
        $interval = $pattern['interval'] ?? 1;
        $count = $pattern['count'] ?? null;
        $until = isset($pattern['until']) ? $this->timezoneService->convertToUtc($pattern['until']) : null;

        $startDate = clone $masterEvent->getStartTime();
        $endDate = clone $masterEvent->getEndTime();
        $duration = $startDate->diff($endDate);

        // For weekly patterns with specific weekdays, we need to generate all instances
        // within the pattern, not just start from the master event date
        if ($frequency === self::FREQUENCY_WEEKLY && isset($pattern['weekdays']) && is_array($pattern['weekdays'])) {
            return $this->generateWeeklyInstances($masterEvent, $rangeStart, $rangeEnd, $pattern);
        }

        $currentDate = clone $startDate;
        $instanceCount = 0;

        // Ensure we don't generate too many instances
        $maxInstances = 1000;

        while ($instanceCount < $maxInstances) {
            // Check if we've reached the count limit
            if ($count !== null && $instanceCount >= $count) {
                break;
            }

            // Check if we've reached the until date
            if ($until !== null && $currentDate > $until) {
                break;
            }

            // Check if current date is within our range
            if ($currentDate >= $rangeStart && $currentDate <= $rangeEnd) {
                $instanceStart = clone $currentDate;
                $instanceEnd = clone $instanceStart;
                $instanceEnd->add($duration);

                $instances[] = [
                    'start' => $instanceStart,
                    'end' => $instanceEnd,
                    'title' => $masterEvent->getTitle(),
                    'description' => $masterEvent->getDescription(),
                    'location' => $masterEvent->getLocation(),
                    'color' => $masterEvent->getEffectiveColor(),
                    'masterEventId' => $masterEvent->getId(),
                ];
            }

            // Move to next occurrence
            $currentDate = $this->getNextOccurrence($currentDate, $frequency, $interval, $pattern);
            $instanceCount++;

            // Safety check to prevent infinite loops
            if ($currentDate > $rangeEnd->modify('+1 year')) {
                break;
            }
        }

        return $instances;
    }

    /**
     * Generate weekly instances for specific weekdays
     */
    private function generateWeeklyInstances(Event $masterEvent, DateTime $rangeStart, DateTime $rangeEnd, array $pattern): array
    {
        $instances = [];
        $weekdays = $pattern['weekdays'];
        $interval = $pattern['interval'] ?? 1;
        $count = $pattern['count'] ?? null;
        $until = isset($pattern['until']) ? $this->timezoneService->convertToUtc($pattern['until']) : null;

        $startDate = clone $masterEvent->getStartTime();
        $endDate = clone $masterEvent->getEndTime();
        $duration = $startDate->diff($endDate);

        // Convert weekday names to numbers
        $weekdayNumbers = array_map(function($day) {
            return self::WEEKDAYS[$day];
        }, $weekdays);
        
        sort($weekdayNumbers);

        // Start from the beginning of the week containing the master event
        $currentWeekStart = clone $startDate;
        $currentWeekStart->modify('monday this week');
        
        $instanceCount = 0;
        $maxInstances = 1000;
        $weekCount = 0;

        while ($instanceCount < $maxInstances && $currentWeekStart <= $rangeEnd) {
            // Check if we've reached the count limit
            if ($count !== null && $instanceCount >= $count) {
                break;
            }

            // Generate instances for this week if it's the right interval
            if ($weekCount % $interval === 0) {
                foreach ($weekdayNumbers as $targetWeekday) {
                    $instanceDate = clone $currentWeekStart;
                    
                    // Calculate days to add to get to target weekday
                    $daysToAdd = ($targetWeekday === 0) ? 6 : $targetWeekday - 1; // Convert Sunday (0) to 6, others to 0-based
                    $instanceDate->add(new DateInterval("P{$daysToAdd}D"));
                    
                    // Set the time from the master event
                    $instanceDate->setTime(
                        (int)$startDate->format('H'),
                        (int)$startDate->format('i'),
                        (int)$startDate->format('s')
                    );

                    // Check if we've reached the until date
                    if ($until !== null && $instanceDate > $until) {
                        break 2; // Break out of both loops
                    }

                    // Check if instance is within our range
                    if ($instanceDate >= $rangeStart && $instanceDate <= $rangeEnd) {
                        $instanceEnd = clone $instanceDate;
                        $instanceEnd->add($duration);

                        $instances[] = [
                            'start' => $instanceDate,
                            'end' => $instanceEnd,
                            'title' => $masterEvent->getTitle(),
                            'description' => $masterEvent->getDescription(),
                            'location' => $masterEvent->getLocation(),
                            'color' => $masterEvent->getEffectiveColor(),
                            'masterEventId' => $masterEvent->getId(),
                        ];

                        $instanceCount++;

                        // Check count limit after each instance
                        if ($count !== null && $instanceCount >= $count) {
                            break 2; // Break out of both loops
                        }
                    }
                }
            }

            // Move to next week
            $currentWeekStart->add(new DateInterval('P7D'));
            $weekCount++;

            // Safety check to prevent infinite loops
            if ($weekCount > 520) { // ~10 years of weeks
                break;
            }
        }

        return $instances;
    }

    /**
     * Get the next occurrence date based on frequency and pattern
     */
    private function getNextOccurrence(DateTime $currentDate, string $frequency, int $interval, array $pattern): DateTime
    {
        $nextDate = clone $currentDate;

        switch ($frequency) {
            case self::FREQUENCY_DAILY:
                $nextDate->add(new DateInterval("P{$interval}D"));
                break;

            case self::FREQUENCY_WEEKLY:
                if (isset($pattern['weekdays']) && is_array($pattern['weekdays'])) {
                    $nextDate = $this->getNextWeeklyOccurrence($nextDate, $pattern['weekdays'], $interval);
                } else {
                    $nextDate->add(new DateInterval("P" . ($interval * 7) . "D"));
                }
                break;

            case self::FREQUENCY_MONTHLY:
                if (isset($pattern['monthlyType']) && $pattern['monthlyType'] === 'dayOfWeek') {
                    $nextDate = $this->getNextMonthlyByDayOfWeek($nextDate, $interval);
                } else {
                    $nextDate->add(new DateInterval("P{$interval}M"));
                }
                break;

            case self::FREQUENCY_YEARLY:
                $nextDate->add(new DateInterval("P{$interval}Y"));
                break;
        }

        return $nextDate;
    }

    /**
     * Get next weekly occurrence for specific weekdays
     */
    private function getNextWeeklyOccurrence(DateTime $currentDate, array $weekdays, int $interval): DateTime
    {
        $nextDate = clone $currentDate;
        
        // Convert weekday names to numbers (0 = Sunday, 1 = Monday, etc.)
        $weekdayNumbers = array_map(function($day) {
            return self::WEEKDAYS[$day];
        }, $weekdays);
        
        sort($weekdayNumbers);
        
        // Start from the next day to avoid returning the same date
        $nextDate->add(new DateInterval('P1D'));
        $currentWeekday = (int)$nextDate->format('w');
        
        // Find next weekday in current week
        $found = false;
        foreach ($weekdayNumbers as $targetWeekday) {
            if ($targetWeekday >= $currentWeekday) {
                $daysToAdd = $targetWeekday - $currentWeekday;
                $nextDate->add(new DateInterval("P{$daysToAdd}D"));
                $found = true;
                break;
            }
        }
        
        // If no weekday found in current week, go to next interval week
        if (!$found) {
            // Calculate days to get to the first weekday of the next interval week
            $daysToNextWeek = (7 - $currentWeekday) + $weekdayNumbers[0] + (($interval - 1) * 7);
            $nextDate->add(new DateInterval("P{$daysToNextWeek}D"));
        }
        
        return $nextDate;
    }

    /**
     * Get next monthly occurrence by day of week (e.g., "first Monday of month")
     */
    private function getNextMonthlyByDayOfWeek(DateTime $currentDate, int $interval): DateTime
    {
        $nextDate = clone $currentDate;
        $dayOfWeek = (int)$currentDate->format('w');
        $weekOfMonth = ceil($currentDate->format('j') / 7);
        
        // Move to next month(s)
        $nextDate->add(new DateInterval("P{$interval}M"));
        
        // Set to first day of month
        $nextDate->setDate($nextDate->format('Y'), $nextDate->format('n'), 1);
        
        // Find the correct week and day
        $targetWeekday = $dayOfWeek;
        $currentWeekday = (int)$nextDate->format('w');
        
        // Calculate days to add to get to target weekday in first week
        $daysToAdd = ($targetWeekday - $currentWeekday + 7) % 7;
        $nextDate->add(new DateInterval("P{$daysToAdd}D"));
        
        // Add weeks to get to correct week of month
        if ($weekOfMonth > 1) {
            $weeksToAdd = $weekOfMonth - 1;
            $nextDate->add(new DateInterval("P" . ($weeksToAdd * 7) . "D"));
        }
        
        return $nextDate;
    }

    /**
     * Create a default recurrence pattern
     */
    public function createDefaultPattern(string $frequency, int $interval = 1): array
    {
        if (!in_array($frequency, self::VALID_FREQUENCIES, true)) {
            throw new InvalidArgumentException("Invalid frequency: {$frequency}");
        }

        return [
            'frequency' => $frequency,
            'interval' => $interval,
        ];
    }

    /**
     * Create a weekly pattern with specific weekdays
     */
    public function createWeeklyPattern(array $weekdays, int $interval = 1): array
    {
        foreach ($weekdays as $weekday) {
            if (!array_key_exists($weekday, self::WEEKDAYS)) {
                throw new InvalidArgumentException("Invalid weekday: {$weekday}");
            }
        }

        return [
            'frequency' => self::FREQUENCY_WEEKLY,
            'interval' => $interval,
            'weekdays' => $weekdays,
        ];
    }

    /**
     * Create a monthly pattern
     */
    public function createMonthlyPattern(string $type = 'dayOfMonth', int $interval = 1): array
    {
        if (!in_array($type, ['dayOfMonth', 'dayOfWeek'], true)) {
            throw new InvalidArgumentException("Invalid monthly type: {$type}");
        }

        return [
            'frequency' => self::FREQUENCY_MONTHLY,
            'interval' => $interval,
            'monthlyType' => $type,
        ];
    }

    /**
     * Add count limit to pattern
     */
    public function addCountLimit(array $pattern, int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Count must be greater than 0');
        }

        $pattern['count'] = $count;
        return $pattern;
    }

    /**
     * Add until date to pattern
     */
    public function addUntilDate(array $pattern, DateTime $until): array
    {
        $pattern['until'] = $until->format('Y-m-d');
        return $pattern;
    }

    /**
     * Get human-readable description of recurrence pattern
     */
    public function getPatternDescription(array $pattern): string
    {
        if (!$this->validateRecurrencePattern($pattern)) {
            return 'Invalid pattern';
        }

        $frequency = $pattern['frequency'];
        $interval = $pattern['interval'] ?? 1;
        $description = '';

        switch ($frequency) {
            case self::FREQUENCY_DAILY:
                if ($interval === 1) {
                    $description = 'Daily';
                } else {
                    $description = "Every {$interval} days";
                }
                break;

            case self::FREQUENCY_WEEKLY:
                if (isset($pattern['weekdays']) && is_array($pattern['weekdays'])) {
                    $weekdayNames = array_map('ucfirst', $pattern['weekdays']);
                    if ($interval === 1) {
                        $description = 'Weekly on ' . implode(', ', $weekdayNames);
                    } else {
                        $description = "Every {$interval} weeks on " . implode(', ', $weekdayNames);
                    }
                } else {
                    if ($interval === 1) {
                        $description = 'Weekly';
                    } else {
                        $description = "Every {$interval} weeks";
                    }
                }
                break;

            case self::FREQUENCY_MONTHLY:
                if ($interval === 1) {
                    $description = 'Monthly';
                } else {
                    $description = "Every {$interval} months";
                }
                break;

            case self::FREQUENCY_YEARLY:
                if ($interval === 1) {
                    $description = 'Yearly';
                } else {
                    $description = "Every {$interval} years";
                }
                break;
        }

        // Add count or until information
        if (isset($pattern['count'])) {
            $description .= ", {$pattern['count']} times";
        } elseif (isset($pattern['until'])) {
            $description .= ", until {$pattern['until']}";
        }

        return $description;
    }

    /**
     * Check if a date string is valid
     */
    private function isValidDateString(string $dateString): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
        return $date && $date->format('Y-m-d') === $dateString;
    }
}