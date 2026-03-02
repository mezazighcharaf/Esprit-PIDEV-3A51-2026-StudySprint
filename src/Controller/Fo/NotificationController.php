<?php

namespace App\Controller\Fo;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fo/notifications', name: 'fo_notifications_')]
/**
 * @method \App\Entity\User|null getUser()
 */
class NotificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(NotificationRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();
        $notifications = $repo->findByUser($user, 50);

        return $this->render('fo/notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/mark-all-read', name: 'mark_all_read', methods: ['POST'])]
    public function markAllRead(Request $request, NotificationRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->isCsrfTokenValid('mark_all_read', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_notifications_index');
        }
        /** @var User $user */
        $user = $this->getUser();
        $repo->markAllAsRead($user);
        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');
        return $this->redirectToRoute('fo_notifications_index');
    }

    #[Route('/{id}/read', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(int $id, NotificationRepository $repo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $notification = $repo->find($id);

        if ($notification && $notification->getUser() === $this->getUser()) {
            $notification->setIsRead(true);
            $em->flush();

            if ($notification->getLink()) {
                return $this->redirect($notification->getLink());
            }
        }

        return $this->redirectToRoute('fo_notifications_index');
    }
}
