<?php

namespace App\Command;

use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-attempts',
    description: 'Supprime les tentatives de quiz non terminées de plus de 24h',
)]
class CleanupAttemptsCommand extends Command
{
    public function __construct(
        private readonly QuizAttemptRepository $attemptRepo,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cutoff = new \DateTimeImmutable('-24 hours');

        $staleAttempts = $this->attemptRepo->createQueryBuilder('a')
            ->where('a.isCompleted = false')
            ->andWhere('a.startedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        $count = count($staleAttempts);

        if ($count === 0) {
            $io->success('Aucune tentative obsolète trouvée.');
            return Command::SUCCESS;
        }

        foreach ($staleAttempts as $attempt) {
            $this->em->remove($attempt);
        }
        $this->em->flush();

        $io->success(sprintf('%d tentative(s) non terminée(s) supprimée(s).', $count));

        return Command::SUCCESS;
    }
}
