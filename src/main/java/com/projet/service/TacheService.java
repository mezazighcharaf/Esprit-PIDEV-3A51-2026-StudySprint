package com.projet.service;

import com.projet.entity.Tache;
import com.projet.util.DatabaseConnection;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class TacheService {

    private Connection conn() {
        return DatabaseConnection.getConnection();
    }

    public void create(Tache t) {
        String sql = "INSERT INTO tache (titre, duree, priorite, statut, objectif_id) VALUES (?,?,?,?,?)";
        System.out.println("[TacheService.create] titre=" + t.getTitre() + " objectifId=" + t.getObjectifId());
        try (PreparedStatement ps = conn().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, t.getTitre());
            ps.setInt(2, t.getDuree());
            ps.setString(3, t.getPriorite());
            ps.setString(4, t.getStatut());
            ps.setInt(5, t.getObjectifId());
            int rows = ps.executeUpdate();
            System.out.println("[TacheService.create] rows=" + rows);
            try (ResultSet keys = ps.getGeneratedKeys()) {
                if (keys.next()) t.setId(keys.getInt(1));
            }
        } catch (SQLException e) {
            System.err.println("[TacheService.create] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void update(Tache t) {
        String sql = "UPDATE tache SET titre=?, duree=?, priorite=?, statut=?, objectif_id=? WHERE id=?";
        System.out.println("[TacheService.update] id=" + t.getId() + " titre=" + t.getTitre());
        try (PreparedStatement ps = conn().prepareStatement(sql)) {
            ps.setString(1, t.getTitre());
            ps.setInt(2, t.getDuree());
            ps.setString(3, t.getPriorite());
            ps.setString(4, t.getStatut());
            ps.setInt(5, t.getObjectifId());
            ps.setInt(6, t.getId());
            int rows = ps.executeUpdate();
            System.out.println("[TacheService.update] rows=" + rows);
        } catch (SQLException e) {
            System.err.println("[TacheService.update] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void delete(int id) {
        System.out.println("[TacheService.delete] id=" + id);
        try (PreparedStatement ps = conn().prepareStatement("DELETE FROM tache WHERE id=?")) {
            ps.setInt(1, id);
            int rows = ps.executeUpdate();
            System.out.println("[TacheService.delete] rows=" + rows);
        } catch (SQLException e) {
            System.err.println("[TacheService.delete] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public List<Tache> findAll() {
        List<Tache> list = new ArrayList<>();
        String sql = "SELECT t.*, o.titre as objectif_titre FROM tache t " +
                     "JOIN objectif o ON t.objectif_id = o.id ORDER BY t.id DESC";
        try (Statement st = conn().createStatement(); ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("[TacheService.findAll] ERREUR: " + e.getMessage());
            e.printStackTrace();
        }
        return list;
    }

    public List<Tache> findByObjectifId(int objectifId) {
        List<Tache> list = new ArrayList<>();
        String sql = "SELECT t.*, o.titre as objectif_titre FROM tache t " +
                     "JOIN objectif o ON t.objectif_id = o.id WHERE t.objectif_id=?";
        try (PreparedStatement ps = conn().prepareStatement(sql)) {
            ps.setInt(1, objectifId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    public List<Tache> findByEtudiantId(int etudiantId) {
        List<Tache> list = new ArrayList<>();
        String sql = "SELECT t.*, o.titre as objectif_titre FROM tache t " +
                     "JOIN objectif o ON t.objectif_id = o.id " +
                     "WHERE o.etudiant_id=? ORDER BY t.id DESC";
        try (PreparedStatement ps = conn().prepareStatement(sql)) {
            ps.setInt(1, etudiantId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    public List<Tache> search(String keyword) {
        List<Tache> list = new ArrayList<>();
        String sql = "SELECT t.*, o.titre as objectif_titre FROM tache t " +
                     "JOIN objectif o ON t.objectif_id = o.id " +
                     "WHERE t.titre LIKE ? OR o.titre LIKE ?";
        try (PreparedStatement ps = conn().prepareStatement(sql)) {
            String like = "%" + keyword + "%";
            ps.setString(1, like);
            ps.setString(2, like);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    public boolean existsByTitreAndObjectif(String titre, int objectifId, Integer excludeId) {
        String sql = excludeId == null
            ? "SELECT id FROM tache WHERE LOWER(titre)=LOWER(?) AND objectif_id=?"
            : "SELECT id FROM tache WHERE LOWER(titre)=LOWER(?) AND objectif_id=? AND id<>?";
        try (PreparedStatement ps = conn().prepareStatement(sql)) {
            ps.setString(1, titre);
            ps.setInt(2, objectifId);
            if (excludeId != null) ps.setInt(3, excludeId);
            return ps.executeQuery().next();
        } catch (SQLException e) { e.printStackTrace(); return false; }
    }

    private Tache mapRow(ResultSet rs) throws SQLException {
        Tache t = new Tache(
            rs.getInt("id"), rs.getString("titre"), rs.getInt("duree"),
            rs.getString("priorite"), rs.getString("statut"), rs.getInt("objectif_id")
        );
        try { t.setObjectifTitre(rs.getString("objectif_titre")); } catch (SQLException ignored) {}
        return t;
    }
}
