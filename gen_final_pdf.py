"""
Insert Quiz & Flashcards backlog pages + replace diagram in the existing presentation PDF.
- Pages are inserted starting at page index 24 (0-indexed=23) i.e. after page 23 (Objectifs avancées)
- Page 25 (old diagram title) is replaced with new diagram title
- Page 26 (old diagram) is replaced with new diagram
Design is matched exactly to existing PDF style.
"""
import io
from PyPDF2 import PdfReader, PdfWriter
from reportlab.lib.pagesizes import landscape
from reportlab.lib import colors
from reportlab.lib.units import cm, mm
from reportlab.pdfgen import canvas
from reportlab.lib.enums import TA_CENTER

# Page size matches the existing PDF (16:9 widescreen)
W = 1440.0  # Match existing PDF width in points
H = 810.0   # Match existing PDF height in points

# Scale factors relative to A4 landscape (842x595) where original layout was designed
SX = W / 842.0   # ~1.71
SY = H / 595.0   # ~1.36

# ─── COLORS extracted from existing PDF ───
DARK_NAVY = colors.HexColor("#3D5A80")
TEAL_BG = colors.HexColor("#A8D8DC")
NAVY_BG = colors.HexColor("#4A6274")
PINK_SALMON = colors.HexColor("#E8A0A0")
MAUVE_SUBTITLE = colors.HexColor("#C88EA7")
WHITE = colors.white
BLACK = colors.HexColor("#2C3E50")
TABLE_HEADER_BG = colors.HexColor("#7B8D9E")
TABLE_ROW_LIGHT = colors.HexColor("#FFFFFF")
TABLE_ROW_ALT = colors.HexColor("#F8F8F8")
TABLE_BORDER = colors.HexColor("#C0C0C0")

# Priority colors
RED_PRIO = colors.HexColor("#E74C3C")
ORANGE_PRIO = colors.HexColor("#F5A623")
GREEN_PRIO = colors.HexColor("#4CAF50")

# Status colors
GREEN_DONE = colors.HexColor("#4CAF50")
BLUE_PROGRESS = colors.HexColor("#42A5F5")
GRAY_TODO = colors.HexColor("#90A4AE")

LIGHT_BG = colors.HexColor("#EBF5F5")


