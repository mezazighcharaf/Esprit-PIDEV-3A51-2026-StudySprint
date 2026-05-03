package com.projet.controller;

import com.projet.service.OffresEmploiService;
import com.projet.service.OffresEmploiService.Offre;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;

import java.awt.Desktop;
import java.net.URI;
import java.util.List;

public class OffresController implements NavigationAware {

    @FXML private TextField     txtKeyword;
    @FXML private ComboBox<String> cbPays;
    @FXML private VBox          offresContainer;
    @FXML private HBox          loadingBox;
    @FXML private HBox          paginationBox;
    @FXML private Label         lblResultats;
    @FXML private Label         lblPage;
    @FXML private Button        btnPrev;
    @FXML private Button        btnNext;

    private OffresEmploiService offresService;
    private MainController      mainController;
    private int                 currentPage = 1;
    private String              currentKeyword = "Java";

    @FXML
    public void initialize() {
        offresService = new OffresEmploiService();
        cbPays.setItems(FXCollections.observableArrayList("France", "Belgique", "Suisse", "Canada"));
        cbPays.getSelectionModel().selectFirst();
    }

    @Override
    public void setMainController(MainController mc) {
        this.mainController = mc;
        // Charger des offres par défaut
        rechercher("Java developer", 1);
    }

    // ─── Recherche ───────────────────────────────────────────────────────────

    @FXML public void handleRechercher() {
        String kw = txtKeyword.getText().trim();
        if (kw.isEmpty()) { showAlert("Entrez un mot-clé de recherche."); return; }
        currentKeyword = kw;
        currentPage = 1;
        rechercher(kw, 1);
    }

    @FXML public void handleReset() {
        txtKeyword.clear();
        offresContainer.getChildren().clear();
        lblResultats.setText("");
        paginationBox.setVisible(false);
        paginationBox.setManaged(false);
    }

    // Suggestions rapides
    @FXML public void searchJava()   { quickSearch("Java developer"); }
    @FXML public void searchPython() { quickSearch("Python developer"); }
    @FXML public void searchDevOps() { quickSearch("DevOps engineer"); }
    @FXML public void searchData()   { quickSearch("Data analyst"); }
    @FXML public void searchSpring() { quickSearch("Spring Boot developer"); }

    private void quickSearch(String kw) {
        txtKeyword.setText(kw);
        currentKeyword = kw;
        currentPage = 1;
        rechercher(kw, 1);
    }

    // Pagination
    @FXML public void handlePrev() {
        if (currentPage > 1) { currentPage--; rechercher(currentKeyword, currentPage); }
    }
    @FXML public void handleNext() {
        currentPage++;
        rechercher(currentKeyword, currentPage);
    }

    // ─── Chargement async ────────────────────────────────────────────────────

    private void rechercher(String keyword, int page) {
        // Afficher loading
        setLoading(true);
        offresContainer.getChildren().clear();

        // Appel API dans un thread séparé pour ne pas bloquer l'UI
        Thread thread = new Thread(() -> {
            List<Offre> offres = offresService.rechercher(keyword, page);
            Platform.runLater(() -> {
                setLoading(false);
                afficherOffres(offres, keyword, page);
            });
        });
        thread.setDaemon(true);
        thread.start();
    }

    private void afficherOffres(List<Offre> offres, String keyword, int page) {
        offresContainer.getChildren().clear();

        if (offres.isEmpty()) {
            VBox empty = new VBox(8);
            empty.setAlignment(Pos.CENTER);
            empty.setStyle("-fx-padding: 40;");
            Label lbl = new Label("😕 Aucune offre trouvée pour \"" + keyword + "\"");
            lbl.setStyle("-fx-font-size: 14px; -fx-text-fill: #6b7280;");
            empty.getChildren().add(lbl);
            offresContainer.getChildren().add(empty);
            lblResultats.setText("0 résultat");
            return;
        }

        lblResultats.setText(offres.size() + " offre(s) trouvée(s) — Page " + page);

        for (Offre o : offres) {
            offresContainer.getChildren().add(buildOffreCard(o));
        }

        // Pagination
        paginationBox.setVisible(true);
        paginationBox.setManaged(true);
        lblPage.setText("Page " + page);
        btnPrev.setDisable(page <= 1);
    }

