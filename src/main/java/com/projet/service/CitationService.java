package com.projet.service;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * Service de citations motivantes via l'API ZenQuotes.
 * 100% gratuite, sans clé API.
 * Doc : https://zenquotes.io/
 */
public class CitationService {

    private static final String API_URL = "https://zenquotes.io/api/random";

    public static class Citation {
        public final String texte;
        public final String auteur;

        public Citation(String texte, String auteur) {
            this.texte  = texte;
            this.auteur = auteur;
        }
    }

    public Citation getCitationDuJour() {
        try {
            HttpURLConnection conn = (HttpURLConnection) new URL(API_URL).openConnection();
            conn.setRequestMethod("GET");
            conn.setConnectTimeout(5000);
            conn.setReadTimeout(5000);
            conn.setRequestProperty("Accept", "application/json");

            if (conn.getResponseCode() == 200) {
                BufferedReader reader = new BufferedReader(
                    new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) sb.append(line);
                reader.close();
                conn.disconnect();

                JSONArray arr = new JSONArray(sb.toString());
                JSONObject obj = arr.getJSONObject(0);
                return new Citation(obj.getString("q"), obj.getString("a"));
            }
            conn.disconnect();
        } catch (Exception e) {
            System.err.println("[CitationService] Erreur: " + e.getMessage());
        }
        // Fallback si pas de connexion
        return getFallback();
    }

    private Citation getFallback() {
        String[][] citations = {
            {"Le succès, c'est tomber sept fois et se relever huit.", "Proverbe japonais"},
            {"La seule façon de faire du bon travail est d'aimer ce que vous faites.", "Steve Jobs"},
            {"Chaque expert a été un jour un débutant.", "Helen Hayes"},
            {"Le talent, c'est avoir envie de faire quelque chose.", "Jacqueline Wilson"},
            {"Ne comptez pas les jours, faites que les jours comptent.", "Muhammad Ali"},
            {"La motivation vous permet de démarrer. L'habitude vous permet de continuer.", "Jim Ryun"},
            {"Croyez en vous et tout devient possible.", "Anonyme"},
            {"Votre seule limite, c'est vous-même.", "Anonyme"}
        };
        int idx = (int)(Math.random() * citations.length);
        return new Citation(citations[idx][0], citations[idx][1]);
    }
}
