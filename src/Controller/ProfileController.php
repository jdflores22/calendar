<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\OfficeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\SecurityService;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SluggerInterface $slugger,
        private OfficeRepository $officeRepository,
        private SecurityService $securityService,
        private LoggerInterface $logger,
        private \App\Repository\OfficeClusterRepository $clusterRepository
    ) {}

    #[Route('', name: 'app_profile_show')]
    public function show(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user->getProfile()) {
            $profile = new UserProfile();
            $user->setProfile($profile);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'profile' => $user->getProfile(),
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit')]
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user->getProfile()) {
            $profile = new UserProfile();
            $user->setProfile($profile);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
        }

        $profile = $user->getProfile();
        $clusters = $this->clusterRepository->findAllWithOffices();

        if ($request->isMethod('POST')) {
            return $this->handleProfileUpdate($request, $user, $profile);
        }

        return $this->render('profile/edit.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'clusters' => $clusters,
        ]);
    }

    #[Route('/complete', name: 'app_profile_complete')]
    public function complete(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user->getProfile()) {
            $profile = new UserProfile();
            $user->setProfile($profile);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
        }

        $profile = $user->getProfile();
        
        // If profile is already complete, redirect to dashboard
        if ($profile->isComplete()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $offices = $this->officeRepository->findAll();
        $clusters = $this->clusterRepository->findAllWithOffices();

        if ($request->isMethod('POST')) {
            $response = $this->handleProfileUpdate($request, $user, $profile);
            
            // If update was successful and profile is now complete, redirect to dashboard
            if ($response->isRedirection() && $profile->isComplete()) {
                return $this->redirectToRoute('app_dashboard');
            }
            
            return $response;
        }

        return $this->render('profile/complete.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'offices' => $offices,
            'clusters' => $clusters,
        ]);
    }

    private function handleProfileUpdate(Request $request, User $user, UserProfile $profile): Response
    {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->securityService->validateCsrfToken('profile_form', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->render($this->getTemplateForRoute($request), [
                'user' => $user,
                'profile' => $profile,
                'clusters' => $this->clusterRepository->findAllWithOffices(),
            ]);
        }

        // Sanitize input data
        $sanitizedData = $this->securityService->sanitizeArray($request->request->all());
        
        $firstName = $sanitizedData['first_name'] ?? '';
        $lastName = $sanitizedData['last_name'] ?? '';
        $middleName = $sanitizedData['middle_name'] ?? '';
        $phone = $sanitizedData['phone'] ?? '';
        $address = $sanitizedData['address'] ?? '';
        $officeId = $sanitizedData['office_id'] ?? '';
        $divisionId = $sanitizedData['division_id'] ?? '';

        // Validate required fields
        $errors = [];
        
        if (empty($firstName)) {
            $errors[] = 'First name is required.';
        }
        
        if (empty($lastName)) {
            $errors[] = 'Last name is required.';
        }
        
        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        }
        
        if (empty($officeId)) {
            $errors[] = 'Office assignment is required.';
        }

        // Handle avatar upload with security validation
        /** @var UploadedFile $avatarFile */
        $avatarFile = $request->files->get('avatar');
        $avatarPath = null;
        
        if ($avatarFile) {
            $avatarPath = $this->handleAvatarUpload($avatarFile, $errors);
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            
            return $this->render($this->getTemplateForRoute($request), [
                'user' => $user,
                'profile' => $profile,
                'clusters' => $this->clusterRepository->findAllWithOffices(),
            ]);
        }

        // Update profile
        $profile->setFirstName($firstName);
        $profile->setLastName($lastName);
        $profile->setMiddleName($middleName);
        $profile->setPhone($phone);
        $profile->setAddress($address);
        
        if ($avatarPath) {
            // Remove old avatar if it exists
            if ($profile->getAvatar()) {
                $oldAvatarPath = $this->getParameter('kernel.project_dir') . '/public/' . $profile->getAvatar();
                if (file_exists($oldAvatarPath)) {
                    @unlink($oldAvatarPath);
                }
            }
            $profile->setAvatar($avatarPath);
        }

        // Update office assignment
        if ($officeId) {
            $office = $this->officeRepository->find($officeId);
            if ($office) {
                $user->setOffice($office);
            }
        }

        // Update division assignment
        if ($divisionId) {
            $division = $this->entityManager->getRepository(\App\Entity\Division::class)->find($divisionId);
            if ($division) {
                $user->setDivision($division);
            }
        } else {
            // Clear division if not selected
            $user->setDivision(null);
        }

        // Validate entities
        $profileErrors = $this->validator->validate($profile);
        $userErrors = $this->validator->validate($user);

        if (count($profileErrors) > 0 || count($userErrors) > 0) {
            foreach ($profileErrors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            foreach ($userErrors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            
            return $this->render($this->getTemplateForRoute($request), [
                'user' => $user,
                'profile' => $profile,
                'clusters' => $this->clusterRepository->findAllWithOffices(),
            ]);
        }

        // Update completion status
        $profile->checkCompletionStatus();

        $this->entityManager->flush();

        // Create detailed success message
        $successMessage = 'Profile updated successfully!';
        if ($avatarPath) {
            $successMessage .= ' Your profile picture has been uploaded.';
        }
        
        $this->addFlash('success', $successMessage);
        
        // Redirect based on the route
        $route = $request->attributes->get('_route');
        if ($route === 'app_profile_complete') {
            return $this->redirectToRoute('app_profile_complete');
        }
        
        // Stay on edit page after successful save
        return $this->redirectToRoute('app_profile_edit');
    }

    private function handleAvatarUpload(UploadedFile $avatarFile, array &$errors): ?string
    {
        // Use security service for file validation
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileArray = [
            'tmp_name' => $avatarFile->getPathname(),
            'name' => $avatarFile->getClientOriginalName(),
            'size' => $avatarFile->getSize(),
            'type' => $avatarFile->getMimeType()
        ];
        
        $validationErrors = $this->securityService->validateFileUpload($fileArray, $allowedTypes, 5242880);
        
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
            return null;
        }

        // Generate secure filename
        $newFilename = $this->securityService->generateSecureFilename($avatarFile->getClientOriginalName());

        try {
            // Get the avatars directory path
            $avatarsDirectory = $this->getParameter('avatars_directory');
            
            // Ensure the directory exists
            if (!is_dir($avatarsDirectory)) {
                if (!mkdir($avatarsDirectory, 0755, true)) {
                    $errors[] = 'Failed to create upload directory. Please contact administrator.';
                    return null;
                }
            }
            
            // Check if directory is writable
            if (!is_writable($avatarsDirectory)) {
                $errors[] = 'Upload directory is not writable. Please contact administrator.';
                return null;
            }
            
            $avatarFile->move($avatarsDirectory, $newFilename);
            
            return 'uploads/avatars/' . $newFilename;
        } catch (FileException $e) {
            $this->logger->error('Avatar upload failed', [
                'error' => $e->getMessage(),
                'file' => $avatarFile->getClientOriginalName(),
                'user' => $this->getUser()?->getId()
            ]);
            $errors[] = 'Failed to upload avatar. Please try again.';
            return null;
        }
    }

    private function getTemplateForRoute(Request $request): string
    {
        $route = $request->attributes->get('_route');
        
        return match ($route) {
            'app_profile_complete' => 'profile/complete.html.twig',
            'app_profile_edit' => 'profile/edit.html.twig',
            default => 'profile/show.html.twig',
        };
    }
}