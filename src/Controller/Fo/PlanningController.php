<?php

namespace App\Controller\Fo;

use App\Entity\RevisionPlan;
use App\Entity\PlanTask;
use App\Form\Fo\GeneratePlanType;
use App\Form\Fo\RevisionPlanType;
use App\Form\Fo\PlanTaskType;
use App\Repository\RevisionPlanRepository;
use App\Repository\PlanTaskRepository;
use App\Repository\UserRepository;
use App\Service\PlanGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;

#[Route('/fo/planning', name: 'fo_planning_')]
class PlanningController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        RevisionPlanRepository $planRepo,
        PlanTaskRepository $taskRepo,
        UserRepository $userRepo
    ): Response {
        // Get month/year from query or default to current
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('m'));
        
        // Demo mode: get first non-admin user
        $currentUser = $this->getUser() ?? $userRepo->createQueryBuilder('u')
            ->where('u.userType != :admin')
            ->setParameter('admin', 'ADMIN')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$currentUser) {
            $currentUser = $userRepo->findOneBy([]);
        }
        
        // Get all tasks for the month
        $startOfMonth = new \DateTimeImmutable("$year-$month-01 00:00:00");
        $endOfMonth = $startOfMonth->modify('last day of this month')->setTime(23, 59, 59);
        
        $tasks = $taskRepo->createQueryBuilder('t')
            ->join('t.plan', 'p')
            ->where('p.user = :user')
            ->andWhere('t.startAt >= :start')
            ->andWhere('t.startAt <= :end')
            ->setParameter('user', $currentUser)
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->orderBy('t.startAt', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Get upcoming tasks (next 7 days)
        $now = new \DateTimeImmutable();
        $nextWeek = $now->modify('+7 days');
        
        $upcomingTasks = $taskRepo->createQueryBuilder('t')
            ->join('t.plan', 'p')
            ->where('p.user = :user')
            ->andWhere('t.startAt >= :now')
            ->andWhere('t.startAt <= :nextWeek')
            ->andWhere('t.status != :done')
            ->setParameter('user', $currentUser)
            ->setParameter('now', $now)
            ->setParameter('nextWeek', $nextWeek)
            ->setParameter('done', PlanTask::STATUS_DONE)
            ->orderBy('t.startAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        // Calculate stats for this month via native SQL (TIMESTAMPDIFF not supported in DQL)
        $conn = $taskRepo->getEntityManager()->getConnection();
        $statsRow = $conn->executeQuery(
            'SELECT COUNT(t.id) AS totalCount,
                    SUM(CASE WHEN t.status = :done THEN 1 ELSE 0 END) AS completedCount,
                    SUM(TIMESTAMPDIFF(MINUTE, t.start_at, t.end_at)) AS totalMinutes
             FROM plan_tasks t
             INNER JOIN revision_plans p ON t.plan_id = p.id
             WHERE p.user_id = :userId AND t.start_at >= :start AND t.start_at <= :end',
            [
                'done'   => PlanTask::STATUS_DONE,
                'userId' => $currentUser->getId(),
                'start'  => $startOfMonth->format('Y-m-d H:i:s'),
                'end'    => $endOfMonth->format('Y-m-d H:i:s'),
            ]
        )->fetchAssociative();
        $statsRow = $statsRow ?: [];

        $sessionsCount = (int) ($statsRow['totalCount'] ?? 0);
        $completedCount = (int) ($statsRow['completedCount'] ?? 0);
        $totalMinutes = (float) ($statsRow['totalMinutes'] ?? 0);
        $completionRate = $sessionsCount > 0 ? round(($completedCount / $sessionsCount) * 100) : 0;

        // Get user's plans for AI suggest buttons
        $userPlans = $planRepo->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.endDate >= :today')
            ->setParameter('user', $currentUser)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('p.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('fo/planning/index.html.twig', [
            'tasks' => $tasks,
            'upcomingTasks' => $upcomingTasks,
            'year' => $year,
            'month' => $month,
            'sessionsCount' => $sessionsCount,
            'totalMinutes' => $totalMinutes,
            'completionRate' => $completionRate,
            'userPlans' => $userPlans,
        ]);
    }

    #[Route('/events', name: 'events_json', methods: ['GET'])]
    public function eventsJson(Request $request, PlanTaskRepository $taskRepo, UserRepository $userRepo): JsonResponse
    {
        $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
        if (!$currentUser) {
            return new JsonResponse([]);
        }

        $start = $request->query->get('start');
        $end = $request->query->get('end');

        $qb = $taskRepo->createQueryBuilder('t')
            ->join('t.plan', 'p')
            ->where('p.user = :user')
            ->setParameter('user', $currentUser)
            ->orderBy('t.startAt', 'ASC');

        if ($start) {
            $qb->andWhere('t.startAt >= :start')->setParameter('start', new \DateTimeImmutable($start));
        }
        if ($end) {
            $qb->andWhere('t.startAt <= :end')->setParameter('end', new \DateTimeImmutable($end));
        }

        $tasks = $qb->getQuery()->getResult();

        $colors = [
            PlanTask::STATUS_TODO  => '#667eea',
            PlanTask::STATUS_DOING => '#f59e0b',
            PlanTask::STATUS_DONE  => '#10b981',
        ];

        $events = array_map(fn(PlanTask $t) => [
            'id' => $t->getId(),
            'title' => $t->getTitle(),
            'start' => $t->getStartAt()->format('c'),
            'end' => $t->getEndAt()->format('c'),
            'backgroundColor' => $colors[$t->getStatus()] ?? '#667eea',
            'borderColor' => $colors[$t->getStatus()] ?? '#667eea',
            'extendedProps' => [
                'status' => $t->getStatus(),
                'planId' => $t->getPlan()->getId(),
            ],
        ], $tasks);

        return new JsonResponse($events);
    }

    #[Route('/generate', name: 'generate', methods: ['GET', 'POST'])]
    public function generate(
        Request $request,
        PlanGeneratorService $generator,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(GeneratePlanType::class);
        $form->handleRequest($request);

        $overlappingPlan = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Get current user or demo user
            $user = $this->getUser() ?? $userRepo->findOneBy([]);
            if (!$user) {
                $this->addFlash('error', 'Aucun utilisateur disponible.');
                return $this->redirectToRoute('fo_planning_index');
            }

            $startDate = \DateTimeImmutable::createFromMutable($data['startDate']);
            $endDate = \DateTimeImmutable::createFromMutable($data['endDate']);

            if ($endDate < $startDate) {
                $this->addFlash('error', 'La date de fin doit être après la date de début.');
                return $this->render('fo/planning/generate.html.twig', [
                    'form' => $form,
                    'overlappingPlan' => null,
                ]);
            }

            // Check for overlapping plan
            $overlappingPlan = $generator->findOverlappingPlan(
                $user,
                $data['subject'],
                $startDate,
                $endDate
            );

            if ($overlappingPlan && !$request->request->has('replace')) {
                return $this->render('fo/planning/generate.html.twig', [
                    'form' => $form,
                    'overlappingPlan' => $overlappingPlan,
                ]);
            }

            // Generate or replace plan
            if ($overlappingPlan && $request->request->has('replace')) {
                if (!$this->isCsrfTokenValid('replace_plan', $request->request->get('_token'))) {
                    $this->addFlash('error', 'Token CSRF invalide.');
                    return $this->redirectToRoute('fo_planning_generate');
                }

                $plan = $generator->replacePlan(
                    $overlappingPlan,
                    $startDate,
                    $endDate,
                    $data['sessionsPerDay'],
                    $data['skipWeekends']
                );
                $this->addFlash('success', 'Plan de révision mis à jour avec succès !');
            } else {
                $plan = $generator->generatePlan(
                    $user,
                    $data['subject'],
                    $startDate,
                    $endDate,
                    $data['sessionsPerDay'],
                    $data['skipWeekends']
                );
                $this->addFlash('success', 'Plan de révision généré avec succès !');
            }

            $em->flush();

            return $this->redirectToRoute('fo_planning_show', ['id' => $plan->getId()]);
        }

        return $this->render('fo/planning/generate.html.twig', [
            'form' => $form,
            'overlappingPlan' => $overlappingPlan,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, RevisionPlanRepository $planRepo, PlanTaskRepository $taskRepo): Response
    {
        $plan = $planRepo->find($id);

        if (!$plan) {
            throw $this->createNotFoundException('Plan de révision introuvable');
        }

        $tasks = $taskRepo->findBy(['plan' => $plan], ['startAt' => 'ASC']);

        return $this->render('fo/planning/show.html.twig', [
            'plan' => $plan,
            'tasks' => $tasks,
        ]);
    }

    // === REVISION PLAN CRUD ===

    #[Route('/plans/new', name: 'plan_new', methods: ['GET', 'POST'])]
    public function newPlan(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $plan = new RevisionPlan();
        $form = $this->createForm(RevisionPlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentUser = $this->getUser() ?? $userRepo->findOneBy([]);
            if (!$currentUser) {
                $this->addFlash('error', 'Utilisateur non connecté.');
                return $this->redirectToRoute('fo_planning_index');
            }

            $plan->setUser($currentUser);
            $em->persist($plan);
            $em->flush();

            $this->addFlash('success', 'Plan de révision créé avec succès !');
            return $this->redirectToRoute('fo_planning_show', ['id' => $plan->getId()]);
        }

        return $this->render('fo/planning/plan_new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/plans/{id}/edit', name: 'plan_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editPlan(int $id, Request $request, RevisionPlanRepository $repository, EntityManagerInterface $em): Response
    {
        $plan = $repository->find($id);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan de révision introuvable');
        }

        $form = $this->createForm(RevisionPlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Plan de révision modifié avec succès !');
            return $this->redirectToRoute('fo_planning_show', ['id' => $plan->getId()]);
        }

        return $this->render('fo/planning/plan_edit.html.twig', [
            'plan' => $plan,
            'form' => $form,
        ]);
    }

    #[Route('/plans/{id}/delete', name: 'plan_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deletePlan(int $id, Request $request, RevisionPlanRepository $repository, EntityManagerInterface $em): Response
    {
        $plan = $repository->find($id);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan de révision introuvable');
        }

        if ($this->isCsrfTokenValid('delete_plan_' . $plan->getId(), $request->request->get('_token'))) {
            $em->remove($plan);
            $em->flush();
            $this->addFlash('success', 'Plan de révision supprimé avec succès !');
        }

        return $this->redirectToRoute('fo_planning_index');
    }

    // === PLAN TASK CRUD ===

    #[Route('/sessions/new', name: 'session_new', methods: ['GET', 'POST'])]
    public function newSession(Request $request, RevisionPlanRepository $planRepo, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        // Demo mode: get first non-admin user
        $currentUser = $this->getUser() ?? $userRepo->createQueryBuilder('u')
            ->where('u.userType != :admin')
            ->setParameter('admin', 'ADMIN')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$currentUser) {
            $currentUser = $userRepo->findOneBy([]);
        }
        
        if (!$currentUser) {
            $this->addFlash('error', 'Utilisateur non connecté.');
            return $this->redirectToRoute('fo_planning_index');
        }

        // Find or create default plan for manual sessions
        $defaultPlan = $planRepo->findOneBy(['user' => $currentUser, 'title' => 'Sessions Manuelles']);
        
        if (!$defaultPlan) {
            $defaultPlan = new RevisionPlan();
            $defaultPlan->setUser($currentUser);
            $defaultPlan->setTitle('Sessions Manuelles');
            $defaultPlan->setStartDate(new \DateTimeImmutable());
            $defaultPlan->setEndDate(new \DateTimeImmutable('+1 year'));
            $defaultPlan->setStatus(RevisionPlan::STATUS_ACTIVE);
            
            // Get any subject (created by this user or any user)
            $subject = $em->getRepository(\App\Entity\Subject::class)->findOneBy(['createdBy' => $currentUser]);
            
            // If no subject for this user, get any subject
            if (!$subject) {
                $subject = $em->getRepository(\App\Entity\Subject::class)->findOneBy([]);
            }
            
            if ($subject) {
                $defaultPlan->setSubject($subject);
            } else {
                $this->addFlash('error', 'Créez d\'abord une matière dans Matières.');
                return $this->redirectToRoute('fo_subjects_index');
            }
            
            $em->persist($defaultPlan);
            $em->flush();
        }

        $task = new PlanTask();
        $task->setPlan($defaultPlan);
        
        $form = $this->createForm(PlanTaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($task);
            $em->flush();

            $this->addFlash('success', 'Session créée avec succès !');
            return $this->redirectToRoute('fo_planning_index');
        }

        return $this->render('fo/planning/session_new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/plans/{planId}/tasks/new', name: 'task_new', methods: ['GET', 'POST'], requirements: ['planId' => '\d+'])]
    public function newTask(int $planId, Request $request, RevisionPlanRepository $planRepo, EntityManagerInterface $em): Response
    {
        $plan = $planRepo->find($planId);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan de révision introuvable');
        }

        $task = new PlanTask();
        $task->setPlan($plan);
        
        $form = $this->createForm(PlanTaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($task);
            $em->flush();

            $this->addFlash('success', 'Session créée avec succès !');
            return $this->redirectToRoute('fo_planning_show', ['id' => $planId]);
        }

        return $this->render('fo/planning/task_new.html.twig', [
            'plan' => $plan,
            'form' => $form,
        ]);
    }

    #[Route('/plans/{planId}/tasks/{taskId}/edit', name: 'task_edit', methods: ['GET', 'POST'], requirements: ['planId' => '\d+', 'taskId' => '\d+'])]
    public function editTask(int $planId, int $taskId, Request $request, RevisionPlanRepository $planRepo, PlanTaskRepository $taskRepo, EntityManagerInterface $em): Response
    {
        $plan = $planRepo->find($planId);
        $task = $taskRepo->find($taskId);
        
        if (!$plan || !$task || $task->getPlan()->getId() !== $plan->getId()) {
            throw $this->createNotFoundException('Session ou plan introuvable');
        }

        $form = $this->createForm(PlanTaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Session modifiée avec succès !');
            return $this->redirectToRoute('fo_planning_show', ['id' => $planId]);
        }

        return $this->render('fo/planning/task_edit.html.twig', [
            'plan' => $plan,
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/plans/{planId}/tasks/{taskId}/delete', name: 'task_delete', methods: ['POST'], requirements: ['planId' => '\d+', 'taskId' => '\d+'])]
    public function deleteTask(int $planId, int $taskId, Request $request, PlanTaskRepository $taskRepo, EntityManagerInterface $em): Response
    {
        $task = $taskRepo->find($taskId);
        
        if (!$task || $task->getPlan()->getId() !== $planId) {
            throw $this->createNotFoundException('Session introuvable');
        }

        if ($this->isCsrfTokenValid('delete_task_' . $task->getId(), $request->request->get('_token'))) {
            $em->remove($task);
            $em->flush();
            $this->addFlash('success', 'Session supprimée avec succès !');
        }

        return $this->redirectToRoute('fo_planning_show', ['id' => $planId]);
    }

    #[Route('/tasks/{taskId}/toggle', name: 'task_toggle', methods: ['POST'], requirements: ['taskId' => '\d+'])]
    public function toggleTask(int $taskId, Request $request, PlanTaskRepository $taskRepo, EntityManagerInterface $em): Response
    {
        $task = $taskRepo->find($taskId);
        
        if (!$task) {
            throw $this->createNotFoundException('Session introuvable');
        }

        if ($this->isCsrfTokenValid('toggle_task_' . $task->getId(), $request->request->get('_token'))) {
            $task->setStatus($task->getStatus() === PlanTask::STATUS_DONE ? PlanTask::STATUS_TODO : PlanTask::STATUS_DONE);
            $em->flush();
        }

        return $this->redirectToRoute('fo_planning_index');
    }

    #[Route('/{id}/ai-suggest', name: 'ai_suggest', methods: ['POST'])]
    public function aiSuggest(
        int $id,
        Request $request,
        RevisionPlanRepository $planRepo,
        AiGatewayService $aiGateway
    ): Response {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        $plan = $planRepo->find($id);
        
        if (!$plan) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Plan introuvable'], 404);
            }
            throw $this->createNotFoundException('Plan introuvable');
        }

        $token = $isAjax
            ? (json_decode($request->getContent(), true)['_token'] ?? '')
            : $request->request->get('_token');

        if (!$this->isCsrfTokenValid('ai_suggest_'.$id, $token)) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_planning_show', ['id' => $id]);
        }

        $optimizationGoals = $isAjax
            ? (json_decode($request->getContent(), true)['optimization_goals'] ?? '')
            : $request->request->get('optimization_goals', '');

        // Call FastAPI AI Gateway
        try {
            $data = $aiGateway->suggestPlanOptimizations(
                $plan->getUser()->getId(),
                $id,
                $optimizationGoals
            );

            $suggestionsData = [
                'log_id' => $data['ai_log_id'] ?? null,
                'suggestions' => $data['suggestions'] ?? [],
                'explanation' => $data['explanation'] ?? '',
                'can_apply' => $data['can_apply'] ?? false,
            ];

            // Store suggestions in session for confirmation step
            $request->getSession()->set('ai_suggestions_' . $id, $suggestionsData);

            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'suggestions' => $suggestionsData['suggestions'],
                    'explanation' => $suggestionsData['explanation'],
                    'can_apply' => $suggestionsData['can_apply'],
                    'ai_log_id' => $suggestionsData['log_id'],
                    'confirm_url' => $this->generateUrl('fo_planning_ai_confirm', ['id' => $id]),
                ]);
            }

            $sugCount = count($suggestionsData['suggestions']);
            $this->addFlash('success', "L'IA a généré {$sugCount} suggestion(s). Vérifiez et appliquez ci-dessous.");

            return $this->redirectToRoute('fo_planning_ai_confirm', ['id' => $id]);
        } catch (\Exception $e) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Service IA indisponible: ' . $e->getMessage()], 503);
            }
            $this->addFlash('error', 'Erreur service IA: ' . $e->getMessage());
        }

        return $this->redirectToRoute('fo_planning_show', ['id' => $id]);
    }

    #[Route('/{id}/ai-confirm', name: 'ai_confirm', methods: ['GET'])]
    public function aiConfirm(
        int $id,
        Request $request,
        RevisionPlanRepository $planRepo,
        PlanTaskRepository $taskRepo
    ): Response {
        $plan = $planRepo->find($id);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan introuvable');
        }

        $suggestions = $request->getSession()->get('ai_suggestions_' . $id);
        
        if (!$suggestions) {
            $this->addFlash('error', 'Aucune suggestion disponible.');
            return $this->redirectToRoute('fo_planning_show', ['id' => $id]);
        }

        $tasks = $taskRepo->findBy(['plan' => $plan], ['startAt' => 'ASC']);

        return $this->render('fo/planning/ai_confirm.html.twig', [
            'plan' => $plan,
            'tasks' => $tasks,
            'suggestions' => $suggestions['suggestions'],
            'explanation' => $suggestions['explanation'],
            'log_id' => $suggestions['log_id'],
            'can_apply' => $suggestions['can_apply'],
        ]);
    }

    #[Route('/{id}/ai-apply', name: 'ai_apply', methods: ['POST'])]
    public function aiApply(
        int $id,
        Request $request,
        RevisionPlanRepository $planRepo,
        AiGatewayService $aiGateway
    ): Response {
        $plan = $planRepo->find($id);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan introuvable');
        }

        if (!$this->isCsrfTokenValid('ai_apply_'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('fo_planning_show', ['id' => $id]);
        }

        $suggestions = $request->getSession()->get('ai_suggestions_' . $id);
        
        if (!$suggestions || !isset($suggestions['log_id'])) {
            $this->addFlash('error', 'Aucune suggestion disponible.');
            return $this->redirectToRoute('fo_planning_show', ['id' => $id]);
        }

        // Call FastAPI to apply suggestions
        try {
            $data = $aiGateway->applyPlanSuggestions(
                $plan->getUser()->getId(),
                $suggestions['log_id']
            );

            // Clear suggestions from session
            $request->getSession()->remove('ai_suggestions_' . $id);

            $this->addFlash('success', sprintf(
                'Suggestions appliquées avec succès ! %d modifications effectuées.',
                $data['applied_count'] ?? 0
            ));

            return $this->redirectToRoute('fo_planning_show', ['id' => $id]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de contacter le service IA: ' . $e->getMessage());
        }

        return $this->redirectToRoute('fo_planning_show', ['id' => $id]);
    }
}
