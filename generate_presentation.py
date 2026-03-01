"""
Génère le PDF des pages Sprint Backlog (Quiz + Flashcards) + Diagramme de séquence objets
"""
from reportlab.lib.pagesizes import landscape, A4
from reportlab.lib import colors
from reportlab.lib.units import cm, mm
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, PageBreak
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.pdfgen import canvas
from reportlab.lib.enums import TA_CENTER, TA_LEFT

WIDTH, HEIGHT = landscape(A4)

# ─── COLORS matching the presentation ───
DARK_BLUE = colors.HexColor("#3B5998")
HEADER_BG = colors.HexColor("#4A6FA5")
PINK_TITLE = colors.HexColor("#E8A0BF")
TEAL_BG = colors.HexColor("#A8E6CF")
LIGHT_BG = colors.HexColor("#F5F7FA")
RED_HIGH = colors.HexColor("#E74C3C")
ORANGE_MED = colors.HexColor("#F39C12")
GREEN_LOW = colors.HexColor("#27AE60")
GREEN_DONE = colors.HexColor("#2ECC71")
BLUE_PROG = colors.HexColor("#3498DB")
GRAY_TODO = colors.HexColor("#95A5A6")
WHITE = colors.white
BLACK = colors.HexColor("#2C3E50")

def priority_text(level):
    if level == "Haute":
        return "● Haute"
    elif level == "Moyenne":
        return "● Moyenne"
    else:
        return "● Basse"

def draw_page_number(c, doc):
    c.saveState()
    c.setFillColor(colors.HexColor("#A8E6CF"))
    c.setFont("Helvetica", 14)
    c.drawRightString(WIDTH - 30, 25, str(doc.page))
    c.restoreState()

