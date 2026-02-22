<?php

namespace App\Controller;

use App\Entity\ReactivationRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ReactivationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class ReactivationController extends AbstractController
{
    #[Route('/reactivation-request', name: 'app_reactivation_request', methods: ['POST'])]
    public function requestReactivation(
        Request $request,
        UserRepository $userRepository,
        ReactivationRequestRepository $reactivationRequestRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if (!$this->isCsrfTokenValid('reactivation', $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_login');
        }

        $email = $request->request->get('email');
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getStatut() !== 'inactif') {
            $this->addFlash('info', 'Votre compte est déjà actif.');
            return $this->redirectToRoute('app_login');
        }

        // Check if a pending request already exists
        $existingRequest = $reactivationRequestRepository->findOneBy([
            'user' => $user,
            'status' => 'pending'
        ]);

        if ($existingRequest) {
            $this->addFlash('warning', 'Une demande est déjà en cours de traitement.');
            return $this->redirectToRoute('app_login');
        }

        $reactivationRequest = new ReactivationRequest();
        $reactivationRequest->setUser($user);

        $entityManager->persist($reactivationRequest);
        $entityManager->flush();
        
        // Notification Email to Admin
        try {
            $email = (new TemplatedEmail())
                ->from('noreply@studysprint.com')
                ->to('studysprintcontact@gmail.com')
                ->subject('Nouvelle demande de réactivation - ' . $user->getFullName())
                ->htmlTemplate('emails/admin_reactivation_notification.html.twig')
                ->context([
                    'user' => $user,
                ]);

            $mailer->send($email);
        } catch (\Exception $e) {
            // Silently fail or log in dev logs if needed
        }

        $this->addFlash('success', 'Votre demande a été envoyée. Veuillez patienter jusqu\'à la réactivation de votre compte. Merci.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/admin/reactivations', name: 'admin_reactivations')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminList(ReactivationRequestRepository $repository): Response
    {
        return $this->render('bo/reactivations.html.twig', [
            'requests' => $repository->findPending(),
        ]);
    }

    #[Route('/admin/reactivations/{id}/approve', name: 'admin_reactivation_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminApprove(
        int $id,
        ReactivationRequestRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        $reactivationRequest = $repository->find($id);
        if (!$reactivationRequest) {
            $this->addFlash('danger', 'Demande introuvable.');
            return $this->redirectToRoute('admin_reactivations');
        }

        $user = $reactivationRequest->getUser();
        $user->setStatut('actif');
        $reactivationRequest->setStatus('approved');

        $entityManager->flush();

        $this->addFlash('success', 'Le compte de ' . $user->getFullName() . ' a été réactivé.');

        return $this->redirectToRoute('admin_reactivations');
    }

    #[Route('/admin/reactivations/{id}/reject', name: 'admin_reactivation_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminReject(
        int $id,
        Request $request,
        ReactivationRequestRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        $reactivationRequest = $repository->find($id);
        if (!$reactivationRequest) {
            $this->addFlash('danger', 'Demande introuvable.');
            return $this->redirectToRoute('admin_reactivations');
        }

        $comment = $request->request->get('comment');
        $reactivationRequest->setStatus('rejected');
        $reactivationRequest->setComment($comment);

        $entityManager->flush();

        $this->addFlash('info', 'La demande de réactivation a été rejetée.');

        return $this->redirectToRoute('admin_reactivations');
    }

    #[Route('/admin/reactivations/count', name: 'admin_reactivation_count')]
    #[IsGranted('ROLE_ADMIN')]
    public function countPending(ReactivationRequestRepository $repository): Response
    {
        $count = $repository->countPending();
        return new Response($count > 0 ? (string) $count : '');
    }
}
