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
            } else {
                // Check if email already exists
                $dto->email = strtolower($dto->email);
                $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);
                if ($existingUser) {
                    $this->addFlash('danger', 'Cet email est déjà utilisé par un autre utilisateur.');
                } else {
                    $user = match ($dto->role) {
                        'ROLE_ADMIN' => new \App\Entity\Administrator(),
                        'ROLE_PROFESSOR' => new \App\Entity\Professor(),
                        default => new \App\Entity\Student(),
                    };

                    $user->setNom($dto->nom);
                    $user->setPrenom($dto->prenom);
                    $user->setEmail($dto->email);
                    $user->setRole($dto->role); 
                    $user->setPays($dto->pays ?? 'TN');

                    if ($user instanceof \App\Entity\Student) {
                        $user->setAge($dto->age ?? 18);
                        $user->setSexe($dto->sexe ?? 'H');
                        $user->setEtablissement($dto->etablissement ?? 'N/A');
                        $user->setNiveau($dto->niveau ?? 'N/A');
                    } elseif ($user instanceof \App\Entity\Professor) {
                        $user->setSpecialite($dto->specialite ?? 'N/A');
                        $user->setNiveauEnseignement($dto->niveauEnseignement ?? 'N/A');
                        $user->setAnneesExperience($dto->anneesExperience ?? 0);
                        $user->setEtablissement($dto->etablissementProfesseur ?? 'N/A');
                    }

                    $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->motDePasse);
                    $user->setMotDePasse($hashedPassword);
                    $user->setStatut('actif');

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Utilisateur créé avec succès');
                    return $this->redirectToRoute('admin_users');
                }
            }
        }

        $state = $request->query->get('state', 'default');
        
        // Search, Filters & Sorting parameters
        $query = $request->query->get('q');
        $role = $request->query->get('role');
        $status = $request->query->get('status');
        $reactivationOnly = $request->query->getBoolean('reactivation_only');
        $sort = $request->query->get('sort', 'dateInscription');
        $direction = $request->query->get('direction', 'DESC');

        $users = $this->userRepository->findBySearchQuery($query, $role, $status, $sort, $direction, $reactivationOnly);
        
        $mockData = $this->mockProvider->getUsersData();
        $mockData['users'] = $users; 
        
        $userKpis = $this->userRepository->getUsersKpiData();
        
        $viewModel = [
            'state' => $state,
            'data' => $mockData,
            'users' => $users,
            'form' => $form->createView(),
            'has_errors' => $form->isSubmitted() && !$form->isValid(),
            'user_kpis' => $userKpis,
            'filters' => [
                'q' => $query,
                'role' => $role,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'reactivation_only' => $reactivationOnly
            ],
            'pending_reactivations' => $this->userRepository->count(['demandeReactivation' => true])
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
        $dto->pays = method_exists($user, 'getPays') ? $user->getPays() : 'TN';
        $dto->motDePasse = ''; // Don't prefill password

        if ($user instanceof \App\Entity\Student) {
            $dto->age = $user->getAge();
            $dto->sexe = $user->getSexe();
            $dto->etablissement = $user->getEtablissement();
            $dto->niveau = $user->getNiveau();
        } elseif ($user instanceof \App\Entity\Professor) {
            $dto->specialite = $user->getSpecialite();
            $dto->niveauEnseignement = $user->getNiveauEnseignement();
            $dto->anneesExperience = $user->getAnneesExperience();
            $dto->etablissementProfesseur = $user->getEtablissement();
        }

        $form = $this->createForm(\App\Form\Bo\UserCreateType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email is already taken by ANOTHER user
            $dto->email = strtolower($dto->email);
            $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('danger', 'Cet email est déjà utilisé par un autre utilisateur.');
                return $this->render('bo/user_edit.html.twig', [
                    'form' => $form->createView(),
                    'user' => $user,
                ]);
            }

            $user->setNom($dto->nom);
            $user->setPrenom($dto->prenom);
            $user->setEmail($dto->email);
            $user->setRole($dto->role);

            $user->setPays($dto->pays ?? 'TN');

            // Specific fields mapping
            if ($user instanceof \App\Entity\Student) {
                $user->setAge($dto->age ?? $user->getAge());
                $user->setSexe($dto->sexe ?? $user->getSexe());
                $user->setEtablissement($dto->etablissement ?? $user->getEtablissement());
                $user->setNiveau($dto->niveau ?? $user->getNiveau());
            } elseif ($user instanceof \App\Entity\Professor) {
                $user->setSpecialite($dto->specialite ?? $user->getSpecialite());
                $user->setNiveauEnseignement($dto->niveauEnseignement ?? $user->getNiveauEnseignement());
                $user->setAnneesExperience($dto->anneesExperience ?? $user->getAnneesExperience());
                $user->setEtablissement($dto->etablissementProfesseur ?? $user->getEtablissement());
            }

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

    #[Route('/admin/utilisateurs/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_status_' . $id, $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users');
        }

        // Toggle status
        $currentStatus = $user->getStatut();
        $newStatus = ($currentStatus === 'actif') ? 'inactif' : 'actif';
        $user->setStatut($newStatus);

        $this->entityManager->flush();

        $statusLabel = $newStatus === 'actif' ? 'activé' : 'désactivé';
        $this->addFlash('success', sprintf('Utilisateur %s avec succès.', $statusLabel));
        
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/utilisateurs/deactivate-inactive', name: 'admin_deactivate_inactive', methods: ['POST'])]
    public function executeDeactivation(Request $request): Response
    {
        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('deactivate_inactive', $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users');
        }

        // Execute the deactivation logic here (same as command)
        $threshold = new \DateTimeImmutable('-5 minutes');

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from(\App\Entity\User::class, 'u')
            ->where('u.statut = :statut')
            ->andWhere('u.lastActivityAt IS NOT NULL')
            ->andWhere('u.lastActivityAt < :threshold')
            ->setParameter('statut', 'actif')
            ->setParameter('threshold', $threshold);

        $inactiveUsers = $qb->getQuery()->getResult();

        if (count($inactiveUsers) === 0) {
            $this->addFlash('info', 'Aucun utilisateur inactif trouvé.');
            return $this->redirectToRoute('admin_users');
        }

        foreach ($inactiveUsers as $user) {
            $user->setStatut('inactif');
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%d utilisateur(s) désactivé(s) pour inactivité.', count($inactiveUsers)));
        
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/utilisateurs/{id}/accepter-reactivation', name: 'admin_user_accept_reactivation', methods: ['POST'])]
    public function acceptReactivation(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('accept_reactivation_' . $id, $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setStatut('actif');
        $user->setDemandeReactivation(false);
        $user->setDateDemandeReactivation(null);

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Le compte de %s %s a été réactivé.', $user->getNom(), $user->getPrenom()));
        
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/utilisateurs/{id}/refuser-reactivation', name: 'admin_user_refuse_reactivation', methods: ['POST'])]
    public function refuseReactivation(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('refuse_reactivation_' . $id, $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setDemandeReactivation(false);
        $user->setDateDemandeReactivation(null);

        $this->entityManager->flush();

        $this->addFlash('info', sprintf('La demande de réactivation de %s %s a été refusée.', $user->getNom(), $user->getPrenom()));
        
        return $this->redirectToRoute('admin_users');
    }
}
