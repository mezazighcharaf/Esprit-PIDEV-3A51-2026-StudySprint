<?php

namespace App\Command;

use App\Dto\PasswordResetDTO;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:simulate-reset',
    description: 'Simulate the password reset flow logic',
)]
class SimulateResetCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('token', InputArgument::REQUIRED)
            ->addArgument('new_password', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $email = strtolower($input->getArgument('email'));
            $token = $input->getArgument('token');
            $newPassword = $input->getArgument('new_password');

            $io->title('Simulating Password Reset Flow');

            // 1. DTO Validation
            $dto = new PasswordResetDTO();
            $dto->verificationCode = $token;
            $dto->newPassword = $newPassword;
            $dto->confirmPassword = $newPassword;

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $io->error('DTO Validation Failed:');
                foreach ($errors as $error) {
                    $io->text(sprintf('- %s: %s', $error->getPropertyPath(), $error->getMessage()));
                }
                return Command::FAILURE;
            }
            $io->success('DTO Validation Passed.');

            // 2. User Lookup
            $io->text(sprintf('Searching for user with resetToken: "%s"', $dto->verificationCode));
            $user = $this->userRepository->findOneBy(['resetToken' => $dto->verificationCode]);

            if (!$user) {
                $io->error('User NOT found by token in DB.');
                return Command::FAILURE;
            }
            $io->success(sprintf('User found: %s (ID: %d)', $user->getEmail(), $user->getId()));

            // 3. Email Check
            if (strtolower($user->getEmail()) !== strtolower($email)) {
                $io->error(sprintf('Email mismatch! DB has "%s", requested "%s"', $user->getEmail(), $email));
                return Command::FAILURE;
            }
            $io->success('Email matches.');

            // 4. Reset and Flush
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->newPassword);
            $user->setMotDePasse($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $io->note('Calling flush()...');
            $this->entityManager->flush();
            $io->success('Flush() completed successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('BLOCKING ERROR: ' . $e->getMessage());
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
