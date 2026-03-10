<?php

namespace App\Controller;

use App\Entity\Form;
use App\Entity\FormField;
use App\Repository\FormRepository;
use App\Repository\FormFieldRepository;
use App\Security\Voter\FormBuilderVoter;
use App\Service\FormFieldTypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/form-builder')]
#[IsGranted('ROLE_ADMIN')]
class FormBuilderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormRepository $formRepository,
        private FormFieldRepository $formFieldRepository,
        private FormFieldTypeRegistry $fieldTypeRegistry,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'form_builder_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::MANAGE);

        $forms = $this->formRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        $statistics = $this->formRepository->getStatistics();

        return $this->render('form_builder/index.html.twig', [
            'forms' => $forms,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/create', name: 'form_builder_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::CREATE);

        if ($request->isMethod('POST')) {
            return $this->handleCreateForm($request);
        }

        $fieldTypes = $this->fieldTypeRegistry->getTypesByCategory();

        return $this->render('form_builder/create.html.twig', [
            'fieldTypes' => $fieldTypes,
            'categoryNames' => $this->fieldTypeRegistry->getCategoryNames(),
        ]);
    }

    #[Route('/{id}/edit', name: 'form_builder_edit', methods: ['GET', 'POST'])]
    public function edit(Form $form, Request $request): Response
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::EDIT, $form);

        if ($request->isMethod('POST')) {
            return $this->handleUpdateForm($form, $request);
        }

        $fieldTypes = $this->fieldTypeRegistry->getTypesByCategory();
        $fields = $this->formFieldRepository->findByFormOrdered($form);

        return $this->render('form_builder/edit.html.twig', [
            'form' => $form,
            'fields' => $fields,
            'fieldTypes' => $fieldTypes,
            'categoryNames' => $this->fieldTypeRegistry->getCategoryNames(),
        ]);
    }

    #[Route('/{id}', name: 'form_builder_show', methods: ['GET'])]
    public function show(Form $form): Response
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::VIEW, $form);

        $fields = $this->formFieldRepository->findByFormOrdered($form);

        return $this->render('form_builder/show.html.twig', [
            'form' => $form,
            'fields' => $fields,
        ]);
    }

    #[Route('/{id}/delete', name: 'form_builder_delete', methods: ['POST'])]
    public function delete(Form $form, Request $request): Response
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::DELETE, $form);

        if ($this->isCsrfTokenValid('delete' . $form->getId(), $request->request->get('_token'))) {
            $form->setActive(false); // Soft delete
            $this->entityManager->flush();

            $this->addFlash('success', 'Form has been deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('form_builder_index');
    }

    #[Route('/{id}/preview', name: 'form_builder_preview', methods: ['GET'])]
    public function preview(Form $form): Response
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::VIEW, $form);

        $fields = $this->formFieldRepository->findByFormOrdered($form);

        return $this->render('form_builder/preview.html.twig', [
            'form' => $form,
            'fields' => $fields,
        ]);
    }

    // API Endpoints for AJAX operations

    #[Route('/api/forms', name: 'form_builder_api_create', methods: ['POST'])]
    public function apiCreateForm(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::CREATE);

        try {
            $data = json_decode($request->getContent(), true);
            
            $form = new Form();
            $form->setName($data['name'] ?? '')
                 ->setDescription($data['description'] ?? null)
                 ->setCreator($this->getUser())
                 ->generateSlug();

            if (!empty($data['tags'])) {
                $form->setTags($data['tags']);
            }

            if (!empty($data['assignedTo'])) {
                $form->setAssignedTo($data['assignedTo']);
            }

            // Set initial schema
            $form->setSchema([
                'version' => '1.0',
                'fields' => [],
                'config' => $data['config'] ?? []
            ]);

            $errors = $this->validator->validate($form);
            if (count($errors) > 0) {
                return $this->json(['success' => false, 'errors' => (string) $errors], 400);
            }

            $this->entityManager->persist($form);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'form' => [
                    'id' => $form->getId(),
                    'name' => $form->getName(),
                    'slug' => $form->getSlug(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/forms/{id}/fields', name: 'form_builder_api_add_field', methods: ['POST'])]
    public function apiAddField(Form $form, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::EDIT, $form);

        try {
            $data = json_decode($request->getContent(), true);
            
            $field = new FormField();
            $field->setName($data['name'] ?? '')
                  ->setLabel($data['label'] ?? '')
                  ->setType($data['type'] ?? FormField::TYPE_TEXT)
                  ->setDescription($data['description'] ?? null)
                  ->setPlaceholder($data['placeholder'] ?? null)
                  ->setDefaultValue($data['defaultValue'] ?? null)
                  ->setRequired($data['isRequired'] ?? false)
                  ->setSortOrder($this->formFieldRepository->getNextSortOrder($form))
                  ->setForm($form);

            if (!empty($data['options'])) {
                $field->setOptions($data['options']);
            }

            if (!empty($data['validationRules'])) {
                $field->setValidationRules($data['validationRules']);
            }

            if (!empty($data['attributes'])) {
                $field->setAttributes($data['attributes']);
            }

            // Validate field configuration
            $configErrors = $this->fieldTypeRegistry->validateFieldConfig($field->getType(), $data);
            if (!empty($configErrors)) {
                return $this->json(['success' => false, 'errors' => $configErrors], 400);
            }

            $errors = $this->validator->validate($field);
            if (count($errors) > 0) {
                return $this->json(['success' => false, 'errors' => (string) $errors], 400);
            }

            $this->entityManager->persist($field);
            
            // Update form schema
            $this->updateFormSchema($form);
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'field' => $field->toArray()
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/fields/{id}', name: 'form_builder_api_update_field', methods: ['PUT'])]
    public function apiUpdateField(FormField $field, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::EDIT, $field->getForm());

        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['name'])) $field->setName($data['name']);
            if (isset($data['label'])) $field->setLabel($data['label']);
            if (isset($data['type'])) $field->setType($data['type']);
            if (isset($data['description'])) $field->setDescription($data['description']);
            if (isset($data['placeholder'])) $field->setPlaceholder($data['placeholder']);
            if (isset($data['defaultValue'])) $field->setDefaultValue($data['defaultValue']);
            if (isset($data['isRequired'])) $field->setRequired($data['isRequired']);
            if (isset($data['sortOrder'])) $field->setSortOrder($data['sortOrder']);
            if (isset($data['options'])) $field->setOptions($data['options']);
            if (isset($data['validationRules'])) $field->setValidationRules($data['validationRules']);
            if (isset($data['attributes'])) $field->setAttributes($data['attributes']);

            // Validate field configuration
            $configErrors = $this->fieldTypeRegistry->validateFieldConfig($field->getType(), $data);
            if (!empty($configErrors)) {
                return $this->json(['success' => false, 'errors' => $configErrors], 400);
            }

            $errors = $this->validator->validate($field);
            if (count($errors) > 0) {
                return $this->json(['success' => false, 'errors' => (string) $errors], 400);
            }

            // Update form schema
            $this->updateFormSchema($field->getForm());
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'field' => $field->toArray()
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/fields/{id}', name: 'form_builder_api_delete_field', methods: ['DELETE'])]
    public function apiDeleteField(FormField $field): JsonResponse
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::EDIT, $field->getForm());

        try {
            $form = $field->getForm();
            $this->entityManager->remove($field);
            
            // Update form schema
            $this->updateFormSchema($form);
            
            $this->entityManager->flush();

            return $this->json(['success' => true]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/forms/{id}/fields/reorder', name: 'form_builder_api_reorder_fields', methods: ['POST'])]
    public function apiReorderFields(Form $form, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::EDIT, $form);

        try {
            $data = json_decode($request->getContent(), true);
            $fieldIds = $data['fieldIds'] ?? [];

            $this->formFieldRepository->reorderFields($form, $fieldIds);
            
            // Update form schema
            $this->updateFormSchema($form);

            return $this->json(['success' => true]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/field-types', name: 'form_builder_api_field_types', methods: ['GET'])]
    public function apiGetFieldTypes(): JsonResponse
    {
        $this->denyAccessUnlessGranted(FormBuilderVoter::MANAGE);

        return $this->json([
            'fieldTypes' => $this->fieldTypeRegistry->getAllTypes(),
            'categories' => $this->fieldTypeRegistry->getCategoryNames(),
        ]);
    }

    private function handleCreateForm(Request $request): Response
    {
        try {
            $form = new Form();
            $form->setName($request->request->get('name', ''))
                 ->setDescription($request->request->get('description'))
                 ->setCreator($this->getUser())
                 ->generateSlug();

            $tags = $request->request->get('tags');
            if ($tags) {
                $form->setTags(array_filter(array_map('trim', explode(',', $tags))));
            }

            $assignedTo = $request->request->get('assignedTo');
            if ($assignedTo) {
                $form->setAssignedTo($assignedTo);
            }

            // Set initial schema
            $form->setSchema([
                'version' => '1.0',
                'fields' => [],
                'config' => []
            ]);

            $errors = $this->validator->validate($form);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->redirectToRoute('form_builder_create');
            }

            $this->entityManager->persist($form);
            $this->entityManager->flush();

            $this->addFlash('success', 'Form created successfully.');
            return $this->redirectToRoute('form_builder_edit', ['id' => $form->getId()]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error creating form: ' . $e->getMessage());
            return $this->redirectToRoute('form_builder_create');
        }
    }

    private function handleUpdateForm(Form $form, Request $request): Response
    {
        try {
            $form->setName($request->request->get('name', $form->getName()))
                 ->setDescription($request->request->get('description'));

            $tags = $request->request->get('tags');
            if ($tags !== null) {
                $form->setTags(array_filter(array_map('trim', explode(',', $tags))));
            }

            $assignedTo = $request->request->get('assignedTo');
            $form->setAssignedTo($assignedTo ?: null);

            $errors = $this->validator->validate($form);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->redirectToRoute('form_builder_edit', ['id' => $form->getId()]);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Form updated successfully.');
            return $this->redirectToRoute('form_builder_edit', ['id' => $form->getId()]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error updating form: ' . $e->getMessage());
            return $this->redirectToRoute('form_builder_edit', ['id' => $form->getId()]);
        }
    }

    private function updateFormSchema(Form $form): void
    {
        $fields = $this->formFieldRepository->findByFormOrdered($form);
        $fieldDefinitions = [];

        foreach ($fields as $field) {
            $fieldDefinitions[] = $field->toArray();
        }

        $schema = $form->getSchema();
        $schema['fields'] = $fieldDefinitions;
        $form->setSchema($schema);
    }
}