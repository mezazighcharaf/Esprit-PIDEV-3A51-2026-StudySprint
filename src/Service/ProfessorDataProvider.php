<?php

namespace App\Service;

class ProfessorDataProvider
{
    /** @return array<string, array<string, string>> */
    public function getSpecialites(): array
    {
        $specialites = [
            'Sciences Exactes' => [
                'Mathématiques',
                'Physique',
                'Chimie',
                'Informatique',
                'Sciences de l\'Ingénieur'
            ],
            'Sciences de la Vie et Santé' => [
                'Biologie / SVT',
                'Médecine',
                'Pharmacie',
                'Sciences Infirmières',
                'Biotechnologie'
            ],
            'Lettres et Langues' => [
                'Français',
                'Anglais',
                'Arabe',
                'Espagnol',
                'Allemand',
                'Italien',
                'Lettres Modernes',
                'Lettres Classiques'
            ],
            'Sciences Humaines et Sociales' => [
                'Histoire',
                'Géographie',
                'Philosophie',
                'Psychologie',
                'Sociologie',
                'Sciences de l\'Education'
            ],
            'Economie et Gestion' => [
                'Economie',
                'Gestion',
                'Marketing',
                'Finance',
                'Comptabilité',
                'Droit'
            ],
            'Arts et Sport' => [
                'Arts Plastiques',
                'Musique',
                'Design',
                'Education Physique et Sportive (EPS)'
            ]
        ];

        // Flatten for ChoiceType if needed, or keep grouped
        // For simple choice type, we can return flattened or use grouped functionality
        // Let's return grouped structure compatible with ChoiceType
        $choices = [];
        foreach ($specialites as $group => $values) {
            foreach ($values as $value) {
                // Key is label, Value is value (or vice versa depending on Symfony version, usually label => value)
                // Symfony ChoiceType: choices => [label => value]
                $choices[$group][$value] = $value;
            }
        }
        
        return $choices;
    }

    /** @return array<string, string> */
    public function getNiveauxEnseignement(): array
    {
        $niveaux = [
            'Enseignement Primaire',
            'Enseignement Secondaire - Collège',
            'Enseignement Secondaire - Lycée',
            'Enseignement Supérieur - Cycle Préparatoire',
            'Enseignement Supérieur - Licence',
            'Enseignement Supérieur - Master',
            'Enseignement Supérieur - Cycle Ingénieur',
            'Enseignement Supérieur - Doctorat/Recherche',
            'Formation Professionnelle',
            'Autre'
        ];

        // Return [label => value]
        return array_combine($niveaux, $niveaux);
    }
}
