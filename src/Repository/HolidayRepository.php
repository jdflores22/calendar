<?php

namespace App\Repository;

use App\Entity\Holiday;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Holiday>
 */
class HolidayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Holiday::class);
    }

    /**
     * Find holidays within a date range
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.date >= :start AND h.date <= :end')
            ->andWhere('h.isActive = :active')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find holidays for a specific year
     */
    public function findByYear(int $year): array
    {
        $startDate = new \DateTime("{$year}-01-01");
        $endDate = new \DateTime("{$year}-12-31");

        return $this->findByDateRange($startDate, $endDate);
    }

    /**
     * Find holidays for current year
     */
    public function findForCurrentYear(): array
    {
        return $this->findByYear((int)date('Y'));
    }

    /**
     * Find holidays by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.type = :type')
            ->andWhere('h.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find holidays by country
     */
    public function findByCountry(string $country): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.country = :country')
            ->andWhere('h.isActive = :active')
            ->setParameter('country', $country)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find holidays by region
     */
    public function findByRegion(string $region): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.region = :region')
            ->andWhere('h.isActive = :active')
            ->setParameter('region', $region)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recurring holidays
     */
    public function findRecurringHolidays(): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.isRecurring = :recurring')
            ->andWhere('h.isActive = :active')
            ->setParameter('recurring', true)
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find holidays happening today
     */
    public function findTodaysHolidays(): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->findByDateRange($today, $tomorrow);
    }

    /**
     * Find upcoming holidays (next 30 days)
     */
    public function findUpcomingHolidays(int $days = 30): array
    {
        $now = new \DateTime();
        $future = new \DateTime("+{$days} days");

        return $this->findByDateRange($now, $future);
    }

    /**
     * Search holidays by name
     */
    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.name LIKE :query OR h.description LIKE :query')
            ->andWhere('h.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find holidays that conflict with a specific date
     */
    public function findConflictingHolidays(\DateTimeInterface $date): array
    {
        $startOfDay = clone $date;
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = clone $date;
        $endOfDay->setTime(23, 59, 59);

        return $this->findByDateRange($startOfDay, $endOfDay);
    }

    /**
     * Get holiday statistics
     */
    public function getHolidayStatistics(): array
    {
        $qb = $this->createQueryBuilder('h');
        
        $total = $qb->select('COUNT(h.id)')
                   ->where('h.isActive = :active')
                   ->setParameter('active', true)
                   ->getQuery()
                   ->getSingleScalarResult();

        $currentYear = (int)date('Y');
        $thisYearCount = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('YEAR(h.date) = :year')
            ->andWhere('h.isActive = :active')
            ->setParameter('year', $currentYear)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $recurringCount = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.isRecurring = :recurring')
            ->andWhere('h.isActive = :active')
            ->setParameter('recurring', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $typeStats = $this->createQueryBuilder('h')
            ->select('h.type, COUNT(h.id) as count')
            ->where('h.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('h.type')
            ->getQuery()
            ->getResult();

        return [
            'total' => $total,
            'thisYear' => $thisYearCount,
            'recurring' => $recurringCount,
            'byType' => $typeStats,
        ];
    }

    /**
     * Find holidays by month
     */
    public function findByMonth(int $year, int $month): array
    {
        $start = new \DateTime("{$year}-{$month}-01");
        $end = clone $start;
        $end->modify('last day of this month')->setTime(23, 59, 59);

        return $this->findByDateRange($start, $end);
    }

    /**
     * Get holidays for calendar display (formatted for FullCalendar)
     */
    public function getHolidaysForCalendar(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $holidays = $this->findByDateRange($start, $end);
        $calendarHolidays = [];

        foreach ($holidays as $holiday) {
            $calendarHolidays[] = [
                'id' => 'holiday_' . $holiday->getId(),
                'title' => $holiday->getName(),
                'start' => $holiday->getDate()->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => $holiday->getColor(),
                'borderColor' => $holiday->getColor(),
                'textColor' => $this->getContrastColor($holiday->getColor()),
                'display' => 'background', // Display as background event
                'extendedProps' => [
                    'type' => 'holiday',
                    'holidayType' => $holiday->getType(),
                    'description' => $holiday->getDescription(),
                    'country' => $holiday->getCountry(),
                    'region' => $holiday->getRegion(),
                    'isRecurring' => $holiday->isRecurring(),
                    'typeDisplayName' => $holiday->getTypeDisplayName(),
                ],
            ];
        }

        return $calendarHolidays;
    }

    /**
     * Get contrast color for text readability
     */
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
}