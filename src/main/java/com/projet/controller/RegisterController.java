package com.projet.controller;

import com.projet.service.AuthService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.StackPane;
import javafx.stage.Stage;

public class RegisterController {

    @FXML private TextField txtEmail;
    @FXML private PasswordField txtPassword;
    @FXML private PasswordField txtConfirm;
    @FXML private Label lblError;

    private AuthService authService;

    @FXML
    public void initialize() {
        authService = new AuthService();
        lblError.setText("");
    }

    @FXML
    public void handleRegister() {
        String email = txtEmail.getText().trim();
        String password = txtPassword.getText();
        String confirm = txtConfirm.getText();

        if (email.isEmpty() || password.isEmpty() || confirm.isEmpty()) {
            lblError.setText("Veuillez remplir tous les champs.");
            return;
        }
        if (!email.contains("@")) {
            lblError.setText("Adresse email invalide.");
            return;
        }
        if (password.length() < 6) {
            lblError.setText("Le mot de passe doit contenir au moins 6 caractères.");
            return;
        }
        if (!password.equals(confirm)) {
            lblError.setText("Les mots de passe ne correspondent pas.");
            return;
        }

        boolean success = authService.register(email, password);
        if (success) {
            goToLogin();
        } else {
            lblError.setText("Cet email est déjà utilisé.");
        }
    }

    @FXML
    public void goToLogin() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/projet/view/LoginView.fxml"));
            StackPane root = loader.load();
            Stage stage = (Stage) txtEmail.getScene().getWindow();
            stage.setScene(new Scene(root, 1100, 700));
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
