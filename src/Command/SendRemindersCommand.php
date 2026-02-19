<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-reminders',
    description: 'Envoie des notifications de rappel aux utilisateurs inactifs depuis 3+ jours',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cutoff = new \DateTimeImmutable('-3 days');

        $inactiveUsers = $this->userRepo->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->where('p.lastActivityDate IS NULL OR p.lastActivityDate < :cutoff')
            ->andWhere('u.isActive = true')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($inactiveUsers as $user) {
            $this->notificationService->create(
                $user,
                'On vous attend !',
                'Vous n\'avez pas étudié depuis quelques jours. Revenez pour maintenir votre streak !',
                'warning'
            );
            $count++;
        }

        $io->success(sprintf('%d rappel(s) envoyé(s).', $count));

        return Command::SUCCESS;
    }
}