def draw_backlog_table_page(c, module_title, section_subtitle, rows, page_num):
    """Draw a backlog table page matching the existing PDF design exactly."""
    c.setFillColor(WHITE)
    c.rect(0, 0, W, H, fill=1, stroke=0)

    # Module title
    if module_title:
        c.setFillColor(colors.HexColor("#4A6274"))
        c.setFont("Helvetica-Bold", 22)
        c.drawString(80, H - 70, f"●  {module_title}")

    # Section subtitle
    c.setFillColor(colors.HexColor("#C88EA7"))
    c.setFont("Helvetica-Bold", 18)
    y_sub = H - 110 if module_title else H - 70
    c.drawString(110, y_sub, f"●  {section_subtitle}")

    # Table
    table_top = y_sub - 35
    col_widths = [520, 420, 110, 90]  # User Story, Tâches, Priorité, Statut
    total_w = sum(col_widths)
    table_left = (W - total_w) / 2
    row_height = 48
    header_height = 42

    # Header
    headers = ["User Story", "Tâches", "Priorité", "Statut"]
    c.setFillColor(colors.HexColor("#E8E0E0"))
    c.rect(table_left, table_top - header_height, total_w, header_height, fill=1, stroke=0)

    c.setStrokeColor(TABLE_BORDER)
    c.setLineWidth(0.5)

    x = table_left
    for i, (header, cw) in enumerate(zip(headers, col_widths)):
        c.setFillColor(colors.HexColor("#5A3E4B"))
        c.setFont("Helvetica-Bold", 12)
        c.drawString(x + 12, table_top - header_height + 15, header)
        x += cw

    # Header bottom line
    c.setStrokeColor(colors.HexColor("#C88EA7"))
    c.setLineWidth(2)
    c.line(table_left, table_top - header_height, table_left + total_w, table_top - header_height)

    # Rows
    y = table_top - header_height
    for ri, row in enumerate(rows):
        us, tache, prio, statut = row

        if ri % 2 == 1:
            c.setFillColor(colors.HexColor("#F5F5F5"))
        else:
            c.setFillColor(WHITE)
        c.rect(table_left, y - row_height, total_w, row_height, fill=1, stroke=0)

        c.setStrokeColor(colors.HexColor("#E0E0E0"))
        c.setLineWidth(0.3)
        c.line(table_left, y - row_height, table_left + total_w, y - row_height)

        x = table_left

        # User Story column
        c.setFont("Helvetica", 10)
        c.setFillColor(BLACK)
        if us:
            if us.startswith("US-"):
                parts = us.split("—", 1)
                c.setFont("Helvetica-Bold", 10)
                c.setFillColor(colors.HexColor("#C0392B"))
                c.drawString(x + 12, y - 16, parts[0].strip())
                if len(parts) > 1:
                    c.setFont("Helvetica", 9)
                    c.setFillColor(BLACK)
                    desc = "— " + parts[1].strip()
                    _draw_wrapped(c, desc, x + 12, y - 30, col_widths[0] - 24, 9, 12)
            else:
                c.drawString(x + 12, y - 20, us)
        x += col_widths[0]

        # Tâches column
        c.setFont("Helvetica", 10)
        c.setFillColor(BLACK)
        _draw_wrapped(c, tache, x + 12, y - 16, col_widths[1] - 24, 10, 13)
        x += col_widths[1]

        # Priorité column
        if "Haute" in prio:
            dot_color = RED_PRIO
        elif "Moyenne" in prio:
            dot_color = ORANGE_PRIO
        else:
            dot_color = GREEN_PRIO

        c.setFillColor(dot_color)
        c.circle(x + 18, y - row_height/2, 6, fill=1, stroke=0)
        c.setFont("Helvetica", 10)
        label = prio.replace("● ", "")
        c.drawString(x + 30, y - row_height/2 - 4, label)
        x += col_widths[2]

        # Statut column
        if "Done" in statut:
            c.setFillColor(GREEN_DONE)
            c.setFont("Helvetica", 10)
            c.drawString(x + 8, y - row_height/2 - 4, "✅ Done")
        elif "Progress" in statut:
            c.setFillColor(BLUE_PROGRESS)
            c.setFont("Helvetica", 10)
            c.drawString(x + 8, y - row_height/2 - 4, "🔄 Progress")
        elif "To Do" in statut:
            c.setFillColor(GRAY_TODO)
            c.setFont("Helvetica", 10)
            c.drawString(x + 8, y - row_height/2 - 4, "📋 To Do")

        y -= row_height

    # Page number
    c.setFillColor(colors.HexColor("#A8D8DC"))
    c.setFont("Helvetica", 24)
    c.drawRightString(W - 45, 30, str(page_num))


def _draw_wrapped(c, text, x, y, max_w, font_size, line_height):
    """Simple word wrap helper."""
    c.setFont("Helvetica", font_size)
    words = text.split()
    line = ""
    for word in words:
        test = line + (" " if line else "") + word
        if c.stringWidth(test, "Helvetica", font_size) > max_w and line:
            c.drawString(x, y, line)
            y -= line_height
            line = word
        else:
            line = test
    if line:
        c.drawString(x, y, line)


def draw_section_title_page(c, title, page_num):
    """Draw a section title page with teal background matching existing style."""
    c.setFillColor(colors.HexColor("#A8D8DC"))
    c.rect(0, 0, W, H, fill=1, stroke=0)

    c.setFillColor(colors.HexColor("#2C3E50"))
    c.setFont("Helvetica-Bold", 48)

    lines = title.split("\n")
    y_start = H/2 + 30 * len(lines) / 2
    for i, line in enumerate(lines):
        c.drawString(120, y_start - i * 65, line)

    c.setFillColor(colors.HexColor("#2C3E50"))
    c.setFont("Helvetica", 24)
    c.drawRightString(W - 45, 30, str(page_num))


