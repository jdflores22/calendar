<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private UserRepository $userRepository,
        private EmailService $emailService
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        #[Autowire(service: 'limiter.login')] RateLimiterFactory $loginRateLimiter,
        Request $request
    ): Response {
        // Rate limiting for login attempts - only apply to POST requests
        if ($request->isMethod('POST')) {
            $limiter = $loginRateLimiter->create($request->getClientIp());
            
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Too many login attempts. Please try again later.');
                return $this->render('security/login.html.twig', [
                    'rate_limited' => true,
                    'last_username' => '',
                    'error' => null,
                ]);
            }
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'rate_limited' => false,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // This method can be blank - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // Basic validation
            if (empty($email) || empty($password) || empty($confirmPassword)) {
                $this->addFlash('error', 'All fields are required.');
                return $this->render('security/register.html.twig', [
                    'email' => $email
                ]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->render('security/register.html.twig', [
                    'email' => $email
                ]);
            }

            if (strlen($password) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters long.');
                return $this->render('security/register.html.twig', [
                    'email' => $email
                ]);
            }

            // Check if user already exists
            $existingUser = $this->userRepository->findByEmail($email);
            if ($existingUser) {
                $this->addFlash('error', 'An account with this email already exists.');
                return $this->render('security/register.html.twig', [
                    'email' => $email
                ]);
            }

            // Create new user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setRoles(['ROLE_USER']);
            
            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));
            $user->setVerificationToken($verificationToken);
            $user->setVerificationTokenExpiresAt(new \DateTime('+24 hours'));

            // Validate user
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('security/register.html.twig', [
                    'email' => $email
                ]);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Send verification email
            try {
                $this->emailService->sendVerificationEmail($user->getEmail(), $verificationToken);
                $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');
            } catch (\Exception $e) {
                $this->addFlash('success', 'Registration successful! However, we could not send the verification email. Please contact support.');
            }
            
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig');
    }

    #[Route('/verify/{token}', name: 'app_verify_email')]
    public function verifyEmail(string $token): Response
    {
        $user = $this->userRepository->findByVerificationToken($token);

        if (!$user) {
            $this->addFlash('error', 'Invalid verification token.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getVerificationTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Verification token has expired. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        $user->setVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);

        $this->entityManager->flush();

        $this->addFlash('success', 'Email verified successfully! You can now log in.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/reset-password', name: 'app_reset_password_request')]
    public function resetPasswordRequest(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            if (empty($email)) {
                $this->addFlash('error', 'Email is required.');
                return $this->render('security/reset_password_request.html.twig');
            }

            $user = $this->userRepository->findByEmail($email);

            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $user->setPasswordResetToken($resetToken);
                $user->setPasswordResetTokenExpiresAt(new \DateTime('+1 hour'));

                $this->entityManager->flush();

                // Send reset email
                try {
                    $this->emailService->sendPasswordResetEmail($user->getEmail(), $resetToken);
                } catch (\Exception $e) {
                    // Log error but don't reveal it to prevent email enumeration
                }
            }

            // Always show success message to prevent email enumeration
            $this->addFlash('success', 'If an account with that email exists, a password reset link has been sent.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_request.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(string $token, Request $request): Response
    {
        $user = $this->userRepository->findByPasswordResetToken($token);

        if (!$user) {
            $this->addFlash('error', 'Invalid reset token.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getPasswordResetTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Reset token has expired. Please request a new one.');
            return $this->redirectToRoute('app_reset_password_request');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (empty($password) || empty($confirmPassword)) {
                $this->addFlash('error', 'All fields are required.');
                return $this->render('security/reset_password.html.twig', [
                    'token' => $token
                ]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->render('security/reset_password.html.twig', [
                    'token' => $token
                ]);
            }

            if (strlen($password) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters long.');
                return $this->render('security/reset_password.html.twig', [
                    'token' => $token
                ]);
            }

            // Update password
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenExpiresAt(null);

            $this->entityManager->flush();

            $this->addFlash('success', 'Password reset successfully! You can now log in with your new password.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token
        ]);
    }
}