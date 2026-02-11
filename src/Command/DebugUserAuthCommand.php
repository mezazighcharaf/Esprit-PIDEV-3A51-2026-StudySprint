<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:debug-user-auth',
    description: 'Debug authentication for a specific user',
)]
class DebugUserAuthCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED, 'The email of the user to debug')
            ->addArgument('password', InputArgument::REQUIRED, 'The password to test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower($input->getArgument('email'));
        $password = $input->getArgument('password');

        $users = $this->userRepository->findBy(['email' => $email]);

        if (empty($users)) {
            $io->error(sprintf('User with email "%s" not found in database.', $email));
            return Command::FAILURE;
        }

        if (count($users) > 1) {
            $io->warning(sprintf('Found %d users with the same email!', count($users)));
        }

        foreach ($users as $index => $user) {
            $details = [
                sprintf('Class: %s', get_class($user)),
                sprintf('Role: %s', $user->getRole()),
                sprintf('Status: %s', $user->getStatut()),
                sprintf('Identifier: %s', $user->getUserIdentifier()),
                sprintf('Pays: %s', $user->getPays()),
                sprintf('Inscrit le: %s', $user->getDateInscription()->format('Y-m-d H:i:s')),
            ];

            if ($user instanceof \App\Entity\Student) {
                $details[] = sprintf('Age: %s', $user->getAge());
                $details[] = sprintf('Sexe: %s', $user->getSexe());
                $details[] = sprintf('Etablissement: %s', $user->getEtablissement());
                $details[] = sprintf('Niveau: %s', $user->getNiveau());
            }

            if ($user instanceof \App\Entity\Professor) {
                $details[] = sprintf('Specialite: %s', $user->getSpecialite());
                $details[] = sprintf('Niveau Enseignement: %s', $user->getNiveauEnseignement());
                $details[] = sprintf('Exp: %s', $user->getAnneesExperience());
            }

            $io->listing($details);

            $isValid = $this->passwordHasher->isPasswordValid($user, $password);

            if ($isValid) {
                $io->success(sprintf('Password is VALID for User #%d', $index + 1));
            } else {
                $io->error(sprintf('Password is INVALID for User #%d', $index + 1));
            }
        }

        return $isValid ? Command::SUCCESS : Command::FAILURE;
    }
}
