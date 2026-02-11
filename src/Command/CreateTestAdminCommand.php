<?php

namespace App\Command;

use App\Entity\Administrator;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-admin',
    description: 'Create a test admin user',
)]
class CreateTestAdminCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = 'testadmin@studysprint.com';
        $password = 'Admin123!';

        // Check if exists
        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $admin = new Administrator();
        $admin->setNom('Test');
        $admin->setPrenom('Admin');
        $admin->setEmail($email);
        $admin->setRole('ROLE_ADMIN');
        $admin->setStatut('actif');
        $admin->setPays('FR');
        
        $hashed = $this->passwordHasher->hashPassword($admin, $password);
        $admin->setMotDePasse($hashed);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Test admin created: %s / %s', $email, $password));

        return Command::SUCCESS;
    }
}
