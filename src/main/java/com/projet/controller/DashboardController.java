package com.projet.controller;

import com.projet.entity.Objectif;
import com.projet.entity.Tache;
import com.projet.service.*;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.chart.PieChart;
import javafx.scene.control.*;
import javafx.scene.layout.*;

import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

import com.projet.service.AuthService;
import com.projet.service.RappelService.Rappel;
import com.projet.service.RecommandationService.Recommandation;
import com.projet.service.ScorePreparationService.ScoreResult;
import com.projet.service.BadgeService.Badge;
import com.projet.service.CitationService;

public class DashboardController implements NavigationAware {

    // Hero
    @FXML private Label       lblPriorityTitle;
    @FXML private Label       lblPriorityDeadline;
    @FXML private Label       lblProgressText;
    @FXML private ProgressBar progressBar;

    // Stats
    @FXML private Label lblTasksDone;
    @FXML private Label lblTasksInProgress;
    @FXML private Label lblObjectifsActifs;
    @FXML private Label lblTasksTodo;

    // Rappels
    @FXML private VBox rappelsBox;

    // Recommandation
    @FXML private VBox   recommandationBox;
    @FXML private Label  lblRecommandationTache;
    @FXML private Label  lblRecommandationRaison;
    @FXML private Button btnGoToRecommandation;

    // Score + Badges
    @FXML private VBox  scoreBox;
    @FXML private Label lblScore;
    @FXML private Label lblScoreNiveau;
    @FXML private Label lblScoreMessage;
    @FXML private ProgressBar progressScore;
    @FXML private HBox  badgesBox;

    // Citation
    @FXML private Label lblCitation;
    @FXML private Label lblAuteurCitation;

    // Todo + Chart
    @FXML private VBox     todoList;
    @FXML private PieChart pieChart;

    private ObjectifService       objectifService;
    private TacheService          tacheService;
    private RappelService         rappelService;
    private RecommandationService recommandationService;
    private ScorePreparationService scoreService;
    private BadgeService          badgeService;
    private RetardService         retardService;
    private CitationService       citationService;
    private MainController        mainController;

    // Tâche recommandée courante (pour navigation)
    private Tache tacheRecommandee;

    private static final DateTimeFormatter FMT = DateTimeFormatter.ofPattern("dd/MM/yyyy");

    @FXML
    public void initialize() {
        objectifService       = new ObjectifService();
        tacheService          = new TacheService();
        rappelService         = new RappelService();
        recommandationService = new RecommandationService();
        scoreService          = new ScorePreparationService();
        badgeService          = new BadgeService();
        retardService         = new RetardService();
        citationService       = new CitationService();
    }

    @Override
    public void setMainController(MainController mc) {
        this.mainController = mc;
        loadDashboard();
    }

    // ─── Chargement principal ────────────────────────────────────────────────

    private void loadDashboard() {
        int userId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 1;

        // ── 1. Données filtrées par utilisateur ──────────────────────────────
        List<Objectif> objectifs = objectifService.findAllWithTachesByEtudiant(userId);
        List<Tache>    allTaches = tacheService.findByEtudiantId(userId);

        // ── 2. Stats ─────────────────────────────────────────────────────────
        long done       = allTaches.stream().filter(t -> "TERMINE".equals(t.getStatut())).count();
        long inProgress = allTaches.stream().filter(t -> "EN_COURS".equals(t.getStatut())).count();
        long todo       = allTaches.stream().filter(t -> "A_FAIRE".equals(t.getStatut())).count();
        long activeObj  = objectifs.stream().filter(o -> "EN_COURS".equals(o.getStatut())).count();

        lblTasksDone.setText(String.valueOf(done));
        lblTasksInProgress.setText(String.valueOf(inProgress));
        lblObjectifsActifs.setText(String.valueOf(activeObj));
        lblTasksTodo.setText(String.valueOf(todo));

        // ── 3. Hero card : objectif prioritaire ──────────────────────────────
        Objectif priority = objectifs.stream()
            .filter(o -> "EN_COURS".equals(o.getStatut()) && o.getDateFin() != null)
            .min((a, b) -> a.getDateFin().compareTo(b.getDateFin()))
            .orElse(null);

        if (priority != null) {
            lblPriorityTitle.setText(priority.getTitre());
            lblPriorityDeadline.setText("⏰ Échéance : " + priority.getDateFin().toLocalDate().format(FMT));
            progressBar.setProgress(priority.getProgressPercent() / 100.0);
            lblProgressText.setText(priority.getCompletedTachesCount() + "/" + priority.getTaches().size() + " tâches terminées");
        } else {
            lblPriorityTitle.setText("Aucun objectif en cours");
            lblPriorityDeadline.setText("Créez votre premier objectif !");
            progressBar.setProgress(0);
            lblProgressText.setText("");
        }

        // ── 4. Rappels ───────────────────────────────────────────────────────
        loadRappels(userId);

        // ── 5. Recommandation ────────────────────────────────────────────────
        loadRecommandation(userId);

        // ── 5b. Détection retards ────────────────────────────────────────────
        retardService.detecterEtMettreAJour(userId);

        // ── 5c. Score + Badges ───────────────────────────────────────────────
        loadScoreEtBadges(userId);

        // ── 5d. Citation motivante (async) ───────────────────────────────────
        loadCitation();

        // ── 6. Todo list ─────────────────────────────────────────────────────
        todoList.getChildren().clear();
        allTaches.stream()
            .filter(t -> !"TERMINE".equals(t.getStatut()))
            .limit(5)
            .forEach(t -> todoList.getChildren().add(buildTodoItem(t)));

        if (todoList.getChildren().isEmpty()) {
            Label empty = new Label("Aucune tâche en attente. Bon travail ! 🎉");
            empty.setStyle("-fx-text-fill: #9ca3af; -fx-font-size: 13px;");
            todoList.getChildren().add(empty);
        }

        // ── 7. Pie chart ─────────────────────────────────────────────────────
        Map<String, Long> statusCount = allTaches.stream()
            .collect(Collectors.groupingBy(
                t -> t.getStatut() != null ? t.getStatut() : "INCONNU",
                Collectors.counting()));

        ObservableList<PieChart.Data> pieData = FXCollections.observableArrayList();
        statusCount.forEach((s, c) -> pieData.add(new PieChart.Data(s + " (" + c + ")", c)));
        pieChart.setData(pieData);
    }

