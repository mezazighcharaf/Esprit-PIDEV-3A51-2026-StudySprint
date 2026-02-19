<?php

namespace App\Controller\Bo;

use App\Entity\UserProfile;
use App\Form\Bo\UserProfileType;
use App\Repository\UserProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bo/user-profiles', name: 'bo_user_profiles_')]
class UserProfileController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserProfileRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = $request->query->get('dir', 'asc');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $allowedSort = ['id', 'level', 'specialty'];
        if (!in_array($sort, $allowedSort)) {
            $sort = 'id';
        }
        if (!in_array($dir, ['asc', 'desc'])) {
            $dir = 'asc';
        }

        $qb = $repository->createQueryBuilder('up')
            ->leftJoin('up.user', 'u');

        if ($q) {
            $qb->andWhere('u.email LIKE :q OR up.specialty LIKE :q OR up.level LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        $qb->orderBy('up.' . $sort, $dir);

        $total = (int) (clone $qb)->select('COUNT(up.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/user_profiles/index.html.twig', [
            'items' => $items, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new UserProfile();
        $form = $this->createForm(UserProfileType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Profil créé avec succès.');
            return $this->redirectToRoute('bo_user_profiles_index');
        }

        return $this->render('bo/user_profiles/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(UserProfile $item): Response
    {
        return $this->render('bo/user_profiles/show.html.twig', [
            'item' => $item,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, UserProfile $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserProfileType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil modifié avec succès.');
            return $this->redirectToRoute('bo_user_profiles_index');
        }

        return $this->render('bo/user_profiles/edit.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, UserProfile $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Profil supprimé avec succès.');
        }

        return $this->redirectToRoute('bo_user_profiles_index');
    }
}