def build_pdf():
    filename = "C:/Users/charaf/Desktop/StudySprint/Sprint_Backlog_Quiz_Flashcards.pdf"
    doc = SimpleDocTemplate(
        filename,
        pagesize=landscape(A4),
        leftMargin=1.5*cm, rightMargin=1.5*cm,
        topMargin=1.2*cm, bottomMargin=1.2*cm
    )

    styles = getSampleStyleSheet()

    # Custom styles
    title_style = ParagraphStyle(
        'CustomTitle', parent=styles['Normal'],
        fontSize=22, fontName='Helvetica-Bold',
        textColor=colors.HexColor("#3B5998"),
        spaceAfter=6, alignment=TA_LEFT
    )
    subtitle_style = ParagraphStyle(
        'CustomSubtitle', parent=styles['Normal'],
        fontSize=16, fontName='Helvetica-Bold',
        textColor=colors.HexColor("#E8A0BF"),
        spaceAfter=12, alignment=TA_LEFT
    )
    section_title_style = ParagraphStyle(
        'SectionTitle', parent=styles['Normal'],
        fontSize=28, fontName='Helvetica-Bold',
        textColor=colors.HexColor("#3B5998"),
        alignment=TA_CENTER, spaceAfter=0, spaceBefore=80
    )
    cell_style = ParagraphStyle(
        'CellStyle', parent=styles['Normal'],
        fontSize=9, fontName='Helvetica', leading=12,
        textColor=BLACK
    )
    cell_bold = ParagraphStyle(
        'CellBold', parent=styles['Normal'],
        fontSize=9, fontName='Helvetica-Bold', leading=12,
        textColor=BLACK
    )
    header_cell = ParagraphStyle(
        'HeaderCell', parent=styles['Normal'],
        fontSize=10, fontName='Helvetica-Bold', leading=13,
        textColor=WHITE
    )

    elements = []

    # ═══════════════════════════════════════════════
    # SECTION SEPARATOR: Quiz
    # ═══════════════════════════════════════════════
    elements.append(Spacer(1, 100))
    elements.append(Paragraph("Sprint Backlog", section_title_style))
    elements.append(Spacer(1, 20))
    sep_subtitle = ParagraphStyle('SepSub', parent=styles['Normal'],
        fontSize=18, fontName='Helvetica', textColor=colors.HexColor("#4A6FA5"),
        alignment=TA_CENTER)
    elements.append(Paragraph("Modules : Gestion Quiz & Gestion Flashcards", sep_subtitle))
    elements.append(PageBreak())

    # ═══════════════════════════════════════════════
    # QUIZ - Fonctionnalités de base
    # ═══════════════════════════════════════════════
    def make_table(title, subtitle, headers, rows, col_widths=None):
        elements.append(Paragraph(title, title_style))
        elements.append(Paragraph(subtitle, subtitle_style))
        elements.append(Spacer(1, 4))

        # Build header row
        header_row = [Paragraph(h, header_cell) for h in headers]
        data = [header_row]

        for row in rows:
            data_row = []
            for i, cell in enumerate(row):
                if i == 0 and cell.startswith("US-"):
                    data_row.append(Paragraph(f"<b>{cell.split('—')[0].strip()}</b> — {cell.split('—')[1].strip()}" if '—' in cell else f"<b>{cell}</b>", cell_style))
                else:
                    data_row.append(Paragraph(cell, cell_style))
            data.append(data_row)

        if col_widths is None:
            col_widths = [9*cm, 10*cm, 2.8*cm, 2.2*cm]

        t = Table(data, colWidths=col_widths, repeatRows=1)

        style_cmds = [
            ('BACKGROUND', (0, 0), (-1, 0), HEADER_BG),
            ('TEXTCOLOR', (0, 0), (-1, 0), WHITE),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, 0), 10),
            ('BOTTOMPADDING', (0, 0), (-1, 0), 8),
            ('TOPPADDING', (0, 0), (-1, 0), 8),
            ('ALIGN', (2, 0), (3, -1), 'CENTER'),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor("#D5D8DC")),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [WHITE, colors.HexColor("#F8F9FA")]),
            ('LEFTPADDING', (0, 0), (-1, -1), 8),
            ('RIGHTPADDING', (0, 0), (-1, -1), 8),
            ('TOPPADDING', (0, 1), (-1, -1), 6),
            ('BOTTOMPADDING', (0, 1), (-1, -1), 6),
        ]

        # Color priority and status cells
        for idx, row in enumerate(rows, 1):
            # Priority coloring
            prio = row[2] if len(row) > 2 else ""
            if "Haute" in prio:
                style_cmds.append(('TEXTCOLOR', (2, idx), (2, idx), RED_HIGH))
            elif "Moyenne" in prio:
                style_cmds.append(('TEXTCOLOR', (2, idx), (2, idx), ORANGE_MED))
            elif "Basse" in prio:
                style_cmds.append(('TEXTCOLOR', (2, idx), (2, idx), GREEN_LOW))

            # Status coloring
            stat = row[3] if len(row) > 3 else ""
            if "Done" in stat:
                style_cmds.append(('TEXTCOLOR', (3, idx), (3, idx), GREEN_DONE))
            elif "Progress" in stat:
                style_cmds.append(('TEXTCOLOR', (3, idx), (3, idx), BLUE_PROG))
            elif "To Do" in stat:
                style_cmds.append(('TEXTCOLOR', (3, idx), (3, idx), GRAY_TODO))

        t.setStyle(TableStyle(style_cmds))
        elements.append(t)

    # ── QUIZ BASE ──
    make_table(
        "● Gestion Quiz",
        "● Fonctionnalités de base",
        ["User Story", "Tâches", "Priorité", "Statut"],
        [
            ["US-30 — L'étudiant peut consulter les quiz disponibles afin de tester ses connaissances",
             "Liste paginée des quiz publiés avec filtres (matière, difficulté) et recherche textuelle",
             "● Haute", "✅ Done"],
            ["",
             "Page de détail du quiz avec nombre de questions, difficulté, matière et chapitre",
             "● Haute", "✅ Done"],
            ["US-31 — L'étudiant peut passer un quiz afin d'évaluer sa maîtrise d'un chapitre",
             "Interface de jeu question par question avec choix multiples",
             "● Haute", "✅ Done"],
            ["",
             "Correction automatique et calcul du score via QuizScoringService",
             "● Haute", "✅ Done"],
            ["",
             "Page de résultat avec score, détail des réponses correctes/incorrectes et feedback",
             "● Haute", "✅ Done"],
            ["US-32 — L'étudiant peut consulter son historique afin de suivre sa progression",
             "Liste des tentatives passées avec scores, dates et quiz associé",
             "● Moyenne", "✅ Done"],
            ["",
             "Statistiques de progression (nombre de tentatives, score moyen)",
             "● Moyenne", "✅ Done"],
            ["US-33 — L'admin peut gérer les quiz depuis le back-office afin de modérer le contenu",
             "CRUD complet des quiz et questions (création, modification, suppression, publication)",
             "● Haute", "✅ Done"],
            ["",
             "Export CSV de la liste des quiz",
             "● Moyenne", "✅ Done"],
        ]
    )
    elements.append(PageBreak())

    # ── QUIZ AVANCÉES ──
    make_table(
        "",
        "● Fonctionnalités avancées",
        ["User Story", "Tâches", "Priorité", "Statut"],
        [
            ["US-34 — L'étudiant peut générer un quiz par IA afin de s'entraîner sur un sujet précis",
             "Formulaire de génération IA (matière, chapitre, nombre de questions)",
             "● Haute", "✅ Done"],
            ["",
             "Appel au service AiGatewayService → API FastAPI pour génération automatique via LLM",
             "● Haute", "✅ Done"],
            ["",
             "Persistance du quiz et des questions générés en base de données",
             "● Haute", "✅ Done"],
            ["US-35 — L'étudiant peut exporter ses résultats en PDF afin de garder une trace",
             "Génération du PDF récapitulatif via PdfExportService + dompdf",
             "● Moyenne", "✅ Done"],
            ["",
             "Génération du certificat PDF pour les quiz réussis (score ≥ 50%)",
             "● Moyenne", "✅ Done"],
            ["",
             "Partage du quiz via QR Code généré (QrCodeService)",
             "● Basse", "✅ Done"],
            ["US-36 — L'étudiant peut noter un quiz et recevoir des récompenses",
             "Système de notation par étoiles après complétion du quiz",
             "● Basse", "✅ Done"],
            ["",
             "Attribution automatique de badges et suivi des streaks (BadgeService, StreakService)",
             "● Moyenne", "✅ Done"],
        ]
    )
    elements.append(PageBreak())

    # ── QUIZ OPTIMISATION ──
    make_table(
        "",
        "● Optimisation & Tests",
        ["User Story", "Tâches", "Priorité", "Statut"],
        [
            ["US-37 — L'étudiant bénéficie d'une recherche et pagination optimisées afin de naviguer efficacement",
             "Filtres combinés (matière + difficulté + recherche texte + tri) avec KnpPaginator",
             "● Moyenne", "✅ Done"],
            ["",
             "Correction du conflit KnpPaginator / paramètre sort (sort_field_allow_list)",
             "● Moyenne", "✅ Done"],
            ["US-38 — L'admin peut s'assurer de la fiabilité du module Quiz afin de garantir un fonctionnement sans régressions",
             "Tests unitaires sur QuizScoringService et les entités Quiz/QuizQuestion",
             "● Moyenne", "📋 To Do"],
            ["",
             "Tests fonctionnels (jouer un quiz, génération IA, export PDF, notation)",
             "● Moyenne", "📋 To Do"],
        ]
    )
    elements.append(PageBreak())

    # ═══════════════════════════════════════════════
    # FLASHCARDS - Fonctionnalités de base
    # ═══════════════════════════════════════════════
    make_table(
        "● Gestion Flashcards",
        "● Fonctionnalités de base",
        ["User Story", "Tâches", "Priorité", "Statut"],
        [
            ["US-40 — L'étudiant peut consulter les decks de flashcards afin de choisir un sujet à réviser",
             "Liste paginée des decks publiés avec filtres (matière, tri) et recherche textuelle",
             "● Haute", "✅ Done"],
            ["",
             "Page de détail du deck avec aperçu des cartes (recto/verso) et statistiques",
             "● Haute", "✅ Done"],
            ["US-41 — L'étudiant peut réviser un deck avec la répétition espacée afin de mémoriser durablement",
             "Interface de révision carte par carte (affichage recto → révélation verso)",
             "● Haute", "✅ Done"],
            ["",
             "Algorithme SM-2 : calcul de l'intervalle et du ease factor (Sm2SchedulerService)",
             "● Haute", "✅ Done"],
            ["",
             "Boutons de notation (Again / Hard / Good / Easy) avec mise à jour de l'état",
             "● Haute", "✅ Done"],
            ["",
             "Page de complétion avec statistiques du jour (cartes révisées, cartes dues)",
             "● Moyenne", "✅ Done"],
            ["US-42 — L'étudiant peut créer et gérer ses propres decks afin de personnaliser ses révisions",
             "Formulaire de création de deck (titre, matière, chapitre)",
             "● Haute", "✅ Done"],
            ["",
             "Ajout, modification et suppression de cartes (front, back, hint)",
             "● Haute", "✅ Done"],
            ["",
             "Page 'Mes Decks' avec liste des decks personnels de l'étudiant",
             "● Moyenne", "✅ Done"],
        ]
    )
    elements.append(PageBreak())

    # ── FLASHCARDS AVANCÉES ──
    make_table(
        "",
        "● Fonctionnalités avancées",
        ["User Story", "Tâches", "Priorité", "Statut"],
        [
            ["US-43 — L'étudiant peut générer un deck de flashcards par IA afin de gagner du temps",
             "Formulaire de génération IA (matière, chapitre, nombre de cartes, inclusion d'indices)",
             "● Haute", "✅ Done"],
            ["",
             "Appel au service AiGatewayService::generateFlashcards() → API FastAPI",
             "● Haute", "✅ Done"],
            ["",
             "Persistance du deck + cartes générées en base de données et redirection",
             "● Haute", "✅ Done"],
            ["US-44 — L'étudiant peut exporter un deck en PDF afin de réviser hors-ligne",
             "Génération PDF du deck complet avec tableau recto/verso (PdfExportService + dompdf)",
             "● Moyenne", "✅ Done"],
            ["",
             "Bouton 'Exporter PDF' sur la page de détail du deck (ouverture nouvel onglet)",
             "● Moyenne", "✅ Done"],
        ]
    )
    elements.append(PageBreak())

    # ── FLASHCARDS OPTIMISATION ──
    make_table(
        "",
        "● Optimisation & Tests",
        ["User Story", "Tâches", "Priorité", "Statut"],
        [
            ["US-45 — L'étudiant bénéficie d'une recherche et pagination optimisées afin de trouver rapidement un deck",
             "Filtres combinés (matière + recherche texte + tri) avec KnpPaginator",
             "● Moyenne", "✅ Done"],
            ["",
             "Correction du conflit KnpPaginator / paramètre sort (sort_field_allow_list)",
             "● Moyenne", "✅ Done"],
            ["US-46 — L'admin peut s'assurer de la fiabilité du module Flashcards afin de garantir un fonctionnement sans régressions",
             "Tests unitaires sur Sm2SchedulerService et entités FlashcardDeck/Flashcard",
             "● Moyenne", "📋 To Do"],
            ["",
             "Tests fonctionnels (révision SM-2, génération IA, export PDF)",
             "● Moyenne", "📋 To Do"],
        ]
    )
    elements.append(PageBreak())

    # ═══════════════════════════════════════════════
    # DIAGRAMME DE SÉQUENCE OBJETS - Titre
    # ═══════════════════════════════════════════════
    elements.append(Spacer(1, 100))
    elements.append(Paragraph("Diagramme de séquence objets", section_title_style))
    elements.append(Spacer(1, 20))
    elements.append(Paragraph("Fonctionnalité avancée : Génération de Quiz par IA", sep_subtitle))
    elements.append(PageBreak())

    # ═══════════════════════════════════════════════
    # DIAGRAMME drawn directly on canvas
    # ═══════════════════════════════════════════════
    elements.append(Spacer(1, 0))  # placeholder, diagram drawn via onPage

    doc.build(elements, onFirstPage=draw_page_number, onLaterPages=draw_page_number)

    # Now add the diagram page using canvas overlay
    import io
    from reportlab.pdfgen import canvas as cv
    from PyPDF2 import PdfReader, PdfWriter

    # Create diagram as separate PDF
    buf = io.BytesIO()
    c = cv.Canvas(buf, pagesize=landscape(A4))
    draw_sequence_diagram(c)
    c.save()
    buf.seek(0)

    # Merge: take generated PDF, replace last page with diagram
    reader_main = PdfReader(filename)
    reader_diag = PdfReader(buf)
    writer = PdfWriter()

    for i, page in enumerate(reader_main.pages):
        writer.add_page(page)
        # Add diagram after the title page (last page)
        if i == len(reader_main.pages) - 1:
            writer.add_page(reader_diag.pages[0])

    with open(filename, 'wb') as f:
        writer.write(f)

    print(f"PDF généré : {filename}")


