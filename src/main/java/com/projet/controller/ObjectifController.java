package com.projet.controller;

import com.projet.entity.Objectif;
import com.projet.service.AuthService;
import com.projet.service.ObjectifService;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;

import java.sql.Date;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.stream.Collectors;

public class ObjectifController implements NavigationAware {

    @FXML private TableView<Objectif> tableObjectifs;
    @FXML private TableColumn<Objectif, String> colTitre;
    @FXML private TableColumn<Objectif, String> colDescription;
    @FXML private TableColumn<Objectif, String> colDateDebut;
    @FXML private TableColumn<Objectif, String> colDateFin;
    @FXML private TableColumn<Objectif, String> colStatut;
    @FXML private TableColumn<Objectif, Void>   colActions;

    @FXML private TextField   txtRecherche;
    @FXML private TextField   txtTitre;
    @FXML private TextArea    txtDescription;
    @FXML private DatePicker  dpDateDebut;
    @FXML private DatePicker  dpDateFin;
    @FXML private ComboBox<String> cbStatut;
    @FXML private Button      btnSave;
    @FXML private Label       lblFormTitle;

    // Labels d'erreur inline
    @FXML private Label errTitre;
    @FXML private Label errDateDebut;
    @FXML private Label errDateFin;
    @FXML private Label errStatut;

    private ObjectifService objectifService;
    private Objectif selectedObjectif = null;
    private MainController mainController;

    private static final DateTimeFormatter FMT = DateTimeFormatter.ofPattern("dd/MM/yyyy");

    @FXML
    public void initialize() {
        objectifService = new ObjectifService();
        cbStatut.setItems(FXCollections.observableArrayList("EN_COURS", "TERMINE", "ANNULE", "EN_RETARD"));
        cbStatut.getSelectionModel().selectFirst();
        setupColumns();

        // Debug : afficher tous les objectifs sans filtre
        System.out.println("[ObjectifController.initialize] Tous les objectifs en DB:");
        objectifService.findAll().forEach(o ->
            System.out.println("  id=" + o.getId() + " titre=" + o.getTitre() + " etudiantId=" + o.getEtudiantId())
        );

        System.out.println("[ObjectifController.initialize] CurrentUser: " +
            (AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() + " / " + AuthService.getCurrentUser().getEmail() : "NULL"));

        loadData();
        txtRecherche.textProperty().addListener((obs, o, n) -> filterData(n));
        txtTitre.textProperty().addListener((o, a, b) -> clearError(txtTitre, errTitre));
        dpDateDebut.valueProperty().addListener((o, a, b) -> clearError(dpDateDebut, errDateDebut));
        dpDateFin.valueProperty().addListener((o, a, b)   -> clearError(dpDateFin,   errDateFin));
        cbStatut.valueProperty().addListener((o, a, b)    -> clearError(cbStatut,    errStatut));
    }

    @Override
    public void setMainController(MainController mc) {
        this.mainController = mc;
        // Recharger maintenant que getCurrentUser() est disponible
        loadData();
    }

    // ─── Colonnes ────────────────────────────────────────────────────────────

