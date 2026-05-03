package com.projet.controller;

import com.projet.entity.Objectif;
import com.projet.entity.Tache;
import com.projet.service.ObjectifService;
import com.projet.service.TacheService;
import com.projet.service.AuthService;
import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.Paragraph;
import com.itextpdf.layout.element.Table;
import com.itextpdf.layout.properties.UnitValue;
import org.apache.poi.ss.usermodel.*;
import org.apache.poi.xssf.usermodel.XSSFWorkbook;
import java.io.FileOutputStream;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.stage.FileChooser;
import javafx.util.StringConverter;

import java.io.File;
import java.util.List;
import java.util.stream.Collectors;

public class TacheController implements NavigationAware {

    @FXML private TableView<Tache>           tableTaches;
    @FXML private TableColumn<Tache, String> colTitre;
    @FXML private TableColumn<Tache, String> colObjectif;
    @FXML private TableColumn<Tache, String> colDuree;
    @FXML private TableColumn<Tache, String> colPriorite;
    @FXML private TableColumn<Tache, String> colStatut;
    @FXML private TableColumn<Tache, Void>   colActions;

    @FXML private TextField        txtRecherche;
    @FXML private TextField        txtTitre;
    @FXML private TextField        txtDuree;
    @FXML private ComboBox<String>  cbPriorite;
    @FXML private ComboBox<String>  cbStatut;
    @FXML private ComboBox<Objectif> cbObjectif;
    @FXML private Button            btnSave;
    @FXML private Label             lblFormTitle;

    // Labels d'erreur inline
    @FXML private Label errTitre;
    @FXML private Label errDuree;
    @FXML private Label errPriorite;
    @FXML private Label errStatut;
    @FXML private Label errObjectif;

    private TacheService    tacheService;
    private ObjectifService objectifService;
    private ObservableList<Objectif> objectifsList;
    private Tache           selectedTache = null;
    private MainController  mainController;

    @FXML
    public void initialize() {
        tacheService    = new TacheService();
        objectifService = new ObjectifService();

        cbPriorite.setItems(FXCollections.observableArrayList("BASSE", "MOYENNE", "HAUTE"));
        cbPriorite.getSelectionModel().selectFirst();
        cbStatut.setItems(FXCollections.observableArrayList("A_FAIRE", "EN_COURS", "TERMINE"));
        cbStatut.getSelectionModel().selectFirst();

        objectifsList = FXCollections.observableArrayList();
        cbObjectif.setItems(objectifsList);
        cbObjectif.setConverter(new StringConverter<>() {
            @Override public String toString(Objectif o)    { return o == null ? "" : o.getTitre(); }
            @Override public Objectif fromString(String s)  { return null; }
        });

        setupColumns();
        // loadData() sera appelé dans setMainController()

        txtRecherche.textProperty().addListener((obs, o, n) -> filterData(n));

        // Effacer erreurs à la saisie
        txtTitre.textProperty().addListener((o, a, b)    -> clearError(txtTitre,    errTitre));
        txtDuree.textProperty().addListener((o, a, b)    -> clearError(txtDuree,    errDuree));
        cbPriorite.valueProperty().addListener((o, a, b) -> clearError(cbPriorite,  errPriorite));
        cbStatut.valueProperty().addListener((o, a, b)   -> clearError(cbStatut,    errStatut));
        cbObjectif.valueProperty().addListener((o, a, b) -> clearError(cbObjectif,  errObjectif));
    }

    @Override
    public void setMainController(MainController mc) {
        this.mainController = mc;
        loadData();
    }

    // ─── Colonnes ────────────────────────────────────────────────────────────

