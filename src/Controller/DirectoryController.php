<?php

namespace App\Controller;

use App\Entity\DirectoryContact;
use App\Entity\Office;
use App\Repository\DirectoryContactRepository;
use App\Repository\OfficeRepository;
use App\Security\Voter\DirectoryVoter;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/directory')]
#[IsGranted('ROLE_ADMIN')]
class DirectoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectoryContactRepository $contactRepository,
        private OfficeRepository $officeRepository,
        private ValidatorInterface $validator,
        private AuditService $auditService
    ) {
    }

    #[Route('/', name: 'directory_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::MANAGE);

        $search = $request->query->get('search', '');
        $officeId = $request->query->get('office');

        if ($search) {
            $contacts = $this->contactRepository->searchContacts($search);
        } elseif ($officeId) {
            $office = $this->officeRepository->find($officeId);
            $contacts = $office ? $this->contactRepository->findByOffice($office) : [];
        } else {
            $contacts = $this->contactRepository->findAllWithOffices();
        }

        $offices = $this->officeRepository->findAll();

        return $this->render('directory/index.html.twig', [
            'contacts' => $contacts,
            'offices' => $offices,
            'search' => $search,
            'selectedOffice' => $officeId,
        ]);
    }

    #[Route('/contact/new', name: 'directory_contact_new', methods: ['GET', 'POST'])]
    public function newContact(Request $request): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::CREATE);

        $contact = new DirectoryContact();
        $offices = $this->officeRepository->findAll();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $contact->setName($data['name'] ?? '');
            $contact->setPosition($data['position'] ?? '');
            $contact->setEmail($data['email'] ?? '');
            $contact->setPhone($data['phone'] ?? null);
            $contact->setAddress($data['address'] ?? null);

            if (!empty($data['office_id'])) {
                $office = $this->officeRepository->find($data['office_id']);
                if ($office) {
                    $contact->setOffice($office);
                }
            }

            $errors = $this->validator->validate($contact);

            if (count($errors) === 0) {
                $this->contactRepository->save($contact, true);
                
                // Log the creation
                $this->auditService->logContactCreated($contact);
                
                $this->addFlash('success', 'Contact created successfully.');
                return $this->redirectToRoute('directory_index');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('directory/contact_form.html.twig', [
            'contact' => $contact,
            'offices' => $offices,
            'action' => 'Create',
        ]);
    }

    #[Route('/contact/{id}', name: 'directory_contact_show', methods: ['GET'])]
    public function showContact(DirectoryContact $contact): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::VIEW, $contact);

        return $this->render('directory/contact_show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/contact/{id}/edit', name: 'directory_contact_edit', methods: ['GET', 'POST'])]
    public function editContact(Request $request, DirectoryContact $contact): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::EDIT, $contact);

        $offices = $this->officeRepository->findAll();

        // Store old values for audit logging
        $oldValues = [
            'name' => $contact->getName(),
            'position' => $contact->getPosition(),
            'email' => $contact->getEmail(),
            'phone' => $contact->getPhone(),
            'address' => $contact->getAddress(),
            'office_id' => $contact->getOffice()?->getId(),
            'office_name' => $contact->getOffice()?->getName(),
        ];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $contact->setName($data['name'] ?? '');
            $contact->setPosition($data['position'] ?? '');
            $contact->setEmail($data['email'] ?? '');
            $contact->setPhone($data['phone'] ?? null);
            $contact->setAddress($data['address'] ?? null);

            if (!empty($data['office_id'])) {
                $office = $this->officeRepository->find($data['office_id']);
                if ($office) {
                    $contact->setOffice($office);
                }
            }

            $errors = $this->validator->validate($contact);

            if (count($errors) === 0) {
                $this->contactRepository->save($contact, true);
                
                // Log the update
                $this->auditService->logContactUpdated($contact, $oldValues);
                
                $this->addFlash('success', 'Contact updated successfully.');
                return $this->redirectToRoute('directory_index');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('directory/contact_form.html.twig', [
            'contact' => $contact,
            'offices' => $offices,
            'action' => 'Edit',
        ]);
    }

    #[Route('/contact/{id}/delete', name: 'directory_contact_delete', methods: ['POST'])]
    public function deleteContact(Request $request, DirectoryContact $contact): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::DELETE, $contact);

        if ($this->isCsrfTokenValid('delete' . $contact->getId(), $request->request->get('_token'))) {
            // Log the deletion before removing
            $this->auditService->logContactDeleted($contact);
            
            $this->contactRepository->remove($contact, true);
            $this->addFlash('success', 'Contact deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('directory_index');
    }

    #[Route('/offices', name: 'directory_offices', methods: ['GET'])]
    public function offices(): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::MANAGE);

        $offices = $this->officeRepository->findAll();
        $officeStats = [];

        foreach ($offices as $office) {
            $officeStats[$office->getId()] = $this->contactRepository->countByOffice($office);
        }

        return $this->render('directory/offices.html.twig', [
            'offices' => $offices,
            'officeStats' => $officeStats,
        ]);
    }

    #[Route('/office/new', name: 'directory_office_new', methods: ['GET', 'POST'])]
    public function newOffice(Request $request): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::MANAGE);

        $office = new Office();
        $clusters = $this->entityManager->getRepository(\App\Entity\OfficeCluster::class)->findAll();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $office->setName($data['name'] ?? '');
            $office->setCode($data['code'] ?? '');
            $office->setDescription($data['description'] ?? null);

            // Handle cluster assignment
            if (!empty($data['cluster_id'])) {
                $cluster = $this->entityManager->getRepository(\App\Entity\OfficeCluster::class)->find($data['cluster_id']);
                if ($cluster) {
                    $office->setCluster($cluster);
                    // Office inherits color from cluster
                    $office->setColor($cluster->getColor());
                }
            } else {
                $office->setCluster(null);
                // Use default color if no cluster
                $office->setColor('#3B82F6');
            }

            $errors = $this->validator->validate($office);

            if (count($errors) === 0) {
                $this->entityManager->persist($office);
                $this->entityManager->flush();
                
                // Log the creation
                $this->auditService->logOfficeCreated($office);
                
                $this->addFlash('success', 'Office created successfully.');
                return $this->redirectToRoute('directory_offices');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('directory/office_form.html.twig', [
            'office' => $office,
            'clusters' => $clusters,
            'action' => 'Create',
        ]);
    }

    #[Route('/office/{id}/edit', name: 'directory_office_edit', methods: ['GET', 'POST'])]
    public function editOffice(Request $request, Office $office): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::MANAGE);

        $clusters = $this->entityManager->getRepository(\App\Entity\OfficeCluster::class)->findAll();

        // Store old values for audit logging
        $oldValues = [
            'name' => $office->getName(),
            'code' => $office->getCode(),
            'color' => $office->getColor(),
            'description' => $office->getDescription(),
            'cluster_id' => $office->getCluster()?->getId(),
            'cluster_name' => $office->getCluster()?->getName(),
        ];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $office->setName($data['name'] ?? '');
            $office->setCode($data['code'] ?? '');
            $office->setDescription($data['description'] ?? null);

            // Handle cluster assignment
            if (!empty($data['cluster_id'])) {
                $cluster = $this->entityManager->getRepository(\App\Entity\OfficeCluster::class)->find($data['cluster_id']);
                if ($cluster) {
                    $office->setCluster($cluster);
                    // Office inherits color from cluster
                    $office->setColor($cluster->getColor());
                }
            } else {
                $office->setCluster(null);
                // Use default color if no cluster
                $office->setColor('#3B82F6');
            }

            $errors = $this->validator->validate($office);

            if (count($errors) === 0) {
                $this->entityManager->flush();
                
                // Log the update
                $this->auditService->logOfficeUpdated($office, $oldValues);
                
                $this->addFlash('success', 'Office updated successfully.');
                return $this->redirectToRoute('directory_offices');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('directory/office_form.html.twig', [
            'office' => $office,
            'clusters' => $clusters,
            'action' => 'Edit',
        ]);
    }

    #[Route('/office/{id}/delete', name: 'directory_office_delete', methods: ['POST'])]
    public function deleteOffice(Request $request, Office $office): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::MANAGE);

        if ($this->isCsrfTokenValid('delete' . $office->getId(), $request->request->get('_token'))) {
            // Check if office has contacts, child offices, events, or divisions
            $contactCount = $this->contactRepository->countByOffice($office);
            $childOffices = $office->getChildren();
            $eventCount = $office->getEvents()->count();
            $divisionCount = $office->getDivisions()->count();

            if ($contactCount > 0) {
                $this->addFlash('error', "Cannot delete office with existing contacts. This office has {$contactCount} contact(s). Please reassign or delete them first.");
            } elseif (count($childOffices) > 0) {
                $this->addFlash('error', "Cannot delete office with child offices. This office has " . count($childOffices) . " child office(s). Please reassign or delete them first.");
            } elseif ($eventCount > 0) {
                $this->addFlash('error', "Cannot delete office with existing events. This office has {$eventCount} event(s). Please reassign or delete them first.");
            } elseif ($divisionCount > 0) {
                $this->addFlash('error', "Cannot delete office with existing divisions. This office has {$divisionCount} division(s). Please delete them first.");
            } else {
                // Log the deletion before removing
                $this->auditService->logOfficeDeleted($office);
                
                $this->entityManager->remove($office);
                $this->entityManager->flush();
                $this->addFlash('success', 'Office deleted successfully.');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('directory_offices');
    }

    #[Route('/audit-logs', name: 'directory_audit_logs', methods: ['GET'])]
    public function auditLogs(Request $request): Response
    {
        $this->denyAccessUnlessGranted(DirectoryVoter::MANAGE);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $logs = $this->auditService->getDirectoryLogs($limit + $offset);
        $paginatedLogs = array_slice($logs, $offset, $limit);
        $hasMore = count($logs) > $offset + $limit;

        $statistics = $this->auditService->getStatistics();

        return $this->render('directory/audit_logs.html.twig', [
            'logs' => $paginatedLogs,
            'statistics' => $statistics,
            'currentPage' => $page,
            'hasMore' => $hasMore,
        ]);
    }
}