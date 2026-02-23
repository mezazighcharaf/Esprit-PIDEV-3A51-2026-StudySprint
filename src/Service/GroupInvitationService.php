<?php

namespace App\Service;

use App\Entity\GroupInvitation;
use App\Entity\StudyGroup;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service for managing group invitations
 * Refactored for PHP 8.0 compatibility (using string roles instead of Enums)
 */
class GroupInvitationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GroupInvitationRepository $invitationRepository,
        private GroupService $groupService,
        private InvitationMailer $invitationMailer,
    ) {
    }

    /**
     * Send invitations to multiple users
     * @param string[] $emails
     * @param string $role
     * @return GroupInvitation[] Created invitations
     */
    public function inviteUsers(StudyGroup $group, array $emails, User $inviter, string $role = GroupRole::MEMBER): array
    {
        if ($group->getPrivacy() !== 'private') {
            throw new AccessDeniedHttpException('Les invitations sont réservées aux groupes privés.');
        }

        if (!$this->groupService->canEditGroup($group, $inviter)) {
            throw new AccessDeniedHttpException('Action non autorisée.');
        }

        $invitations = [];

        foreach ($emails as $email) {
            $email = strtolower(trim($email));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if ($email === strtolower($inviter->getEmail())) {
                continue;
            }

            // Check for existing invitation
            $invitation = $this->invitationRepository->findOneByGroupAndEmail($group, $email);

            if ($invitation) {
                // If already pending, skip
                if ($invitation->getStatus() === 'pending') {
                    continue;
                }

                // If already accepted, check if user is still a member
                if ($invitation->getStatus() === 'accepted') {
                    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($user && $this->groupService->isMember($group, $user)) {
                        continue;
                    }
                }

                // If declined or no longer a member, we "re-use" the invitation record
                $invitation->setStatus('pending');
                $invitation->setCode($this->generateInvitationCode());
                $invitation->setToken(bin2hex(random_bytes(32)));
                $invitation->setExpiresAt(new \DateTimeImmutable('+7 days'));
                $invitation->setInvitedBy($inviter);
                $invitation->setInvitedAt(new \DateTimeImmutable());
                $invitation->setRespondedAt(null);
                $invitation->setRole($role);
            } else {
                // Check if user is already a member
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($user && $this->groupService->isMember($group, $user)) {
                    continue;
                }

                // Create new invitation
                $invitation = new GroupInvitation();
                $invitation->setGroup($group);
                $invitation->setEmail($email);
                $invitation->setInvitedBy($inviter);
                $invitation->setCode($this->generateInvitationCode());
                $invitation->setStatus('pending');
                $invitation->setRole($role);
                $this->entityManager->persist($invitation);
            }

            $invitations[] = $invitation;
        }

        $this->entityManager->flush();

        // Send invitation emails
        foreach ($invitations as $invitation) {
            $this->invitationMailer->sendInvitation($invitation);
        }

        return $invitations;
    }

    /**
     * Accept an invitation using its token
     */
    public function acceptInvitationByToken(string $token, User $user): void
    {
        $invitation = $this->invitationRepository->findValidByToken($token);

        if (!$invitation) {
            throw new NotFoundHttpException('Invitation invalide ou expirée.');
        }

        if ($invitation->isExpired()) {
            throw new AccessDeniedHttpException('Cette invitation a expiré.');
        }

        $this->acceptInvitation($invitation, $user);
    }

    /**
     * Accept an invitation using its code
     */
    public function acceptInvitationByCode(string $code, User $user): void
    {
        $invitation = $this->invitationRepository->findValidByCode($code);

        if (!$invitation) {
            throw new NotFoundHttpException('Invitation invalide ou expirée.');
        }

        // Email must match
        if (strtolower($invitation->getEmail()) !== strtolower($user->getEmail())) {
            throw new AccessDeniedHttpException('Cette invitation n\'est pas pour vous.');
        }

        $this->acceptInvitation($invitation, $user);
    }

    /**
     * Accept an invitation entity
     */
    public function acceptInvitation(GroupInvitation $invitation, User $user): void
    {
        if ($invitation->getStatus() !== 'pending') {
            throw new \LogicException('Cette invitation n\'est plus valide.');
        }

        // Add member to group
        $this->groupService->addMember(
            $invitation->getGroup(),
            $user,
            $invitation->getRole()
        );

        // Mark invitation as accepted
        $invitation->setStatus('accepted');
        $invitation->setRespondedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * Decline an invitation
     */
    public function declineInvitation(GroupInvitation $invitation, User $user): void
    {
        if ($invitation->getStatus() !== 'pending') {
            throw new NotFoundHttpException('Invitation non trouvée.');
        }

        if (strtolower($invitation->getEmail()) !== strtolower($user->getEmail())) {
            throw new AccessDeniedHttpException('Cette invitation n\'est pas pour vous.');
        }

        $invitation->setStatus('declined');
        $invitation->setRespondedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * Generate a short readable invitation code
     */
    private function generateInvitationCode(): string
    {
        return 'INV-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Cancel an invitation
     */
    public function cancelInvitation(GroupInvitation $invitation, User $user): void
    {
        if ($invitation->getStatus() !== 'pending') {
            throw new \LogicException('Seules les invitations en attente peuvent être annulées.');
        }

        if (!$invitation->getInvitedBy() || $invitation->getInvitedBy()->getId() !== $user->getId()) {
            // Also allow group admins or BO admins to cancel
            $isBoAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
            if (!$isBoAdmin && !$this->groupService->canEditGroup($invitation->getGroup(), $user)) {
                throw new AccessDeniedHttpException('Vous n\'êtes pas autorisé à annuler cette invitation.');
            }
        }

        $invitation->setStatus('cancelled');
        $invitation->setRespondedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}