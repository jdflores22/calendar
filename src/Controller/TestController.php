<?php

namespace App\Controller;

use App\Entity\Event;
use App\Service\TimezoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test-filters', name: 'test_filters')]
    public function testFilters(EntityManagerInterface $entityManager): Response
    {
        // Get Event 76
        $event = $entityManager->getRepository(Event::class)->find(76);
        
        if (!$event) {
            throw $this->createNotFoundException('Event 76 not found');
        }
        
        return $this->render('test_filters.html.twig', [
            'event' => $event,
        ]);
    }
    
    #[Route('/test-timezone', name: 'test_timezone')]
    public function testTimezone(EntityManagerInterface $entityManager, TimezoneService $timezoneService): Response
    {
        // Get Event 76
        $event = $entityManager->getRepository(Event::class)->find(76);
        
        if (!$event) {
            return new Response('<h1>Event 76 not found</h1>', 404);
        }
        
        $startTime = $event->getStartTime();
        
        $html = '<h1>🧪 Timezone Test Results</h1>';
        $html .= '<h2>PHP Configuration</h2>';
        $html .= '<table border="1" style="border-collapse: collapse; margin: 10px 0;">';
        $html .= '<tr><th>Setting</th><th>Value</th></tr>';
        $html .= '<tr><td>PHP Default Timezone</td><td><strong>' . date_default_timezone_get() . '</strong></td></tr>';
        $html .= '<tr><td>Current Time</td><td>' . date('Y-m-d H:i:s T') . '</td></tr>';
        $html .= '</table>';
        
        $html .= '<h2>Event 76 DateTime Analysis</h2>';
        $html .= '<table border="1" style="border-collapse: collapse; margin: 10px 0;">';
        $html .= '<tr><th>Property</th><th>Value</th><th>Timezone</th></tr>';
        $html .= '<tr><td>Raw startTime</td><td>' . $startTime->format('Y-m-d H:i:s') . '</td><td>' . $startTime->getTimezone()->getName() . '</td></tr>';
        $html .= '</table>';
        
        $html .= '<h2>TimezoneService Results</h2>';
        $html .= '<table border="1" style="border-collapse: collapse; margin: 10px 0;">';
        $html .= '<tr><th>Method</th><th>Result</th><th>Expected</th><th>Status</th></tr>';
        
        $frontendFormat = $timezoneService->formatForFrontend($startTime);
        $displayFormat = $timezoneService->formatForDisplay($startTime, 'l, F j, Y g:i A');
        
        $frontendStatus = ($frontendFormat === '2026-02-12T00:00') ? '✅ PASS' : '❌ FAIL';
        $displayStatus = (strpos($displayFormat, '12:00 AM') !== false) ? '✅ PASS' : '❌ FAIL';
        
        $html .= '<tr><td>formatForFrontend</td><td><strong>' . $frontendFormat . '</strong></td><td>2026-02-12T00:00</td><td>' . $frontendStatus . '</td></tr>';
        $html .= '<tr><td>formatForDisplay</td><td><strong>' . $displayFormat . '</strong></td><td>Wednesday, February 12, 2026 12:00 AM</td><td>' . $displayStatus . '</td></tr>';
        $html .= '</table>';
        
        $html .= '<h2>Twig Filter Test</h2>';
        
        // Test Twig filters
        $template = $this->renderView('test_timezone_inline.html.twig', ['startTime' => $startTime]);
        $html .= $template;
        
        $html .= '<h2>🔗 Test Links</h2>';
        $html .= '<ul>';
        $html .= '<li><a href="/events/76" target="_blank">Event 76 Details</a></li>';
        $html .= '<li><a href="/events/76/edit" target="_blank">Event 76 Edit</a></li>';
        $html .= '<li><a href="/calendar" target="_blank">Calendar</a></li>';
        $html .= '</ul>';
        
        return new Response($html);
    }
}