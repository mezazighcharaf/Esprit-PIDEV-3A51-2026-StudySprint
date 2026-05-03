package com.projet.controller;

import com.projet.entity.Etudiant;
import com.projet.service.AuthService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.HBox;
import javafx.scene.layout.StackPane;
import javafx.stage.Stage;

public class LoginController {

    @FXML private TextField txtEmail;
    @FXML private PasswordField txtPassword;
    @FXML private Label lblError;

    private AuthService authService;

    @FXML
    public void initialize() {
        authService = new AuthService();
        lblError.setText("");
    }

    @FXML
    public void handleLogin() {
        String email = txtEmail.getText().trim();
        String password = txtPassword.getText();

        if (email.isEmpty() || password.isEmpty()) {
            lblError.setText("Veuillez remplir tous les champs.");
            return;
        }

        Etudiant user = authService.login(email, password);
        if (user != null) {
            openMainApp(user);
        } else {
            lblError.setText("Email ou mot de passe incorrect.");
            txtPassword.clear();
        }
    }

    @FXML
    public void handleRegister() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/projet/view/RegisterView.fxml"));
            StackPane root = loader.load();
            Stage stage = (Stage) txtEmail.getScene().getWindow();
            stage.setScene(new Scene(root, 1100, 700));
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void openMainApp(Etudiant user) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/projet/view/MainLayout.fxml"));
            HBox root = loader.load();

            // Pass user info to MainController
            MainController mainCtrl = loader.getController();
            mainCtrl.setCurrentUser(user);

            Stage stage = (Stage) txtEmail.getScene().getWindow();
            stage.setScene(new Scene(root, 1100, 700));
            stage.setTitle("StudySprint - Gestion des Objectifs & Tâches");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