def draw_diagram_page(c, page_num):
    """Draw the UML sequence diagram for AI Quiz generation."""
    c.setFillColor(colors.HexColor("#EBF5F5"))
    c.rect(0, 0, W, H, fill=1, stroke=0)

    # ─── PARTICIPANTS ───
    participants = [
        {"name": ":Étudiant", "x": 100, "type": "actor"},
        {"name": ":PageGénérationIA", "x": 300, "type": "boundary",
         "stereo": "<<boundary>>",
         "methods": ["saisirFormulaire()", "afficherRésultat()"]},
        {"name": ":QuizController", "x": 530, "type": "control",
         "stereo": "<<control>>",
         "methods": ["aiGenerate()", "vérifierCSRF()", "chargerSubject()"]},
        {"name": ":AiGatewayService", "x": 760, "type": "service",
         "stereo": "<<service>>",
         "methods": ["generateQuiz()"]},
        {"name": ":FastAPI", "x": 970, "type": "service",
         "stereo": "<<service>>",
         "methods": ["POST /generate-quiz()"]},
        {"name": ":BaseDeDonnées", "x": 1190, "type": "entity",
         "stereo": "<<entity>>",
         "methods": ["persist()", "find()"]},
    ]

    box_top = H - 45
    box_w = 170
    box_h = 85

    for p in participants:
        x = p["x"]
        if p["type"] == "actor":
            cy = box_top - 12
            c.setStrokeColor(colors.HexColor("#2C3E50"))
            c.setLineWidth(2)
            c.circle(x, cy, 10, fill=0)
            c.line(x, cy - 10, x, cy - 38)
            c.line(x - 16, cy - 21, x + 16, cy - 21)
            c.line(x, cy - 38, x - 14, cy - 56)
            c.line(x, cy - 38, x + 14, cy - 56)
            c.setFillColor(colors.HexColor("#2C3E50"))
            c.setFont("Helvetica-Bold", 11)
            c.drawCentredString(x, cy - 68, p["name"])
        else:
            bx = x - box_w/2
            by = box_top - box_h

            c.setFillColor(WHITE)
            c.setStrokeColor(colors.HexColor("#2C3E50"))
            c.setLineWidth(1.2)
            c.roundRect(bx, by, box_w, box_h, 3, fill=1, stroke=1)

            # Stereotype
            c.setFillColor(colors.HexColor("#7F8C8D"))
            c.setFont("Helvetica-Oblique", 9)
            c.drawCentredString(x, box_top - 15, p.get("stereo", ""))

            # Name (underlined)
            c.setFillColor(colors.HexColor("#2C3E50"))
            c.setFont("Helvetica-Bold", 10)
            name = p["name"]
            tw = c.stringWidth(name, "Helvetica-Bold", 10)
            ny = box_top - 32
            c.drawCentredString(x, ny, name)
            c.line(x - tw/2, ny - 2, x + tw/2, ny - 2)

            # Separator
            c.setStrokeColor(colors.HexColor("#BDC3C7"))
            c.setLineWidth(0.5)
            c.line(bx + 5, box_top - 42, bx + box_w - 5, box_top - 42)

            # Methods
            c.setFont("Helvetica", 8)
            c.setFillColor(colors.HexColor("#2C3E50"))
            for mi, m in enumerate(p.get("methods", [])):
                c.drawCentredString(x, box_top - 54 - mi * 12, m)

    # ─── LIFELINES ───
    ll_top = box_top - box_h - 5
    ll_bot = 40
    c.setStrokeColor(colors.HexColor("#BDC3C7"))
    c.setLineWidth(0.7)
    c.setDash(4, 4)
    for p in participants:
        c.line(p["x"], ll_top, p["x"], ll_bot)
    c.setDash()

    # ─── MESSAGES ───
    msgs = [
        (0, 1, 22,  "1: saisirFormulaire(matière, chapitre, nbQuestions)", False, False),
        (1, 2, 52,  "2: aiGenerate(request)", False, False),
        (2, 2, 82,  "3: vérifierCSRF(token)", False, True),
        (2, 5, 118, "4: find(subjectId)", False, False),
        (5, 2, 140, "5: return Subject", True, False),
        (2, 3, 168, "6: generateQuiz(userId, subjectId, chapterId, numQuestions)", False, False),
        (3, 4, 198, "7: POST /api/generate-quiz", False, False),
        (4, 4, 228, "8: appelerLLM() → générer questions/réponses", False, True),
        (4, 5, 262, "9: persist(Quiz, QuizQuestions)", False, False),
        (5, 4, 284, "10: return OK", True, False),
        (4, 3, 310, "11: return {quiz_id, questions_count}", True, False),
        (3, 2, 340, "12: return {quiz_id, redirect_url}", True, False),
        (2, 1, 370, "13: redirect(/fo/training/quizzes/{id})", True, False),
        (1, 0, 400, "14: afficherQuizGénéré()", True, False),
    ]

    for frm, to, offset, text, dashed, is_self in msgs:
        fx = participants[frm]["x"]
        tx = participants[to]["x"]
        y = ll_top - offset

        c.setLineWidth(1.2)
        stroke_col = colors.HexColor("#7F8C8D") if dashed else colors.HexColor("#2C3E50")
        c.setStrokeColor(stroke_col)

        if is_self:
            loop_w = 42
            c.line(fx, y, fx + loop_w, y)
            c.line(fx + loop_w, y, fx + loop_w, y - 18)
            c.line(fx + loop_w, y - 18, fx, y - 18)
            c.setFillColor(stroke_col)
            path = c.beginPath()
            path.moveTo(fx, y - 18)
            path.lineTo(fx + 7, y - 14)
            path.lineTo(fx + 7, y - 22)
            path.close()
            c.drawPath(path, fill=1, stroke=0)
            c.setFont("Helvetica", 8)
            c.setFillColor(colors.HexColor("#2C3E50"))
            c.drawString(fx + loop_w + 6, y - 8, text)
        else:
            if dashed:
                c.setDash(4, 4)
            c.line(fx, y, tx, y)
            c.setDash()

            direction = 1 if tx > fx else -1
            c.setFillColor(stroke_col)
            if dashed:
                c.line(tx, y, tx - direction * 8, y + 4)
                c.line(tx, y, tx - direction * 8, y - 4)
            else:
                path = c.beginPath()
                path.moveTo(tx, y)
                path.lineTo(tx - direction * 8, y + 4)
                path.lineTo(tx - direction * 8, y - 4)
                path.close()
                c.drawPath(path, fill=1, stroke=0)

            mid_x = (fx + tx) / 2
            c.setFont("Helvetica", 8)
            c.setFillColor(colors.HexColor("#2C3E50"))
            c.drawCentredString(mid_x, y + 6, text)

    # Note
    nx, ny = 1030, ll_top - 240
    c.setFillColor(colors.HexColor("#FFFDE7"))
    c.setStrokeColor(colors.HexColor("#FBC02D"))
    c.setLineWidth(0.8)
    c.roundRect(nx, ny, 180, 35, 3, fill=1, stroke=1)
    c.setFillColor(colors.HexColor("#5D4037"))
    c.setFont("Helvetica-Oblique", 8)
    c.drawString(nx + 6, ny + 20, "Le serveur FastAPI utilise un LLM")
    c.drawString(nx + 6, ny + 7, "pour générer questions et réponses")

    # Page number
    c.setFillColor(colors.HexColor("#A8D8DC"))
    c.setFont("Helvetica", 24)
    c.drawRightString(W - 45, 30, str(page_num))


