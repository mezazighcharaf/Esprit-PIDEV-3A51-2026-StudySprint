<?php

namespace App\Controller\Bo;

use App\Entity\GroupPost;
use App\Form\Bo\GroupPostType;
use App\Repository\GroupPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/posts', name: 'bo_posts_')]
class GroupPostController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, GroupPostRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = strtolower($request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $allowedSort = ['id', 'title', 'postType', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';

        $qb = $repo->createQueryBuilder('p');
        if ($q) $qb->where('p.title LIKE :q OR p.body LIKE :q')->setParameter('q', "%$q%");
        $qb->orderBy("p.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/posts/index.html.twig', [
            'items' => $items, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new GroupPost();
        $form = $this->createForm(GroupPostType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Publication créée.');
            return $this->redirectToRoute('bo_posts_index');
        }
        return $this->render('bo/posts/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(GroupPost $item): Response
    {
        return $this->render('bo/posts/show.html.twig', ['item' => $item]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, GroupPost $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(GroupPostType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Publication modifiée.');
            return $this->redirectToRoute('bo_posts_index');
        }
        return $this->render('bo/posts/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, GroupPost $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Publication supprimée.');
        }
        return $this->redirectToRoute('bo_posts_index');
    }
}
