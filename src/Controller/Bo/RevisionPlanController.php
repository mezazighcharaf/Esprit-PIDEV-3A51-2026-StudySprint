<?php

namespace App\Controller\Bo;

use App\Entity\RevisionPlan;
use App\Form\Bo\RevisionPlanType;
use App\Repository\RevisionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/plans', name: 'bo_plans_')]
class RevisionPlanController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        RevisionPlanRepository $repo,
        PaginatorInterface $paginator
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = strtolower($request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $allowedSort = ['id', 'title', 'status', 'startDate', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';

        $qb = $repo->createQueryBuilder('p');
        if ($q) $qb->where('p.title LIKE :q')->setParameter('q', "%$q%");
        $qb->orderBy("p.$sort", $dir);

        $pagination = $paginator->paginate($qb, $page, $perPage);

        $total = (int) $pagination->getTotalItemCount();

        return $this->render('bo/plans/index.html.twig', [
            'items' => $pagination,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $pagination->getCurrentPageNumber(),
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new RevisionPlan();
        $form = $this->createForm(RevisionPlanType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Plan créé.');
            return $this->redirectToRoute('bo_plans_index');
        }
        return $this->render('bo/plans/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(RevisionPlan $item): Response
    {
        return $this->render('bo/plans/show.html.twig', ['item' => $item]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RevisionPlan $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RevisionPlanType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Plan modifié.');
            return $this->redirectToRoute('bo_plans_index');
        }
        return $this->render('bo/plans/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, RevisionPlan $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Plan supprimé.');
        }
        return $this->redirectToRoute('bo_plans_index');
    }
}
