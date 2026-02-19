<?php

namespace App\Controller\Bo;

use App\Entity\User;
use App\Form\Bo\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\CsvExportService;

#[Route('/bo/users', name: 'bo_users_')]
class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = $request->query->get('dir', 'desc');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $allowedSort = ['id', 'email', 'fullName', 'userType', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $qb = $repo->createQueryBuilder('u');
        if ($q) {
            $qb->where('u.email LIKE :q OR u.fullName LIKE :q')->setParameter('q', "%$q%");
        }
        $qb->orderBy("u.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $users = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/users/index.html.twig', [
            'users' => $users, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(UserRepository $repo, CsvExportService $csv): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $repo->findBy([], ['id' => 'DESC']);

        $rows = array_map(fn($u) => [
            $u->getId(),
            $u->getFullName(),
            $u->getEmail(),
            $u->getUserType(),
            implode(', ', $u->getRoles()),
            in_array('ROLE_ADMIN', $u->getRoles()) ? 'Admin' : ucfirst(strtolower($u->getUserType())),
            $u->getCreatedAt()?->format('d/m/Y H:i'),
        ], $users);

        return $csv->export(
            'users_export_' . date('Y-m-d') . '.csv',
            ['ID', 'Nom complet', 'Email', 'Type', 'Rôles', 'Actif', 'Créé le'],
            $rows
        );
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plainPassword));
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('bo_users_index');
        }

        return $this->render('bo/users/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('bo/users/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }
            $em->flush();
            $this->addFlash('success', 'Utilisateur modifié.');
            return $this->redirectToRoute('bo_users_index');
        }

        return $this->render('bo/users/edit.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }
        return $this->redirectToRoute('bo_users_index');
    }
}
