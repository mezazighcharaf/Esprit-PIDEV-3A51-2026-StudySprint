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
                'INSA Lyon',
                'HEC Paris',
                'Sciences Po Paris',
                'Université PSL (Paris Sciences & Lettres)',
                'Université Paris Cité',
                'ESCP Business School',
                'EM Lyon',
                'EDHEC Business School'
            ],
            'niveaux' => [
                'Seconde',
                'Première',
                'Terminale',
                'Licence 1 (L1)',
                'Licence 2 (L2)',
                'Licence 3 (L3)',
                'Master 1 (M1)',
                'Master 2 (M2)',
                'Doctorat',
                'Classes Préparatoires (CPGE)',
                'BTS / DUT'
            ]
        ],
        'TN' => [
            'etablissements' => [
                'Université de Tunis El Manar (FST, ENIT, Faculté de Médecine)',
                'Université de Carthage (INSAT, IHEC, EPT, FSJPS)',
                'Université de la Manouba (ENSI, ESCT, IPSI, ISAMM)',
                'Université de Tunis (ESSEC, TBS, ISG, ENS)',
                'Université de Sfax (ENIS, Faculté de Médecine, FSEG)',
                'Université de Sousse (Faculté de Médecine, ISSAT, ISG)',
                'Université de Monastir (Pharmacie, Dentaire, Médecine)',
                'Université de Gabès',
                'Université de Kairouan',
                'Université de Gafsa',
                'Université de Jendouba',
                'Esprit',
                'Université Centrale',
                'Dauphine Tunis',
                'South Mediterranean University (MSB/MedTech)'
            ],
            'niveaux' => [
                'Lycée (2ème/3ème/Bac)',
                'Licence 1 (L1)',
                'Licence 2 (L2)',
                'Licence 3 (L3)',
                'Professionnel / Mastère 1',
                'Professionnel / Mastère 2',
                'Cycle Ingénieur 1 (C1)',
                'Cycle Ingénieur 2 (C2)',
                'Cycle Ingénieur 3 (C3)',
                'Cycle Préparatoire 1',
                'Cycle Préparatoire 2',
                'Doctorat'
            ]
        ],
        'US' => [
            'etablissements' => [
                'Harvard University',
                'Stanford University',
                'Massachusetts Institute of Technology (MIT)',
                'California Institute of Technology (Caltech)',
                'University of California, Berkeley (UCB)',
                'Princeton University',
                'Yale University',
                'Columbia University',
                'University of California, Los Angeles (UCLA)',
                'University of Chicago',
                'University of Pennsylvania',
                'New York University (NYU)'
            ],
            'niveaux' => [
                'High School (Junior/Senior)',
                'Undergraduate (Freshman)',
                'Undergraduate (Sophomore)',
                'Undergraduate (Junior)',
                'Undergraduate (Senior)',
                'Graduate (Master)',
                'Graduate (PhD / Doctoral)',
                'Post-Doc'
            ]
        ],
        'CA' => [
            'etablissements' => [
                'University of Toronto',
                'McGill University',
                'University of British Columbia (UBC)',
                'Université de Montréal',
                'University of Waterloo',
                'University of Alberta',
                'McMaster University',
                'Université Laval',
                'University of Ottawa',
                'Concordia University',
                'HEC Montréal',
                'Polytechnique Montréal'
            ],
            'niveaux' => [
                'Secondaire',
                'Cégep (Québec)',
                'Baccalauréat / Bachelor (1ère année)',
                'Baccalauréat / Bachelor (2ème année)',
                'Baccalauréat / Bachelor (3ème année)',
                'Baccalauréat / Bachelor (4ème année)',
                'Maîtrise / Master',
                'Doctorat (PhD)'
            ]
        ]
    ];

    /** @return list<string> */ /** @return list<string> */ public function getEtablissements(string $countryCode): array
    {
        return self::DATA[$countryCode]['etablissements'] ?? [];
    }

    /** @return list<string> */ /** @return list<string> */ public function getNiveaux(string $countryCode): array
    {
        return self::DATA[$countryCode]['niveaux'] ?? [];
    }
}
