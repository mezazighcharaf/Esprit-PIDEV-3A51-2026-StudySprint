<?php

namespace App\Service;

use App\Entity\Objectif;
use App\Entity\User;

class AIService
{
    /**
     * Rules-based decomposition of an objective into logical tasks.
     * In a real app, this would call a Gemini/OpenAI API.
     */
    public function generateTasksFromObjective(Objectif $objectif): array
    {
        $title = strtolower($objectif->getTitre());
        $tasks = [];

        // Simple keyword-based task generation
        if (str_contains($title, 'réviser') || str_contains($title, 'examen') || str_contains($title, 'cours')) {
            $tasks = [
                ['titre' => 'Organiser les supports de cours', 'duree' => 30, 'priorite' => 'MOYENNE'],
                ['titre' => 'Créer des fiches de révision (Active Recall)', 'duree' => 60, 'priorite' => 'HAUTE'],
                ['titre' => 'Faire des exercices d\'application', 'duree' => 90, 'priorite' => 'HAUTE'],
                ['titre' => 'Auto-évaluation sur les points difficiles', 'duree' => 45, 'priorite' => 'MOYENNE'],
            ];
        } elseif (str_contains($title, 'projet') || str_contains($title, 'développer') || str_contains($title, 'créer')) {
            $tasks = [
                ['titre' => 'Analyse des besoins et planification', 'duree' => 45, 'priorite' => 'MOYENNE'],
                ['titre' => 'Mise en place de la structure de base', 'duree' => 60, 'priorite' => 'HAUTE'],
                ['titre' => 'Implémentation des fonctionnalités clés', 'duree' => 120, 'priorite' => 'HAUTE'],
                ['titre' => 'Tests et débogage final', 'duree' => 60, 'priorite' => 'BASSE'],
            ];
        } else {
            // Generic fallback
            $tasks = [
                ['titre' => 'Recherche préliminaire et documentation', 'duree' => 45, 'priorite' => 'MOYENNE'],
                ['titre' => 'Première phase de réalisation', 'duree' => 90, 'priorite' => 'HAUTE'],
                ['titre' => 'Revue et corrections', 'duree' => 60, 'priorite' => 'MOYENNE'],
                ['titre' => 'Finalisation', 'duree' => 30, 'priorite' => 'BASSE'],
            ];
        }

        return $tasks;
    }

    /**
     * Generates a holistic, diverse, and direct motivational narrative.
     */
    public function generateProgressNarrative(User $user): string
    {
        $objectifs = $user->getObjectifs();
        if (count($objectifs) === 0) {
            return "Le tableau est vide. Pas d'objectifs, pas de victoires. Déposez votre première cible pour que nous puissions commencer le travail.";
        }

        $totalTaches = 0;
        $tachesTerminees = 0;
        $tachesEnCours = 0;
        $objectifsActifs = 0;
        $objectifsUrgents = [];
        $now = new \DateTime();

        foreach ($objectifs as $obj) {
            if ($obj->getStatut() !== 'TERMINE') {
                $objectifsActifs++;
                if ($obj->getDateFin() && $obj->getDateFin() < (new \DateTime('+3 days'))) {
                    $objectifsUrgents[] = $obj->getTitre();
                }
            }
            foreach ($obj->getTaches() as $t) {
                $totalTaches++;
                if ($t->getStatut() === 'TERMINE') {
                    $tachesTerminees++;
                } else if ($t->getStatut() === 'EN_COURS') {
                    $tachesEnCours++;
                }
            }
        }

        $rate = $totalTaches > 0 ? ($tachesTerminees / $totalTaches) * 100 : 0;
        $pending = $totalTaches - $tachesTerminees;
        $urgentTitle = !empty($objectifsUrgents) ? $objectifsUrgents[0] : 'vos objectifs urgents';

        // Personalities
        $personalities = [
            'COACH' => [
                'urgent' => "ALERTE ! Vous avez " . count($objectifsUrgents) . " objectifs dans le rouge. Arrêtez de tourner autour du pot : attaquez-vous à '" . $urgentTitle . "' immédiatement. Pas d'excuses.",
                'productive' => "Bien. Vous avez " . $tachesEnCours . " tâches sur le feu et " . $pending . " qui attendent. Votre taux de réussite est de " . round($rate) . "%. C'est solide, mais on peut faire mieux. Prochaine étape ?",
                'idle' => "Le moteur tourne à vide. Vous avez " . $objectifsActifs . " objectifs ouverts mais aucune tâche en cours. Choisissez-en une et foncez.",
                'completed' => "Excellent travail. " . $tachesTerminees . " victoires au compteur. Savourez l'instant, puis définissez votre prochain sommet."
            ],
            'STRATEGE' => [
                'urgent' => "Analyse tactique : Le front est critique sur '" . $urgentTitle . "'. Vos ressources sont dispersées sur " . $objectifsActifs . " objectifs. Concentrez toute votre force sur l'urgence immédiate pour éviter l'effondrement du planning.",
                'productive' => "Progression globale : " . round($rate) . "%. La structure tient. Avec " . $pending . " tâches restantes, vous êtes à mi-chemin de la domination totale de votre semestre. Maintenez le rythme.",
                'idle' => "Inertie détectée. Vos objectifs sont définis mais l'exécution est à l'arrêt. Sans action concrète, la stratégie n'est qu'une illusion. Activez une tâche.",
                'completed' => "Campagne réussie. Tous les objectifs ont été atteints. Votre efficacité a été exemplaire. Préparez le prochain plan de bataille."
            ],
            'MENTOR' => [
                'urgent' => "Je sens que la pression monte. '" . $urgentTitle . "' approche à grands pas. Ne vous laissez pas submerger par la panique. Prenez une seule petite tâche, et commencez maintenant. Je suis avec vous.",
                'productive' => "Vous avancez magnifiquement. " . $tachesTerminees . " pas déjà accomplis vers vos rêves. Prenez le temps de respirer, puis continuez ce beau voyage sur vos " . $objectifsActifs . " projets.",
                'idle' => "Prenez un moment pour vous reconnecter à vos intentions. " . $objectifsActifs . " projets attendent votre lumière. Quelle est la plus petite action que vous puissiez faire aujourd'hui ?",
                'completed' => "Quelle fierté ! Vous avez accompli tout ce que vous aviez prévu. C'est le moment de vous féliciter pour votre persévérance."
            ]
        ];

        // Context Selection
        $style = array_rand($personalities);
        $context = 'productive';

        if (!empty($objectifsUrgents)) {
            $context = 'urgent';
        } elseif ($totalTaches > 0 && $tachesEnCours === 0 && $rate < 100) {
            $context = 'idle';
        } elseif ($rate === 100 && $totalTaches > 0) {
            $context = 'completed';
        }

        return $personalities[$style][$context];
    }
}
