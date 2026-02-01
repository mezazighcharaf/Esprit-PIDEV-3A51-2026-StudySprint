<?php

namespace App\Service;

class SchoolDataProvider
{
    private const DATA = [
        'FR' => [
            'etablissements' => [
                'Université Paris-Saclay',
                'Sorbonne Université',
                'École Polytechnique',
                'CentraleSupélec',
                'INSA Lyon'
            ],
            'niveaux' => [
                'Baccalauréat',
                'Licence 1',
                'Licence 2',
                'Licence 3',
                'Master 1',
                'Master 2',
                'Doctorat'
            ]
        ],
        'TN' => [
            'etablissements' => [
                // Universités et Facultés de Tunis
                'Université de Tunis El Manar',
                'Faculté de Médecine de Tunis',
                'Institut Supérieur des Sciences Infirmières de Tunis (ISSIT)',
                'Faculté des Sciences de Tunis (FST)',
                'Ecole Nationale d\'Ingénieurs de Tunis (ENIT)',
                'Institut Bourguiba des Langues Vivantes (IBLV)',

                // Université de Carthage
                'Université de Carthage',
                'Institut des Hautes Etudes Commerciales de Carthage (IHEC)',
                'Institut National des Sciences Appliquées et de Technologie (INSAT)',
                'Faculté des Sciences Juridiques, Politiques et Sociales de Tunis',
                'Ecole Polytechnique de Tunisie (EPT)',

                // Université de la Manouba
                'Université de la Manouba',
                'Ecole Supérieure de Commerce de Tunis (ESCT)',
                'Institut de Presse et des Sciences de l\'Information (IPSI)',
                'Ecole Nationale des Sciences de l\'Informatique (ENSI)',

                // Université de Tunis
                'Université de Tunis',
                'Ecole Supérieure des Sciences Economiques et Commerciales de Tunis (ESSEC)',
                'Institut Supérieur de Gestion de Tunis (ISG)',
                'Institut Préparatoire aux Etudes d\'Ingénieurs de Tunis (IPEIT)',
                'Tunis Business School (TBS)',

                // Régions
                'Université de Sfax',
                'Faculté de Médecine de Sfax',
                'Ecole Nationale d\'Ingénieurs de Sfax (ENIS)',
                'Université de Sousse',
                'Faculté de Médecine de Sousse',
                'Université de Monastir',
                'Faculté de Médecine de Monastir',
                'Faculté de Médecine Dentaire de Monastir',
                'Faculté de Pharmacie de Monastir',
                'Université de Gabès',
                'Université de Kairouan',
                'Université de Gafsa',
                'Université de Jendouba',

                // Privé
                'Esprit',
                'Université Centrale',
                'Dauphine Tunis',
                'MSB (Mediterranean School of Business)'
            ],
            'niveaux' => [
                'Baccalauréat',
                'Licence 1',
                'Licence 2',
                'Licence 3',
                'Master 1',
                'Master 2',
                'Doctorat',
                'Cycle Préparatoire 1ère année',
                'Cycle Préparatoire 2ème année',
                'Cycle Ingénieur 1ère année',
                'Cycle Ingénieur 2ème année',
                'Cycle Ingénieur 3ème année',
                'Résidanat Médecine'
            ]
        ],
        'US' => [
            'etablissements' => [
                'MIT',
                'Stanford University',
                'Harvard University',
                'Caltech'
            ],
            'niveaux' => [
                'High School',
                'Freshman',
                'Sophomore',
                'Junior',
                'Senior',
                'Graduate'
            ]
        ]
    ];

    public function getEtablissements(string $countryCode): array
    {
        return self::DATA[$countryCode]['etablissements'] ?? [];
    }

    public function getNiveaux(string $countryCode): array
    {
        return self::DATA[$countryCode]['niveaux'] ?? [];
    }
}
