<?php

namespace App\Controller\Fo;

use App\Repository\QuizAttemptRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/fo/leaderboard', name: 'fo_leaderboard', methods: ['GET'])]
    public function index(QuizAttemptRepository $attemptRepo): Response
    {
        // Top users by number of passed quizzes
        $topByPassed = $attemptRepo->createQueryBuilder('a')
            ->select('u.id, u.fullName, u.email, COUNT(a.id) as quizCount, AVG(a.score) as avgScore')
            ->join('a.user', 'u')
            ->where('a.completedAt IS NOT NULL')
            ->andWhere('a.score >= 50')
            ->groupBy('u.id, u.fullName, u.email')
            ->orderBy('quizCount', 'DESC')
            ->addOrderBy('avgScore', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        // Top users by average score
        $topByScore = $attemptRepo->createQueryBuilder('a')
            ->select('u.id, u.fullName, u.email, COUNT(a.id) as quizCount, AVG(a.score) as avgScore')
            ->join('a.user', 'u')
            ->where('a.completedAt IS NOT NULL')
            ->groupBy('u.id, u.fullName, u.email')
            ->having('COUNT(a.id) >= 3')
            ->orderBy('avgScore', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('fo/leaderboard/index.html.twig', [
            'topByPassed' => $topByPassed,
            'topByScore' => $topByScore,
        ]);
    }
}