    // ─── Rappels ─────────────────────────────────────────────────────────────

    private void loadRappels(int userId) {
        rappelsBox.getChildren().clear();
        List<Rappel> rappels = rappelService.getRappels(userId, 7);

        if (rappels.isEmpty()) {
            rappelsBox.setVisible(false);
            rappelsBox.setManaged(false);
            return;
        }

        rappelsBox.setVisible(true);
        rappelsBox.setManaged(true);

        for (Rappel r : rappels) {
            HBox row = new HBox(12);
            row.setAlignment(Pos.CENTER_LEFT);
            row.setPadding(new javafx.geometry.Insets(10, 14, 10, 14));
            row.setStyle(getRappelStyle(r.niveau));

            Label icon = new Label(getRappelIcon(r.niveau));
            icon.setStyle("-fx-font-size: 16px;");

            VBox info = new VBox(2);
            Label msg = new Label(r.getMessage());
            msg.setStyle("-fx-font-weight: bold; -fx-font-size: 13px; -fx-text-fill: " + getRappelTextColor(r.niveau) + ";");
            Label sub = new Label("Objectif : " + r.objectif.getTitre()
                + "  •  Échéance : " + r.objectif.getDateFin().toLocalDate().format(FMT));
            sub.setStyle("-fx-font-size: 11px; -fx-text-fill: #6b7280;");
            info.getChildren().addAll(msg, sub);

            Region spacer = new Region();
            HBox.setHgrow(spacer, Priority.ALWAYS);

            Button btnVoir = new Button("Voir →");
            btnVoir.getStyleClass().add("btn-light");
            btnVoir.setOnAction(e -> {
                if (mainController != null) mainController.showObjectifs();
            });

            row.getChildren().addAll(icon, info, spacer, btnVoir);
            rappelsBox.getChildren().add(row);
        }

        // Afficher popup si rappels urgents au démarrage
        long urgents = rappels.stream()
            .filter(r -> "URGENT".equals(r.niveau) || "EXPIRE".equals(r.niveau))
            .count();
        if (urgents > 0) {
            Platform.runLater(() -> showRappelPopup(rappels));
        }
    }

    private void showRappelPopup(List<Rappel> rappels) {
        StringBuilder sb = new StringBuilder();
        rappels.stream()
            .filter(r -> "URGENT".equals(r.niveau) || "EXPIRE".equals(r.niveau))
            .forEach(r -> sb.append(r.getMessage()).append("\n"));

        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle("⚠ Rappels urgents");
        alert.setHeaderText("Vous avez des objectifs urgents !");
        alert.setContentText(sb.toString().trim());
        alert.showAndWait();
    }

    private String getRappelStyle(String niveau) {
        return switch (niveau) {
            case "EXPIRE"    -> "-fx-background-color: #fee2e2; -fx-background-radius: 10;";
            case "URGENT"    -> "-fx-background-color: #fee2e2; -fx-background-radius: 10;";
            case "ATTENTION" -> "-fx-background-color: #fef3c7; -fx-background-radius: 10;";
            default          -> "-fx-background-color: #dbeafe; -fx-background-radius: 10;";
        };
    }

    private String getRappelIcon(String niveau) {
        return switch (niveau) {
            case "EXPIRE", "URGENT" -> "🔴";
            case "ATTENTION"        -> "🟠";
            default                 -> "🔵";
        };
    }

    private String getRappelTextColor(String niveau) {
        return switch (niveau) {
            case "EXPIRE", "URGENT" -> "#991b1b";
            case "ATTENTION"        -> "#92400e";
            default                 -> "#1e40af";
        };
    }

    // ─── Recommandation ──────────────────────────────────────────────────────

