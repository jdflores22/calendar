<?php

require_once 'vendor/autoload.php';

use App\Kernel;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\Office;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env');

// Boot Symfony kernel
$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get entity manager
$entityManager = $container->get('doctrine.orm.entity_manager');

// Get repositories
$userRepository = $entityManager->getRepository(User::class);
$officeRepository = $entityManager->getRepository(Office::class);
$eventRepository = $entityManager->getRepository(Event::class);

// Get admin user and OSEC office
$adminUser = $userRepository->findOneBy(['email' => 'admin@tesda.gov.ph']);
$osecOffice = $officeRepository->findOneBy(['code' => 'OSEC']);

if (!$adminUser || !$osecOffice) {
    echo "Error: Admin user or OSEC office not found. Please run seeding first.\n";
    exit(1);
}

// Get an existing event to create conflicts with
$existingEvent = $eventRepository->findOneBy(['title' => 'Monthly Directors Meeting']);

if (!$existingEvent) {
    echo "Error: No existing event found to create conflicts with.\n";
    exit(1);
}

echo "Creating conflicting events...\n";

// Create conflicting events with the same time as existing event
$conflictingEvents = [
    [
        'title' => 'Conflicting Meeting #1',
        'description' => 'This meeting conflicts with the Monthly Directors Meeting',
        'location' => 'Conference Room B',
        'priority' => 'high'
    ],
    [
        'title' => 'Overlapping Workshop',
        'description' => 'This workshop overlaps with existing events',
        'location' => 'Training Room A',
        'priority' => 'medium'
    ],
    [
        'title' => 'Emergency Session',
        'description' => 'Emergency session that conflicts with scheduled meeting',
        'location' => 'Executive Office',
        'priority' => 'urgent'
    ]
];

foreach ($conflictingEvents as $eventData) {
    // Create event with same time as existing event
    $event = new Event();
    $event->setTitle($eventData['title']);
    $event->setStartTime($existingEvent->getStartTime());
    $event->setEndTime($existingEvent->getEndTime());
    $event->setDescription($eventData['description']);
    $event->setLocation($eventData['location']);
    $event->setPriority($eventData['priority']);
    $event->setStatus('confirmed');
    $event->setCreator($adminUser);
    $event->setOffice($osecOffice);
    $event->setColor($osecOffice->getColor());
    
    $entityManager->persist($event);
    
    echo "Created: {$eventData['title']} at {$existingEvent->getStartTime()->format('Y-m-d H:i')}\n";
}

// Also create some events for February 2nd to test current date conflicts
$feb2Start = new \DateTime('2026-02-02 10:00:00');
$feb2End = new \DateTime('2026-02-02 11:00:00');

$feb2Events = [
    [
        'title' => 'Feb 2 Meeting A',
        'description' => 'First meeting on Feb 2',
        'location' => 'Room A',
        'priority' => 'normal'
    ],
    [
        'title' => 'Feb 2 Meeting B',
        'description' => 'Second meeting on Feb 2 - conflicts with Meeting A',
        'location' => 'Room B',
        'priority' => 'high'
    ],
    [
        'title' => 'Feb 2 Workshop',
        'description' => 'Workshop on Feb 2 - also conflicts',
        'location' => 'Workshop Room',
        'priority' => 'medium'
    ]
];

foreach ($feb2Events as $eventData) {
    $event = new Event();
    $event->setTitle($eventData['title']);
    $event->setStartTime(clone $feb2Start);
    $event->setEndTime(clone $feb2End);
    $event->setDescription($eventData['description']);
    $event->setLocation($eventData['location']);
    $event->setPriority($eventData['priority']);
    $event->setStatus('confirmed');
    $event->setCreator($adminUser);
    $event->setOffice($osecOffice);
    $event->setColor($osecOffice->getColor());
    
    $entityManager->persist($event);
    
    echo "Created: {$eventData['title']} at {$feb2Start->format('Y-m-d H:i')}\n";
}

// Flush all changes
$entityManager->flush();

echo "\nConflicting events created successfully!\n";
echo "Total events in database: " . count($eventRepository->findAll()) . "\n";

// Show events for February 2026
echo "\nEvents for February 2026:\n";
$februaryEvents = $eventRepository->createQueryBuilder('e')
    ->where('e.startTime >= :start')
    ->andWhere('e.startTime < :end')
    ->setParameter('start', new \DateTime('2026-02-01'))
    ->setParameter('end', new \DateTime('2026-03-01'))
    ->orderBy('e.startTime', 'ASC')
    ->getQuery()
    ->getResult();

foreach ($februaryEvents as $event) {
    echo "- {$event->getTitle()} ({$event->getStartTime()->format('M j, Y H:i')} - {$event->getEndTime()->format('H:i')})\n";
}

echo "\nYou can now test the calendar at: http://127.0.0.1:8000/calendar\n";