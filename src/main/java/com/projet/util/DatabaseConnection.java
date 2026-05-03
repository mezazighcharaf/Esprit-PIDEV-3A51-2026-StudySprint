package com.projet.util;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class DatabaseConnection {
    private static final String URL  = "jdbc:mysql://127.0.0.1:3306/studysprint?serverTimezone=UTC&autoReconnect=true";
    private static final String USER = "root";
    private static final String PASSWORD = "";

    private static Connection connection;

    private DatabaseConnection() {}

    public static Connection getConnection() {
        try {
            if (connection == null || connection.isClosed() || !connection.isValid(2)) {
                Class.forName("com.mysql.cj.jdbc.Driver");
                connection = DriverManager.getConnection(URL, USER, PASSWORD);
                System.out.println("✅ Connexion DB établie.");
            }
        } catch (Exception e) {
            System.err.println("❌ Connexion DB échouée: " + e.getMessage());
            e.printStackTrace();
        }
        return connection;
    }
}
