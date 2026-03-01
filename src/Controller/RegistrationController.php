<?php

namespace App\Controller;

use App\Dto\UserRegistrationDTO;
use App\Entity\Professor;
use App\Entity\Student;
use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $dto = new UserRegistrationDTO();
        $form = $this->createForm(RegistrationType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
            $dto->email = strtolower((string) $dto->email);
            // Check Uniqueness
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $dto->email]);
            if ($existingUser) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('Cet email est déjà utilisé.'));
            } else {
                $user = null;

            if ($dto->role === 'student') {
                $user = new Student();
                $user->setAge((int) $dto->age);
                $user->setSexe((string) $dto->sexe);
                $user->setEtablissement((string) $dto->etablissement);
                $user->setNiveau((string) $dto->niveau);
                $user->setPays((string) $dto->pays);
                $user->setRole('ROLE_STUDENT');
            } elseif ($dto->role === 'professor') {
                $user = new Professor();
                $user->setAge((int) $dto->age);
                $user->setSexe((string) $dto->sexe);
                $user->setSpecialite((string) $dto->specialite);
                $user->setNiveauEnseignement((string) $dto->niveauEnseignement);
                $user->setAnneesExperience((int) $dto->anneesExperience);
                $user->setPays($dto->pays ?? 'TN');
                $user->setEtablissement((string) $dto->etablissement);
                $user->setRole('ROLE_PROFESSOR');
            }

            if ($user && !$form->getErrors(true)->count()) { // Double check no errors added manually
                // Common fields
                $user->setNom((string) $dto->nom);
                $user->setPrenom((string) $dto->prenom);
                $user->setEmail((string) $dto->email);
                $user->setTelephone($dto->telephone);
                
                // Hash Password
                $user->setMotDePasse(
                    $userPasswordHasher->hashPassword(
                        $user,
                        (string) $dto->motDePasse
                    )
                );
                
                $user->setStatut('actif');
                $user->setDateInscription(new \DateTimeImmutable());

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Inscription réussie !');
                return $this->redirectToRoute('app_register');
            }
            } // End else unique
            } else {
                // Log Errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    if ($error instanceof \Symfony\Component\Form\FormError) {
                        $origin = $error->getOrigin();
                        $fieldName = $origin !== null ? $origin->getName() : 'unknown';
                        $errors[] = $error->getMessage() . ' (' . $fieldName . ')';
                    }
                }
                file_put_contents('form_errors.log', date('Y-m-d H:i:s') . ': ' . implode(', ', $errors) . PHP_EOL, FILE_APPEND);
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
