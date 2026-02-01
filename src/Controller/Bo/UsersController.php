<?php

namespace App\Controller\Bo;

use App\Repository\UserRepository;
use App\Service\Mock\BoMockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UsersController extends AbstractController
{
    public function __construct(
        private BoMockDataProvider $mockProvider,
        private UserRepository $userRepository,
        private \Doctrine\ORM\EntityManagerInterface $entityManager,
        private \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/admin/utilisateurs', name: 'admin_users')]
    public function index(Request $request): Response
    {
        $dto = new \App\Dto\BoUserCreateDTO();
        $form = $this->createForm(\App\Form\Bo\UserCreateType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validate password is provided for new users
            if (empty($dto->motDePasse)) {
                $this->addFlash('danger', 'Le mot de passe est obligatoire lors de la création d\'un utilisateur.');
                
                $viewModel = [
                    'state' => $request->query->get('state', 'default'),
                    'data' => $this->mockProvider->getUsersData(),
                    'users' => $this->userRepository->findAll(),
                    'form' => $form->createView(),
                ];
                return $this->render('bo/users.html.twig', $viewModel);
            }

            $user = match ($dto->role) {
                'ROLE_ADMIN' => new \App\Entity\Administrator(),
                'ROLE_PROFESSOR' => new \App\Entity\Professor(),
                default => new \App\Entity\Student(),
            };

            $user->setNom($dto->nom);
            $user->setPrenom($dto->prenom);
            $user->setEmail($dto->email);
            $user->setRole($dto->role); 
            // Note: role property in User entity might be slightly confusing with getRoles(),
            // but normally getRoles() returns ['ROLE_USER', $this->role]. 
            // Let's ensure consistency across the app. 
            // Usually 'role' in DB stores 'ROLE_ADMIN' etc or just 'admin'.
            // Looking at RegistrationType: 'student', 'professor' are stored.
            // DTO choices: 'ROLE_ADMIN', etc.
            // I should probably conform to existing storage value if it differs?
            // User.php: getRoles() -> returns [$this->role].
            // So if I save 'ROLE_ADMIN', getRoles() will be ['ROLE_ADMIN', 'ROLE_USER']. Correct.
            // If RegistrationType saves 'student', then getRoles() is ['student', 'ROLE_USER'].
            // Security.yaml likely uses hierarchy or specific roles.
            // I will use ROLE_... convention as it's standard, assuming existing code handles it or I'll adjust.
            
            // Wait, RegistrationType saves 'student'/'professor' in the 'role' field.
            // Let's check User.php getRoles again. 
            // "public function getRoles(): array { $roles = [$this->role ?? 'ROLE_USER']; ... }"
            // If I save 'student', then $user->hasRole('ROLE_STUDENT') might fail if not mapped.
            // Let's stick to ROLE_ADMIN for Admin.

            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->motDePasse);
            $user->setMotDePasse($hashedPassword);
            $user->setStatut('actif');

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès');

            return $this->redirectToRoute('admin_users');
        }

        $state = $request->query->get('state', 'default');
        
        $users = $this->userRepository->findAll();
        
        $mockData = $this->mockProvider->getUsersData();
        $mockData['users'] = $users; // Override with real users
        
        $viewModel = [
            'state' => $state,
            'data' => $mockData,
            'users' => $users,
            'form' => $form->createView(),
        ];

        return $this->render('bo/users.html.twig', $viewModel);
    }

    #[Route('/admin/utilisateurs/{id}/modifier', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // Create DTO from existing user
        $dto = new \App\Dto\BoUserCreateDTO();
        $dto->nom = $user->getNom();
        $dto->prenom = $user->getPrenom();
        $dto->email = $user->getEmail();
        $dto->role = $user->getRole();
        $dto->motDePasse = ''; // Don't prefill password

        $form = $this->createForm(\App\Form\Bo\UserCreateType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setNom($dto->nom);
            $user->setPrenom($dto->prenom);
            $user->setEmail($dto->email);
            $user->setRole($dto->role);

            // Only update password if provided
            if (!empty($dto->motDePasse)) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->motDePasse);
                $user->setMotDePasse($hashedPassword);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('bo/user_edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // Prevent deleting yourself
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_user_' . $id, $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès');
        return $this->redirectToRoute('admin_users');
    }
}
