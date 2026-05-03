package com.projet.service;

import com.projet.entity.Objectif;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.ArrayList;
import java.util.List;

/**
 * Service de rappels : détecte les objectifs dont l'échéance approche.
 */
public class RappelService {

    private final ObjectifService objectifService = new ObjectifService();

    public static class Rappel {
        public final Objectif objectif;
        public final long joursRestants;
        public final String niveau; // URGENT, ATTENTION, INFO

        public Rappel(Objectif objectif, long joursRestants) {
            this.objectif     = objectif;
            this.joursRestants = joursRestants;
            if (joursRestants <= 0)      this.niveau = "EXPIRE";
            else if (joursRestants <= 1) this.niveau = "URGENT";
            else if (joursRestants <= 3) this.niveau = "ATTENTION";
            else                         this.niveau = "INFO";
        }

        public String getMessage() {
            if (joursRestants <= 0)
                return "⛔ \"" + objectif.getTitre() + "\" est expiré !";
            if (joursRestants == 1)
                return "🔴 \"" + objectif.getTitre() + "\" expire demain !";
            return "🟠 \"" + objectif.getTitre() + "\" expire dans " + joursRestants + " jours.";
        }
    }

    /**
     * Retourne les rappels pour les objectifs EN_COURS dont l'échéance est dans <= seuilJours.
     */
    public List<Rappel> getRappels(int etudiantId, int seuilJours) {
        List<Rappel> rappels = new ArrayList<>();
        LocalDate today = LocalDate.now();

        for (Objectif o : objectifService.findByEtudiantId(etudiantId)) {
            if (!"EN_COURS".equals(o.getStatut())) continue;
            if (o.getDateFin() == null) continue;

            long jours = ChronoUnit.DAYS.between(today, o.getDateFin().toLocalDate());
            if (jours <= seuilJours) {
                rappels.add(new Rappel(o, jours));
            }
        }

        // Trier : les plus urgents en premier
        rappels.sort((a, b) -> Long.compare(a.joursRestants, b.joursRestants));
        return rappels;
    }
}
