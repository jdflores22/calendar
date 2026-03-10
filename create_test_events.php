<?php

require_once 'vendor/autoload.php';

use App\Entity\Event;
use App\Entity\User;
use App\Entity\Office;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Bootstrap Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get('doctrine.orm.entity_manager');

// Get the first user and office for testing
$userRepository = $entityManager->getRepository(User::class);
$officeRepository = $entityManager->getRepository(Office::class);

$user = $userRepository->findOneBy([]);
$office = $officeRepository->findOneBy([]);

if (!$user) {
    echo "No users found. Please create a user first.\n";
    exit(1);
}

if (!$office) {
    echo "No offices found. Please create an office first.\n";
    exit(1);
}

// Create test events for conflict detection
$testEvents = [
    [
        'title' => 'Morning Meeting',
        'description' => 'Daily standup meeting',
        'start' => new DateTime('2026-02-03 09:00:00'),
        'end' => new DateTime('2026-02-03 10:00:00'),
        'location' => 'Conference Room A',
    ],
    [
        'title' => 'Project Review',
        'description' => 'Weekly project review session',
        'start' => new DateTime('2026-02-03 14:00:00'),
        'end' => new DateTime('2026-02-03 15:30:00'),
        'location' => 'Meeting Room B',
    ],
    [
        'title' => 'Training Session',
        'description' => 'Employee training on new procedures',
        'start' => new DateTime('2026-02-04 10:00:00'),
        'end' => new DateTime('2026-02-04 12:00:00'),
        'location' => 'Training Room',
    ],
    [
        'title' => 'Client Presentation',
        'description' => 'Quarterly client presentation',
        'start' => new DateTime('2026-02-04 15:00:00'),
        'end' => new DateTime('2026-02-04 16:30:00'),
        'location' => 'Main Conference Room',
    ],
    [
        'title' => 'Team Building',
        'description' => 'Monthly team building activity',
        'start' => new DateTime('2026-02-05 13:00:00'),
        'end' => new DateTime('2026-02-05 17:00:00'),
        'location' => 'Recreation Area',
    ]
];

echo "Creating test events for conflict detection...\n";

foreach ($testEvents as $eventData) {
    $event = new Event();
    $event->setTitle($eventData['title']);
    $event->setDescription($eventData['description']);
    $event->setStartTime($eventData['start']);
    $event->setEndTime($eventData['end']);
    $event->setLocation($eventData['location']);
    $event->setCreator($user);
    $event->setOffice($office);
    $event->setColor($office->getColor() ?? '#3B82F6');
    $event->setPriority('normal');
    $event->setStatus('confirmed');
    $event->setAllDay(false);
    $event->setRecurring(false);
    
    $entityManager->persist($event);
    
    echo "Created: {$eventData['title']} ({$eventData['start']->format('Y-m-d H:i')} - {$eventData['end']->format('Y-m-d H:i')})\n";
}

$entityManager->flush();

echo "\nTest events created successfully!\n";
echo "You can now test conflict detection with these time slots:\n";
echo "- 2026-02-03 09:30-10:30 (should conflict with Morning Meeting)\n";
echo "- 2026-02-03 14:30-15:00 (should conflict with Project Review)\n";
echo "- 2026-02-04 11:00-13:00 (should conflict with Training Session)\n";
echo "- 2026-02-04 15:30-16:00 (should conflict with Client Presentation)\n";
echo "- 2026-02-05 14:00-16:00 (should conflict with Team Building)\n";