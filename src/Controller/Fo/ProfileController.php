<?php

namespace App\Controller\Fo;

use App\Dto\ProfileDTO;
use App\Entity\Student;
use App\Entity\Professor;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {}

    #[Route('', name: 'app_profile')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('fo/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $dto = new ProfileDTO();
        $this->mapEntityToDto($user, $dto);

        $form = $this->createForm(ProfileType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mapDtoToEntity($dto, $user);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
        }

        return $this->render('fo/profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/request-password-change', name: 'app_profile_request_password_change')]
    public function requestPasswordChange(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
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
                ->subject('Votre code de vérification pour changer de mot de passe - StudySprint')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'verificationCode' => $verificationCode,
                ]);

            $this->mailer->send($email);
            
            $this->addFlash('success', 'Un code de vérification a été envoyé à votre adresse email.');
        } catch (\Exception $e) {
            $this->addFlash('danger', "Une erreur est survenue lors de l'envoi de l'email. Veuillez réessayer plus tard.");
            return $this->redirectToRoute('app_profile_edit');
        }

        return $this->redirectToRoute('app_reset_password', ['email' => $user->getEmail()]);
    }

    private function mapEntityToDto($user, ProfileDTO $dto): void
    {
        $dto->nom = $user->getNom();
        $dto->prenom = $user->getPrenom();
        $dto->email = $user->getEmail();
        
        // Handle pays (defined in subclasses)
        if (method_exists($user, 'getPays')) {
            $dto->pays = $user->getPays();
        }

        $rawRole = strtoupper($user->getRole() ?? '');
        $isStudent = ($user instanceof Student || str_contains($rawRole, 'STUDENT'));
        $isProfessor = ($user instanceof Professor || str_contains($rawRole, 'PROFESSOR'));

        if ($isStudent) {
            $dto->age = method_exists($user, 'getAge') ? $user->getAge() : null;
            $dto->sexe = method_exists($user, 'getSexe') ? $user->getSexe() : null;
            $dto->etablissement = method_exists($user, 'getEtablissement') ? $user->getEtablissement() : null;
            $dto->niveau = method_exists($user, 'getNiveau') ? $user->getNiveau() : null; 
        } elseif ($isProfessor) {
            $dto->specialite = method_exists($user, 'getSpecialite') ? $user->getSpecialite() : null;
            $dto->niveauEnseignement = method_exists($user, 'getNiveauEnseignement') ? $user->getNiveauEnseignement() : null;
            $dto->anneesExperience = method_exists($user, 'getAnneesExperience') ? $user->getAnneesExperience() : null;
            $dto->etablissementProfesseur = method_exists($user, 'getEtablissement') ? $user->getEtablissement() : null;
        }
    }

    private function mapDtoToEntity(ProfileDTO $dto, $user): void
    {
        $user->setNom($dto->nom);
        $user->setPrenom($dto->prenom);
        $user->setEmail($dto->email);
        
        if (method_exists($user, 'setPays')) {
            $user->setPays($dto->pays);
        }

        $rawRole = strtoupper($user->getRole() ?? '');
        $isStudent = ($user instanceof Student || str_contains($rawRole, 'STUDENT'));
        $isProfessor = ($user instanceof Professor || str_contains($rawRole, 'PROFESSOR'));

        if ($isStudent) {
            if (method_exists($user, 'setAge')) $user->setAge($dto->age);
            if (method_exists($user, 'setSexe')) $user->setSexe($dto->sexe);
            if (method_exists($user, 'setEtablissement')) $user->setEtablissement($dto->etablissement);
            if (method_exists($user, 'setNiveau')) $user->setNiveau($dto->niveau);
        } elseif ($isProfessor) {
            if (method_exists($user, 'setSpecialite')) $user->setSpecialite($dto->specialite);
            if (method_exists($user, 'setNiveauEnseignement')) $user->setNiveauEnseignement($dto->niveauEnseignement);
            if (method_exists($user, 'setAnneesExperience')) $user->setAnneesExperience($dto->anneesExperience);
            if (method_exists($user, 'setEtablissement')) $user->setEtablissement($dto->etablissementProfesseur);
        }
    }
}
