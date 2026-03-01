<?php

namespace App\Controller\Bo;

use App\Repository\UserRepository;
use App\Service\Mock\BoMockDataProvider;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UsersController extends AbstractController
{
    public function __construct(
        private BoMockDataProvider $mockProvider,
        private UserRepository $userRepository,
        private \Doctrine\ORM\EntityManagerInterface $entityManager,
        private \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher,
        private \Symfony\Component\Mailer\MailerInterface $mailer
    ) {}

    #[Route('/admin/utilisateurs', name: 'admin_users')]
    public function index(Request $request): Response
    {
        $dto = new \App\Dto\BoUserCreateDTO();
        $form = $this->createForm(\App\Form\Bo\UserCreateType::class, $dto, [
            'validation_groups' => function($form) {
                $data = $form->getData();
                $groups = ['Default', 'create'];
                if ($data->role === 'ROLE_STUDENT') $groups[] = 'student';
                if ($data->role === 'ROLE_PROFESSOR') $groups[] = 'professor';
                return $groups;
            }
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Check if email already exists
                $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);
                if ($existingUser) {
                    $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur.');
                } else {
                    try {
                        $user = match ($dto->role) {
                            'ROLE_ADMIN' => new \App\Entity\Administrator(),
                            'ROLE_PROFESSOR' => new \App\Entity\Professor(),
                            default => new \App\Entity\Student(),
                        };

                        $user->setNom((string) $dto->nom);
                        $user->setPrenom((string) $dto->prenom);
                        $user->setEmail((string) $dto->email);
                        $user->setTelephone($dto->telephone);
                        $user->setRole((string) $dto->role);
                        $user->setPays($dto->pays ?? 'TN');

                        if ($user instanceof \App\Entity\Student) {
                            $user->setAge((int) $dto->age);
                            $user->setSexe((string) $dto->sexe);
                            $user->setEtablissement((string) $dto->etablissement);
                            $user->setNiveau((string) $dto->niveau);
                        } elseif ($user instanceof \App\Entity\Professor) {
                            $user->setAge((int) $dto->age);
                            $user->setSexe((string) $dto->sexe);
                            $user->setSpecialite((string) $dto->specialite);
                            $user->setNiveauEnseignement((string) $dto->niveauEnseignement);
                            $user->setAnneesExperience((int) $dto->anneesExperience);
                            $user->setEtablissement((string) $dto->etablissementProfesseur);
                        }

                        $hashedPassword = $this->passwordHasher->hashPassword($user, (string) $dto->motDePasse);
                        $user->setMotDePasse($hashedPassword);
                        $user->setStatut('actif');

                        $this->entityManager->persist($user);
                        $this->entityManager->flush();

                        $this->addFlash('success', 'Utilisateur créé avec succès');
                        return $this->redirectToRoute('admin_users');
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
                    }
                }
            } else {
                foreach ($form->getErrors(true) as $error) {
                    if ($error instanceof \Symfony\Component\Form\FormError) {
                        $this->addFlash('error', 'Erreur de validation : ' . $error->getMessage());
                    }
                }
            }
        }

        $state = $request->query->get('state', 'default');
        
        // Search, Filters & Sorting parameters
        $query     = (string) $request->query->get('q') ?: null;
        $role      = (string) $request->query->get('role') ?: null;
        $status    = (string) $request->query->get('status') ?: null;
        $sort      = (string) $request->query->get('sort', 'dateInscription');
        $direction = (string) $request->query->get('direction', 'DESC');
        $page      = max(1, (int) $request->query->get('page', 1));
        $perPage   = (int) $request->query->get('perPage', 25);

        $pagination = $this->userRepository->findPaginated($query, $role, $status, $sort, $direction, $page, $perPage);
        
        $mockData = $this->mockProvider->getUsersData();
        $mockData['users'] = $pagination['users'];
        
        $userKpis = $this->userRepository->getUsersKpiData();
        
        $viewModel = [
            'state'      => $state,
            'data'       => $mockData,
            'users'      => $pagination['users'],
            'form'       => $form->createView(),
            'user_kpis'  => $userKpis,
            'pagination' => $pagination,
            'filters'    => [
                'q'         => $query,
                'role'      => $role,
                'status'    => $status,
                'sort'      => $sort,
                'direction' => $direction,
                'page'      => $pagination['page'],
                'perPage'   => $pagination['perPage'],
            ]
        ];

        return $this->render('bo/users.html.twig', $viewModel);
    }

    #[Route('/admin/utilisateurs/search', name: 'admin_users_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query     = (string) $request->query->get('q') ?: null;
        $role      = (string) $request->query->get('role') ?: null;
        $status    = (string) $request->query->get('status') ?: null;
        $sort      = (string) $request->query->get('sort', 'dateInscription');
        $direction = (string) $request->query->get('direction', 'DESC');
        $page      = max(1, (int) $request->query->get('page', 1));
        $perPage   = (int) $request->query->get('perPage', 25);

        $pagination = $this->userRepository->findPaginated($query, $role, $status, $sort, $direction, $page, $perPage);
        
        // Render the table rows partial
        $html = $this->renderView('bo/_user_table_rows.html.twig', [
            'users' => $pagination['users']
        ]);

        return $this->json([
            'success' => true,
            'html'    => $html,
            'count'   => count($pagination['users']),
            'total'   => $pagination['total'],
            'pages'   => $pagination['pages'],
            'page'    => $pagination['page'],
            'perPage' => $pagination['perPage'],
            'query'   => $query,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/modifier', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // Create DTO from existing user
        $dto = new \App\Dto\BoUserCreateDTO();
        $dto->nom = $user->getNom();
        $dto->prenom = $user->getPrenom();
        $dto->email = $user->getEmail();
        $dto->telephone = $user->getTelephone();
        $dto->role = $user->getRole();
        $dto->pays = $user->getPays() ?? 'TN';
        $dto->motDePasse = ''; // Don't prefill password

        if ($user instanceof \App\Entity\Student) {
            $dto->age = $user->getAge();
            $dto->sexe = $user->getSexe();
            $dto->etablissement = $user->getEtablissement();
            $dto->niveau = $user->getNiveau();
        } elseif ($user instanceof \App\Entity\Professor) {
            $dto->age = $user->getAge();
            $dto->sexe = $user->getSexe();
            $dto->specialite = $user->getSpecialite();
            $dto->niveauEnseignement = $user->getNiveauEnseignement();
            $dto->anneesExperience = $user->getAnneesExperience();
            $dto->etablissementProfesseur = $user->getEtablissement();
        }

        $form = $this->createForm(\App\Form\Bo\UserCreateType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email is already taken by ANOTHER user
            $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur.');
                return $this->render('bo/user_edit.html.twig', [
                    'form' => $form->createView(),
                    'user' => $user,
                ]);
            }

            $user->setNom((string) $dto->nom);
            $user->setPrenom((string) $dto->prenom);
            $user->setEmail((string) $dto->email);
            $user->setTelephone($dto->telephone);
            $user->setRole((string) $dto->role);

            $user->setPays($dto->pays ?? 'TN');

            // Specific fields mapping
            if ($user instanceof \App\Entity\Student) {
                $user->setAge((int) ($dto->age ?? $user->getAge()));
                $user->setSexe((string) ($dto->sexe ?? $user->getSexe()));
                $user->setEtablissement((string) ($dto->etablissement ?? $user->getEtablissement()));
                $user->setNiveau((string) ($dto->niveau ?? $user->getNiveau()));
            } elseif ($user instanceof \App\Entity\Professor) {
                $user->setAge((int) ($dto->age ?? $user->getAge()));
                $user->setSexe((string) ($dto->sexe ?? $user->getSexe()));
                $user->setSpecialite((string) ($dto->specialite ?? $user->getSpecialite()));
                $user->setNiveauEnseignement((string) ($dto->niveauEnseignement ?? $user->getNiveauEnseignement()));
                $user->setAnneesExperience((int) ($dto->anneesExperience ?? $user->getAnneesExperience()));
                $user->setEtablissement((string) ($dto->etablissementProfesseur ?? $user->getEtablissement()));
            }

            // Only update password if provided
            if (!empty($dto->motDePasse)) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, (string) $dto->motDePasse);
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
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // Prevent deleting yourself
        /** @var \App\Entity\User|null $currentAdmin */
        $currentAdmin = $this->getUser();
        if ($currentAdmin !== null && $user->getId() === $currentAdmin->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_user_' . $id, (string) $token)) {
            $this->addFlash('error', 'Token de sécurité invalide.');
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
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_status_' . $id, (string) $token)) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users');
        }

        // Toggle status
        $currentStatus = $user->getStatut();
        $newStatus = ($currentStatus === 'actif') ? 'inactif' : 'actif';
        $user->setStatut($newStatus);

        $this->entityManager->flush();

        // Send email notification if account was deactivated
        if ($newStatus === 'inactif') {
            try {
                $email = (new \Symfony\Bridge\Twig\Mime\TemplatedEmail())
                    ->from('noreply@studysprint.com')
                    ->to((string) $user->getEmail())
                    ->subject('Votre compte StudySprint a été désactivé')
                    ->htmlTemplate('emails/account_disabled.html.twig')
                    ->context(['user' => $user]);

                $this->mailer->send($email);
            } catch (\Exception $e) {
                // Log error but don't block the status change
                $this->addFlash('warning', 'Compte désactivé mais l\'email de notification n\'a pas pu être envoyé.');
            }
        }

        $statusLabel = $newStatus === 'actif' ? 'activé' : 'désactivé';
        $this->addFlash('success', sprintf('Utilisateur %s avec succès.', $statusLabel));
        
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/utilisateurs/deactivate-inactive', name: 'admin_deactivate_inactive', methods: ['POST'])]
    public function executeDeactivation(Request $request): Response
    {
        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('deactivate_inactive', (string) $token)) {
            $this->addFlash('error', 'Token de sécurité invalide.');
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

    #[Route('/admin/utilisateurs/bulk-action', name: 'admin_users_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('bulk_action', (string) $token)) {
            return $this->json(['success' => false, 'message' => 'Token de sécurité invalide.'], 403);
        }

        $ids    = $request->request->all('ids');
        $action = $request->request->get('action');

        if (empty($ids) || !in_array($action, ['activate', 'deactivate', 'delete'], true)) {
            return $this->json(['success' => false, 'message' => 'Paramètres invalides.'], 400);
        }

        $count = 0;
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();

        try {
            if ($action === 'delete') {
                foreach ($ids as $id) {
                    $user = $this->userRepository->find((int) $id);
                    if ($user && ($currentUser === null || $user->getId() !== $currentUser->getId())) {
                        $this->entityManager->remove($user);
                        $count++;
                    }
                }
            } else {
                $newStatut = $action === 'activate' ? 'actif' : 'inactif';
                foreach ($ids as $id) {
                    $user = $this->userRepository->find((int) $id);
                    if ($user && ($currentUser === null || $user->getId() !== $currentUser->getId())) {
                        $user->setStatut($newStatut);
                        $count++;
                    }
                }
            }

            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur technique : ' . $e->getMessage()
            ], 500);
        }

        $labels = [
            'activate'   => 'activé(s)',
            'deactivate' => 'désactivé(s)',
            'delete'     => 'supprimé(s)',
        ];
        $label = $labels[$action];

        return $this->json([
            'success' => true,
            'count'   => $count,
            'message' => sprintf('%d utilisateur(s) %s avec succès.', $count, $label),
        ]);
    }

    #[Route('/admin/utilisateurs/export/csv', name: 'admin_users_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $ids   = $request->query->all('ids');
        $users = $this->resolveExportUsers($ids);

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Prénom', 'Nom', 'Email', 'Rôle', 'Statut', 'Pays', 'Téléphone', 'Date Inscription'], ';');

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->getId(),
                    $user->getPrenom(),
                    $user->getNom(),
                    $user->getEmail(),
                    $user->getRole(),
                    $user->getStatut(),
                    $user->getPays() ?? '',
                    $user->getTelephone() ?? '',
                    $user->getDateInscription()?->format('Y-m-d H:i:s') ?? '',
                ], ';');
            }
            fclose($handle);
        });

        $filename = 'utilisateurs_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/admin/utilisateurs/export/pdf', name: 'admin_users_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        $ids   = $request->query->all('ids');
        $users = $this->resolveExportUsers($ids);

        $html = $this->renderView('bo/_export_pdf.html.twig', [
            'users'      => $users,
            'exportDate' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'utilisateurs_' . date('Ymd_His') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Returns users by IDs (or all non-admin users if no IDs given).
     *
     * @param int[]|string[] $ids
     * @return \App\Entity\User[]
     */
    private function resolveExportUsers(array $ids): array
    {
        if (!empty($ids)) {
            $intIds = array_map('intval', $ids);
            return $this->userRepository->findBy(['id' => $intIds]);
        }
        // Export all non-admin users
        return $this->userRepository->findBySearchQuery(null, null, null, 'dateInscription', 'DESC');
    }
}
