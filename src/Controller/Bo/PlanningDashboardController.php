<?php

namespace App\Controller\Bo;

use App\Entity\PlanTask;
use App\Entity\RevisionPlan;
use App\Repository\PlanTaskRepository;
use App\Repository\RevisionPlanRepository;
use App\Service\Planning\BoMagicBreakdownService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bo/planning', name: 'bo_planning_')]
class PlanningDashboardController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        RevisionPlanRepository $planRepo,
        PlanTaskRepository $taskRepo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $activePlans = (int) $planRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', RevisionPlan::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $totalPlans = (int) $planRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $doneTasks = (int) $taskRepo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', PlanTask::STATUS_DONE)
            ->getQuery()
            ->getSingleScalarResult();

        $totalTasks = (int) $taskRepo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $completionRate = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;

        $urgentPlans = $planRepo->createQueryBuilder('p')
            ->where('p.status IN (:statuses)')
            ->andWhere('p.endDate >= :today')
            ->setParameter('statuses', [RevisionPlan::STATUS_ACTIVE, RevisionPlan::STATUS_DRAFT])
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('p.endDate', 'ASC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $urgentTasks = $taskRepo->createQueryBuilder('t')
            ->join('t.plan', 'p')
            ->addSelect('p')
            ->where('t.status != :done')
            ->andWhere('p.status != :planDone')
            ->setParameter('done', PlanTask::STATUS_DONE)
            ->setParameter('planDone', RevisionPlan::STATUS_DONE)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.startAt', 'ASC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();

        $progressRows = $taskRepo->createQueryBuilder('t')
            ->select('IDENTITY(t.plan) AS planId')
            ->addSelect('COUNT(t.id) AS totalTasks')
            ->addSelect('SUM(CASE WHEN t.status = :done THEN 1 ELSE 0 END) AS doneTasks')
            ->setParameter('done', PlanTask::STATUS_DONE)
            ->groupBy('t.plan')
            ->getQuery()
            ->getArrayResult();

        $planProgress = [];
        foreach ($progressRows as $row) {
            $planProgress[(int) $row['planId']] = [
                'total' => (int) $row['totalTasks'],
                'done' => (int) $row['doneTasks'],
            ];
        }

        return $this->render('bo/planning/dashboard.html.twig', [
            'activePlans' => $activePlans,
            'totalPlans' => $totalPlans,
            'doneTasks' => $doneTasks,
            'totalTasks' => $totalTasks,
            'completionRate' => $completionRate,
            'totalMinutes' => $this->computeCompletedMinutes($taskRepo),
            'urgentPlans' => $urgentPlans,
            'urgentTasks' => $urgentTasks,
            'planProgress' => $planProgress,
        ]);
    }

    #[Route('/{id}/generate-tasks', name: 'generate_tasks', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generateTasks(
        int $id,
        Request $request,
        RevisionPlanRepository $planRepo,
        BoMagicBreakdownService $magicBreakdown,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $plan = $planRepo->find($id);
        if (!$plan) {
            throw $this->createNotFoundException('Plan introuvable.');
        }

        if (!$this->isCsrfTokenValid('bo_generate_tasks_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('bo_plans_show', ['id' => $id]);
        }

        try {
            $generatedTasks = $magicBreakdown->generateTasks($plan);

            if (count($generatedTasks) === 0) {
                $this->addFlash('warning', 'Aucune nouvelle tache n a ete creee (doublons detectes).');
                return $this->redirectToRoute('bo_plans_show', ['id' => $id]);
            }

            foreach ($generatedTasks as $task) {
                $em->persist($task);
            }

            if ($plan->getStatus() === RevisionPlan::STATUS_DRAFT) {
                $plan->setStatus(RevisionPlan::STATUS_ACTIVE);
            }

            $em->flush();

            $this->addFlash('success', sprintf(
                'Magic Breakdown: %d tache(s) ajoutee(s) au plan.',
                count($generatedTasks)
            ));
        } catch (\RuntimeException $e) {
            $this->addFlash('warning', $e->getMessage());
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Echec de generation des taches: ' . $e->getMessage());
        }

        return $this->redirectToRoute('bo_plans_show', ['id' => $id]);
    }

    private function computeCompletedMinutes(PlanTaskRepository $taskRepo): int
    {
        $tasks = $taskRepo->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', PlanTask::STATUS_DONE)
            ->getQuery()
            ->getResult();

        $minutes = 0;
        foreach ($tasks as $task) {
            $startAt = $task->getStartAt();
            $endAt = $task->getEndAt();
            if ($endAt <= $startAt) {
                continue;
            }

            $seconds = $endAt->getTimestamp() - $startAt->getTimestamp();
            $minutes += max(0, (int) floor($seconds / 60));
        }

        return $minutes;
    }
}

