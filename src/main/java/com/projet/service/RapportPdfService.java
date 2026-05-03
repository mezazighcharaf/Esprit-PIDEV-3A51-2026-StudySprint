package com.projet.service;

import com.itextpdf.kernel.colors.ColorConstants;
import com.itextpdf.kernel.colors.DeviceRgb;
import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.*;
import com.itextpdf.layout.properties.TextAlignment;
import com.itextpdf.layout.properties.UnitValue;
import com.projet.entity.Objectif;
import com.projet.entity.Tache;
import com.projet.service.ScorePreparationService.ScoreResult;

import java.io.File;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.List;

/**
 * Métier 4 : Génération d'un rapport PDF complet de progression.
 */
public class RapportPdfService {

    private static final DateTimeFormatter FMT = DateTimeFormatter.ofPattern("dd/MM/yyyy");
    private static final DeviceRgb BLUE   = new DeviceRgb(59, 130, 246);
    private static final DeviceRgb GREEN  = new DeviceRgb(16, 185, 129);
    private static final DeviceRgb ORANGE = new DeviceRgb(245, 158, 11);
    private static final DeviceRgb RED    = new DeviceRgb(239, 68, 68);
    private static final DeviceRgb GRAY   = new DeviceRgb(107, 114, 128);
    private static final DeviceRgb LIGHT  = new DeviceRgb(249, 250, 251);

