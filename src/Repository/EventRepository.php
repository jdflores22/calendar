<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Office;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Find events within a date range
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.startTime <= :end AND e.endTime >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events within a date range (alias for findByDateRange)
     */
    public function findEventsInRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->findByDateRange($start, $end);
    }

    /**
     * Find events for a specific user
     */
    public function findByUser(User $user, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.creator = :user')
            ->setParameter('user', $user);

        if ($start) {
            $qb->andWhere('e.endTime >= :start')
               ->setParameter('start', $start);
        }

        if ($end) {
            $qb->andWhere('e.startTime <= :end')
               ->setParameter('end', $end);
        }

        return $qb->orderBy('e.startTime', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find events for a specific office
     */
    public function findByOffice(Office $office, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.office = :office')
            ->setParameter('office', $office);

        if ($start) {
            $qb->andWhere('e.endTime >= :start')
               ->setParameter('start', $start);
        }

        if ($end) {
            $qb->andWhere('e.startTime <= :end')
               ->setParameter('end', $end);
        }

        return $qb->orderBy('e.startTime', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find conflicting events for a given time range
     */
    public function findConflictingEvents(\DateTimeInterface $start, \DateTimeInterface $end, ?Event $excludeEvent = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.startTime < :end AND e.endTime > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($excludeEvent) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeEvent->getId());
        }

        return $qb->orderBy('e.startTime', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find events happening today
     */
    public function findTodaysEvents(): array
    {
        // Use Asia/Manila timezone to determine "today"
        $timezone = new \DateTimeZone('Asia/Manila');
        $today = new \DateTime('today', $timezone);
        $tomorrow = new \DateTime('tomorrow', $timezone);
        
        // Convert to UTC for database comparison (events are stored in UTC)
        $todayUTC = clone $today;
        $todayUTC->setTimezone(new \DateTimeZone('UTC'));
        $tomorrowUTC = clone $tomorrow;
        $tomorrowUTC->setTimezone(new \DateTimeZone('UTC'));

        return $this->createQueryBuilder('e')
            ->where('e.startTime >= :today AND e.startTime < :tomorrow')
            ->setParameter('today', $todayUTC)
            ->setParameter('tomorrow', $tomorrowUTC)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming events (next 7 days)
     */
    public function findUpcomingEvents(int $days = 7): array
    {
        // Use Asia/Manila timezone to determine date range
        $timezone = new \DateTimeZone('Asia/Manila');
        $now = new \DateTime('now', $timezone);
        $future = new \DateTime("+{$days} days", $timezone);
        
        // Convert to UTC for database comparison
        $nowUTC = clone $now;
        $nowUTC->setTimezone(new \DateTimeZone('UTC'));
        $futureUTC = clone $future;
        $futureUTC->setTimezone(new \DateTimeZone('UTC'));

        return $this->createQueryBuilder('e')
            ->where('e.startTime >= :now AND e.startTime <= :future')
            ->setParameter('now', $nowUTC)
            ->setParameter('future', $futureUTC)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recurring events
     */
    public function findRecurringEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.isRecurring = :recurring')
            ->setParameter('recurring', true)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by priority
     */
    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.priority = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.status = :status')
            ->setParameter('status', $status)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search events by title or description
     */
    public function searchEvents(string $query): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.title LIKE :query OR e.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Advanced search with multiple criteria
     */
    public function searchEventsAdvanced(array $criteria): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.office', 'o')
            ->leftJoin('e.tags', 't')
            ->leftJoin('e.creator', 'c');

        // Text search in title and description
        if (!empty($criteria['query'])) {
            $qb->andWhere('e.title LIKE :query OR e.description LIKE :query OR e.location LIKE :query')
               ->setParameter('query', '%' . $criteria['query'] . '%');
        }

        // Office filter
        if (!empty($criteria['office_ids']) && is_array($criteria['office_ids'])) {
            $qb->andWhere('e.office IN (:office_ids)')
               ->setParameter('office_ids', $criteria['office_ids']);
        }

        // Date range filter
        if (!empty($criteria['start_date'])) {
            $startDate = $criteria['start_date'] instanceof \DateTimeInterface 
                ? $criteria['start_date'] 
                : new \DateTime($criteria['start_date']);
            $qb->andWhere('e.endTime >= :start_date')
               ->setParameter('start_date', $startDate);
        }

        if (!empty($criteria['end_date'])) {
            $endDate = $criteria['end_date'] instanceof \DateTimeInterface 
                ? $criteria['end_date'] 
                : new \DateTime($criteria['end_date']);
            $qb->andWhere('e.startTime <= :end_date')
               ->setParameter('end_date', $endDate);
        }

        // Tag filter
        if (!empty($criteria['tags']) && is_array($criteria['tags'])) {
            $qb->andWhere('t.name IN (:tags)')
               ->setParameter('tags', $criteria['tags']);
        }

        // Priority filter
        if (!empty($criteria['priority'])) {
            $qb->andWhere('e.priority = :priority')
               ->setParameter('priority', $criteria['priority']);
        }

        // Status filter
        if (!empty($criteria['status'])) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        // Creator filter
        if (!empty($criteria['creator_id'])) {
            $qb->andWhere('e.creator = :creator_id')
               ->setParameter('creator_id', $criteria['creator_id']);
        }

        // Recurring events filter
        if (isset($criteria['is_recurring'])) {
            $qb->andWhere('e.isRecurring = :is_recurring')
               ->setParameter('is_recurring', (bool)$criteria['is_recurring']);
        }

        // All day events filter
        if (isset($criteria['is_all_day'])) {
            $qb->andWhere('e.isAllDay = :is_all_day')
               ->setParameter('is_all_day', (bool)$criteria['is_all_day']);
        }

        return $qb->orderBy('e.startTime', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find events with specific tags
     */
    public function findByTags(array $tagNames): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.tags', 't')
            ->where('t.name IN (:tagNames)')
            ->setParameter('tagNames', $tagNames)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get event statistics
     */
    public function getEventStatistics(): array
    {
        // Use Asia/Manila timezone to determine date ranges
        $timezone = new \DateTimeZone('Asia/Manila');
        
        $qb = $this->createQueryBuilder('e');
        
        $total = $qb->select('COUNT(e.id)')
                   ->getQuery()
                   ->getSingleScalarResult();

        // Today's events
        $today = new \DateTime('today', $timezone);
        $tomorrow = new \DateTime('tomorrow', $timezone);
        $todayUTC = clone $today;
        $todayUTC->setTimezone(new \DateTimeZone('UTC'));
        $tomorrowUTC = clone $tomorrow;
        $tomorrowUTC->setTimezone(new \DateTimeZone('UTC'));
        
        $todayCount = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.startTime >= :today AND e.startTime < :tomorrow')
            ->setParameter('today', $todayUTC)
            ->setParameter('tomorrow', $tomorrowUTC)
            ->getQuery()
            ->getSingleScalarResult();

        // This week's events
        $thisWeek = new \DateTime('monday this week', $timezone);
        $nextWeek = new \DateTime('monday next week', $timezone);
        $thisWeekUTC = clone $thisWeek;
        $thisWeekUTC->setTimezone(new \DateTimeZone('UTC'));
        $nextWeekUTC = clone $nextWeek;
        $nextWeekUTC->setTimezone(new \DateTimeZone('UTC'));
        
        $weekCount = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.startTime >= :thisWeek AND e.startTime < :nextWeek')
            ->setParameter('thisWeek', $thisWeekUTC)
            ->setParameter('nextWeek', $nextWeekUTC)
            ->getQuery()
            ->getSingleScalarResult();

        $recurringCount = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.isRecurring = :recurring')
            ->setParameter('recurring', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'today' => $todayCount,
            'thisWeek' => $weekCount,
            'recurring' => $recurringCount,
        ];
    }

    /**
     * Find events by month and year
     */
    public function findByMonth(int $year, int $month): array
    {
        $start = new \DateTime("{$year}-{$month}-01");
        $end = clone $start;
        $end->modify('last day of this month')->setTime(23, 59, 59);

        return $this->findByDateRange($start, $end);
    }

    /**
     * Find events that are currently happening
     */
    public function findCurrentEvents(): array
    {
        // Use Asia/Manila timezone to determine "now"
        $timezone = new \DateTimeZone('Asia/Manila');
        $now = new \DateTime('now', $timezone);
        
        // Convert to UTC for database comparison
        $nowUTC = clone $now;
        $nowUTC->setTimezone(new \DateTimeZone('UTC'));
        
        return $this->createQueryBuilder('e')
            ->where('e.startTime <= :now AND e.endTime >= :now')
            ->setParameter('now', $nowUTC)
            ->orderBy('e.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events created by a user within a date range
     */
    public function findCreatedByUserInRange(User $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.creator = :user')
            ->andWhere('e.createdAt >= :start AND e.createdAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count events for a specific date
     */
    public function countEventsForDate(\DateTimeInterface $date): int
    {
        $startOfDay = clone $date;
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = clone $date;
        $endOfDay->setTime(23, 59, 59);

        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.startTime >= :start AND e.startTime <= :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count upcoming events from a given date
     */
    public function countUpcomingEvents(\DateTimeInterface $fromDate): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.startTime >= :fromDate')
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count past events before a given date
     */
    public function countPastEvents(\DateTimeInterface $beforeDate): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.startTime < :beforeDate')
            ->setParameter('beforeDate', $beforeDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}