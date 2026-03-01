<?php

namespace App\Service;

use App\Entity\Author;

class AuthorManager
{
    public function validate(Author $author): bool
    {
        if (empty($author->getName())) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }

        if (!filter_var($author->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        return true;
    }
}
