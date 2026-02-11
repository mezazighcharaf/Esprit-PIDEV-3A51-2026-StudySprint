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
    name: 'app:normalize-emails',
    description: 'Lowercase all user emails in the database',
)]
class NormalizeEmailsCommand extends Command
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
        $users = $this->userRepository->findAll();

        $count = 0;
        foreach ($users as $user) {
            $original = $user->getEmail();
            $user->setEmail($original); // Setter handles trim and strtolower
            if ($user->getEmail() !== $original) {
                $count++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Normalized %d emails to lowercase.', $count));

        return Command::SUCCESS;
    }
}
