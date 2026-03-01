<?php

namespace App\Controller;

use App\Dto\PasswordResetDTO;
use App\Dto\PasswordResetRequestDTO;
use App\Form\PasswordResetRequestType;
use App\Form\PasswordResetType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class PasswordResetController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer
    ) {}

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function request(Request $request, \Symfony\Component\Notifier\TexterInterface $texter): Response
    {
        $dto = new PasswordResetRequestDTO();
        $form = $this->createForm(PasswordResetRequestType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = null;
            if ($dto->method === 'telephone') {
                $user = $this->userRepository->findOneBy(['telephone' => $dto->telephone]);
            } else {
                $dto->email = strtolower($dto->email ?? '');
                $user = $this->userRepository->findOneBy(['email' => $dto->email]);
            }

            if ($user) {
                // Generate a 6-digit verification code
                $verificationCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                
                // Set token and expiration (15 minutes)
                $user->setResetToken($verificationCode);
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+15 minutes'));
                
                $this->entityManager->flush();

                if ($dto->method === 'telephone') {
                    try {
                        // Ensure phone number is E.164 format (assume +216 if missing)
                        $phoneNumber = (string) $user->getTelephone();
                        if (!str_starts_with($phoneNumber, '+')) {
                            $phoneNumber = '+216' . ltrim($phoneNumber, '0');
                        }

                        $sms = new \Symfony\Component\Notifier\Message\SmsMessage(
                            $phoneNumber,
                            "StudySprint: Votre code de vérification est " . $verificationCode
                        );
                        $texter->send($sms);
                        $this->addFlash('success', 'Un code de vérification a été envoyé par SMS à votre numéro.');
                    } catch (\Exception $e) {
                         // En cas d'erreur (ex: numéro non vérifié en mode trial), on affiche l'erreur.
                         $this->addFlash('error', "Erreur d'envoi SMS : " . $e->getMessage());
                    }
                } else {
                    // Send email with verification code
                    try {
                        $email = (new TemplatedEmail())
                            ->from('noreply@studysprint.com')
                            ->to((string) $user->getEmail())
                            ->subject('Réinitialisation de votre mot de passe - StudySprint')
                            ->htmlTemplate('emails/password_reset.html.twig')
                            ->context([
                                'user' => $user,
                                'verificationCode' => $verificationCode,
                            ]);

                        $this->mailer->send($email);
                        
                        $this->addFlash('success', 'Un code de vérification a été envoyé à votre adresse email.');
                    } catch (\Exception $e) {
                        $this->addFlash('error', "Une erreur est survenue lors de l'envoi de l'email. Veuillez réessayer plus tard.");
                    }
                }

                $redirectParams = ['method' => $dto->method];
                if ($dto->method === 'telephone') {
                    $redirectParams['telephone'] = $user->getTelephone();
                } else {
                    $redirectParams['email'] = $user->getEmail();
                }

                return $this->redirectToRoute('app_reset_password', $redirectParams);
            }

            // Don't reveal if user exists or not for security
            $this->addFlash('success', 'Si ces coordonnées existent, un code de vérification a été envoyé.');
            
            $redirectParams = ['method' => $dto->method];
            if ($dto->method === 'telephone') {
                $redirectParams['telephone'] = $dto->telephone;
            } else {
                $redirectParams['email'] = $dto->email;
            }
            return $this->redirectToRoute('app_reset_password', $redirectParams);
        } elseif ($form->isSubmitted()) {
            foreach ($form->getErrors(true) as $error) {
                if (method_exists($error, 'getMessage')) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('security/password_reset_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function reset(Request $request): Response
    {
        $method = (string) $request->query->get('method', 'email');
        $email = strtolower((string) $request->query->get('email', ''));
        $telephone = (string) $request->query->get('telephone', '');
        
        if ($method === 'email' && !$email) {
            return $this->redirectToRoute('app_forgot_password');
        }
        if ($method === 'telephone' && !$telephone) {
            return $this->redirectToRoute('app_forgot_password');
        }

        $dto = new PasswordResetDTO();
        $form = $this->createForm(PasswordResetType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = null;
            if ($method === 'telephone') {
                $user = $this->userRepository->findOneBy(['telephone' => $telephone]);
            } else {
                $user = $this->userRepository->findOneBy(['email' => $email]);
            }

            if (!$user) {
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Verify token
            if ($user->getResetToken() !== $dto->verificationCode) {
                $this->addFlash('error', 'Code de vérification invalide.');
                return $this->render('security/password_reset.html.twig', [
                    'form' => $form->createView(),
                    'email' => $email,
                    'telephone' => $telephone,
                    'method' => $method,
                ]);
            }

            // Check if token expired
            if ($user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
                $this->addFlash('danger', 'Le code de vérification a expiré. Veuillez demander un nouveau code.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Reset password
            $hashedPassword = $this->passwordHasher->hashPassword($user, (string) $dto->newPassword);
            $user->setMotDePasse($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/password_reset.html.twig', [
            'form' => $form->createView(),
            'email' => $email,
            'telephone' => $telephone,
            'method' => $method,
        ]);
    }
}
