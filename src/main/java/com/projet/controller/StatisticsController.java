package com.projet.controller;

import com.projet.entity.Tache;
import com.projet.service.TacheService;
import com.projet.service.AuthService;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.chart.BarChart;
import javafx.scene.chart.PieChart;
import javafx.scene.chart.XYChart;
import javafx.scene.control.Label;

import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

public class StatisticsController implements NavigationAware {

    @FXML private PieChart pieChartStatut;
    @FXML private BarChart<String, Number> barChartPriorite;
    @FXML private Label lblTotal;
    @FXML private Label lblTermine;
    @FXML private Label lblEnCours;
    @FXML private Label lblAFaire;

    private TacheService tacheService;
    private MainController mainController;

    @FXML
    public void initialize() {
        tacheService = new TacheService();
        // loadStatistics() sera appelé dans setMainController()
    }

    @Override
    public void setMainController(MainController mc) {
        this.mainController = mc;
        loadStatistics();
    }

    @FXML
    public void loadStatistics() {
        int userId = AuthService.getCurrentUser() != null ? AuthService.getCurrentUser().getId() : 0;
        List<Tache> taches = userId == 0
            ? tacheService.findAll()
            : tacheService.findByEtudiantId(userId);

        long total = taches.size();
        long termine = taches.stream().filter(t -> "TERMINE".equals(t.getStatut())).count();
        long enCours = taches.stream().filter(t -> "EN_COURS".equals(t.getStatut())).count();
        long aFaire = taches.stream().filter(t -> "A_FAIRE".equals(t.getStatut())).count();

        lblTotal.setText(String.valueOf(total));
        lblTermine.setText(String.valueOf(termine));
        lblEnCours.setText(String.valueOf(enCours));
        lblAFaire.setText(String.valueOf(aFaire));

        // Pie Chart
        Map<String, Long> statusCount = taches.stream()
            .collect(Collectors.groupingBy(t -> t.getStatut() != null ? t.getStatut() : "INCONNU", Collectors.counting()));

        ObservableList<PieChart.Data> pieData = FXCollections.observableArrayList();
        statusCount.forEach((s, c) -> pieData.add(new PieChart.Data(s + " (" + c + ")", c)));
        pieChartStatut.setData(pieData);

        // Bar Chart
        Map<String, Long> prioriteCount = taches.stream()
            .collect(Collectors.groupingBy(t -> t.getPriorite() != null ? t.getPriorite() : "INCONNU", Collectors.counting()));

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Tâches");
        // Order: BASSE, MOYENNE, HAUTE
        for (String p : new String[]{"BASSE", "MOYENNE", "HAUTE"}) {
            series.getData().add(new XYChart.Data<>(p, prioriteCount.getOrDefault(p, 0L)));
        }
        barChartPriorite.getData().clear();
        barChartPriorite.getData().add(series);
    }
}
