<?php

namespace App\Service;

class FlashcardTipsService
{
    private const TIPS = [
        [
            'icon' => '💡',
            'title' => 'Soyez concis',
            'content' => 'Les meilleures flashcards contiennent une seule information par carte. Évitez de surcharger.',
        ],
        [
            'icon' => '🎯',
            'title' => 'Question précise',
            'content' => 'Formulez votre question de manière claire et précise. Utilisez "Qu\'est-ce que...", "Définir...", "Comment..."',
        ],
        [
            'icon' => '📝',
            'title' => 'Réponse complète',
            'content' => 'La réponse doit être suffisamment détaillée pour comprendre le concept sans être trop longue.',
        ],
        [
            'icon' => '🔄',
            'title' => 'Bidirectionnel',
            'content' => 'Créez des paires question/réponse réversibles : "Définition → Terme" ET "Terme → Définition".',
        ],
        [
            'icon' => '🌟',
            'title' => 'Utilisez des exemples',
            'content' => 'Ajoutez des exemples concrets dans vos réponses pour mieux mémoriser les concepts abstraits.',
        ],
        [
            'icon' => '🎨',
            'title' => 'Mnémotechnique',
            'content' => 'Utilisez des acronymes, associations d\'idées ou images mentales dans vos flashcards.',
        ],
        [
            'icon' => '📊',
            'title' => 'Hiérarchisez',
            'content' => 'Commencez par les concepts de base avant de créer des cartes sur les notions avancées.',
        ],
        [
            'icon' => '🔗',
            'title' => 'Liens logiques',
            'content' => 'Créez des séries de cartes liées pour construire progressivement votre compréhension.',
        ],
        [
            'icon' => '✏️',
            'title' => 'Reformulez',
            'content' => 'Utilisez vos propres mots plutôt que de copier-coller du contenu de cours.',
        ],
        [
            'icon' => '🎓',
            'title' => 'Testez-vous',
            'content' => 'Révisez régulièrement vos cartes. La répétition espacée est la clé de la mémorisation.',
        ],
        [
            'icon' => '🔍',
            'title' => 'Un concept = une carte',
            'content' => 'Ne mélangez pas plusieurs idées dans une même carte. Divisez les concepts complexes.',
        ],
        [
            'icon' => '📌',
            'title' => 'Indices visuels',
            'content' => 'Utilisez le formatage (gras, majuscules) pour mettre en évidence les mots-clés importants.',
        ],
    ];

    public function getRandomTip(): array
    {
        return self::TIPS[array_rand(self::TIPS)];
    }

    public function getAllTips(): array
    {
        return self::TIPS;
    }

    public function getTips(int $count = 3): array
    {
        $tips = self::TIPS;
        shuffle($tips);
        return array_slice($tips, 0, min($count, count($tips)));
    }
}
