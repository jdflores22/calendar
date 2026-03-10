<?php

namespace App\Controller\Admin;

use App\Entity\Division;
use App\Repository\DivisionRepository;
use App\Repository\OfficeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/divisions')]
#[IsGranted('ROLE_ADMIN')]
class DivisionController extends AbstractController
{
    public function __construct(
        private DivisionRepository $divisionRepository,
        private OfficeRepository $officeRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_division_index', methods: ['GET'])]
    public function index(): Response
    {
        $divisions = $this->divisionRepository->findAllActive();

        return $this->render('admin/division/index.html.twig', [
            'divisions' => $divisions,
        ]);
    }

    #[Route('/new', name: 'admin_division_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $offices = $this->officeRepository->findAll();

        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('division_form', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_division_new');
            }

            $office = $this->officeRepository->find($request->request->get('office_id'));
            
            if (!$office) {
                $this->addFlash('error', 'Office not found.');
                return $this->redirectToRoute('admin_division_new');
            }

            $division = new Division();
            $division->setName($request->request->get('name'));
            $division->setCode($request->request->get('code'));
            $division->setDescription($request->request->get('description'));
            $division->setDisplayOrder((int) $request->request->get('displayOrder', 0));
            $division->setActive($request->request->get('isActive') === '1');
            $division->setOffice($office);

            $this->entityManager->persist($division);
            $this->entityManager->flush();

            $this->addFlash('success', 'Division created successfully.');
            return $this->redirectToRoute('admin_division_index');
        }

        return $this->render('admin/division/form.html.twig', [
            'division' => null,
            'offices' => $offices,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_division_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Division $division): Response
    {
        $offices = $this->officeRepository->findAll();

        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('division_form', $token)) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_division_edit', ['id' => $division->getId()]);
            }

            $office = $this->officeRepository->find($request->request->get('office_id'));
            
            if (!$office) {
                $this->addFlash('error', 'Office not found.');
                return $this->redirectToRoute('admin_division_edit', ['id' => $division->getId()]);
            }

            $division->setName($request->request->get('name'));
            $division->setCode($request->request->get('code'));
            $division->setDescription($request->request->get('description'));
            $division->setDisplayOrder((int) $request->request->get('displayOrder', 0));
            $division->setActive($request->request->get('isActive') === '1');
            $division->setOffice($office);

            $this->entityManager->flush();

            $this->addFlash('success', 'Division updated successfully.');
            return $this->redirectToRoute('admin_division_index');
        }

        return $this->render('admin/division/form.html.twig', [
            'division' => $division,
            'offices' => $offices,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_division_delete', methods: ['POST'])]
    public function delete(Division $division): Response
    {
        $this->entityManager->remove($division);
        $this->entityManager->flush();

        $this->addFlash('success', 'Division deleted successfully.');
        return $this->redirectToRoute('admin_division_index');
    }
}
