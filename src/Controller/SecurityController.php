<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Service\MailerService;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, redirect to appropriate dashboard
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('fo_subjects_index');
        }

        // Get login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // This method can be blank - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerService $mailerService
    ): Response {
        // If user is already logged in, redirect
        if ($this->getUser()) {
            return $this->redirectToRoute('fo_subjects_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $mailerService->sendWelcomeEmail($user);
            } catch (\Exception $e) {
                // Mail sending failure should not block registration
            }

            $this->addFlash('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        // If user is logged in, redirect to appropriate area
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('fo_subjects_index');
        }

        return $this->render('security/home.html.twig');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->redirectToRoute('fo_subjects_index');
    }
}
