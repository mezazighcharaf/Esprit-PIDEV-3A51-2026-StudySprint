<?php

namespace App\Controller\Bo;

use App\Entity\PlanTask;
use App\Form\Bo\PlanTaskType;
use App\Repository\PlanTaskRepository;
use App\Repository\RevisionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/tasks', name: 'bo_tasks_')]
class PlanTaskController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PlanTaskRepository $repo,
        RevisionPlanRepository $planRepo,
        PaginatorInterface $paginator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = trim((string) $request->query->get('q', ''));
        $status = strtoupper((string) $request->query->get('status', ''));
        $priority = $request->query->getInt('priority', 0);
        $planId = $request->query->getInt('plan', 0);
        $dateFrom = (string) $request->query->get('date_from', '');
        $dateTo = (string) $request->query->get('date_to', '');

        $sort = (string) $request->query->get('sort', 'id');
        $dir = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $allowedStatuses = [PlanTask::STATUS_TODO, PlanTask::STATUS_DOING, PlanTask::STATUS_DONE];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }
        if ($priority < 1 || $priority > 3) {
            $priority = 0;
        }
        if ($planId < 1) {
            $planId = 0;
        }

        $sortMap = [
            'id' => 't.id',
            'title' => 't.title',
            'plan' => 'p.title',
            'taskType' => 't.taskType',
            'status' => 't.status',
            'priority' => 't.priority',
            'startAt' => 't.startAt',
        ];
        if (!array_key_exists($sort, $sortMap)) {
            $sort = 'id';
        }

        $qb = $repo->createQueryBuilder('t')
            ->leftJoin('t.plan', 'p')
            ->addSelect('p');

        if ($q !== '') {
            $qb->andWhere('t.title LIKE :q OR p.title LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }
        if ($status !== '') {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }
        if ($priority > 0) {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', $priority);
        }
        if ($planId > 0) {
            $qb->andWhere('p.id = :planId')
                ->setParameter('planId', $planId);
        }

        if ($dateFrom !== '') {
            $from = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
            if ($from instanceof \DateTimeImmutable) {
                $qb->andWhere('t.startAt >= :dateFrom')
                    ->setParameter('dateFrom', $from->setTime(0, 0));
            }
        }
        if ($dateTo !== '') {
            $to = \DateTimeImmutable::createFromFormat('Y-m-d', $dateTo);
            if ($to instanceof \DateTimeImmutable) {
                $qb->andWhere('t.startAt <= :dateTo')
                    ->setParameter('dateTo', $to->setTime(23, 59, 59));
            }
        }

        $qb->orderBy($sortMap[$sort], $dir);

        $pagination = $paginator->paginate($qb, $page, $perPage);

        $total = (int) $pagination->getTotalItemCount();

        return $this->render('bo/tasks/index.html.twig', [
            'items' => $pagination,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $pagination->getCurrentPageNumber(),
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
            'status' => $status,
            'priority' => $priority,
            'plan' => $planId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'plans' => $planRepo->findBy([], ['title' => 'ASC']),
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
            $this->addFlash('success', 'Tache creee.');
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
            $this->addFlash('success', 'Tache modifiee.');
            return $this->redirectToRoute('bo_tasks_index');
        }
        return $this->render('bo/tasks/edit.html.twig', ['form' => $form, 'item' => $item]);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, PlanTask $item, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('toggle' . $item->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('bo_tasks_index'));
        }

        if ($item->getStatus() === PlanTask::STATUS_DONE) {
            $item->setStatus(PlanTask::STATUS_TODO);
            $label = 'A_FAIRE';
        } else {
            $item->setStatus(PlanTask::STATUS_DONE);
            $label = 'TERMINE';
        }

        $em->flush();
        $this->addFlash('success', sprintf('Statut de la tache #%d: %s', $item->getId(), $label));

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('bo_tasks_index'));
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, PlanTask $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $item->getId(), (string) $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Tache supprimee.');
        }
        return $this->redirectToRoute('bo_tasks_index');
    }
}
