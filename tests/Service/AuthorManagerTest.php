<?php

namespace App\Tests\Service;

use App\Entity\Author;
use App\Service\AuthorManager;
use PHPUnit\Framework\TestCase;

class AuthorManagerTest extends TestCase
{
    public function testValidAuthor()
    {
        $author = new Author();
        $author->setName('Victor Hugo');
        $author->setEmail('victor.hugo@gmail.com');

        $manager = new AuthorManager();
        $this->assertTrue($manager->validate($author));
    }

    public function testAuthorWithoutName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $author = new Author();
        $author->setEmail('test@gmail.com');

        $manager = new AuthorManager();
        $manager->validate($author);
    }

    public function testAuthorWithInvalidEmail()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $author = new Author();
        $author->setName('Author Test');
        $author->setEmail('email_invalide');

        $manager = new AuthorManager();
        $manager->validate($author);
    }
}
