<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $senderEmail = 'noreply@studysprint.tn'
    ) {}

    public function sendWelcomeEmail(User $user): void
    {
        $email = (new Email())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject('Bienvenue sur StudySprint !')
            ->html($this->buildWelcomeHtml($user));

        $this->mailer->send($email);
    }

    public function sendCertificationNotification(User $user, string $status, ?string $reason = null): void
    {
        $isApproved = $status === 'APPROVED';

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject($isApproved
                ? 'Certification professeur approuvée !'
                : 'Mise à jour de votre demande de certification'
            )
            ->html($this->buildCertificationHtml($user, $isApproved, $reason));

        $this->mailer->send($email);
    }

    public function sendQuizScoreEmail(User $user, string $quizTitle, float $score): void
    {
        $passed = $score >= 50;

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject($passed
                ? "Bravo ! Vous avez réussi le quiz « {$quizTitle} »"
                : "Résultat du quiz « {$quizTitle} »"
            )
            ->html($this->buildQuizResultHtml($user, $quizTitle, $score, $passed));

        $this->mailer->send($email);
    }

    private function buildWelcomeHtml(User $user): string
    {
        $name = htmlspecialchars($user->getFullName());
        return <<<HTML
        <div style="font-family: 'Inter', Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 2rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 2rem; color: white; text-align: center; margin-bottom: 1.5rem;">
                <h1 style="margin: 0; font-size: 24px;">Bienvenue sur StudySprint !</h1>
            </div>
            <p>Bonjour <strong>{$name}</strong>,</p>
            <p>Votre compte a été créé avec succès. Vous pouvez maintenant :</p>
            <ul>
                <li>Passer des <strong>quiz</strong> et suivre vos scores</li>
                <li>Créer et réviser des <strong>flashcards</strong> avec l'algorithme SM-2</li>
                <li>Planifier vos <strong>sessions de révision</strong></li>
                <li>Rejoindre des <strong>groupes d'étude</strong></li>
            </ul>
            <p>Bonne révision !</p>
            <p style="color: #6b7280; font-size: 13px;">— L'équipe StudySprint</p>
        </div>
        HTML;
    }

    private function buildCertificationHtml(User $user, bool $isApproved, ?string $reason): string
    {
        $name = htmlspecialchars($user->getFullName());
        $statusText = $isApproved ? 'approuvée' : 'refusée';
        $color = $isApproved ? '#10b981' : '#ef4444';
        $reasonBlock = !$isApproved && $reason
            ? "<p><strong>Motif :</strong> " . htmlspecialchars($reason) . "</p>"
            : '';

        return <<<HTML
        <div style="font-family: 'Inter', Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 2rem;">
            <div style="background: {$color}; border-radius: 12px; padding: 2rem; color: white; text-align: center; margin-bottom: 1.5rem;">
                <h1 style="margin: 0; font-size: 24px;">Certification {$statusText}</h1>
            </div>
            <p>Bonjour <strong>{$name}</strong>,</p>
            <p>Votre demande de certification professeur a été <strong>{$statusText}</strong>.</p>
            {$reasonBlock}
            <p style="color: #6b7280; font-size: 13px;">— L'équipe StudySprint</p>
        </div>
        HTML;
    }

    private function buildQuizResultHtml(User $user, string $quizTitle, float $score, bool $passed): string
    {
        $name = htmlspecialchars($user->getFullName());
        $title = htmlspecialchars($quizTitle);
        $color = $passed ? '#10b981' : '#ef4444';
        $icon = $passed ? '&#10004;' : '&#10008;';
        $scoreStr = number_format($score, 1);

        return <<<HTML
        <div style="font-family: 'Inter', Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 2rem;">
            <div style="background: {$color}; border-radius: 12px; padding: 2rem; color: white; text-align: center; margin-bottom: 1.5rem;">
                <div style="font-size: 48px;">{$icon}</div>
                <h1 style="margin: 0.5rem 0 0; font-size: 24px;">{$scoreStr}%</h1>
                <p style="margin: 0.25rem 0 0; opacity: 0.9;">{$title}</p>
            </div>
            <p>Bonjour <strong>{$name}</strong>,</p>
            <p>Vous avez obtenu <strong>{$scoreStr}%</strong> au quiz « {$title} ».</p>
            <p style="color: #6b7280; font-size: 13px;">— L'équipe StudySprint</p>
        </div>
        HTML;
    }
}
