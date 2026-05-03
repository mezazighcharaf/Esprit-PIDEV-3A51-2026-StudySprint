package com.projet.service;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

/**
 * Service d'offres d'emploi via l'API Remotive (100% gratuite, sans clé API).
 * Documentation : https://remotive.com/api/remote-jobs
 */
public class OffresEmploiService {

    // API Remotive — gratuite, sans inscription, sans clé
    private static final String BASE_URL = "https://remotive.com/api/remote-jobs";

    public static class Offre {
        public final String titre;
        public final String entreprise;
        public final String lieu;
        public final String description;
        public final String salaire;
        public final String url;
        public final String datePublication;
        public final String categorie;

        public Offre(String titre, String entreprise, String lieu,
                     String description, String salaire, String url,
                     String datePublication, String categorie) {
            this.titre           = titre;
            this.entreprise      = entreprise;
            this.lieu            = lieu;
            this.description     = description;
            this.salaire         = salaire;
            this.url             = url;
            this.datePublication = datePublication;
            this.categorie       = categorie;
        }
    }

    /**
     * Recherche des offres d'emploi par mot-clé via Remotive API.
     */
    public List<Offre> rechercher(String keyword, int page) {
        List<Offre> offres = new ArrayList<>();
        try {
            String encodedKeyword = URLEncoder.encode(keyword, StandardCharsets.UTF_8);
            String urlStr = BASE_URL + "?search=" + encodedKeyword + "&limit=10";

            System.out.println("[OffresEmploiService] Appel API: " + urlStr);

            HttpURLConnection conn = (HttpURLConnection) new URL(urlStr).openConnection();
            conn.setRequestMethod("GET");
            conn.setConnectTimeout(8000);
            conn.setReadTimeout(8000);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("User-Agent", "StudySprint/1.0");

            int status = conn.getResponseCode();
            System.out.println("[OffresEmploiService] HTTP Status: " + status);

            if (status == 200) {
                BufferedReader reader = new BufferedReader(
                    new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) sb.append(line);
                reader.close();

                JSONObject json    = new JSONObject(sb.toString());
                JSONArray  jobs    = json.optJSONArray("jobs");

                if (jobs != null && jobs.length() > 0) {
                    System.out.println("[OffresEmploiService] " + jobs.length() + " offres trouvées.");
                    for (int i = 0; i < jobs.length(); i++) {
                        JSONObject job = jobs.getJSONObject(i);

                        String titre      = job.optString("title", "N/A");
                        String entreprise = job.optString("company_name", "N/A");
                        String lieu       = job.optString("candidate_required_location", "Remote");
                        String salaire    = job.optString("salary", "Non précisé");
                        String url2       = job.optString("url", "#");
                        String categorie  = job.optString("category", "");
                        String date       = job.optString("publication_date", "");
                        if (date.length() >= 10) date = date.substring(0, 10);

                        // Nettoyer la description HTML
                        String desc = job.optString("description", "");
                        desc = desc.replaceAll("<[^>]*>", " ")
                                   .replaceAll("&amp;", "&")
                                   .replaceAll("&lt;", "<")
                                   .replaceAll("&gt;", ">")
                                   .replaceAll("&nbsp;", " ")
                                   .replaceAll("\\s+", " ")
                                   .trim();
                        if (desc.length() > 250) desc = desc.substring(0, 250) + "...";

                        if (salaire.isEmpty()) salaire = "Non précisé";

                        offres.add(new Offre(titre, entreprise, lieu, desc,
                                             salaire, url2, date, categorie));
                    }
                } else {
                    System.out.println("[OffresEmploiService] Aucun résultat → démo.");
                    offres.addAll(getDemoOffres(keyword));
                }
            } else {
                System.out.println("[OffresEmploiService] Erreur HTTP " + status + " → démo.");
                offres.addAll(getDemoOffres(keyword));
            }
            conn.disconnect();

        } catch (Exception e) {
            System.err.println("[OffresEmploiService] Exception: " + e.getMessage());
            offres.addAll(getDemoOffres(keyword));
        }
        return offres;
    }

    /**
     * Données de démo si l'API est inaccessible.
     */
    private List<Offre> getDemoOffres(String keyword) {
        System.out.println("[OffresEmploiService] Chargement données démo pour: " + keyword);
        List<Offre> demo = new ArrayList<>();
        demo.add(new Offre(
            "Développeur " + keyword + " — Remote",
            "TechCorp Paris", "France / Remote",
            "Nous recherchons un développeur passionné pour rejoindre notre équipe agile. " +
            "Vous travaillerez sur des projets innovants avec les dernières technologies.",
            "35 000€ - 50 000€", "#", "2026-04-27", "Software Development"));
        demo.add(new Offre(
            "Ingénieur " + keyword + " Senior",
            "InnoSoft Lyon", "Lyon, France",
            "Poste senior avec management d'une équipe de 5 personnes. " +
            "Expérience de 5 ans minimum requise. Environnement international.",
            "55 000€ - 75 000€", "#", "2026-04-26", "Engineering"));
        demo.add(new Offre(
            "Consultant " + keyword,
            "Capgemini", "Paris / Hybride",
            "Mission de conseil en transformation digitale pour grands comptes. " +
            "Déplacements occasionnels en Europe.",
            "45 000€ - 60 000€", "#", "2026-04-25", "Consulting"));
        demo.add(new Offre(
            "Alternant " + keyword,
            "Startup FinTech", "Bordeaux, France",
            "Alternance de 12 mois dans une startup innovante du secteur FinTech. " +
            "Encadrement par des experts et projets concrets dès le premier jour.",
            "1 200€ - 1 500€/mois", "#", "2026-04-24", "Internship"));
        demo.add(new Offre(
            "Lead " + keyword,
            "Orange Digital Center", "Marseille, France",
            "Pilotage technique d'une équipe agile sur des projets cloud et microservices. " +
            "Forte autonomie et culture d'innovation.",
            "65 000€ - 85 000€", "#", "2026-04-23", "Leadership"));
        return demo;
    }
}
