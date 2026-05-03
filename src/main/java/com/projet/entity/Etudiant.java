package com.projet.entity;

public class Etudiant {
    private int id;
    private String email;

    public Etudiant(int id, String email) {
        this.id = id;
        this.email = email;
    }

    public int getId() { return id; }
    public String getEmail() { return email; }

    public String getInitials() {
        if (email == null || email.isEmpty()) return "ET";
        return email.substring(0, Math.min(2, email.length())).toUpperCase();
    }
}
