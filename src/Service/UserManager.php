<?php

namespace App\Service;

use App\Entity\User;

class UserManager
{
    /**
     * Valide les règles métier d'un utilisateur.
     * 
     * @param User $user
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validate(User $user): bool
    {
        // Règle 1 : Le mot de passe doit contenir au moins 8 caractères
        if (strlen($user->getMotDePasse() ?? '') < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        // Règle 2 : Le statut doit être valide
        $validStatuses = ['actif', 'inactif', 'suspendu'];
        if (!in_array($user->getStatut(), $validStatuses)) {
            throw new \InvalidArgumentException('Le statut est invalide.');
        }

        return true;
    }
}
