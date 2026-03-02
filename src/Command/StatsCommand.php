<?php

namespace App\Command;

use App\Repository\QuizAttemptRepository;
use App\Repository\QuizRepository;
use App\Repository\FlashcardDeckRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stats',
    description: 'Affiche les statistiques globales de la plateforme',
)]
class StatsCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly QuizRepository $quizRepo,
        private readonly QuizAttemptRepository $attemptRepo,
        private readonly FlashcardDeckRepository $deckRepo
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $totalUsers = $this->userRepo->count([]);
        $totalQuizzes = $this->quizRepo->count([]);
        $totalDecks = $this->deckRepo->count([]);
        $totalAttempts = $this->attemptRepo->count([]);
        $completedAttempts = (int) $this->attemptRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.completedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $avgScore = $this->attemptRepo->createQueryBuilder('a')
            ->select('AVG(a.score)')
            ->where('a.completedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $io->title('StudySprint - Statistiques globales');

        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['Utilisateurs', $totalUsers],
                ['Quiz publiés', $totalQuizzes],
                ['Decks de flashcards', $totalDecks],
                ['Tentatives totales', $totalAttempts],
                ['Tentatives terminées', $completedAttempts],
                ['Score moyen', $avgScore !== null ? number_format((float) $avgScore, 1) . '%' : 'N/A'],
            ]
        );

        return Command::SUCCESS;
    }
}
