<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:deactivate-inactive-users',
    description: 'Désactive les utilisateurs inactifs depuis plus de 5 minutes',
)]
class DeactivateInactiveUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Calculate the threshold (5 minutes ago)
        $threshold = new \DateTimeImmutable('-5 minutes');

        // Find all active users who haven't been active in the last 5 minutes
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from('App\Entity\User', 'u')
            ->where('u.statut = :statut')
            ->andWhere('u.lastActivityAt IS NOT NULL')
            ->andWhere('u.lastActivityAt < :threshold')
            ->setParameter('statut', 'actif')
            ->setParameter('threshold', $threshold);

        $inactiveUsers = $qb->getQuery()->getResult();

        if (count($inactiveUsers) === 0) {
            $io->success('Aucun utilisateur inactif trouvé.');
            return Command::SUCCESS;
        }

        $deactivatedCount = 0;
        foreach ($inactiveUsers as $user) {
            $user->setStatut('inactif');
            $deactivatedCount++;
            
            $io->writeln(sprintf(
                'Désactivation de l\'utilisateur : %s %s (%s) - Dernière activité : %s',
                $user->getPrenom(),
                $user->getNom(),
                $user->getEmail(),
                $user->getLastActivityAt()?->format('Y-m-d H:i:s') ?? 'Jamais'
            ));
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d utilisateur(s) désactivé(s) pour inactivité de plus de 5 minutes.',
            $deactivatedCount
        ));

        return Command::SUCCESS;
    }
}