def main():
    input_pdf = r"C:\Users\charaf\Downloads\Nouveau dossier\Meriem Dimassi Developpeur.pdf"
    output_pdf = r"C:\Users\charaf\Downloads\Nouveau dossier\Meriem Dimassi Developpeur_FINAL2.pdf"

    reader = PdfReader(input_pdf)
    writer = PdfWriter()

    new_pages = []

    # ── Quiz backlog pages ──
    quiz_base_rows = [
        ["US-30 — L'étudiant peut consulter les quiz disponibles afin de tester ses connaissances",
         "Liste paginée des quiz publiés avec filtres (matière, difficulté) et recherche textuelle",
         "Haute", "Done"],
        ["", "Page de détail du quiz avec nombre de questions, difficulté, matière et chapitre", "Haute", "Done"],
        ["US-31 — L'étudiant peut passer un quiz afin d'évaluer sa maîtrise d'un chapitre",
         "Interface de jeu question par question avec choix multiples", "Haute", "Done"],
        ["", "Correction automatique et calcul du score via QuizScoringService", "Haute", "Done"],
        ["", "Page de résultat avec score, détail des réponses correctes/incorrectes", "Haute", "Done"],
        ["US-32 — L'étudiant peut consulter son historique afin de suivre sa progression",
         "Liste des tentatives passées avec scores, dates et quiz associé", "Moyenne", "Done"],
        ["", "Statistiques de progression (nombre de tentatives, score moyen)", "Moyenne", "Done"],
        ["US-33 — L'admin peut gérer les quiz depuis le back-office afin de modérer le contenu",
         "CRUD complet des quiz et questions (création, modification, suppression)", "Haute", "Done"],
        ["", "Export CSV de la liste des quiz depuis le back-office", "Moyenne", "Done"],
    ]

    quiz_advanced_rows = [
        ["US-34 — L'étudiant peut générer un quiz par IA afin de s'entraîner sur un sujet précis",
         "Formulaire de génération IA (matière, chapitre, nombre de questions)", "Haute", "Done"],
        ["", "Appel au service AiGatewayService → API FastAPI pour génération via LLM", "Haute", "Done"],
        ["", "Persistance du quiz et des questions générés en base de données", "Haute", "Done"],
        ["US-35 — L'étudiant peut exporter ses résultats en PDF afin de garder une trace",
         "Génération du PDF récapitulatif via PdfExportService + dompdf", "Moyenne", "Done"],
        ["", "Génération du certificat PDF pour les quiz réussis (score ≥ 50%)", "Moyenne", "Done"],
        ["", "Partage du quiz via QR Code généré (QrCodeService)", "Basse", "Done"],
        ["US-36 — L'étudiant peut noter un quiz et recevoir des récompenses",
         "Système de notation par étoiles après complétion", "Basse", "Done"],
        ["", "Attribution automatique de badges et suivi des streaks", "Moyenne", "Done"],
    ]

    quiz_optim_rows = [
        ["US-37 — L'étudiant bénéficie d'une recherche et pagination optimisées",
         "Filtres combinés (matière + difficulté + recherche + tri) avec KnpPaginator", "Moyenne", "Done"],
        ["", "Correction du conflit KnpPaginator / paramètre sort", "Moyenne", "Done"],
        ["US-38 — L'admin peut s'assurer de la fiabilité du module Quiz",
         "Tests unitaires sur QuizScoringService et entités", "Moyenne", "To Do"],
        ["", "Tests fonctionnels (jouer un quiz, génération IA, export PDF)", "Moyenne", "To Do"],
    ]

    # ── Flashcards backlog pages ──
    fc_base_rows = [
        ["US-40 — L'étudiant peut consulter les decks de flashcards afin de choisir un sujet à réviser",
         "Liste paginée des decks publiés avec filtres (matière, tri) et recherche", "Haute", "Done"],
        ["", "Page de détail du deck avec aperçu des cartes (recto/verso) et statistiques", "Haute", "Done"],
        ["US-41 — L'étudiant peut réviser un deck avec la répétition espacée afin de mémoriser",
         "Interface de révision carte par carte (recto → verso)", "Haute", "Done"],
        ["", "Algorithme SM-2 : calcul de l'intervalle et du ease factor (Sm2SchedulerService)", "Haute", "Done"],
        ["", "Boutons de notation (Again / Hard / Good / Easy) avec mise à jour de l'état", "Haute", "Done"],
        ["", "Page de complétion avec statistiques du jour", "Moyenne", "Done"],
        ["US-42 — L'étudiant peut créer et gérer ses propres decks",
         "Formulaire de création de deck (titre, matière, chapitre)", "Haute", "Done"],
        ["", "Ajout, modification et suppression de cartes (front, back, hint)", "Haute", "Done"],
        ["", "Page 'Mes Decks' avec liste des decks personnels", "Moyenne", "Done"],
    ]

    fc_advanced_rows = [
        ["US-43 — L'étudiant peut générer un deck par IA afin de gagner du temps",
         "Formulaire de génération IA (matière, chapitre, nb cartes, indices)", "Haute", "Done"],
        ["", "Appel au service AiGatewayService::generateFlashcards() → API FastAPI", "Haute", "Done"],
        ["", "Persistance du deck + cartes générées en BDD et redirection", "Haute", "Done"],
        ["US-44 — L'étudiant peut exporter un deck en PDF afin de réviser hors-ligne",
         "Génération PDF du deck complet avec tableau recto/verso (dompdf)", "Moyenne", "Done"],
        ["", "Bouton 'Exporter PDF' sur la page de détail du deck", "Moyenne", "Done"],
    ]

    fc_optim_rows = [
        ["US-45 — L'étudiant bénéficie d'une recherche et pagination optimisées",
         "Filtres combinés (matière + recherche + tri) avec KnpPaginator", "Moyenne", "Done"],
        ["", "Correction du conflit KnpPaginator / paramètre sort", "Moyenne", "Done"],
        ["US-46 — L'admin peut s'assurer de la fiabilité du module Flashcards",
         "Tests unitaires sur Sm2SchedulerService et entités", "Moyenne", "To Do"],
        ["", "Tests fonctionnels (révision SM-2, génération IA, export PDF)", "Moyenne", "To Do"],
    ]

    # Create all new pages in a buffer
    buf = io.BytesIO()
    c = canvas.Canvas(buf, pagesize=(W, H))

    pn = 24

    # Quiz base
    draw_backlog_table_page(c, "Gestion Quiz", "Fonctionnalités de base", quiz_base_rows, pn)
    c.showPage(); pn += 1

    # Quiz advanced
    draw_backlog_table_page(c, "", "Fonctionnalités avancées", quiz_advanced_rows, pn)
    c.showPage(); pn += 1

    # Quiz optim
    draw_backlog_table_page(c, "", "Optimisation & Tests", quiz_optim_rows, pn)
    c.showPage(); pn += 1

    # Flashcards base
    draw_backlog_table_page(c, "Gestion Flashcards", "Fonctionnalités de base", fc_base_rows, pn)
    c.showPage(); pn += 1

    # Flashcards advanced
    draw_backlog_table_page(c, "", "Fonctionnalités avancées", fc_advanced_rows, pn)
    c.showPage(); pn += 1

    # Flashcards optim
    draw_backlog_table_page(c, "", "Optimisation & Tests", fc_optim_rows, pn)
    c.showPage(); pn += 1

    # Diagram title page (replaces old page 25 = index 24)
    draw_section_title_page(c, "Diagramme de séquence\nobjets : Génération\nde Quiz par IA", pn)
    c.showPage(); pn += 1

    # Diagram page (replaces old page 26 = index 25)
    draw_diagram_page(c, pn)
    c.showPage()

    c.save()
    buf.seek(0)
    new_reader = PdfReader(buf)

    # ─── ASSEMBLE FINAL PDF ───
    total_orig = len(reader.pages)

    # Pages 0-23: keep original
    for i in range(24):
        writer.add_page(reader.pages[i])

    # Insert 6 new backlog pages
    for i in range(6):
        writer.add_page(new_reader.pages[i])

    # New diagram title page (replaces old page index 24)
    writer.add_page(new_reader.pages[6])

    # New diagram page (replaces old page index 25)
    writer.add_page(new_reader.pages[7])

    # Keep remaining original pages (index 26 onward)
    for i in range(26, total_orig):
        writer.add_page(reader.pages[i])

    with open(output_pdf, 'wb') as f:
        writer.write(f)

    print(f"PDF FINAL genere : {output_pdf}")
    print(f"Pages: {len(writer.pages)} (original: {total_orig}, added: 6 backlog)")


if __name__ == '__main__':
    main()