    private void setupColumns() {
        colTitre.setCellValueFactory(new PropertyValueFactory<>("titre"));
        colDescription.setCellValueFactory(c -> new SimpleStringProperty(
            c.getValue().getDescription() != null ? c.getValue().getDescription() : "—"));
        colDateDebut.setCellValueFactory(c -> new SimpleStringProperty(
            c.getValue().getDateDebut() != null ? c.getValue().getDateDebut().toLocalDate().format(FMT) : ""));
        colDateFin.setCellValueFactory(c -> new SimpleStringProperty(
            c.getValue().getDateFin() != null ? c.getValue().getDateFin().toLocalDate().format(FMT) : ""));
        colStatut.setCellValueFactory(new PropertyValueFactory<>("statut"));

        colStatut.setCellFactory(col -> new TableCell<>() {
            @Override protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setGraphic(null); return; }
                Label b = new Label(item);
                b.getStyleClass().add(statutBadge(item));
                setGraphic(b); setText(null);
            }
        });

        colActions.setCellFactory(col -> new TableCell<>() {
            final Button btnEdit   = new Button("✏ Modifier");
            final Button btnDelete = new Button("🗑 Supprimer");
            final HBox   box       = new HBox(6, btnEdit, btnDelete);
            {
                btnEdit.getStyleClass().add("btn-warning");
                btnDelete.getStyleClass().add("btn-danger");
                btnEdit.setOnAction(e   -> fillForm(getTableView().getItems().get(getIndex())));
                btnDelete.setOnAction(e -> confirmDelete(getTableView().getItems().get(getIndex())));
            }
            @Override protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : box);
            }
        });
    }

    // ─── Données ─────────────────────────────────────────────────────────────

    private void loadData() {
        int userId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 0;
        System.out.println("[ObjectifController.loadData] userId=" + userId);
        if (userId == 0) {
            tableObjectifs.setItems(FXCollections.observableArrayList(objectifService.findAll()));
        } else {
            tableObjectifs.setItems(FXCollections.observableArrayList(
                objectifService.findByEtudiantId(userId)));
        }
    }

    private void filterData(String kw) {
        if (kw == null || kw.trim().isEmpty()) { loadData(); return; }
        int userId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 0;
        String lower = kw.toLowerCase();
        List<Objectif> source = userId == 0
            ? objectifService.findAll()
            : objectifService.findByEtudiantId(userId);
        List<Objectif> filtered = source.stream()
            .filter(o -> (o.getTitre() != null && o.getTitre().toLowerCase().contains(lower))
                      || (o.getDescription() != null && o.getDescription().toLowerCase().contains(lower)))
            .collect(Collectors.toList());
        tableObjectifs.setItems(FXCollections.observableArrayList(filtered));
    }

    // ─── Formulaire ──────────────────────────────────────────────────────────

    private void fillForm(Objectif o) {
        selectedObjectif = o;
        lblFormTitle.setText("Modifier l'objectif");
        btnSave.setText("Mettre à jour");
        txtTitre.setText(o.getTitre());
        txtDescription.setText(o.getDescription() != null ? o.getDescription() : "");
        dpDateDebut.setValue(o.getDateDebut() != null ? o.getDateDebut().toLocalDate() : null);
        dpDateFin.setValue(o.getDateFin()   != null ? o.getDateFin().toLocalDate()   : null);
        cbStatut.setValue(o.getStatut());
        clearAllErrors();
    }

    @FXML public void handleAjouter() { clearForm(); }

    @FXML
    public void handleSave() {
        System.out.println("=== handleSave OBJECTIF appelé ===");
        System.out.println("Titre saisi: '" + txtTitre.getText() + "'");
        System.out.println("DateDebut: " + dpDateDebut.getValue());
        System.out.println("DateFin: " + dpDateFin.getValue());
        System.out.println("Statut: " + cbStatut.getValue());
        System.out.println("selectedObjectif: " + selectedObjectif);

        if (!validateInput()) {
            System.out.println("Validation ÉCHOUÉE");
            return;
        }
        System.out.println("Validation OK");

        String titre      = txtTitre.getText().trim();
        int etudiantId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 1;
        System.out.println("etudiantId: " + etudiantId);

        if (selectedObjectif == null) {
            // Contrôle unicité
            boolean exists = objectifService.existsByTitreAndEtudiant(titre, etudiantId, null);
            System.out.println("Unicité check: " + exists);
            if (exists) {
                markError(txtTitre, errTitre, "Un objectif avec ce titre existe déjà.");
                return;
            }
            Objectif o = new Objectif(null, titre,
                txtDescription.getText().trim(),
                Date.valueOf(dpDateDebut.getValue()),
                Date.valueOf(dpDateFin.getValue()),
                cbStatut.getValue(), etudiantId);
            objectifService.create(o);
            showSuccess("Objectif ajouté avec succès !");
        } else {
            if (objectifService.existsByTitreAndEtudiant(titre, etudiantId, selectedObjectif.getId())) {
                markError(txtTitre, errTitre, "Un objectif avec ce titre existe déjà.");
                return;
            }
            selectedObjectif.setTitre(titre);
            selectedObjectif.setDescription(txtDescription.getText().trim());
            selectedObjectif.setDateDebut(Date.valueOf(dpDateDebut.getValue()));
            selectedObjectif.setDateFin(Date.valueOf(dpDateFin.getValue()));
            selectedObjectif.setStatut(cbStatut.getValue());
            objectifService.update(selectedObjectif);
            showSuccess("Objectif modifié avec succès !");
        }
        loadData();
        clearForm();
    }

    @FXML
    public void clearForm() {
        selectedObjectif = null;
        lblFormTitle.setText("Nouvel Objectif");
        btnSave.setText("Enregistrer");
        txtTitre.clear();
        txtDescription.clear();
        dpDateDebut.setValue(null);
        dpDateFin.setValue(null);
        cbStatut.getSelectionModel().selectFirst();
        clearAllErrors();
    }

    private void confirmDelete(Objectif o) {
        Alert a = new Alert(Alert.AlertType.CONFIRMATION);
        a.setTitle("Confirmation de suppression");
        a.setHeaderText("Supprimer l'objectif : " + o.getTitre());
        a.setContentText("Cette action supprimera aussi toutes les tâches associées. Continuer ?");
        a.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                objectifService.delete(o.getId());
                if (selectedObjectif != null && selectedObjectif.getId().equals(o.getId())) clearForm();
                loadData();
                showSuccess("Objectif supprimé.");
            }
        });
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    private boolean validateInput() {
        clearAllErrors();
        boolean valid = true;

        // Titre
        if (txtTitre.getText() == null || txtTitre.getText().trim().isEmpty()) {
            markError(txtTitre, errTitre, "Le titre est obligatoire."); valid = false;
        } else if (txtTitre.getText().trim().length() < 3) {
            markError(txtTitre, errTitre, "Le titre doit contenir au moins 3 caractères."); valid = false;
        } else if (txtTitre.getText().trim().length() > 255) {
            markError(txtTitre, errTitre, "Le titre ne doit pas dépasser 255 caractères."); valid = false;
        }

        LocalDate today = LocalDate.now();

        // Date début
        if (dpDateDebut.getValue() == null) {
            markError(dpDateDebut, errDateDebut, "La date de début est obligatoire."); valid = false;
        } else if (dpDateDebut.getValue().isBefore(today)) {
            markError(dpDateDebut, errDateDebut, "La date de début doit être supérieure ou égale à la date d'aujourd'hui (" + today.format(FMT) + ")."); valid = false;
        }

        // Date fin
        if (dpDateFin.getValue() == null) {
            markError(dpDateFin, errDateFin, "La date de fin est obligatoire."); valid = false;
        } else if (dpDateDebut.getValue() != null && !dpDateDebut.getValue().isBefore(today)) {
            // Vérifier seulement si date début est valide
            if (!dpDateFin.getValue().isAfter(dpDateDebut.getValue())) {
                markError(dpDateFin, errDateFin, "La date de fin doit être strictement supérieure à la date de début."); valid = false;
            }
        }

        // Statut
        if (cbStatut.getValue() == null) {
            markError(cbStatut, errStatut, "Le statut est obligatoire."); valid = false;
        }

        return valid;
    }

    // ─── Helpers visuels ─────────────────────────────────────────────────────

    private void markError(Control field, Label errLabel, String msg) {
        field.setStyle("-fx-border-color: #ef4444; -fx-border-width: 1.5; -fx-border-radius: 8; -fx-background-radius: 8;");
        if (errLabel != null) { errLabel.setText("⚠ " + msg); errLabel.setVisible(true); }
    }

    private void clearError(Control field, Label errLabel) {
        field.setStyle("");
        if (errLabel != null) { errLabel.setText(""); errLabel.setVisible(false); }
    }

    private void clearAllErrors() {
        clearError(txtTitre,    errTitre);
        clearError(dpDateDebut, errDateDebut);
        clearError(dpDateFin,   errDateFin);
        clearError(cbStatut,    errStatut);
    }

    private void showSuccess(String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle("Succès"); a.setHeaderText(null); a.setContentText(msg);
        a.showAndWait();
    }

    private String statutBadge(String s) {
        return switch (s) {
            case "TERMINE"   -> "badge-success";
            case "EN_COURS"  -> "badge-primary";
            case "ANNULE"    -> "badge-danger";
            case "EN_RETARD" -> "badge-danger";
            default          -> "badge-secondary";
        };
    }
}
