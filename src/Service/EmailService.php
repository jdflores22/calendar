<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private string $fromEmail = 'noreply@tesda.gov.ph'
    ) {}

    public function sendVerificationEmail(string $userEmail, string $verificationToken): void
    {
        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($userEmail)
            ->subject('TESDA Calendar - Verify your email address')
            ->html($this->twig->render('emails/verification.html.twig', [
                'verification_url' => $verificationUrl
            ]));

        $this->mailer->send($email);
    }

    public function sendPasswordResetEmail(string $userEmail, string $resetToken): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $resetToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($userEmail)
            ->subject('TESDA Calendar - Password Reset Request')
            ->html($this->twig->render('emails/password_reset.html.twig', [
                'reset_url' => $resetUrl
            ]));

        $this->mailer->send($email);
    }
}