    // ─── Carte offre ─────────────────────────────────────────────────────────

    private VBox buildOffreCard(Offre o) {
        VBox card = new VBox(10);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 12; " +
                      "-fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.06), 8, 0, 0, 2); " +
                      "-fx-padding: 16;");

        // Ligne 1 : Titre + Date
        HBox header = new HBox(12);
        header.setAlignment(Pos.CENTER_LEFT);

        Label lblTitre = new Label(o.titre);
        lblTitre.setStyle("-fx-font-size: 15px; -fx-font-weight: bold; -fx-text-fill: #1f2937;");
        lblTitre.setWrapText(true);

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Label lblDate = new Label("📅 " + o.datePublication);
        lblDate.setStyle("-fx-font-size: 11px; -fx-text-fill: #9ca3af;");

        header.getChildren().addAll(lblTitre, spacer, lblDate);

        // Ligne 2 : Entreprise + Lieu + Salaire
        HBox meta = new HBox(16);
        meta.setAlignment(Pos.CENTER_LEFT);

        Label lblEntreprise = new Label("🏢 " + o.entreprise);
        lblEntreprise.setStyle("-fx-font-size: 12px; -fx-text-fill: #3b82f6; -fx-font-weight: bold;");

        Label lblLieu = new Label("📍 " + o.lieu);
        lblLieu.setStyle("-fx-font-size: 12px; -fx-text-fill: #6b7280;");

        Label lblSalaire = new Label("💰 " + o.salaire);
        lblSalaire.setStyle("-fx-font-size: 12px; -fx-text-fill: #10b981; -fx-font-weight: bold;");

        meta.getChildren().addAll(lblEntreprise, lblLieu, lblSalaire);

        // Ligne 3 : Description
        Label lblDesc = new Label(o.description);
        lblDesc.setStyle("-fx-font-size: 12px; -fx-text-fill: #6b7280;");
        lblDesc.setWrapText(true);

        // Ligne 4 : Bouton Postuler
        HBox footer = new HBox();
        footer.setAlignment(Pos.CENTER_RIGHT);

        Button btnPostuler = new Button("Voir l'offre →");
        btnPostuler.getStyleClass().add("btn-primary");
        btnPostuler.setOnAction(e -> ouvrirUrl(o.url));

        footer.getChildren().add(btnPostuler);

        card.getChildren().addAll(header, meta, lblDesc, footer);

        // Hover effect
        card.setOnMouseEntered(e -> card.setStyle(
            "-fx-background-color: #f8faff; -fx-background-radius: 12; " +
            "-fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.12), 12, 0, 0, 4); " +
            "-fx-padding: 16;"));
        card.setOnMouseExited(e -> card.setStyle(
            "-fx-background-color: white; -fx-background-radius: 12; " +
            "-fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.06), 8, 0, 0, 2); " +
            "-fx-padding: 16;"));

        return card;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private void setLoading(boolean loading) {
        loadingBox.setVisible(loading);
        loadingBox.setManaged(loading);
    }

    private void ouvrirUrl(String url) {
        if (url == null || url.equals("#")) {
            showAlert("Lien non disponible en mode démo.\nInscrivez-vous sur developer.adzuna.com pour accéder aux vraies offres.");
            return;
        }
        try {
            Desktop.getDesktop().browse(new URI(url));
        } catch (Exception e) {
            showAlert("Impossible d'ouvrir le lien : " + e.getMessage());
        }
    }

    private void showAlert(String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle("Info"); a.setHeaderText(null); a.setContentText(msg);
        a.showAndWait();
    }
}
