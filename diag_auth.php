<?php
// diag_auth.php
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require __DIR__ . '/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
// Use public alias or find it in the container
$userRepo = $container->get('doctrine')->getRepository(\App\Entity\User::class);
$hasher = $container->get('security.user_password_hasher');

$email = 'cherniranym@gmail.com';
$testPassword = 'Ranymchernytest12&';

$user = $userRepo->findOneBy(['email' => $email]);

if (!$user) {
    echo "USER NOT FOUND\n";
    exit(1);
}

echo "USER FOUND: " . $user->getEmail() . " (ID: " . $user->getId() . ")\n";
echo "STATUS: " . $user->getStatut() . "\n";
echo "HASH IN DB: " . $user->getPassword() . "\n";

$isValid = $hasher->isPasswordValid($user, $testPassword);

if ($isValid) {
    echo "SUCCESS: PASSWORD IS VALID\n";
} else {
    echo "FAILURE: PASSWORD IS INVALID\n";
}
