package com.projet.controller;

import com.projet.entity.Etudiant;
import com.projet.service.AuthService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Node;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.StackPane;
import javafx.stage.Stage;

import java.io.IOException;

public class MainController {

    @FXML private StackPane contentArea;
    @FXML private Button    btnDashboard;
    @FXML private Button    btnObjectifs;
    @FXML private Button    btnTaches;
    @FXML private Button    btnOffres;
    @FXML private Button    btnStats;
    @FXML private Label     lblUserName;
    @FXML private Label     lblUserAvatar;

    private Button activeBtn;

    @FXML
    public void initialize() {
        // Ne pas charger ici — on attend setCurrentUser() depuis LoginController
    }

    /**
     * Appelé par LoginController après connexion réussie.
     * C'est ici qu'on charge le dashboard avec l'user déjà défini.
     */
    public void setCurrentUser(Etudiant user) {
        if (user != null) {
            if (lblUserName   != null) lblUserName.setText(user.getEmail());
            if (lblUserAvatar != null) lblUserAvatar.setText(user.getInitials());
        }
        // Maintenant AuthService.getCurrentUser() est défini → on charge
        showDashboard();
    }

    @FXML public void showDashboard() { loadView("DashboardView.fxml");   setActive(btnDashboard); }
    @FXML public void showObjectifs() { loadView("ObjectifView.fxml");    setActive(btnObjectifs); }
    @FXML public void showTaches()    { loadView("TacheView.fxml");       setActive(btnTaches);    }
    @FXML public void showOffres()    { loadView("OffresView.fxml");      setActive(btnOffres);    }
    @FXML public void showStats()     { loadView("StatisticsView.fxml");  setActive(btnStats);     }

    @FXML
    public void handleLogout() {
        AuthService.logout();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/projet/view/LoginView.fxml"));
            StackPane root = loader.load();
            Stage stage = (Stage) contentArea.getScene().getWindow();
            stage.setScene(new Scene(root, 1100, 700));
            stage.setTitle("StudySprint - Connexion");
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    private void loadView(String fxmlFile) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/projet/view/" + fxmlFile));
            Node view = loader.load();
            Object controller = loader.getController();
            if (controller instanceof NavigationAware) {
                ((NavigationAware) controller).setMainController(this);
            }
            contentArea.getChildren().setAll(view);
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    private void setActive(Button btn) {
        if (activeBtn != null) activeBtn.getStyleClass().remove("nav-btn-active");
        activeBtn = btn;
        if (btn != null) btn.getStyleClass().add("nav-btn-active");
    }
}
