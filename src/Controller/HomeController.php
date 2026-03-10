<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\OfficeRepository;
use App\Repository\OfficeClusterRepository;
use App\Service\TimezoneService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private OfficeRepository $officeRepository,
        private OfficeClusterRepository $clusterRepository,
        private TimezoneService $timezoneService
    ) {}

    #[Route('/', name: 'app_home')]
    #[Route('/home', name: 'app_home_alt')]
    public function index(): Response
    {
        // Get all confirmed events for public viewing
        $events = $this->eventRepository->findBy(
            ['status' => 'confirmed'],
            ['startTime' => 'ASC']
        );

        // Format events for JavaScript
        $formattedEvents = array_map(function($event) {
            $office = $event->getOffice();
            $cluster = $office ? $office->getCluster() : null;
            $creator = $event->getCreator();
            
            // Build host information
            $hostInfo = [
                'full' => $creator ? $creator->getOrganizationalUnit() : 'Unknown',
                'cluster_code' => null,
                'office_code' => null,
                'office_name' => null,
                'division_name' => null,
                'has_division' => false,
            ];
            
            if ($creator && $creator->getOffice()) {
                $creatorOffice = $creator->getOffice();
                $creatorCluster = $creatorOffice->getCluster();
                
                $hostInfo['cluster_code'] = $creatorCluster ? $creatorCluster->getCode() : null;
                $hostInfo['office_code'] = $creatorOffice->getCode();
                $hostInfo['office_name'] = $creatorOffice->getName();
                
                if ($creator->getDivision()) {
                    $hostInfo['division_name'] = $creator->getDivision()->getName();
                    $hostInfo['has_division'] = true;
                }
            }
            
            return [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'location' => $event->getLocation(),
                'startTime' => $this->timezoneService->convertFromUtc($event->getStartTime())->format('Y-m-d H:i:s'),
                'endTime' => $this->timezoneService->convertFromUtc($event->getEndTime())->format('Y-m-d H:i:s'),
                'allDay' => $event->isAllDay(),
                'status' => $event->getStatus(),
                'priority' => $event->getPriority(),
                'effectiveColor' => $event->getEffectiveColor(),
                'isRecurring' => $event->isRecurring(),
                'meetingType' => $event->getMeetingType(),
                'zoomLink' => $event->getZoomLink(),
                'office_id' => $office ? $office->getId() : null,
                'office_name' => $office ? $office->getName() : null,
                'cluster_id' => $cluster ? $cluster->getId() : null,
                'cluster_name' => $cluster ? $cluster->getName() : null,
                'host_info' => $hostInfo,
                'attachments' => $event->getAttachments()->map(function($attachment) use ($event) {
                    return [
                        'id' => $attachment->getId(),
                        'originalName' => $attachment->getOriginalName(),
                        'filename' => $attachment->getFilename(),
                        'fileSize' => $attachment->getFormattedFileSize(),
                        'mimeType' => $attachment->getMimeType(),
                        'isImage' => $attachment->isImage(),
                        'isDocument' => $attachment->isDocument(),
                        'extension' => $attachment->getFileExtension(),
                        'downloadUrl' => '/uploads/events/' . $event->getId() . '/' . $attachment->getFilename(),
                    ];
                })->toArray(),
            ];
        }, $events);
        
        // Get all clusters with offices and divisions for legend
        $clusters = $this->clusterRepository->findAllWithOffices();

        return $this->render('home/index.html.twig', [
            'events' => $formattedEvents,
            'clusters' => $clusters,
        ]);
    }
}
