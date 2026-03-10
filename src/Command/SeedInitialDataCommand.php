<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Entity\Office;
use App\Entity\Event;
use App\Entity\EventTag;
use App\Entity\DirectoryContact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-initial-data',
    description: 'Seed the database with initial system data including offices, users, and sample events',
)]
class SeedInitialDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear existing data before seeding')
            ->addOption('users-only', null, InputOption::VALUE_NONE, 'Seed only users and profiles')
            ->addOption('offices-only', null, InputOption::VALUE_NONE, 'Seed only offices')
            ->addOption('events-only', null, InputOption::VALUE_NONE, 'Seed only sample events')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clear = $input->getOption('clear');
        $usersOnly = $input->getOption('users-only');
        $officesOnly = $input->getOption('offices-only');
        $eventsOnly = $input->getOption('events-only');

        if ($clear) {
            $io->note('Clearing existing data...');
            $this->clearExistingData();
        }

        $io->title('Seeding TESDA Calendar System Initial Data');

        // Seed offices first (required for users)
        if (!$usersOnly && !$eventsOnly) {
            $io->section('Creating default offices...');
            $offices = $this->seedOffices();
            $io->success(sprintf('Created %d offices', count($offices)));
        } else {
            $offices = $this->getExistingOffices();
        }

        // Seed users and profiles
        if (!$officesOnly && !$eventsOnly) {
            $io->section('Creating default users and profiles...');
            $users = $this->seedUsers($offices);
            $io->success(sprintf('Created %d users with profiles', count($users)));
        } else {
            $users = $this->getExistingUsers();
        }

        // Seed sample events
        if (!$usersOnly && !$officesOnly) {
            $io->section('Creating sample events...');
            $events = $this->seedEvents($users, $offices);
            $io->success(sprintf('Created %d sample events', count($events)));
        }

        // Seed directory contacts
        if (!$usersOnly && !$eventsOnly && !$officesOnly) {
            $io->section('Creating directory contacts...');
            $contacts = $this->seedDirectoryContacts($offices);
            $io->success(sprintf('Created %d directory contacts', count($contacts)));
        }

        $this->entityManager->flush();

        $io->success('Database seeding completed successfully!');
        $io->note('Default admin credentials: admin@tesda.gov.ph / admin123');

        return Command::SUCCESS;
    }

    private function clearExistingData(): void
    {
        // Disable foreign key checks temporarily
        $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        
        try {
            // Clear in reverse dependency order
            $this->entityManager->createQuery('DELETE FROM App\Entity\Event')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\DirectoryContact')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\EventTag')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\UserProfile')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
        } finally {
            // Re-enable foreign key checks
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function seedOffices(): array
    {
        $officesData = [
            [
                'name' => 'Office of the Secretary',
                'code' => 'OSEC',
                'color' => '#E74C3C',
                'description' => 'The highest office in TESDA, responsible for overall policy and direction',
                'parent' => null
            ],
            [
                'name' => 'Executive Office',
                'code' => 'EO',
                'color' => '#3498DB',
                'description' => 'Executive support and administrative functions',
                'parent' => null
            ],
            [
                'name' => 'Planning and Policy Development Division',
                'code' => 'PPDD',
                'color' => '#2ECC71',
                'description' => 'Strategic planning and policy development',
                'parent' => null
            ],
            [
                'name' => 'Technical Education and Skills Development Division',
                'code' => 'TESDD',
                'color' => '#F39C12',
                'description' => 'Technical education programs and curriculum development',
                'parent' => null
            ],
            [
                'name' => 'Regional Office IV-A (CALABARZON)',
                'code' => 'RO4A',
                'color' => '#9B59B6',
                'description' => 'Regional office covering Cavite, Laguna, Batangas, Rizal, and Quezon',
                'parent' => null
            ],
            [
                'name' => 'Regional Office NCR',
                'code' => 'NCR',
                'color' => '#1ABC9C',
                'description' => 'National Capital Region office',
                'parent' => null
            ],
            [
                'name' => 'Provincial Office - Batangas',
                'code' => 'PO-BAT',
                'color' => '#E67E22',
                'description' => 'Provincial office for Batangas',
                'parent' => 'RO4A'
            ],
            [
                'name' => 'Provincial Office - Laguna',
                'code' => 'PO-LAG',
                'color' => '#34495E',
                'description' => 'Provincial office for Laguna',
                'parent' => 'RO4A'
            ],
            [
                'name' => 'Training Center - Manila',
                'code' => 'TC-MNL',
                'color' => '#95A5A6',
                'description' => 'Main training center in Manila',
                'parent' => 'NCR'
            ],
            [
                'name' => 'Assessment Center - Quezon City',
                'code' => 'AC-QC',
                'color' => '#F1C40F',
                'description' => 'Skills assessment center in Quezon City',
                'parent' => 'NCR'
            ]
        ];

        $offices = [];
        $officeMap = [];

        // First pass: create all offices without parents
        foreach ($officesData as $data) {
            // Check if office already exists
            $existingOffice = $this->entityManager->getRepository(Office::class)
                ->findOneBy(['code' => $data['code']]);
            
            if ($existingOffice) {
                $offices[] = $existingOffice;
                $officeMap[$data['code']] = $existingOffice;
                continue;
            }
            
            $office = new Office();
            $office->setName($data['name']);
            $office->setCode($data['code']);
            $office->setColor($data['color']);
            $office->setDescription($data['description']);

            $this->entityManager->persist($office);
            $offices[] = $office;
            $officeMap[$data['code']] = $office;
        }

        $this->entityManager->flush();

        // Second pass: set parent relationships
        foreach ($officesData as $index => $data) {
            if ($data['parent'] && isset($officeMap[$data['parent']])) {
                $offices[$index]->setParent($officeMap[$data['parent']]);
            }
        }

        return $offices;
    }

    private function seedUsers(array $offices): array
    {
        $usersData = [
            [
                'email' => 'admin@tesda.gov.ph',
                'password' => 'admin123',
                'roles' => ['ROLE_ADMIN'],
                'firstName' => 'System',
                'lastName' => 'Administrator',
                'phone' => '+63-2-8631-1111',
                'office' => 'OSEC',
                'verified' => true
            ],
            [
                'email' => 'secretary@tesda.gov.ph',
                'password' => 'secretary123',
                'roles' => ['ROLE_OSEC'],
                'firstName' => 'Maria',
                'lastName' => 'Santos',
                'middleName' => 'Cruz',
                'phone' => '+63-2-8631-1100',
                'office' => 'OSEC',
                'verified' => true
            ],
            [
                'email' => 'eo.director@tesda.gov.ph',
                'password' => 'eo123',
                'roles' => ['ROLE_EO'],
                'firstName' => 'Juan',
                'lastName' => 'Dela Cruz',
                'phone' => '+63-2-8631-1200',
                'office' => 'EO',
                'verified' => true
            ],
            [
                'email' => 'ppdd.chief@tesda.gov.ph',
                'password' => 'division123',
                'roles' => ['ROLE_DIVISION'],
                'firstName' => 'Ana',
                'lastName' => 'Rodriguez',
                'phone' => '+63-2-8631-1300',
                'office' => 'PPDD',
                'verified' => true
            ],
            [
                'email' => 'tesdd.chief@tesda.gov.ph',
                'password' => 'division123',
                'roles' => ['ROLE_DIVISION'],
                'firstName' => 'Carlos',
                'lastName' => 'Mendoza',
                'phone' => '+63-2-8631-1400',
                'office' => 'TESDD',
                'verified' => true
            ],
            [
                'email' => 'ro4a.director@tesda.gov.ph',
                'password' => 'region123',
                'roles' => ['ROLE_EO'],
                'firstName' => 'Elena',
                'lastName' => 'Garcia',
                'phone' => '+63-49-531-1000',
                'office' => 'RO4A',
                'verified' => true
            ],
            [
                'email' => 'ncr.director@tesda.gov.ph',
                'password' => 'region123',
                'roles' => ['ROLE_EO'],
                'firstName' => 'Roberto',
                'lastName' => 'Villanueva',
                'phone' => '+63-2-8631-1500',
                'office' => 'NCR',
                'verified' => true
            ],
            [
                'email' => 'batangas.chief@tesda.gov.ph',
                'password' => 'province123',
                'roles' => ['ROLE_PROVINCE'],
                'firstName' => 'Lisa',
                'lastName' => 'Reyes',
                'phone' => '+63-43-723-1000',
                'office' => 'PO-BAT',
                'verified' => true
            ],
            [
                'email' => 'laguna.chief@tesda.gov.ph',
                'password' => 'province123',
                'roles' => ['ROLE_PROVINCE'],
                'firstName' => 'Miguel',
                'lastName' => 'Torres',
                'phone' => '+63-49-536-1000',
                'office' => 'PO-LAG',
                'verified' => true
            ],
            [
                'email' => 'manila.manager@tesda.gov.ph',
                'password' => 'center123',
                'roles' => ['ROLE_PROVINCE'],
                'firstName' => 'Sofia',
                'lastName' => 'Aquino',
                'phone' => '+63-2-8631-1600',
                'office' => 'TC-MNL',
                'verified' => true
            ]
        ];

        $officeMap = [];
        foreach ($offices as $office) {
            $officeMap[$office->getCode()] = $office;
        }

        $users = [];
        foreach ($usersData as $data) {
            // Check if user already exists
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $data['email']]);
            
            if ($existingUser) {
                $users[] = $existingUser;
                continue;
            }
            
            $user = new User();
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setRoles($data['roles']);
            $user->setVerified($data['verified']);

            if (isset($officeMap[$data['office']])) {
                $user->setOffice($officeMap[$data['office']]);
            }

            // Create profile
            $profile = new UserProfile();
            $profile->setFirstName($data['firstName']);
            $profile->setLastName($data['lastName']);
            if (isset($data['middleName'])) {
                $profile->setMiddleName($data['middleName']);
            }
            $profile->setPhone($data['phone']);
            $profile->setAddress('TESDA Complex, East Service Road, Taguig City');
            $profile->setAvatar('default-avatar.png');
            $profile->setUser($user);

            $user->setProfile($profile);
            
            // Manually trigger completion status check after all fields are set
            $profile->checkCompletionStatus();

            $this->entityManager->persist($user);
            $this->entityManager->persist($profile);
            $users[] = $user;
        }

        return $users;
    }

    private function seedEvents(array $users, array $offices): array
    {
        $now = new \DateTime();
        $eventsData = [
            [
                'title' => 'Monthly Directors Meeting',
                'startTime' => (clone $now)->modify('+1 day')->setTime(9, 0),
                'endTime' => (clone $now)->modify('+1 day')->setTime(11, 0),
                'description' => 'Monthly meeting of all regional directors to discuss policies and updates',
                'location' => 'TESDA Main Conference Room',
                'office' => 'OSEC',
                'creator' => 'admin@tesda.gov.ph',
                'priority' => 'high',
                'tags' => ['meeting', 'directors', 'monthly']
            ],
            [
                'title' => 'Skills Assessment Workshop',
                'startTime' => (clone $now)->modify('+2 days')->setTime(8, 0),
                'endTime' => (clone $now)->modify('+2 days')->setTime(17, 0),
                'description' => 'Workshop on new skills assessment methodologies',
                'location' => 'Training Center Manila',
                'office' => 'TC-MNL',
                'creator' => 'manila.manager@tesda.gov.ph',
                'priority' => 'normal',
                'tags' => ['workshop', 'assessment', 'training']
            ],
            [
                'title' => 'Policy Review Session',
                'startTime' => (clone $now)->modify('+3 days')->setTime(14, 0),
                'endTime' => (clone $now)->modify('+3 days')->setTime(16, 0),
                'description' => 'Review of current TESDA policies and proposed amendments',
                'location' => 'PPDD Conference Room',
                'office' => 'PPDD',
                'creator' => 'ppdd.chief@tesda.gov.ph',
                'priority' => 'high',
                'tags' => ['policy', 'review', 'planning']
            ],
            [
                'title' => 'Curriculum Development Meeting',
                'startTime' => (clone $now)->modify('+4 days')->setTime(10, 0),
                'endTime' => (clone $now)->modify('+4 days')->setTime(12, 0),
                'description' => 'Discussion on new curriculum for emerging technologies',
                'location' => 'TESDD Meeting Room',
                'office' => 'TESDD',
                'creator' => 'tesdd.chief@tesda.gov.ph',
                'priority' => 'normal',
                'tags' => ['curriculum', 'development', 'technology']
            ],
            [
                'title' => 'Regional Coordination Meeting',
                'startTime' => (clone $now)->modify('+5 days')->setTime(13, 0),
                'endTime' => (clone $now)->modify('+5 days')->setTime(15, 0),
                'description' => 'Coordination meeting for CALABARZON region activities',
                'location' => 'RO4A Conference Room',
                'office' => 'RO4A',
                'creator' => 'ro4a.director@tesda.gov.ph',
                'priority' => 'normal',
                'tags' => ['regional', 'coordination', 'calabarzon']
            ],
            [
                'title' => 'Provincial Training Planning',
                'startTime' => (clone $now)->modify('+1 week')->setTime(9, 0),
                'endTime' => (clone $now)->modify('+1 week')->setTime(11, 0),
                'description' => 'Planning session for upcoming provincial training programs',
                'location' => 'Batangas Provincial Office',
                'office' => 'PO-BAT',
                'creator' => 'batangas.chief@tesda.gov.ph',
                'priority' => 'normal',
                'tags' => ['training', 'planning', 'provincial']
            ],
            [
                'title' => 'Quality Assurance Review',
                'startTime' => (clone $now)->modify('+1 week +1 day')->setTime(14, 0),
                'endTime' => (clone $now)->modify('+1 week +1 day')->setTime(17, 0),
                'description' => 'Review of training quality assurance procedures',
                'location' => 'Assessment Center QC',
                'office' => 'AC-QC',
                'creator' => 'ncr.director@tesda.gov.ph',
                'priority' => 'high',
                'tags' => ['quality', 'assurance', 'review']
            ],
            [
                'title' => 'Budget Planning Session',
                'startTime' => (clone $now)->modify('+1 week +2 days')->setTime(10, 0),
                'endTime' => (clone $now)->modify('+1 week +2 days')->setTime(12, 0),
                'description' => 'Annual budget planning and allocation discussion',
                'location' => 'Executive Office',
                'office' => 'EO',
                'creator' => 'eo.director@tesda.gov.ph',
                'priority' => 'high',
                'tags' => ['budget', 'planning', 'finance']
            ]
        ];

        // Create tag entities first
        $tagMap = [];
        $allTags = [];
        foreach ($eventsData as $eventData) {
            $allTags = array_merge($allTags, $eventData['tags']);
        }
        $allTags = array_unique($allTags);

        foreach ($allTags as $tagName) {
            // Check if tag already exists
            $existingTag = $this->entityManager->getRepository(EventTag::class)
                ->findOneBy(['name' => $tagName]);
            
            if ($existingTag) {
                $tagMap[$tagName] = $existingTag;
                continue;
            }
            
            $tag = new EventTag();
            $tag->setName($tagName);
            $tag->setColor($this->getRandomColor());
            $this->entityManager->persist($tag);
            $tagMap[$tagName] = $tag;
        }

        // Create user and office maps
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user->getEmail()] = $user;
        }

        $officeMap = [];
        foreach ($offices as $office) {
            $officeMap[$office->getCode()] = $office;
        }

        $events = [];
        foreach ($eventsData as $data) {
            $event = new Event();
            $event->setTitle($data['title']);
            $event->setStartTime($data['startTime']);
            $event->setEndTime($data['endTime']);
            $event->setDescription($data['description']);
            $event->setLocation($data['location']);
            $event->setPriority($data['priority']);

            if (isset($userMap[$data['creator']])) {
                $event->setCreator($userMap[$data['creator']]);
            }

            if (isset($officeMap[$data['office']])) {
                $event->setOffice($officeMap[$data['office']]);
                $event->setColor($officeMap[$data['office']]->getColor());
            }

            // Add tags
            foreach ($data['tags'] as $tagName) {
                if (isset($tagMap[$tagName])) {
                    $event->addTag($tagMap[$tagName]);
                }
            }

            $this->entityManager->persist($event);
            $events[] = $event;
        }

        return $events;
    }

    private function seedDirectoryContacts(array $offices): array
    {
        $contactsData = [
            [
                'name' => 'Dr. Maria Elena Santos',
                'position' => 'Secretary',
                'email' => 'secretary@tesda.gov.ph',
                'phone' => '+63-2-8631-1100',
                'address' => 'TESDA Complex, East Service Road, Taguig City',
                'office' => 'OSEC'
            ],
            [
                'name' => 'Atty. Juan Carlos Dela Cruz',
                'position' => 'Executive Director',
                'email' => 'executive.director@tesda.gov.ph',
                'phone' => '+63-2-8631-1200',
                'address' => 'TESDA Complex, East Service Road, Taguig City',
                'office' => 'EO'
            ],
            [
                'name' => 'Dr. Ana Maria Rodriguez',
                'position' => 'Division Chief',
                'email' => 'ppdd.chief@tesda.gov.ph',
                'phone' => '+63-2-8631-1300',
                'address' => 'TESDA Complex, East Service Road, Taguig City',
                'office' => 'PPDD'
            ],
            [
                'name' => 'Engr. Carlos Miguel Mendoza',
                'position' => 'Division Chief',
                'email' => 'tesdd.chief@tesda.gov.ph',
                'phone' => '+63-2-8631-1400',
                'address' => 'TESDA Complex, East Service Road, Taguig City',
                'office' => 'TESDD'
            ],
            [
                'name' => 'Ms. Elena Garcia',
                'position' => 'Regional Director',
                'email' => 'ro4a.director@tesda.gov.ph',
                'phone' => '+63-49-531-1000',
                'address' => 'TESDA Regional Office IV-A, Calamba, Laguna',
                'office' => 'RO4A'
            ]
        ];

        $officeMap = [];
        foreach ($offices as $office) {
            $officeMap[$office->getCode()] = $office;
        }

        $contacts = [];
        foreach ($contactsData as $data) {
            $contact = new DirectoryContact();
            $contact->setName($data['name']);
            $contact->setPosition($data['position']);
            $contact->setEmail($data['email']);
            $contact->setPhone($data['phone']);
            $contact->setAddress($data['address']);

            if (isset($officeMap[$data['office']])) {
                $contact->setOffice($officeMap[$data['office']]);
            }

            $this->entityManager->persist($contact);
            $contacts[] = $contact;
        }

        return $contacts;
    }

    private function getExistingOffices(): array
    {
        return $this->entityManager->getRepository(Office::class)->findAll();
    }

    private function getExistingUsers(): array
    {
        return $this->entityManager->getRepository(User::class)->findAll();
    }

    private function getRandomColor(): string
    {
        $colors = [
            '#E74C3C', '#3498DB', '#2ECC71', '#F39C12', '#9B59B6',
            '#1ABC9C', '#E67E22', '#34495E', '#95A5A6', '#F1C40F'
        ];
        return $colors[array_rand($colors)];
    }
}