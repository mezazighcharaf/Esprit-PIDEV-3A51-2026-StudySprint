<?php

namespace App\Tests\Service;

use App\Entity\Student; // Using Student as User is abstract
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    public function testValidUser()
    {
        $user = new Student();
        $user->setMotDePasse('password123');
        $user->setStatut('actif');

        $manager = new UserManager();
        $this->assertTrue($manager->validate($user));
    }

    public function testShortPassword()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe doit contenir au moins 8 caractères.');

        $user = new Student();
        $user->setMotDePasse('short');
        $user->setStatut('actif');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testInvalidStatus()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut est invalide.');

        $user = new Student();
        $user->setMotDePasse('password123');
        $user->setStatut('inconnu');

        $manager = new UserManager();
        $manager->validate($user);
    }
}
