package com.projet.service;

import com.projet.entity.Objectif;
import com.projet.entity.Tache;

import java.util.ArrayList;
import java.util.List;

/**
 * Métier 2 : Système de badges/gamification.
 * Attribue des badges selon les accomplissements de l'étudiant.
 */
public class BadgeService {

    public static class Badge {
        public final String emoji;
        public final String titre;
        public final String description;
        public final boolean obtenu;

        public Badge(String emoji, String titre, String description, boolean obtenu) {
            this.emoji       = emoji;
            this.titre       = titre;
            this.description = description;
            this.obtenu      = obtenu;
        }
    }

    public List<Badge> getBadges(int etudiantId) {
        ObjectifService objectifService = new ObjectifService();
        List<Objectif> objectifs = objectifService.findAllWithTachesByEtudiant(etudiantId);

        long totalTaches     = objectifs.stream().mapToLong(o -> o.getTaches().size()).sum();
        long tachesTerminees = objectifs.stream()
            .flatMap(o -> o.getTaches().stream())
            .filter(t -> "TERMINE".equals(t.getStatut())).count();
        long objTermines     = objectifs.stream().filter(o -> "TERMINE".equals(o.getStatut())).count();
        long hautePrioFaites = objectifs.stream()
            .flatMap(o -> o.getTaches().stream())
            .filter(t -> "HAUTE".equals(t.getPriorite()) && "TERMINE".equals(t.getStatut())).count();

        List<Badge> badges = new ArrayList<>();

        badges.add(new Badge("🚀", "Premier pas",
            "Créer votre premier objectif",
            !objectifs.isEmpty()));

        badges.add(new Badge("✅", "Première tâche",
            "Terminer votre première tâche",
            tachesTerminees >= 1));

        badges.add(new Badge("🔥", "En feu",
            "Terminer 5 tâches",
            tachesTerminees >= 5));

        badges.add(new Badge("💪", "Travailleur",
            "Terminer 10 tâches",
            tachesTerminees >= 10));

        badges.add(new Badge("🏆", "Champion",
            "Terminer un objectif complet",
            objTermines >= 1));

        badges.add(new Badge("⚡", "Prioritaire",
            "Terminer 3 tâches de haute priorité",
            hautePrioFaites >= 3));

        badges.add(new Badge("🎯", "Focalisé",
            "Avoir 3 objectifs en cours simultanément",
            objectifs.stream().filter(o -> "EN_COURS".equals(o.getStatut())).count() >= 3));

        badges.add(new Badge("🌟", "Expert",
            "Terminer 3 objectifs",
            objTermines >= 3));

        badges.add(new Badge("📚", "Studieux",
            "Créer 10 tâches au total",
            totalTaches >= 10));

        badges.add(new Badge("🎓", "Prêt pour le recrutement",
            "Atteindre un score de préparation ≥ 80%",
            new ScorePreparationService().calculerScore(etudiantId).score >= 80));

        return badges;
    }

    public long countObtained(List<Badge> badges) {
        return badges.stream().filter(b -> b.obtenu).count();
    }
}
