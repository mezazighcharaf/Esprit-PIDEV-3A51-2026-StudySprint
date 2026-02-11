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
    public function request(Request $request): Response
    {
        $dto = new PasswordResetRequestDTO();
        $form = $this->createForm(PasswordResetRequestType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto->email = strtolower(trim($dto->email));
            file_put_contents('web_debug.log', sprintf("[%s] REQUEST RESET for: %s\n", date('Y-m-d H:i:s'), $dto->email), FILE_APPEND);
            $user = $this->userRepository->findOneBy(['email' => $dto->email]);

            if ($user) {
                // Generate a 6-digit verification code
                $verificationCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                
                // Set token and expiration (15 minutes)
                $user->setResetToken($verificationCode);
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+15 minutes'));
                
                $this->entityManager->flush();

                // Send email with verification code
                try {
                    $email = (new TemplatedEmail())
                        ->from('noreply@studysprint.com')
                        ->to($user->getEmail())
                        ->subject('Réinitialisation de votre mot de passe - StudySprint')
                        ->htmlTemplate('emails/password_reset.html.twig')
                        ->context([
                            'user' => $user,
                            'verificationCode' => $verificationCode,
                        ]);

                    $this->mailer->send($email);
                    
                    $this->addFlash('success', 'Un code de vérification a été envoyé à votre adresse email.');
                } catch (\Exception $e) {
                    // If email fails, show a generic error to the user
                    $this->addFlash('danger', "Une erreur est survenue lors de l'envoi de l'email. Veuillez réessayer plus tard.");
                }

                return $this->redirectToRoute('app_reset_password', ['email' => $user->getEmail()]);
            }

            // Don't reveal if user exists or not for security
            file_put_contents('web_debug.log', sprintf("[%s] USER NOT FOUND for reset request: %s\n", date('Y-m-d H:i:s'), $dto->email), FILE_APPEND);
            $this->addFlash('success', 'Si cet email existe, un code de vérification a été envoyé.');
            return $this->redirectToRoute('app_reset_password', ['email' => $dto->email]);
        }

        return $this->render('security/password_reset_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function reset(Request $request): Response
    {
        $email = strtolower($request->query->get('email', ''));
        file_put_contents('web_debug.log', sprintf("[%s] RESET REACHED. Method: %s, Email: %s\n", date('Y-m-d H:i:s'), $request->getMethod(), $email), FILE_APPEND);
        
        if (!$email) {
            return $this->redirectToRoute('app_forgot_password');
        }

        $dto = new PasswordResetDTO();
        $form = $this->createForm(PasswordResetType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logPath = $this->getParameter('kernel.project_dir') . '/public/debug_reset.txt';
            file_put_contents($logPath, sprintf("[%s] FORM VALID. Code: %s, Email: %s\n", date('Y-m-d H:i:s'), $dto->verificationCode, $email), FILE_APPEND);
            
            // Find user by both token and email for maximum reliability
            $user = $this->userRepository->findOneBy([
                'resetToken' => trim($dto->verificationCode),
                'email' => $email
            ]);

            if (!$user) {
                file_put_contents($logPath, sprintf("[%s] USER NOT FOUND by Token: %s and Email: %s\n", date('Y-m-d H:i:s'), $dto->verificationCode, $email), FILE_APPEND);
                $this->addFlash('danger', 'Code de vérification invalide ou expiré pour cet email.');
                return $this->render('security/password_reset.html.twig', [
                    'form' => $form->createView(),
                    'email' => $email,
                ]);
            }

            // Check if token expired
            if ($user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
                $this->addFlash('danger', 'Le code de vérification a expiré. Veuillez demander un nouveau code.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Reset password - DO NOT trim the password itself to stay consistent with registration
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->newPassword);
            $user->setMotDePasse($hashedPassword);
            $user->setResetToken(null);
            $user->setMotDePasse($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $this->entityManager->flush();
            
            $logPath = $this->getParameter('kernel.project_dir') . '/public/debug_reset.txt';
            file_put_contents($logPath, sprintf("[%s] SUCCESSFUL FLUSH for: %s. New Hash: %s\n", date('Y-m-d H:i:s'), $user->getEmail(), $hashedPassword), FILE_APPEND);

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        } elseif ($form->isSubmitted()) {
            $logPath = $this->getParameter('kernel.project_dir') . '/public/debug_reset.txt';
            file_put_contents($logPath, sprintf("[%s] FORM SUBMITTED BUT INVALID\n", date('Y-m-d H:i:s')), FILE_APPEND);
            foreach ($form->getErrors(true) as $error) {
                file_put_contents($logPath, "ERROR: " . $error->getMessage() . "\n", FILE_APPEND);
            }
        }

        return $this->render('security/password_reset.html.twig', [
            'form' => $form->createView(),
            'email' => $email,
        ]);
    }
}
