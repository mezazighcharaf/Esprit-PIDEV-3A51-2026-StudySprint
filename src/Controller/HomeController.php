<?php

namespace App\Controller;

use App\Entity\Objectif;
use App\Entity\Tache;
use App\Service\AIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager, AIService $aiService): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $objectiveRepo = $entityManager->getRepository(Objectif::class);
        $taskRepo = $entityManager->getRepository(Tache::class);

        // 1. Priority of the day: Find next urgent active objective
        $priorityObjective = $objectiveRepo->findOneBy(
            ['etudiant' => $user, 'statut' => ['EN_COURS', 'A_FAIRE']],
            ['dateFin' => 'ASC']
        );

        // 2. Stats Calculation
        $allObjectives = $objectiveRepo->findBy(['etudiant' => $user]);

        $totalMinutes = 0;
        $tachesTerminees = 0;
        $totalPlannedMinutes = 0;
        $todoTasks = [];

        foreach ($allObjectives as $obj) {
            foreach ($obj->getTaches() as $t) {
                if ($t->getStatut() === 'TERMINE') {
                    $totalMinutes += $t->getDuree();
                    $tachesTerminees++;
                } else {
                    $todoTasks[] = $t;
                }
                $totalPlannedMinutes += $t->getDuree();
            }
        }

        // Format Total Time (e.g., 8h45)
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        $totalTimeStr = sprintf('%dh%02d', $hours, $minutes);

        // Weekly Goal Progress (Completion rate)
        $weeklyGoalPercent = $totalPlannedMinutes > 0 ? round(($totalMinutes / $totalPlannedMinutes) * 100) : 0;

        // 3. To Do list (Slice to show only first 5)
        $todoTasks = array_slice($todoTasks, 0, 5);

        $narrative = $aiService->generateProgressNarrative($user);

        return $this->render('dashboard/index.html.twig', [
            'priorityObjective' => $priorityObjective,
            'sessionsCount' => $tachesTerminees,
            'todoTasks' => $todoTasks,
            'narrative' => $narrative,
            'totalTime' => $totalTimeStr,
            'quizScore' => '24/28', // Quizzes not yet implemented
            'weeklyGoal' => $weeklyGoalPercent,
            'weeklyGoalDescription' => sprintf('%dh / %dh', $hours, ceil($totalPlannedMinutes / 60))
        ]);
    }
}