    private void loadRecommandation(int userId) {
        Recommandation rec = recommandationService.getRecommandation(userId);

        if (rec == null) {
            recommandationBox.setVisible(false);
            recommandationBox.setManaged(false);
            return;
        }

        recommandationBox.setVisible(true);
        recommandationBox.setManaged(true);
        tacheRecommandee = rec.tache;

        lblRecommandationTache.setText("📌 " + rec.tache.getTitre()
            + "  —  " + rec.objectif.getTitre());
        lblRecommandationRaison.setText(rec.raison.isEmpty()
            ? "Tâche prioritaire recommandée."
            : rec.raison);
    }

    @FXML
    public void goToRecommandation() {
        if (mainController != null) mainController.showTaches();
    }

    // ─── Todo item ───────────────────────────────────────────────────────────

    private HBox buildTodoItem(Tache t) {
        HBox row = new HBox(12);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-padding: 8 0 8 0; -fx-border-color: transparent transparent #f3f4f6 transparent; -fx-border-width: 0 0 1 0;");

        VBox info = new VBox(2);
        Label title = new Label(t.getTitre());
        title.setStyle("-fx-font-weight: bold; -fx-font-size: 13px;");
        Label sub = new Label((t.getObjectifTitre() != null ? t.getObjectifTitre() : "") + "  •  " + t.getDuree() + " min");
        sub.setStyle("-fx-font-size: 11px; -fx-text-fill: #9ca3af;");
        info.getChildren().addAll(title, sub);

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Label badge = new Label(t.getPriorite());
        badge.getStyleClass().add(prioriteBadge(t.getPriorite()));

        row.getChildren().addAll(info, spacer, badge);
        return row;
    }

    // ─── Citation motivante ──────────────────────────────────────────────────

    private void loadCitation() {
        if (lblCitation == null) return;
        lblCitation.setText("Chargement...");
        lblAuteurCitation.setText("");

        Thread t = new Thread(() -> {
            CitationService.Citation c = citationService.getCitationDuJour();
            Platform.runLater(() -> {
                lblCitation.setText("\" " + c.texte + " \"");
                lblAuteurCitation.setText("— " + c.auteur);
            });
        });
        t.setDaemon(true);
        t.start();
    }

    @FXML
    public void handleNouvellecitation() {
        loadCitation();
    }

    // ─── Score + Badges ──────────────────────────────────────────────────────

    private void loadScoreEtBadges(int userId) {
        ScoreResult score = scoreService.calculerScore(userId);
        List<Badge> badges = badgeService.getBadges(userId);
        long obtenus = badgeService.countObtained(badges);

        if (lblScore != null) {
            lblScore.setText(score.score + "%");
            lblScore.setStyle("-fx-font-size: 28px; -fx-font-weight: bold; -fx-text-fill: " + score.couleur + ";");
        }
        if (lblScoreNiveau != null)  lblScoreNiveau.setText(score.niveau);
        if (lblScoreMessage != null) lblScoreMessage.setText(score.message);
        if (progressScore != null)   progressScore.setProgress(score.score / 100.0);

        if (badgesBox != null) {
            badgesBox.getChildren().clear();
            for (Badge b : badges) {
                Label lbl = new Label(b.emoji);
                lbl.setStyle("-fx-font-size: 22px; -fx-opacity: " + (b.obtenu ? "1.0" : "0.25") + ";");
                Tooltip tip = new Tooltip(b.titre + "\n" + b.description + (b.obtenu ? " ✓" : " (non obtenu)"));
                Tooltip.install(lbl, tip);
                badgesBox.getChildren().add(lbl);
            }
        }
    }

    @FXML
    public void handleGenererRapport() {
        javafx.stage.FileChooser fc = new javafx.stage.FileChooser();
        fc.setTitle("Enregistrer le rapport PDF");
        fc.setInitialFileName("rapport_progression.pdf");
        fc.getExtensionFilters().add(new javafx.stage.FileChooser.ExtensionFilter("PDF", "*.pdf"));
        java.io.File file = fc.showSaveDialog(rappelsBox.getScene().getWindow());
        if (file == null) return;

        int userId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 1;
        String email = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getEmail() : "Étudiant";

        try {
            new RapportPdfService().generer(userId, email, file);
            Alert a = new Alert(Alert.AlertType.INFORMATION);
            a.setTitle("Succès"); a.setHeaderText(null);
            a.setContentText("Rapport PDF généré avec succès !");
            a.showAndWait();
        } catch (Exception e) {
            Alert a = new Alert(Alert.AlertType.ERROR);
            a.setContentText("Erreur : " + e.getMessage());
            a.showAndWait();
        }
    }

    @FXML public void goToNewObjectif() { if (mainController != null) mainController.showObjectifs(); }
    @FXML public void goToTaches()      { if (mainController != null) mainController.showTaches(); }

    private String prioriteBadge(String p) {
        if ("HAUTE".equals(p))   return "badge-danger";
        if ("MOYENNE".equals(p)) return "badge-warning";
        return "badge-secondary";
    }
}
