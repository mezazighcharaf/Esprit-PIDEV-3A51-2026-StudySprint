<?php
require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use App\Entity\User;
use Symfony\Component\Console\Output\ConsoleOutput;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();
$userRepo = $entityManager->getRepository(User::class);
$hasher = $container->get('security.user_password_hasher');

echo "--- DIAGNOSTIC START ---\n";

// 1. Check for duplicate emails
$users = $userRepo->findAll();
$emails = [];
$duplicatesFound = false;

echo "--- LISTING USERS ---\n";
foreach ($users as $user) {
    $email = strtolower($user->getEmail());
    echo sprintf("ID: %d | Email: %s | Role: %s | Status: %s\n", 
        $user->getId(), 
        $user->getEmail(), 
        $user->getRole(),
        $user->getStatut()
    );

    if (isset($emails[$email])) {
        echo "!!! DUPLICATE EMAIL FOUND: $email !!!\n";
        $duplicatesFound = true;
    }
    $emails[$email] = true;
}

if (!$duplicatesFound) {
    echo "No duplicate emails found.\n";
}

// 2. Simulate Password Reset for a specific user (if requested)
// Change this email to the one you are testing with
$targetEmail = 'cherniranym@gmail.com'; 
$targetUser = $userRepo->findOneBy(['email' => $targetEmail]);

if ($targetUser) {
    echo "\n--- TESTING PASSWORD RESET SIMULATION for $targetEmail ---\n";
    $newPassword = 'NewTestPassword123!';
    
    echo "Current Hash: " . $targetUser->getPassword() . "\n";
    
    // Simulate Hashing
    $hashedPassword = $hasher->hashPassword($targetUser, $newPassword);
    echo "New Hash Generated: $hashedPassword\n";
    
    // Verify immediate validity
    $isValid = $hasher->isPasswordValid($targetUser, $newPassword);
    echo "Is Password Valid (Pre-Flush)? " . ($isValid ? "YES" : "NO") . "\n";
    
    // Note: We are NOT flushing here to avoid changing the actual data, 
    // but checking if the hasher works as expected on this object.
} else {
    echo "\nUser $targetEmail not found for testing.\n";
}

echo "--- DIAGNOSTIC END ---\n";
