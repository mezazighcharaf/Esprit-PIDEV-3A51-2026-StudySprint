package com.projet.service;

import com.projet.entity.Objectif;
import com.projet.entity.Tache;

import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.List;

/**
 * Métier 1 : Calcul du score de préparation au recrutement (0-100).
 * Basé sur : tâches terminées, respect des délais, priorités, objectifs complétés.
 */
public class ScorePreparationService {

    public static class ScoreResult {
        public final int    score;          // 0-100
        public final String niveau;         // FAIBLE, MOYEN, BON, EXCELLENT
        public final String couleur;        // hex color
        public final String message;
        public final int    pctTaches;
        public final int    pctObjectifs;
        public final int    pctDelais;
        public final int    pctPriorites;

        public ScoreResult(int score, int pctTaches, int pctObjectifs,
                           int pctDelais, int pctPriorites) {
            this.score        = score;
            this.pctTaches    = pctTaches;
            this.pctObjectifs = pctObjectifs;
            this.pctDelais    = pctDelais;
            this.pctPriorites = pctPriorites;

            if (score >= 80)      { niveau = "EXCELLENT"; couleur = "#10b981"; message = "Excellent ! Vous êtes prêt pour le recrutement 🎉"; }
            else if (score >= 60) { niveau = "BON";       couleur = "#3b82f6"; message = "Bon niveau ! Continuez vos efforts 💪"; }
            else if (score >= 40) { niveau = "MOYEN";     couleur = "#f59e0b"; message = "Niveau moyen. Concentrez-vous sur les priorités ⚡"; }
            else                  { niveau = "FAIBLE";    couleur = "#ef4444"; message = "Niveau faible. Commencez par les tâches urgentes 🔥"; }
        }
    }

    public ScoreResult calculerScore(int etudiantId) {
        ObjectifService objectifService = new ObjectifService();
        List<Objectif> objectifs = objectifService.findAllWithTachesByEtudiant(etudiantId);
        LocalDate today = LocalDate.now();

        if (objectifs.isEmpty()) return new ScoreResult(0, 0, 0, 0, 0);

        // ── 1. % tâches terminées (poids 40%) ────────────────────────────────
        long totalTaches = objectifs.stream().mapToLong(o -> o.getTaches().size()).sum();
        long tachesTerminees = objectifs.stream()
            .flatMap(o -> o.getTaches().stream())
            .filter(t -> "TERMINE".equals(t.getStatut())).count();
        int pctTaches = totalTaches > 0 ? (int)(tachesTerminees * 100 / totalTaches) : 0;

        // ── 2. % objectifs terminés (poids 30%) ──────────────────────────────
        long totalObj = objectifs.size();
        long objTermines = objectifs.stream().filter(o -> "TERMINE".equals(o.getStatut())).count();
        int pctObjectifs = (int)(objTermines * 100 / totalObj);

        // ── 3. Respect des délais (poids 20%) ────────────────────────────────
        long objEnCours = objectifs.stream().filter(o -> "EN_COURS".equals(o.getStatut())).count();
        long objEnRetard = objectifs.stream()
            .filter(o -> "EN_COURS".equals(o.getStatut())
                && o.getDateFin() != null
                && o.getDateFin().toLocalDate().isBefore(today))
            .count();
        int pctDelais = objEnCours > 0
            ? (int)((objEnCours - objEnRetard) * 100 / objEnCours)
            : 100;

        // ── 4. Tâches HAUTE priorité terminées (poids 10%) ───────────────────
        long hautePrioTotal = objectifs.stream()
            .flatMap(o -> o.getTaches().stream())
            .filter(t -> "HAUTE".equals(t.getPriorite())).count();
        long hautePrioTerminees = objectifs.stream()
            .flatMap(o -> o.getTaches().stream())
            .filter(t -> "HAUTE".equals(t.getPriorite()) && "TERMINE".equals(t.getStatut())).count();
        int pctPriorites = hautePrioTotal > 0
            ? (int)(hautePrioTerminees * 100 / hautePrioTotal)
            : 100;

        // ── Score final ───────────────────────────────────────────────────────
        int score = (pctTaches * 40 + pctObjectifs * 30 + pctDelais * 20 + pctPriorites * 10) / 100;

        return new ScoreResult(score, pctTaches, pctObjectifs, pctDelais, pctPriorites);
    }
}
