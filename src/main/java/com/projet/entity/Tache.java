package com.projet.entity;

public class Tache {
    private Integer id;
    private String titre;
    private Integer duree; // en minutes
    private String priorite; // BASSE, MOYENNE, HAUTE
    private String statut;   // A_FAIRE, EN_COURS, TERMINE
    private Integer objectifId;
    private String objectifTitre; // for display

    public Tache() {}

    public Tache(Integer id, String titre, Integer duree, String priorite, String statut, Integer objectifId) {
        this.id = id;
        this.titre = titre;
        this.duree = duree;
        this.priorite = priorite;
        this.statut = statut;
        this.objectifId = objectifId;
    }

    public Integer getId() { return id; }
    public void setId(Integer id) { this.id = id; }
    public String getTitre() { return titre; }
    public void setTitre(String titre) { this.titre = titre; }
    public Integer getDuree() { return duree; }
    public void setDuree(Integer duree) { this.duree = duree; }
    public String getPriorite() { return priorite; }
    public void setPriorite(String priorite) { this.priorite = priorite; }
    public String getStatut() { return statut; }
    public void setStatut(String statut) { this.statut = statut; }
    public Integer getObjectifId() { return objectifId; }
    public void setObjectifId(Integer objectifId) { this.objectifId = objectifId; }
    public String getObjectifTitre() { return objectifTitre; }
    public void setObjectifTitre(String objectifTitre) { this.objectifTitre = objectifTitre; }

    @Override
    public String toString() { return titre != null ? titre : ""; }
}
