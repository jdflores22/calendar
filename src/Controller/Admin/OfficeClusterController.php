<?php

namespace App\Controller\Admin;

use App\Entity\OfficeCluster;
use App\Repository\OfficeClusterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/clusters')]
#[IsGranted('ROLE_ADMIN')]
class OfficeClusterController extends AbstractController
{
    public function __construct(
        private OfficeClusterRepository $clusterRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_cluster_index', methods: ['GET'])]
    public function index(): Response
    {
        $clusters = $this->clusterRepository->findAll();

        return $this->render('admin/cluster/index.html.twig', [
            'clusters' => $clusters,
        ]);
    }

    #[Route('/new', name: 'admin_cluster_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('cluster_form', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_cluster_new');
            }

            try {
                $cluster = new OfficeCluster();
                $cluster->setName($request->request->get('name'));
                $cluster->setCode($request->request->get('code'));
                $cluster->setDescription($request->request->get('description'));
                
                // Handle color - ensure it's a valid hex color or null
                $color = $request->request->get('color');
                if ($color && $color !== '') {
                    $cluster->setColor($color);
                }
                
                $cluster->setDisplayOrder((int) $request->request->get('displayOrder', 0));
                $cluster->setActive($request->request->get('isActive') === '1');

                $this->entityManager->persist($cluster);
                $this->entityManager->flush();

                $this->addFlash('success', 'Cluster created successfully.');
                return $this->redirectToRoute('admin_cluster_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating cluster: ' . $e->getMessage());
            }
        }

        return $this->render('admin/cluster/form.html.twig', [
            'cluster' => null,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_cluster_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OfficeCluster $cluster): Response
    {
        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('cluster_form', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_cluster_edit', ['id' => $cluster->getId()]);
            }

            $cluster->setName($request->request->get('name'));
            $cluster->setCode($request->request->get('code'));
            $cluster->setDescription($request->request->get('description'));
            $cluster->setColor($request->request->get('color'));
            $cluster->setDisplayOrder((int) $request->request->get('displayOrder', 0));
            $cluster->setActive($request->request->get('isActive') === '1');

            $this->entityManager->flush();

            $this->addFlash('success', 'Cluster updated successfully.');
            return $this->redirectToRoute('admin_cluster_index');
        }

        return $this->render('admin/cluster/form.html.twig', [
            'cluster' => $cluster,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_cluster_delete', methods: ['POST'])]
    public function delete(OfficeCluster $cluster): Response
    {
        if ($cluster->getOffices()->count() > 0) {
            $this->addFlash('error', 'Cannot delete cluster with associated offices.');
            return $this->redirectToRoute('admin_cluster_index');
        }

        $this->entityManager->remove($cluster);
        $this->entityManager->flush();

        $this->addFlash('success', 'Cluster deleted successfully.');
        return $this->redirectToRoute('admin_cluster_index');
    }
}
