package com.projet.service;

import com.projet.entity.Etudiant;
import com.projet.util.DatabaseConnection;
import org.mindrot.jbcrypt.BCrypt;

import java.sql.*;

public class AuthService {

    private static Etudiant currentUser = null;

    private Connection conn() {
        return DatabaseConnection.getConnection();
    }

    public Etudiant login(String email, String password) {
        System.out.println("[AuthService.login] email=" + email);
        try (PreparedStatement ps = conn().prepareStatement("SELECT * FROM etudiant WHERE email=?")) {
            ps.setString(1, email);
            ResultSet rs = ps.executeQuery();
            if (!rs.next()) {
                System.out.println("[AuthService.login] Aucun utilisateur trouvé.");
                return null;
            }
            String stored = rs.getString("password");
            System.out.println("[AuthService.login] Hash trouvé: " + stored);
            if (verifyPassword(password, stored)) {
                currentUser = new Etudiant(rs.getInt("id"), rs.getString("email"));
                System.out.println("[AuthService.login] Connexion OK, id=" + currentUser.getId());
                return currentUser;
            }
            System.out.println("[AuthService.login] Mot de passe incorrect.");
            return null;
        } catch (SQLException e) {
            System.err.println("[AuthService.login] ERREUR: " + e.getMessage());
            e.printStackTrace();
            return null;
        }
    }

    public boolean register(String email, String password) {
        try (PreparedStatement ps = conn().prepareStatement("SELECT id FROM etudiant WHERE email=?")) {
            ps.setString(1, email);
            if (ps.executeQuery().next()) return false;
        } catch (SQLException e) { e.printStackTrace(); return false; }

        try (PreparedStatement ps = conn().prepareStatement(
                "INSERT INTO etudiant (email, password, roles) VALUES (?,?,?)")) {
            ps.setString(1, email);
            ps.setString(2, BCrypt.hashpw(password, BCrypt.gensalt(12)));
            ps.setString(3, "[\"ROLE_USER\"]");
            ps.executeUpdate();
            return true;
        } catch (SQLException e) { e.printStackTrace(); return false; }
    }

    public static Etudiant getCurrentUser() { return currentUser; }
    public static void logout() { currentUser = null; }

    private boolean verifyPassword(String plain, String stored) {
        if (stored == null) return false;
        try {
            // BCrypt (hash $2a$ ou $2y$ Symfony)
            if (stored.startsWith("$2")) {
                return BCrypt.checkpw(plain, stored.replace("$2y$", "$2a$"));
            }
        } catch (Exception e) {
            System.out.println("[AuthService] BCrypt error: " + e.getMessage());
        }
        // Fallback mot de passe en clair (pour tests)
        return plain.equals(stored);
    }
}
