<?php

namespace App\Controller;

use App\Service\SecurityMonitoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/security')]
#[IsGranted('ROLE_ADMIN')]
class SecurityDashboardController extends AbstractController
{
    public function __construct(
        private SecurityMonitoringService $securityMonitoring
    ) {}

    #[Route('/dashboard', name: 'app_security_dashboard')]
    public function dashboard(): Response
    {
        $dashboardData = $this->securityMonitoring->getSecurityDashboard();
        
        return $this->render('security/dashboard.html.twig', [
            'dashboard_data' => $dashboardData,
        ]);
    }

    #[Route('/events', name: 'app_security_events')]
    public function events(): Response
    {
        $events = $this->securityMonitoring->getRecentSecurityEvents(100);
        
        return $this->render('security/events.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route('/alerts', name: 'app_security_alerts')]
    public function alerts(): Response
    {
        $alerts = $this->securityMonitoring->getActiveAlerts();
        
        return $this->render('security/alerts.html.twig', [
            'alerts' => $alerts,
        ]);
    }

    #[Route('/api/dashboard-data', name: 'app_security_api_dashboard', methods: ['GET'])]
    public function apiDashboardData(): JsonResponse
    {
        $dashboardData = $this->securityMonitoring->getSecurityDashboard();
        
        return new JsonResponse([
            'success' => true,
            'data' => $dashboardData
        ]);
    }

    #[Route('/api/monitor', name: 'app_security_api_monitor', methods: ['POST'])]
    public function apiMonitor(): JsonResponse
    {
        try {
            $this->securityMonitoring->monitorSecurityEvents();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Security monitoring completed'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Security monitoring failed: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/events', name: 'app_security_api_events', methods: ['GET'])]
    public function apiEvents(): JsonResponse
    {
        $events = $this->securityMonitoring->getRecentSecurityEvents(50);
        
        $formattedEvents = array_map(function($event) {
            return [
                'id' => $event->getId(),
                'action' => $event->getAction(),
                'description' => $event->getDescription(),
                'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
                'ip_address' => $event->getIpAddress(),
                'user_agent' => $event->getUserAgent(),
                'user' => $event->getUser() ? $event->getUser()->getEmail() : null,
                'data' => $event->getNewValues()
            ];
        }, $events);
        
        return new JsonResponse([
            'success' => true,
            'events' => $formattedEvents
        ]);
    }

    #[Route('/api/alerts', name: 'app_security_api_alerts', methods: ['GET'])]
    public function apiAlerts(): JsonResponse
    {
        $alerts = $this->securityMonitoring->getActiveAlerts();
        
        return new JsonResponse([
            'success' => true,
            'alerts' => $alerts
        ]);
    }
}