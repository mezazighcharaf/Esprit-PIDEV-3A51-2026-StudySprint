package com.projet.service;

import com.projet.entity.Objectif;
import com.projet.util.DatabaseConnection;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class ObjectifService {

    private Connection conn() {
        return DatabaseConnection.getConnection();
    }

    public void create(Objectif o) {
        String sql = "INSERT INTO objectif (titre, description, date_debut, date_fin, statut, etudiant_id) VALUES (?,?,?,?,?,?)";
        System.out.println("[ObjectifService.create] titre=" + o.getTitre() + " etudiantId=" + o.getEtudiantId());
        try (PreparedStatement ps = conn().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, o.getTitre());
            ps.setString(2, o.getDescription());
            ps.setDate(3, o.getDateDebut());
            ps.setDate(4, o.getDateFin());
            ps.setString(5, o.getStatut());
            ps.setInt(6, o.getEtudiantId());
            int rows = ps.executeUpdate();
            System.out.println("[ObjectifService.create] rows=" + rows);
            try (ResultSet keys = ps.getGeneratedKeys()) {
                if (keys.next()) o.setId(keys.getInt(1));
            }
        } catch (SQLException e) {
            System.err.println("[ObjectifService.create] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void update(Objectif o) {
        String sql = "UPDATE objectif SET titre=?, description=?, date_debut=?, date_fin=?, statut=? WHERE id=?";
        System.out.println("[ObjectifService.update] id=" + o.getId() + " titre=" + o.getTitre());
        try (PreparedStatement ps = conn().prepareStatement(sql)) {
            ps.setString(1, o.getTitre());
            ps.setString(2, o.getDescription());
            ps.setDate(3, o.getDateDebut());
            ps.setDate(4, o.getDateFin());
            ps.setString(5, o.getStatut());
            ps.setInt(6, o.getId());
            int rows = ps.executeUpdate();
            System.out.println("[ObjectifService.update] rows=" + rows);
        } catch (SQLException e) {
            System.err.println("[ObjectifService.update] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void delete(int id) {
        System.out.println("[ObjectifService.delete] id=" + id);
        // Supprimer d'abord les tâches liées
        try (PreparedStatement ps = conn().prepareStatement("DELETE FROM tache WHERE objectif_id=?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        } catch (SQLException e) { e.printStackTrace(); }

        try (PreparedStatement ps = conn().prepareStatement("DELETE FROM objectif WHERE id=?")) {
            ps.setInt(1, id);
            int rows = ps.executeUpdate();
            System.out.println("[ObjectifService.delete] rows=" + rows);
        } catch (SQLException e) {
            System.err.println("[ObjectifService.delete] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public List<Objectif> findAll() {
        List<Objectif> list = new ArrayList<>();
        try (Statement st = conn().createStatement();
             ResultSet rs = st.executeQuery("SELECT * FROM objectif ORDER BY id DESC")) {
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("[ObjectifService.findAll] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
        return list;
    }

    public List<Objectif> findAllWithTaches() {
        List<Objectif> objectifs = findAll();
        TacheService ts = new TacheService();
        for (Objectif o : objectifs) o.setTaches(ts.findByObjectifId(o.getId()));
        return objectifs;
    }

    public List<Objectif> findAllWithTachesByEtudiant(int etudiantId) {
        List<Objectif> objectifs = findByEtudiantId(etudiantId);
        TacheService ts = new TacheService();
        for (Objectif o : objectifs) o.setTaches(ts.findByObjectifId(o.getId()));
        return objectifs;
    }

    public List<Objectif> findByEtudiantId(int etudiantId) {
        List<Objectif> list = new ArrayList<>();
        try (PreparedStatement ps = conn().prepareStatement(
                "SELECT * FROM objectif WHERE etudiant_id=? ORDER BY id DESC")) {
            ps.setInt(1, etudiantId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    public Objectif findById(int id) {
        try (PreparedStatement ps = conn().prepareStatement("SELECT * FROM objectif WHERE id=?")) {
            ps.setInt(1, id);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return mapRow(rs);
        } catch (SQLException e) { e.printStackTrace(); }
        return null;
    }

    public boolean existsByTitreAndEtudiant(String titre, int etudiantId, Integer excludeId) {
        String sql = excludeId == null
            ? "SELECT id FROM objectif WHERE LOWER(titre)=LOWER(?) AND etudiant_id=?"
            : "SELECT id FROM objectif WHERE LOWER(titre)=LOWER(?) AND etudiant_id=? AND id<>?";
        try (PreparedStatement ps = conn().prepareStatement(sql)) {
            ps.setString(1, titre);
            ps.setInt(2, etudiantId);
            if (excludeId != null) ps.setInt(3, excludeId);
            return ps.executeQuery().next();
        } catch (SQLException e) { e.printStackTrace(); return false; }
    }

    private Objectif mapRow(ResultSet rs) throws SQLException {
        return new Objectif(
            rs.getInt("id"), rs.getString("titre"), rs.getString("description"),
            rs.getDate("date_debut"), rs.getDate("date_fin"),
            rs.getString("statut"), rs.getInt("etudiant_id")
        );
    }
}
