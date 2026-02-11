<?php
// Diag script
require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$userRepo = $container->get('App\Repository\UserRepository');

$user = $userRepo->findOneBy(['email' => 'cherniranym@gmail.com']);

if ($user) {
    echo "USER FOUND\n";
    echo "EMAIL: " . $user->getEmail() . "\n";
    echo "HASH: " . $user->getPassword() . "\n";
    echo "HASH LENGTH: " . strlen($user->getPassword()) . "\n";
} else {
    echo "USER NOT FOUND\n";
}
