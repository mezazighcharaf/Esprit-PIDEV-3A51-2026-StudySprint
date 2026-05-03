package com.projet.entity;

import java.sql.Date;
import java.util.ArrayList;
import java.util.List;

public class Objectif {
    private Integer id;
    private String titre;
    private String description;
    private Date dateDebut;
    private Date dateFin;
    private String statut; // EN_COURS, TERMINE, ANNULE
    private Integer etudiantId;
    private List<Tache> taches = new ArrayList<>();

    public Objectif() {}

    public Objectif(Integer id, String titre, String description, Date dateDebut, Date dateFin, String statut, Integer etudiantId) {
        this.id = id;
        this.titre = titre;
        this.description = description;
        this.dateDebut = dateDebut;
        this.dateFin = dateFin;
        this.statut = statut;
        this.etudiantId = etudiantId;
    }

    public Integer getId() { return id; }
    public void setId(Integer id) { this.id = id; }
    public String getTitre() { return titre; }
    public void setTitre(String titre) { this.titre = titre; }
    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
    public Date getDateDebut() { return dateDebut; }
    public void setDateDebut(Date dateDebut) { this.dateDebut = dateDebut; }
    public Date getDateFin() { return dateFin; }
    public void setDateFin(Date dateFin) { this.dateFin = dateFin; }
    public String getStatut() { return statut; }
    public void setStatut(String statut) { this.statut = statut; }
    public Integer getEtudiantId() { return etudiantId; }
    public void setEtudiantId(Integer etudiantId) { this.etudiantId = etudiantId; }
    public List<Tache> getTaches() { return taches; }
    public void setTaches(List<Tache> taches) { this.taches = taches; }

    public int getCompletedTachesCount() {
        return (int) taches.stream().filter(t -> "TERMINE".equals(t.getStatut())).count();
    }

    public double getProgressPercent() {
        if (taches.isEmpty()) return 0;
        return (double) getCompletedTachesCount() / taches.size() * 100;
    }

    @Override
    public String toString() { return titre != null ? titre : ""; }
}
