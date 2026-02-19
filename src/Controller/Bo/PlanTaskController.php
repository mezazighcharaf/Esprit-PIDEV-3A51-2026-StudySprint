<?php

namespace App\Controller\Bo;

use App\Entity\PlanTask;
use App\Form\Bo\PlanTaskType;
use App\Repository\PlanTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/tasks', name: 'bo_tasks_')]
class PlanTaskController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PlanTaskRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir = strtolower($request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $allowedSort = ['id', 'title', 'taskType', 'status', 'priority', 'startAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'id';

        $qb = $repo->createQueryBuilder('t');
        if ($q) $qb->where('t.title LIKE :q')->setParameter('q', "%$q%");
        $qb->orderBy("t.$sort", $dir);

        $total = (int) (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->render('bo/tasks/index.html.twig', [
            'items' => $items, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'page' => $page, 'totalPages' => (int) ceil($total / $perPage), 'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new PlanTask();
        $form = $this->createForm(PlanTaskType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Tâche créée.');
            return $this->redirectToRoute('bo_tasks_index');
        }
        return $this->render('bo/tasks/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(PlanTask $item): Response
    {
        return $this->render('bo/tasks/show.html.twig', ['item' => $item]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlanTask $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PlanTaskType::class, $item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Tâche modifiée.');
            return $this->redirectToRoute('bo_tasks_index');
        }
        return $this->render('bo/tasks/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, PlanTask $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Tâche supprimée.');
        }
        return $this->redirectToRoute('bo_tasks_index');
    }
}
