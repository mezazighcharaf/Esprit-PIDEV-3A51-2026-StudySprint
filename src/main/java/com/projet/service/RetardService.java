package com.projet.service;

import com.projet.entity.Objectif;
import com.projet.util.DatabaseConnection;

import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;

/**
 * Métier 3 : Détection et mise à jour automatique des objectifs en retard.
 * Un objectif EN_COURS dont la date de fin est dépassée → EN_RETARD.
 */
public class RetardService {

    public static class RetardInfo {
        public final Objectif objectif;
        public final long     joursRetard;

        public RetardInfo(Objectif o, long joursRetard) {
            this.objectif    = o;
            this.joursRetard = joursRetard;
        }

        public String getMessage() {
            return "⚠ \"" + objectif.getTitre() + "\" — " + joursRetard + " jour(s) de retard";
        }
    }

    private final ObjectifService objectifService = new ObjectifService();

    /**
     * Détecte les objectifs en retard et les met à jour en DB avec statut EN_RETARD.
     * Retourne la liste des objectifs mis à jour.
     */
    public List<RetardInfo> detecterEtMettreAJour(int etudiantId) {
        List<RetardInfo> retards = new ArrayList<>();
        LocalDate today = LocalDate.now();

        for (Objectif o : objectifService.findByEtudiantId(etudiantId)) {
            if (!"EN_COURS".equals(o.getStatut())) continue;
            if (o.getDateFin() == null) continue;

            LocalDate dateFin = o.getDateFin().toLocalDate();
            if (dateFin.isBefore(today)) {
                long jours = java.time.temporal.ChronoUnit.DAYS.between(dateFin, today);
                retards.add(new RetardInfo(o, jours));
                // Mettre à jour le statut en DB
                mettreEnRetard(o.getId());
            }
        }
        return retards;
    }

    /**
     * Remet un objectif EN_COURS (si l'utilisateur prolonge la date).
     */
    public void remettreEnCours(int objectifId) {
        try (PreparedStatement ps = DatabaseConnection.getConnection()
                .prepareStatement("UPDATE objectif SET statut='EN_COURS' WHERE id=?")) {
            ps.setInt(1, objectifId);
            ps.executeUpdate();
        } catch (SQLException e) { e.printStackTrace(); }
    }

    private void mettreEnRetard(int objectifId) {
        try (PreparedStatement ps = DatabaseConnection.getConnection()
                .prepareStatement("UPDATE objectif SET statut='EN_RETARD' WHERE id=?")) {
            ps.setInt(1, objectifId);
            ps.executeUpdate();
        } catch (SQLException e) { e.printStackTrace(); }
    }
}
