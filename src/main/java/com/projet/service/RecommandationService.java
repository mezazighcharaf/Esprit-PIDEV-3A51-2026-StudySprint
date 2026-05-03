package com.projet.service;

import com.projet.entity.Objectif;
import com.projet.entity.Tache;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.Comparator;
import java.util.List;
import java.util.stream.Collectors;

/**
 * Service de recommandation : suggère la prochaine tâche à faire
 * en fonction de la priorité, de l'urgence de l'objectif et du statut.
 */
public class RecommandationService {

    private final ObjectifService objectifService = new ObjectifService();
    private final TacheService    tacheService    = new TacheService();

    public static class Recommandation {
        public final Tache   tache;
        public final Objectif objectif;
        public final String  raison;
        public final int     score;

        public Recommandation(Tache tache, Objectif objectif, String raison, int score) {
            this.tache    = tache;
            this.objectif = objectif;
            this.raison   = raison;
            this.score    = score;
        }
    }

    /**
     * Retourne la tâche la plus recommandée pour l'étudiant connecté.
     */
    public Recommandation getRecommandation(int etudiantId) {
        List<Objectif> objectifs = objectifService.findAllWithTachesByEtudiant(etudiantId);
        LocalDate today = LocalDate.now();

        List<Recommandation> candidates = objectifs.stream()
            .filter(o -> "EN_COURS".equals(o.getStatut()))
            .flatMap(o -> o.getTaches().stream()
                .filter(t -> !"TERMINE".equals(t.getStatut()))
                .map(t -> new Recommandation(t, o, buildRaison(t, o, today), calcScore(t, o, today)))
            )
            .sorted(Comparator.comparingInt((Recommandation r) -> r.score).reversed())
            .collect(Collectors.toList());

        return candidates.isEmpty() ? null : candidates.get(0);
    }

    private int calcScore(Tache t, Objectif o, LocalDate today) {
        int score = 0;

        // Priorité de la tâche
        if ("HAUTE".equals(t.getPriorite()))        score += 50;
        else if ("MOYENNE".equals(t.getPriorite())) score += 25;
        else                                         score += 10;

        // Urgence de l'objectif (plus c'est proche, plus le score est élevé)
        if (o.getDateFin() != null) {
            long jours = ChronoUnit.DAYS.between(today, o.getDateFin().toLocalDate());
            if (jours <= 0)       score += 100;
            else if (jours <= 1)  score += 80;
            else if (jours <= 3)  score += 60;
            else if (jours <= 7)  score += 30;
            else if (jours <= 14) score += 10;
        }

        // Tâche EN_COURS déjà commencée → priorité
        if ("EN_COURS".equals(t.getStatut())) score += 20;

        // Durée courte → plus facile à finir rapidement
        if (t.getDuree() != null && t.getDuree() <= 30) score += 10;

        return score;
    }

    private String buildRaison(Tache t, Objectif o, LocalDate today) {
        StringBuilder sb = new StringBuilder();
        if (o.getDateFin() != null) {
            long jours = ChronoUnit.DAYS.between(today, o.getDateFin().toLocalDate());
            if (jours <= 0)      sb.append("Objectif expiré ! ");
            else if (jours <= 1) sb.append("Objectif expire demain ! ");
            else if (jours <= 3) sb.append("Objectif expire dans " + jours + " jours. ");
        }
        if ("HAUTE".equals(t.getPriorite()))   sb.append("Priorité haute. ");
        if ("EN_COURS".equals(t.getStatut()))  sb.append("Déjà commencée. ");
        if (t.getDuree() != null && t.getDuree() <= 30) sb.append("Rapide à faire (" + t.getDuree() + " min).");
        return sb.toString().trim();
    }
}
