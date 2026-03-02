<?php

namespace App\Service;

use App\Entity\GroupInvitation;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InvitationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {}

    /**
     * Send an invitation email with 3 methods: direct link, invitation code, and QR code.
     */
    public function sendInvitation(GroupInvitation $invitation): void
    {
        $this->logger->info('[InvitationMailer] Envoi email invitation', [
            'email' => $invitation->getEmail(),
            'group' => $invitation->getGroup()->getName(),
            'token' => $invitation->getToken(),
        ]);

        // 1. Generate the direct accept link (using the unique token)
        $acceptUrl = $this->urlGenerator->generate('app_invitation_accept_token', [
            'token' => $invitation->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 2. Generate the QR Code pointing to the accept URL (endroid/qr-code v6 API)
        $qrCode = new QrCode(
            data: $acceptUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 10,
        );

        $writer = new PngWriter();
        $qrCodeResult = $writer->write($qrCode);

        // 3. Build and send the email containing all 3 methods
        $inviterName = $invitation->getInvitedBy()
            ? $invitation->getInvitedBy()->getFullName()
            : 'Un membre';

        $groupName = $invitation->getGroup()->getName();

        // Use same sender as password reset emails
        $email = (new TemplatedEmail())
            ->from('noreply@studysprint.com')
            ->to($invitation->getEmail())
            ->subject(sprintf('%s vous invite à rejoindre "%s" sur StudySprint', $inviterName, $groupName))
            ->htmlTemplate('emails/group_invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'acceptUrl' => $acceptUrl,
                'inviterName' => $inviterName,
                'groupName' => $groupName,
            ])
            // Embed the QR code as inline image
            ->embed($qrCodeResult->getString(), 'qr-code', 'image/png');

        $this->mailer->send($email);

        $this->logger->info('[InvitationMailer] Email envoyé avec succès à ' . $invitation->getEmail());
    }
}