def draw_sequence_diagram(c):
    """Draw the sequence diagram for AI Quiz Generation on canvas"""
    W, H = landscape(A4)

    # Background
    c.setFillColor(colors.HexColor("#F5F7FA"))
    c.rect(0, 0, W, H, fill=1, stroke=0)

    # Title
    c.setFillColor(colors.HexColor("#3B5998"))
    c.setFont("Helvetica-Bold", 14)
    c.drawCentredString(W/2, H - 35, "Diagramme de séquence objets — Génération de Quiz par IA")

    # ─── PARTICIPANTS (classes without attributes, only methods) ───
    participants = [
        {"name": ":Étudiant", "x": 80, "type": "actor"},
        {"name": ":PageGénérationIA", "x": 195, "type": "boundary",
         "methods": ["saisirFormulaire()", "afficherRésultat()"]},
        {"name": ":QuizController", "x": 330, "type": "control",
         "methods": ["aiGenerate()", "vérifierCSRF()", "chargerSubject()"]},
        {"name": ":AiGatewayService", "x": 475, "type": "class",
         "methods": ["generateQuiz()"]},
        {"name": ":FastAPI (IA)", "x": 610, "type": "class",
         "methods": ["POST /generate-quiz()"]},
        {"name": ":BaseDeDonnées", "x": 735, "type": "entity",
         "methods": ["persist()", "find()"]},
    ]

    # Draw participant boxes
    box_top = H - 60
    box_h = 55
    box_w = 105

    for p in participants:
        x = p["x"]
        if p["type"] == "actor":
            # Stick figure
            cy = box_top - 10
            c.setStrokeColor(colors.HexColor("#3B5998"))
            c.setLineWidth(1.5)
            # Head
            c.circle(x, cy, 8, fill=0)
            # Body
            c.line(x, cy - 8, x, cy - 28)
            # Arms
            c.line(x - 12, cy - 16, x + 12, cy - 16)
            # Legs
            c.line(x, cy - 28, x - 10, cy - 42)
            c.line(x, cy - 28, x + 10, cy - 42)
            # Label
            c.setFont("Helvetica-Bold", 8)
            c.setFillColor(colors.HexColor("#3B5998"))
            c.drawCentredString(x, cy - 50, p["name"])
        else:
            # Class box (stereotype)
            bx = x - box_w/2
            by = box_top - box_h

            # Stereotype symbol
            stereo_map = {"boundary": "<<boundary>>", "control": "<<control>>",
                         "class": "<<service>>", "entity": "<<entity>>"}
            stereo = stereo_map.get(p["type"], "")

            # Box background
            c.setFillColor(WHITE)
            c.setStrokeColor(colors.HexColor("#3B5998"))
            c.setLineWidth(1)
            c.roundRect(bx, by, box_w, box_h, 3, fill=1, stroke=1)

            # Stereotype
            c.setFillColor(colors.HexColor("#7F8C8D"))
            c.setFont("Helvetica-Oblique", 6)
            c.drawCentredString(x, box_top - 10, stereo)

            # Class name
            c.setFillColor(colors.HexColor("#2C3E50"))
            c.setFont("Helvetica-Bold", 7)
            c.drawCentredString(x, box_top - 20, p["name"])

            # Separator line
            c.setStrokeColor(colors.HexColor("#BDC3C7"))
            c.line(bx + 3, box_top - 25, bx + box_w - 3, box_top - 25)

            # Methods
            c.setFont("Helvetica", 5.5)
            c.setFillColor(colors.HexColor("#2C3E50"))
            methods = p.get("methods", [])
            for mi, m in enumerate(methods):
                c.drawCentredString(x, box_top - 33 - mi * 8, m)

    # ─── LIFELINES ───
    lifeline_top = box_top - box_h - 5
    lifeline_bottom = 45

    c.setStrokeColor(colors.HexColor("#BDC3C7"))
    c.setLineWidth(0.5)
    c.setDash(3, 3)
    for p in participants:
        c.line(p["x"], lifeline_top, p["x"], lifeline_bottom)
    c.setDash()

    # ─── MESSAGES ───
    messages = [
        {"from": 0, "to": 1, "y": lifeline_top - 15, "text": "1: saisirFormulaire(matière, chapitre, nbQuestions)", "type": "solid"},
        {"from": 1, "to": 2, "y": lifeline_top - 35, "text": "2: aiGenerate(request)", "type": "solid"},
        {"from": 2, "to": 2, "y": lifeline_top - 55, "text": "3: vérifierCSRF(token)", "type": "self"},
        {"from": 2, "to": 5, "y": lifeline_top - 75, "text": "4: find(subjectId)", "type": "solid"},
        {"from": 5, "to": 2, "y": lifeline_top - 90, "text": "5: Subject", "type": "dashed"},
        {"from": 2, "to": 3, "y": lifeline_top - 110, "text": "6: generateQuiz(userId, subjectId, chapterId, numQuestions)", "type": "solid"},
        {"from": 3, "to": 4, "y": lifeline_top - 130, "text": "7: POST /api/generate-quiz {subject, chapter, num}", "type": "solid"},
        {"from": 4, "to": 4, "y": lifeline_top - 150, "text": "8: appeler LLM et générer questions", "type": "self"},
        {"from": 4, "to": 5, "y": lifeline_top - 170, "text": "9: persist(Quiz + QuizQuestions)", "type": "solid"},
        {"from": 5, "to": 4, "y": lifeline_top - 185, "text": "10: OK", "type": "dashed"},
        {"from": 4, "to": 3, "y": lifeline_top - 200, "text": "11: {quiz_id, questions_count}", "type": "dashed"},
        {"from": 3, "to": 2, "y": lifeline_top - 215, "text": "12: {quiz_id, redirect_url}", "type": "dashed"},
        {"from": 2, "to": 1, "y": lifeline_top - 235, "text": "13: redirect(/fo/training/quizzes/{id})", "type": "dashed"},
        {"from": 1, "to": 0, "y": lifeline_top - 255, "text": "14: afficherRésultat(quiz généré)", "type": "dashed"},
    ]

    for msg in messages:
        fx = participants[msg["from"]]["x"]
        tx = participants[msg["to"]]["x"]
        y = msg["y"]
        text = msg["text"]
        is_dashed = msg["type"] == "dashed"
        is_self = msg["type"] == "self"

        c.setLineWidth(1)
        c.setStrokeColor(colors.HexColor("#2C3E50") if not is_dashed else colors.HexColor("#7F8C8D"))

        if is_self:
            # Self-call: small loop
            c.setDash([] if not is_dashed else [3, 3])
            loop_w = 30
            c.line(fx, y, fx + loop_w, y)
            c.line(fx + loop_w, y, fx + loop_w, y - 12)
            c.line(fx + loop_w, y - 12, fx, y - 12)
            # Arrow
            c.line(fx, y - 12, fx + 5, y - 9)
            c.line(fx, y - 12, fx + 5, y - 15)
            # Text
            c.setFont("Helvetica", 5.5)
            c.setFillColor(colors.HexColor("#2C3E50"))
            c.drawString(fx + 33, y - 4, text)
            c.setDash()
        else:
            if is_dashed:
                c.setDash(3, 3)

            # Arrow line
            c.line(fx, y, tx, y)
            c.setDash()

            # Arrowhead
            direction = 1 if tx > fx else -1
            arrow_size = 6
            c.setFillColor(colors.HexColor("#2C3E50") if not is_dashed else colors.HexColor("#7F8C8D"))
            if is_dashed:
                # Open arrow
                c.line(tx, y, tx - direction * arrow_size, y + 3)
                c.line(tx, y, tx - direction * arrow_size, y - 3)
            else:
                # Filled arrow
                path = c.beginPath()
                path.moveTo(tx, y)
                path.lineTo(tx - direction * arrow_size, y + 3)
                path.lineTo(tx - direction * arrow_size, y - 3)
                path.close()
                c.drawPath(path, fill=1, stroke=0)

            # Text above line
            mid_x = (fx + tx) / 2
            c.setFont("Helvetica", 5.5)
            c.setFillColor(colors.HexColor("#2C3E50"))
            c.drawCentredString(mid_x, y + 4, text)

    # ─── ALT / NOTE boxes ───
    # Note on FastAPI
    note_x = participants[4]["x"] + 60
    note_y = lifeline_top - 155
    c.setFillColor(colors.HexColor("#FFF9C4"))
    c.setStrokeColor(colors.HexColor("#F9A825"))
    c.setLineWidth(0.5)
    c.roundRect(note_x - 5, note_y - 5, 100, 22, 2, fill=1, stroke=1)
    c.setFillColor(colors.HexColor("#5D4037"))
    c.setFont("Helvetica-Oblique", 5.5)
    c.drawString(note_x, note_y + 8, "Le serveur FastAPI appelle")
    c.drawString(note_x, note_y, "un LLM pour générer les Q/R")

    c.showPage()


if __name__ == '__main__':
    build_pdf()
