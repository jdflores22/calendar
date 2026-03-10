<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\OfficeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private OfficeRepository $officeRepository
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Get today's events
        $todaysEvents = $this->eventRepository->findTodaysEvents();
        
        // Get upcoming events (next 7 days)
        $upcomingEvents = $this->eventRepository->findUpcomingEvents(7);
        
        // Get current events (happening now)
        $currentEvents = $this->eventRepository->findCurrentEvents();
        
        // Get all offices for color legend
        $offices = $this->officeRepository->findAll();
        
        // Get event statistics
        $statistics = $this->eventRepository->getEventStatistics();
        
        // Create notifications array
        $notifications = $this->generateNotifications($user, $todaysEvents, $currentEvents);
        
        return $this->render('dashboard/index.html.twig', [
            'todaysEvents' => $todaysEvents,
            'upcomingEvents' => $upcomingEvents,
            'currentEvents' => $currentEvents,
            'offices' => $offices,
            'statistics' => $statistics,
            'notifications' => $notifications,
        ]);
    }

    private function generateNotifications($user, array $todaysEvents, array $currentEvents): array
    {
        $notifications = [];
        
        // Current events notification
        if (!empty($currentEvents)) {
            $eventTitles = array_map(fn($event) => $event->getTitle(), $currentEvents);
            $notifications[] = [
                'type' => 'info',
                'title' => 'Events Happening Now',
                'message' => 'Currently: ' . implode(', ', $eventTitles),
                'icon' => 'clock'
            ];
        }
        
        // Today's events notification
        if (!empty($todaysEvents)) {
            $notifications[] = [
                'type' => 'success',
                'title' => 'Today\'s Schedule',
                'message' => sprintf('You have %d event%s scheduled for today', 
                    count($todaysEvents), 
                    count($todaysEvents) === 1 ? '' : 's'
                ),
                'icon' => 'calendar'
            ];
        }
        
        // Profile completion check
        if ($user && $user->getProfile() && !$user->getProfile()->isComplete()) {
            $notifications[] = [
                'type' => 'warning',
                'title' => 'Profile Incomplete',
                'message' => 'Please complete your profile to access all features',
                'icon' => 'user',
                'action' => [
                    'url' => $this->generateUrl('app_profile_complete'),
                    'text' => 'Complete Profile'
                ]
            ];
        }
        
        return $notifications;
    }
}