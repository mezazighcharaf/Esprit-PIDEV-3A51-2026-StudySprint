<?php

namespace App\Controller\Bo;

use App\Entity\StudyGroup;
use App\Form\Bo\StudyGroupType;
use App\Repository\StudyGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/groups', name: 'bo_groups_')]
class StudyGroupController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, StudyGroupRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = strtolower($request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $allowedSort = ['id', 'name', 'privacy', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';

        $qb = $repo->createQueryBuilder('g');
        if ($q) $qb->where('g.name LIKE :q')->setParameter('q', "%$q%");
        $qb->orderBy("g.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(g.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/groups/index.html.twig', [
            'items' => $items, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new StudyGroup();
        $form = $this->createForm(StudyGroupType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Groupe créé.');
            return $this->redirectToRoute('bo_groups_index');
        }
        return $this->render('bo/groups/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(StudyGroup $item): Response
    {
        return $this->render('bo/groups/show.html.twig', ['item' => $item]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, StudyGroup $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(StudyGroupType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Groupe modifié.');
            return $this->redirectToRoute('bo_groups_index');
        }
        return $this->render('bo/groups/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, StudyGroup $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Groupe supprimé.');
        }
        return $this->redirectToRoute('bo_groups_index');
    }
}
