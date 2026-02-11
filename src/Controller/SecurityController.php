<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
             return $this->redirectToRoute('app_post_login');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/request-reactivation', name: 'app_request_reactivation', methods: ['POST'])]
    public function requestReactivation(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $email = strtolower($request->request->get('email'));
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('request_reactivation', $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getStatut() !== 'inactif') {
            $this->addFlash('info', 'Votre compte est déjà actif.');
            return $this->redirectToRoute('app_login');
        }

        $user->setDemandeReactivation(true);
        $user->setDateDemandeReactivation(new \DateTimeImmutable());
        $entityManager->flush();

        // Notify admin
        $adminEmail = (new Email())
            ->from('studysprintcontact@gmail.com')
            ->to('studysprintcontact@gmail.com')
            ->subject('Nouvelle demande de réactivation de compte')
            ->html(sprintf(
                '<p>L\'utilisateur <strong>%s %s</strong> (%s) a demandé la réactivation de son compte.</p>' .
                '<p><a href="%s">Cliquez ici pour gérer les utilisateurs</a></p>',
                $user->getNom(),
                $user->getPrenom(),
                $user->getEmail(),
                $this->generateUrl('admin_users', [], 0) // Absolute URL
            ));

        try {
            $mailer->send($adminEmail);
        } catch (\Exception $e) {
            // Log error but don't stop the flow
        }

        return $this->redirectToRoute('app_reactivation_pending');
    }

    #[Route(path: '/reactivation-pending', name: 'app_reactivation_pending', methods: ['GET'])]
    public function reactivationPending(): Response
    {
        return $this->render('security/reactivation_pending.html.twig');
    }

    #[Route(path: '/post-login', name: 'app_post_login')]
    public function redirectByRole(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($this->isGranted('ROLE_PROFESSOR')) {
            return $this->redirectToRoute('app_profile');
        }

        return $this->redirectToRoute('app_profile');
    }
}
