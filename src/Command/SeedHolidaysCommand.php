<?php

namespace App\Command;

use App\Entity\Holiday;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-holidays',
    description: 'Seed the database with Philippine holidays',
)]
class SeedHolidaysCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('year', 'y', InputOption::VALUE_OPTIONAL, 'Year to seed holidays for', date('Y'))
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear existing holidays before seeding')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $year = (int) $input->getOption('year');
        $clear = $input->getOption('clear');

        if ($clear) {
            $io->note('Clearing existing holidays...');
            $this->entityManager->createQuery('DELETE FROM App\Entity\Holiday')->execute();
        }

        $io->title("Seeding Philippine holidays for {$year}");

        $holidays = $this->getPhilippineHolidays($year);

        foreach ($holidays as $holidayData) {
            $holiday = new Holiday();
            $holiday->setName($holidayData['name']);
            $holiday->setDate(new \DateTime($holidayData['date']));
            $holiday->setDescription($holidayData['description'] ?? null);
            $holiday->setType($holidayData['type']);
            $holiday->setColor($holidayData['color']);
            $holiday->setRecurring($holidayData['recurring'] ?? false);
            $holiday->setYear($year);
            $holiday->setCountry('Philippines');
            $holiday->setRegion($holidayData['region'] ?? null);

            if (isset($holidayData['recurrence_pattern'])) {
                $holiday->setRecurrencePattern($holidayData['recurrence_pattern']);
            }

            $this->entityManager->persist($holiday);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully seeded %d holidays for %d', count($holidays), $year));

        return Command::SUCCESS;
    }

    private function getPhilippineHolidays(int $year): array
    {
        return [
            // Regular Holidays (National)
            [
                'name' => 'New Year\'s Day',
                'date' => "{$year}-01-01",
                'type' => 'national',
                'color' => '#FF6B6B',
                'description' => 'The first day of the year in the Gregorian calendar',
                'recurring' => true,
            ],
            [
                'name' => 'Maundy Thursday',
                'date' => $this->getEasterDate($year, -3), // 3 days before Easter
                'type' => 'national',
                'color' => '#9B59B6',
                'description' => 'Christian holy day commemorating the Last Supper',
            ],
            [
                'name' => 'Good Friday',
                'date' => $this->getEasterDate($year, -2), // 2 days before Easter
                'type' => 'national',
                'color' => '#8E44AD',
                'description' => 'Christian holy day commemorating the crucifixion of Jesus',
            ],
            [
                'name' => 'Araw ng Kagitingan (Day of Valor)',
                'date' => "{$year}-04-09",
                'type' => 'national',
                'color' => '#E74C3C',
                'description' => 'Commemorates the fall of Bataan during World War II',
                'recurring' => true,
            ],
            [
                'name' => 'Labor Day',
                'date' => "{$year}-05-01",
                'type' => 'national',
                'color' => '#F39C12',
                'description' => 'International Workers\' Day',
                'recurring' => true,
            ],
            [
                'name' => 'Independence Day',
                'date' => "{$year}-06-12",
                'type' => 'national',
                'color' => '#3498DB',
                'description' => 'Commemorates Philippine independence from Spain in 1898',
                'recurring' => true,
            ],
            [
                'name' => 'National Heroes Day',
                'date' => $this->getLastMondayOfAugust($year),
                'type' => 'national',
                'color' => '#2ECC71',
                'description' => 'Honors Filipino heroes and their sacrifices',
                'recurring' => true,
            ],
            [
                'name' => 'Bonifacio Day',
                'date' => "{$year}-11-30",
                'type' => 'national',
                'color' => '#E67E22',
                'description' => 'Commemorates the birth of Andres Bonifacio',
                'recurring' => true,
            ],
            [
                'name' => 'Christmas Day',
                'date' => "{$year}-12-25",
                'type' => 'national',
                'color' => '#C0392B',
                'description' => 'Christian celebration of the birth of Jesus Christ',
                'recurring' => true,
            ],
            [
                'name' => 'Rizal Day',
                'date' => "{$year}-12-30",
                'type' => 'national',
                'color' => '#16A085',
                'description' => 'Commemorates the execution of José Rizal',
                'recurring' => true,
            ],

            // Special Non-Working Holidays
            [
                'name' => 'Chinese New Year',
                'date' => $this->getChineseNewYear($year),
                'type' => 'observance',
                'color' => '#F1C40F',
                'description' => 'Traditional Chinese New Year celebration',
            ],
            [
                'name' => 'EDSA People Power Revolution Anniversary',
                'date' => "{$year}-02-25",
                'type' => 'observance',
                'color' => '#9B59B6',
                'description' => 'Commemorates the 1986 People Power Revolution',
                'recurring' => true,
            ],
            [
                'name' => 'Black Saturday',
                'date' => $this->getEasterDate($year, -1), // 1 day before Easter
                'type' => 'observance',
                'color' => '#34495E',
                'description' => 'Christian holy day between Good Friday and Easter',
            ],
            [
                'name' => 'Ninoy Aquino Day',
                'date' => "{$year}-08-21",
                'type' => 'observance',
                'color' => '#F39C12',
                'description' => 'Commemorates the assassination of Benigno Aquino Jr.',
                'recurring' => true,
            ],
            [
                'name' => 'All Saints\' Day',
                'date' => "{$year}-11-01",
                'type' => 'observance',
                'color' => '#95A5A6',
                'description' => 'Christian feast day honoring all saints',
                'recurring' => true,
            ],
            [
                'name' => 'All Souls\' Day',
                'date' => "{$year}-11-02",
                'type' => 'observance',
                'color' => '#7F8C8D',
                'description' => 'Christian day of prayer for the souls of the dead',
                'recurring' => true,
            ],
            [
                'name' => 'Immaculate Conception',
                'date' => "{$year}-12-08",
                'type' => 'observance',
                'color' => '#3498DB',
                'description' => 'Catholic feast of the Immaculate Conception',
                'recurring' => true,
            ],
            [
                'name' => 'New Year\'s Eve',
                'date' => "{$year}-12-31",
                'type' => 'observance',
                'color' => '#E74C3C',
                'description' => 'Last day of the year',
                'recurring' => true,
            ],
        ];
    }

    private function getEasterDate(int $year, int $offset = 0): string
    {
        $easter = easter_date($year);
        $easterDateTime = new \DateTime();
        $easterDateTime->setTimestamp($easter);
        
        if ($offset !== 0) {
            $easterDateTime->modify("{$offset} days");
        }
        
        return $easterDateTime->format('Y-m-d');
    }

    private function getLastMondayOfAugust(int $year): string
    {
        $date = new \DateTime("last monday of august {$year}");
        return $date->format('Y-m-d');
    }

    private function getChineseNewYear(int $year): string
    {
        // Simplified Chinese New Year dates (approximate)
        $chineseNewYearDates = [
            2024 => '02-10',
            2025 => '01-29',
            2026 => '02-17',
            2027 => '02-06',
            2028 => '01-26',
            2029 => '02-13',
            2030 => '02-03',
        ];

        $date = $chineseNewYearDates[$year] ?? '02-01'; // Default fallback
        return "{$year}-{$date}";
    }
}