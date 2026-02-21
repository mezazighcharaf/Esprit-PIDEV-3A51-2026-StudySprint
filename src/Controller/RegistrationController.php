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
            $dto->email = strtolower($dto->email);
            // Check Uniqueness
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $dto->email]);
            if ($existingUser) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('Cet email est déjà utilisé.'));
            } else {
                $user = null;

            if ($dto->role === 'student') {
                $user = new Student();
                $user->setAge($dto->age);
                $user->setSexe($dto->sexe);
                $user->setEtablissement($dto->etablissement);
                $user->setNiveau($dto->niveau);
                $user->setPays($dto->pays);
                $user->setRole('ROLE_STUDENT');
            } elseif ($dto->role === 'professor') {
                $user = new Professor();
                $user->setAge($dto->age);
                $user->setSexe($dto->sexe);
                $user->setSpecialite($dto->specialite);
                $user->setNiveauEnseignement($dto->niveauEnseignement);
                $user->setAnneesExperience($dto->anneesExperience);
                $user->setPays($dto->pays ?? 'TN');
                $user->setEtablissement($dto->etablissement);
                $user->setRole('ROLE_PROFESSOR');
            }

            if ($user && !$form->getErrors(true)->count()) { // Double check no errors added manually
                // Common fields
                $user->setNom($dto->nom);
                $user->setPrenom($dto->prenom);
                $user->setEmail($dto->email);
                $user->setTelephone($dto->telephone);
                
                // Hash Password
                $user->setMotDePasse(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $dto->motDePasse
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
                    $errors[] = $error->getMessage() . ' (' . $error->getOrigin()->getName() . ')';
                }
                file_put_contents('form_errors.log', date('Y-m-d H:i:s') . ': ' . implode(', ', $errors) . PHP_EOL, FILE_APPEND);
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
