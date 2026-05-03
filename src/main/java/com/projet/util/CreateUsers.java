package com.projet.util;

import org.mindrot.jbcrypt.BCrypt;
import java.sql.Connection;
import java.sql.PreparedStatement;

/**
 * Utilitaire à exécuter UNE SEULE FOIS pour créer les utilisateurs en DB.
 * Lance main() directement depuis IntelliJ (clic droit → Run).
 */
public class CreateUsers {

    public static void main(String[] args) throws Exception {
        Connection conn = DatabaseConnection.getConnection();

        // Supprimer les anciens
        conn.createStatement().executeUpdate(
            "DELETE FROM etudiant WHERE email IN ('user@studysprint.com','admin@studysprint.com')"
        );

        String sql = "INSERT INTO etudiant (email, roles, password) VALUES (?, ?, ?)";

        // USER : user123
        String hashUser = BCrypt.hashpw("user123", BCrypt.gensalt(12));
        try (PreparedStatement pst = conn.prepareStatement(sql)) {
            pst.setString(1, "user@studysprint.com");
            pst.setString(2, "[\"ROLE_USER\"]");
            pst.setString(3, hashUser);
            pst.executeUpdate();
        }

        // ADMIN : admin123
        String hashAdmin = BCrypt.hashpw("admin123", BCrypt.gensalt(12));
        try (PreparedStatement pst = conn.prepareStatement(sql)) {
            pst.setString(1, "admin@studysprint.com");
            pst.setString(2, "[\"ROLE_USER\",\"ROLE_ADMIN\"]");
            pst.setString(3, hashAdmin);
            pst.executeUpdate();
        }

        System.out.println("✅ Utilisateurs créés avec succès !");
        System.out.println("   user@studysprint.com  / user123");
        System.out.println("   admin@studysprint.com / admin123");
        conn.close();
    }
}
