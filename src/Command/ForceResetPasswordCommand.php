<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:force-reset-password',
    description: 'Force reset a user password with correct hashing',
)]
class ForceResetPasswordCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email of the user')
            ->addArgument('password', InputArgument::REQUIRED, 'The new password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower($input->getArgument('email'));
        $password = $input->getArgument('password');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('User "%s" not found.', $email));
            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setMotDePasse($hashedPassword);
        
        $this->entityManager->flush();

        $io->success(sprintf('Password successfully reset for user %s', $email));
        $io->note('New hash: ' . $hashedPassword);

        return Command::SUCCESS;
    }
}