    public void generer(int etudiantId, String email, File fichier) throws Exception {
        ObjectifService objectifService = new ObjectifService();
        ScorePreparationService scoreService = new ScorePreparationService();
        BadgeService badgeService = new BadgeService();

        List<Objectif> objectifs = objectifService.findAllWithTachesByEtudiant(etudiantId);
        ScoreResult score = scoreService.calculerScore(etudiantId);
        List<BadgeService.Badge> badges = badgeService.getBadges(etudiantId);
        long badgesObtenus = badgeService.countObtained(badges);

        Document doc = new Document(new PdfDocument(new PdfWriter(fichier.getAbsolutePath())));
        doc.setMargins(36, 36, 36, 36);

        // ── En-tête ──────────────────────────────────────────────────────────
        Paragraph header = new Paragraph("StudySprint — Rapport de Progression")
            .setFontSize(22).setBold().setFontColor(BLUE)
            .setTextAlignment(TextAlignment.CENTER);
        doc.add(header);

        doc.add(new Paragraph("Étudiant : " + email + "   |   Date : " + LocalDate.now().format(FMT))
            .setFontSize(11).setFontColor(GRAY).setTextAlignment(TextAlignment.CENTER));

        doc.add(new LineSeparator(new com.itextpdf.kernel.pdf.canvas.draw.SolidLine()).setMarginTop(8).setMarginBottom(16));

        // ── Score de préparation ─────────────────────────────────────────────
        doc.add(new Paragraph("Score de Préparation au Recrutement")
            .setFontSize(14).setBold().setFontColor(BLUE));

        Table scoreTable = new Table(new float[]{2, 1, 1, 1, 1})
            .setWidth(UnitValue.createPercentValue(100)).setMarginBottom(16);
        scoreTable.addHeaderCell(styledCell("Score Global", true));
        scoreTable.addHeaderCell(styledCell("Tâches", true));
        scoreTable.addHeaderCell(styledCell("Objectifs", true));
        scoreTable.addHeaderCell(styledCell("Délais", true));
        scoreTable.addHeaderCell(styledCell("Priorités", true));

        scoreTable.addCell(styledCell(score.score + "% — " + score.niveau, false));
        scoreTable.addCell(styledCell(score.pctTaches + "%", false));
        scoreTable.addCell(styledCell(score.pctObjectifs + "%", false));
        scoreTable.addCell(styledCell(score.pctDelais + "%", false));
        scoreTable.addCell(styledCell(score.pctPriorites + "%", false));
        doc.add(scoreTable);

        // ── Badges ───────────────────────────────────────────────────────────
        doc.add(new Paragraph("Badges Obtenus (" + badgesObtenus + "/" + badges.size() + ")")
            .setFontSize(14).setBold().setFontColor(BLUE));

        StringBuilder badgeStr = new StringBuilder();
        badges.stream().filter(b -> b.obtenu)
            .forEach(b -> badgeStr.append(b.emoji).append(" ").append(b.titre).append("   "));
        doc.add(new Paragraph(badgeStr.length() > 0 ? badgeStr.toString() : "Aucun badge encore obtenu.")
            .setFontSize(11).setMarginBottom(16));

        // ── Résumé statistiques ───────────────────────────────────────────────
        long totalTaches = objectifs.stream().mapToLong(o -> o.getTaches().size()).sum();
        long terminees   = objectifs.stream().flatMap(o -> o.getTaches().stream())
            .filter(t -> "TERMINE".equals(t.getStatut())).count();
        long enCours     = objectifs.stream().flatMap(o -> o.getTaches().stream())
            .filter(t -> "EN_COURS".equals(t.getStatut())).count();
        long aFaire      = objectifs.stream().flatMap(o -> o.getTaches().stream())
            .filter(t -> "A_FAIRE".equals(t.getStatut())).count();

        doc.add(new Paragraph("Résumé Statistiques")
            .setFontSize(14).setBold().setFontColor(BLUE));

        Table statsTable = new Table(new float[]{1, 1, 1, 1})
            .setWidth(UnitValue.createPercentValue(100)).setMarginBottom(16);
        statsTable.addHeaderCell(styledCell("Total Tâches", true));
        statsTable.addHeaderCell(styledCell("Terminées", true));
        statsTable.addHeaderCell(styledCell("En cours", true));
        statsTable.addHeaderCell(styledCell("À faire", true));
        statsTable.addCell(styledCell(String.valueOf(totalTaches), false));
        statsTable.addCell(styledCell(String.valueOf(terminees), false));
        statsTable.addCell(styledCell(String.valueOf(enCours), false));
        statsTable.addCell(styledCell(String.valueOf(aFaire), false));
        doc.add(statsTable);

        // ── Détail par objectif ───────────────────────────────────────────────
        doc.add(new Paragraph("Détail des Objectifs")
            .setFontSize(14).setBold().setFontColor(BLUE));

        for (Objectif o : objectifs) {
            // Titre objectif
            doc.add(new Paragraph(o.getTitre() + "  [" + o.getStatut() + "]")
                .setFontSize(12).setBold()
                .setFontColor("TERMINE".equals(o.getStatut()) ? GREEN :
                              "EN_COURS".equals(o.getStatut()) ? BLUE : RED));

            if (o.getDescription() != null && !o.getDescription().isEmpty()) {
                doc.add(new Paragraph(o.getDescription())
                    .setFontSize(10).setFontColor(GRAY).setMarginBottom(4));
            }

            String dates = "Début : " + (o.getDateDebut() != null ? o.getDateDebut().toLocalDate().format(FMT) : "N/A")
                + "   Fin : " + (o.getDateFin() != null ? o.getDateFin().toLocalDate().format(FMT) : "N/A")
                + "   Progression : " + (int) o.getProgressPercent() + "%";
            doc.add(new Paragraph(dates).setFontSize(10).setFontColor(GRAY));

            // Tâches
            if (!o.getTaches().isEmpty()) {
                Table tacheTable = new Table(new float[]{3, 1, 1, 1})
                    .setWidth(UnitValue.createPercentValue(100)).setMarginBottom(12);
                tacheTable.addHeaderCell(styledCell("Tâche", true));
                tacheTable.addHeaderCell(styledCell("Durée", true));
                tacheTable.addHeaderCell(styledCell("Priorité", true));
                tacheTable.addHeaderCell(styledCell("Statut", true));

                for (Tache t : o.getTaches()) {
                    tacheTable.addCell(styledCell(t.getTitre(), false));
                    tacheTable.addCell(styledCell(t.getDuree() + " min", false));
                    tacheTable.addCell(styledCell(t.getPriorite(), false));
                    tacheTable.addCell(styledCell(t.getStatut(), false));
                }
                doc.add(tacheTable);
            } else {
                doc.add(new Paragraph("Aucune tâche.").setFontSize(10).setFontColor(GRAY).setMarginBottom(12));
            }
        }

        // ── Pied de page ─────────────────────────────────────────────────────
        doc.add(new LineSeparator(new com.itextpdf.kernel.pdf.canvas.draw.SolidLine()).setMarginTop(8));
        doc.add(new Paragraph("Généré par StudySprint le " + LocalDate.now().format(FMT))
            .setFontSize(9).setFontColor(GRAY).setTextAlignment(TextAlignment.CENTER));

        doc.close();
    }

    private Cell styledCell(String text, boolean isHeader) {
        Cell cell = new Cell().add(new Paragraph(text).setFontSize(10));
        if (isHeader) cell.setBackgroundColor(BLUE).setFontColor(ColorConstants.WHITE).setBold();
        else cell.setBackgroundColor(LIGHT);
        return cell;
    }
}