    private void setupColumns() {
        colTitre.setCellValueFactory(new PropertyValueFactory<>("titre"));
        colObjectif.setCellValueFactory(c -> new SimpleStringProperty(
            c.getValue().getObjectifTitre() != null ? c.getValue().getObjectifTitre() : ""));
        colDuree.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().getDuree() + " min"));
        colPriorite.setCellValueFactory(new PropertyValueFactory<>("priorite"));
        colStatut.setCellValueFactory(new PropertyValueFactory<>("statut"));

        colPriorite.setCellFactory(col -> new TableCell<>() {
            @Override protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setGraphic(null); return; }
                Label b = new Label(item); b.getStyleClass().add(prioriteBadge(item));
                setGraphic(b); setText(null);
            }
        });

        colStatut.setCellFactory(col -> new TableCell<>() {
            @Override protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setGraphic(null); return; }
                Label b = new Label(item); b.getStyleClass().add(statutBadge(item));
                setGraphic(b); setText(null);
            }
        });

        colObjectif.setCellFactory(col -> new TableCell<>() {
            @Override protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setGraphic(null); return; }
                Label b = new Label(item); b.getStyleClass().add("badge-info");
                setGraphic(b); setText(null);
            }
        });

        colActions.setCellFactory(col -> new TableCell<>() {
            final Button btnToggle = new Button("✓");
            final Button btnEdit   = new Button("✏ Modifier");
            final Button btnDelete = new Button("🗑 Supprimer");
            final HBox   box       = new HBox(4, btnToggle, btnEdit, btnDelete);
            {
                btnEdit.getStyleClass().add("btn-warning");
                btnDelete.getStyleClass().add("btn-danger");
                btnEdit.setOnAction(e   -> fillForm(getTableView().getItems().get(getIndex())));
                btnDelete.setOnAction(e -> confirmDelete(getTableView().getItems().get(getIndex())));
                btnToggle.setOnAction(e -> toggleStatut(getTableView().getItems().get(getIndex())));
            }
            @Override protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (!empty && getIndex() < getTableView().getItems().size()) {
                    Tache t = getTableView().getItems().get(getIndex());
                    if ("TERMINE".equals(t.getStatut())) {
                        btnToggle.setText("↩"); btnToggle.getStyleClass().setAll("btn-secondary");
                    } else {
                        btnToggle.setText("✓"); btnToggle.getStyleClass().setAll("btn-success");
                    }
                }
                setGraphic(empty ? null : box);
            }
        });
    }

    // ─── Données ─────────────────────────────────────────────────────────────

    private void loadData() {
        int userId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 0;
        System.out.println("[TacheController.loadData] userId=" + userId);
        List<Tache> list = userId == 0
            ? tacheService.findAll()
            : tacheService.findByEtudiantId(userId);
        tableTaches.setItems(FXCollections.observableArrayList(list));
        List<Objectif> objList = userId == 0
            ? objectifService.findAll()
            : objectifService.findByEtudiantId(userId);
        objectifsList.setAll(objList);
    }

    private void filterData(String kw) {
        if (kw == null || kw.trim().isEmpty()) { loadData(); return; }
        int userId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 0;
        String lower = kw.toLowerCase();
        List<Tache> source = userId == 0
            ? tacheService.findAll()
            : tacheService.findByEtudiantId(userId);
        List<Tache> filtered = source.stream()
            .filter(t -> (t.getTitre() != null && t.getTitre().toLowerCase().contains(lower))
                      || (t.getObjectifTitre() != null && t.getObjectifTitre().toLowerCase().contains(lower)))
            .collect(Collectors.toList());
        tableTaches.setItems(FXCollections.observableArrayList(filtered));
    }

    // ─── Formulaire ──────────────────────────────────────────────────────────

    private void fillForm(Tache t) {
        selectedTache = t;
        lblFormTitle.setText("Modifier la tâche");
        btnSave.setText("Mettre à jour");
        txtTitre.setText(t.getTitre());
        txtDuree.setText(String.valueOf(t.getDuree()));
        cbPriorite.setValue(t.getPriorite());
        cbStatut.setValue(t.getStatut());
        cbObjectif.setValue(objectifsList.stream()
            .filter(o -> o.getId().equals(t.getObjectifId()))
            .findFirst().orElse(null));
        clearAllErrors();
    }

    private void toggleStatut(Tache t) {
        t.setStatut("TERMINE".equals(t.getStatut()) ? "EN_COURS" : "TERMINE");
        tacheService.update(t);
        loadData();
    }

    @FXML public void handleAjouter() { clearForm(); }

    @FXML
    public void handleSave() {
        if (!validateInput()) return;

        String titre      = txtTitre.getText().trim();
        int    duree      = Integer.parseInt(txtDuree.getText().trim());
        int    objectifId = cbObjectif.getValue().getId();

        if (selectedTache == null) {
            // Unicité : même titre dans le même objectif
            if (tacheService.existsByTitreAndObjectif(titre, objectifId, null)) {
                markError(txtTitre, errTitre, "Une tâche avec ce titre existe déjà dans cet objectif.");
                return;
            }
            tacheService.create(new Tache(null, titre, duree,
                cbPriorite.getValue(), cbStatut.getValue(), objectifId));
            showSuccess("Tâche ajoutée avec succès !");
        } else {
            if (tacheService.existsByTitreAndObjectif(titre, objectifId, selectedTache.getId())) {
                markError(txtTitre, errTitre, "Une tâche avec ce titre existe déjà dans cet objectif.");
                return;
            }
            selectedTache.setTitre(titre);
            selectedTache.setDuree(duree);
            selectedTache.setPriorite(cbPriorite.getValue());
            selectedTache.setStatut(cbStatut.getValue());
            selectedTache.setObjectifId(objectifId);
            tacheService.update(selectedTache);
            showSuccess("Tâche modifiée avec succès !");
        }
        loadData();
        clearForm();
    }

    @FXML
    public void clearForm() {
        selectedTache = null;
        lblFormTitle.setText("Nouvelle Tâche");
        btnSave.setText("Enregistrer");
        txtTitre.clear();
        txtDuree.clear();
        cbPriorite.getSelectionModel().selectFirst();
        cbStatut.getSelectionModel().selectFirst();
        cbObjectif.getSelectionModel().clearSelection();
        clearAllErrors();
    }

    private void confirmDelete(Tache t) {
        Alert a = new Alert(Alert.AlertType.CONFIRMATION);
        a.setTitle("Confirmation de suppression");
        a.setHeaderText("Supprimer la tâche : " + t.getTitre());
        a.setContentText("Cette action est irréversible. Continuer ?");
        a.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                tacheService.delete(t.getId());
                if (selectedTache != null && selectedTache.getId().equals(t.getId())) clearForm();
                loadData();
                showSuccess("Tâche supprimée.");
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
        } else if (txtTitre.getText().trim().length() < 2) {
            markError(txtTitre, errTitre, "Le titre doit contenir au moins 2 caractères."); valid = false;
        } else if (txtTitre.getText().trim().length() > 255) {
            markError(txtTitre, errTitre, "Le titre ne doit pas dépasser 255 caractères."); valid = false;
        }

        // Durée
        if (txtDuree.getText() == null || txtDuree.getText().trim().isEmpty()) {
            markError(txtDuree, errDuree, "La durée est obligatoire."); valid = false;
        } else {
            try {
                int d = Integer.parseInt(txtDuree.getText().trim());
                if (d <= 0)    { markError(txtDuree, errDuree, "La durée doit être supérieure à 0."); valid = false; }
                else if (d > 1440) { markError(txtDuree, errDuree, "La durée ne peut pas dépasser 1440 min (24h)."); valid = false; }
            } catch (NumberFormatException e) {
                markError(txtDuree, errDuree, "La durée doit être un nombre entier."); valid = false;
            }
        }

        // Priorité
        if (cbPriorite.getValue() == null) {
            markError(cbPriorite, errPriorite, "La priorité est obligatoire."); valid = false;
        }

        // Statut
        if (cbStatut.getValue() == null) {
            markError(cbStatut, errStatut, "Le statut est obligatoire."); valid = false;
        }

        // Objectif
        if (cbObjectif.getValue() == null) {
            markError(cbObjectif, errObjectif, "Veuillez sélectionner un objectif."); valid = false;
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
        clearError(txtTitre,   errTitre);
        clearError(txtDuree,   errDuree);
        clearError(cbPriorite, errPriorite);
        clearError(cbStatut,   errStatut);
        clearError(cbObjectif, errObjectif);
    }

    private void showSuccess(String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle("Succès"); a.setHeaderText(null); a.setContentText(msg);
        a.showAndWait();
    }

    // ─── Export PDF ──────────────────────────────────────────────────────────

    @FXML
    public void handleExportPDF() {
        FileChooser fc = new FileChooser();
        fc.setTitle("Exporter les tâches en PDF");
        fc.setInitialFileName("liste_taches.pdf");
        fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("PDF", "*.pdf"));
        File file = fc.showSaveDialog(tableTaches.getScene().getWindow());
        if (file == null) return;

        try {
            Document doc = new Document(new PdfDocument(new PdfWriter(file.getAbsolutePath())));
            doc.add(new Paragraph("Liste des Tâches — StudySprint").setBold().setFontSize(18).setMarginBottom(16));

            Table table = new Table(new float[]{3, 2, 1, 1, 1});
            table.setWidth(UnitValue.createPercentValue(100));
            for (String h : new String[]{"Titre", "Objectif", "Durée (min)", "Priorité", "Statut"})
                table.addHeaderCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(h).setBold()));

            for (Tache t : tacheService.findAll()) {
                table.addCell(t.getTitre() != null ? t.getTitre() : "");
                table.addCell(t.getObjectifTitre() != null ? t.getObjectifTitre() : "");
                table.addCell(String.valueOf(t.getDuree()));
                table.addCell(t.getPriorite() != null ? t.getPriorite() : "");
                table.addCell(t.getStatut()   != null ? t.getStatut()   : "");
            }
            doc.add(table);
            doc.close();
            showSuccess("PDF exporté avec succès !");
        } catch (Exception e) {
            Alert err = new Alert(Alert.AlertType.ERROR);
            err.setContentText("Erreur PDF : " + e.getMessage());
            err.showAndWait();
        }
    }

    // ─── Export Excel ────────────────────────────────────────────────────────

    @FXML
    public void handleExportExcel() {
        FileChooser fc = new FileChooser();
        fc.setTitle("Exporter les tâches en Excel");
        fc.setInitialFileName("liste_taches.xlsx");
        fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("Excel", "*.xlsx"));
        File file = fc.showSaveDialog(tableTaches.getScene().getWindow());
        if (file == null) return;

        try (Workbook workbook = new XSSFWorkbook();
             FileOutputStream fileOut = new FileOutputStream(file)) {

            Sheet sheet = workbook.createSheet("Tâches");

            // Création de l'en-tête
            Row headerRow = sheet.createRow(0);
            String[] columns = {"Titre", "Objectif", "Durée (min)", "Priorité", "Statut"};
            
            CellStyle headerStyle = workbook.createCellStyle();
            Font headerFont = workbook.createFont();
            headerFont.setBold(true);
            headerStyle.setFont(headerFont);

            for (int i = 0; i < columns.length; i++) {
                org.apache.poi.ss.usermodel.Cell cell = headerRow.createCell(i);
                cell.setCellValue(columns[i]);
                cell.setCellStyle(headerStyle);
            }

            // Remplissage des données
            int rowNum = 1;
            for (Tache t : tacheService.findAll()) {
                Row row = sheet.createRow(rowNum++);
                row.createCell(0).setCellValue(t.getTitre() != null ? t.getTitre() : "");
                row.createCell(1).setCellValue(t.getObjectifTitre() != null ? t.getObjectifTitre() : "");
                row.createCell(2).setCellValue(t.getDuree());
                row.createCell(3).setCellValue(t.getPriorite() != null ? t.getPriorite() : "");
                row.createCell(4).setCellValue(t.getStatut() != null ? t.getStatut() : "");
            }

            // Ajustement automatique de la taille des colonnes
            for (int i = 0; i < columns.length; i++) {
                sheet.autoSizeColumn(i);
            }

            workbook.write(fileOut);
            showSuccess("Excel exporté avec succès !");
            
        } catch (Exception e) {
            Alert err = new Alert(Alert.AlertType.ERROR);
            err.setContentText("Erreur Excel : " + e.getMessage());
            err.showAndWait();
        }
    }

    private String prioriteBadge(String p) {
        return switch (p) {
            case "HAUTE"   -> "badge-danger";
            case "MOYENNE" -> "badge-warning";
            default        -> "badge-secondary";
        };
    }

    private String statutBadge(String s) {
        return switch (s) {
            case "TERMINE"  -> "badge-success";
            case "EN_COURS" -> "badge-primary";
            default         -> "badge-secondary";
        };
    }
}
