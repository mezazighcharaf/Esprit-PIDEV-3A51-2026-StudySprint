<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-login',
    description: 'Test actual login logic',
)]
class TestLoginCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower($input->getArgument('email'));
        $password = $input->getArgument('password');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error('User not found');
            return Command::FAILURE;
        }

        $io->note('Trying to verify password for user: ' . $user->getEmail());
        $isValid = $this->passwordHasher->isPasswordValid($user, $password);

        if ($isValid) {
            $io->success('Password is valid manually.');
        } else {
            $io->error('Password is invalid manually.');
            return Command::FAILURE;
        }

        // Now let's see if there's anything else that might fail
        // For example, if we were using a real authenticator
        
        return Command::SUCCESS;
    }
}
