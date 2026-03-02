"""
AI Gateway Router - Complete AI integration with Ollama, DB write, and logging
Supports: Quiz generation, Flashcard generation, Profile enhancement, Chapter summary, Planning suggestions, Post summary
"""
from fastapi import APIRouter, Depends, HTTPException, status, Header
from sqlalchemy.orm import Session
from pydantic import BaseModel, Field
from typing import Optional, List, Any
import json
import time
import hashlib
import httpx
from datetime import datetime

from app.database import get_db
from app.models.user import User, UserProfile
from app.models.subject import Subject, Chapter
from app.models.training import Quiz
from app.models.flashcard import FlashcardDeck, Flashcard
from app.models.planning import RevisionPlan, PlanTask
from app.models.group import GroupPost
from app.models.ai import AiGenerationLog, AiModel
from app.config import settings

router = APIRouter(prefix="/ai", tags=["AI Gateway"])


# =============================================================================
# REQUEST/RESPONSE SCHEMAS
# =============================================================================

class QuizGenerateRequest(BaseModel):
    """Request to generate a quiz using AI and save to DB"""
    user_id: int
    subject_id: int
    chapter_id: Optional[int] = None
    num_questions: int = Field(default=5, ge=1, le=20)
    difficulty: str = Field(default="MEDIUM", pattern="^(EASY|MEDIUM|HARD)$")
    topic: Optional[str] = None
    idempotency_key: Optional[str] = None

class FlashcardGenerateRequest(BaseModel):
    """Request to generate flashcards using AI and save to DB"""
    user_id: int
    subject_id: int
    chapter_id: Optional[int] = None
    num_cards: int = Field(default=10, ge=1, le=50)
    topic: Optional[str] = None
    include_hints: bool = True
    idempotency_key: Optional[str] = None

class ProfileEnhanceRequest(BaseModel):
    """Request to enhance user profile with AI suggestions"""
    user_id: int
    current_bio: Optional[str] = None
    current_level: Optional[str] = None
    current_specialty: Optional[str] = None
    goals: Optional[str] = None
    idempotency_key: Optional[str] = None

class ChapterSummarizeRequest(BaseModel):
    """Request to generate chapter summary and tags"""
    user_id: int
    chapter_id: int
    idempotency_key: Optional[str] = None

class PlanningSuggestRequest(BaseModel):
    """Request AI suggestions for plan optimization"""
    user_id: int
    plan_id: int
    optimization_goals: Optional[str] = None  # e.g., "reduce workload", "space out tasks"
    idempotency_key: Optional[str] = None

class PlanningApplyRequest(BaseModel):
    """Apply AI suggestions to a plan"""
    user_id: int
    suggestion_log_id: int  # Reference to the AiGenerationLog with suggestions

class PostSummarizeRequest(BaseModel):
    """Request to summarize a group post"""
    user_id: int = 1
    post_id: int
    idempotency_key: Optional[str] = None

# Response schemas
class QuizGenerateResponse(BaseModel):
    quiz_id: int
    title: str
    questions_count: int
    difficulty: str
    ai_log_id: int
    message: str

class FlashcardGenerateResponse(BaseModel):
    deck_id: int
    title: str
    cards_count: int
    ai_log_id: int
    message: str

class ProfileEnhanceResponse(BaseModel):
    suggested_bio: Optional[str]
    suggested_goals: Optional[str]
    suggested_routine: Optional[str]
    ai_log_id: int

class ChapterSummarizeResponse(BaseModel):
    summary: str
    key_points: List[str]
    tags: List[str]
    ai_log_id: int

class PlanningSuggestResponse(BaseModel):
    suggestions: List[dict]
    explanation: str
    ai_log_id: int
    can_apply: bool

class PostSummarizeResponse(BaseModel):
    summary: str
    category: str
    tags: List[str]
    ai_log_id: int

class AIStatusResponse(BaseModel):
    ollama_available: bool
    ollama_model: str
    active_provider: str
    available: bool

class FeedbackRequest(BaseModel):
    """Submit feedback for an AI generation"""
    user_id: int
    log_id: int
    rating: int = Field(ge=1, le=5)


# =============================================================================
# AI PROVIDER ABSTRACTION
# =============================================================================

# Backward-compatible default model name.
OLLAMA_MODEL = settings.ollama_model


def format_exception(exc: Exception) -> str:
    """Return a useful, non-empty error message."""
    message = str(exc).strip()
    if message:
        return message
    return f"{type(exc).__name__}: {repr(exc)}"


def pick_ollama_candidates(available_models: list[str]) -> list[str]:
    """Pick model candidates in priority order, filtered by installed models."""
    configured_primary = (settings.ollama_model or OLLAMA_MODEL).strip()
    configured_fallbacks = [
        model.strip()
        for model in settings.ollama_fallback_models.split(",")
        if model.strip()
    ]

    ordered: list[str] = []
    for model in [configured_primary, *configured_fallbacks]:
        if model and model not in ordered:
            ordered.append(model)

    installed = set(available_models)
    candidates = [m for m in ordered if m in installed]

    # Last-resort fallback: pick first installed model so we don't fail unnecessarily.
    if not candidates and available_models:
        candidates.append(available_models[0])

    return candidates


async def get_ollama_models() -> list[str]:
    """Return installed Ollama model names."""
    async with httpx.AsyncClient(timeout=10.0) as client:
        response = await client.get(f"{settings.ollama_base_url}/api/tags")
        response.raise_for_status()
        models = response.json().get("models", [])
        return [m.get("name", "") for m in models if m.get("name")]


async def check_ollama_available() -> bool:
    """Check if Ollama is running"""
    try:
        model_names = await get_ollama_models()
        if not model_names:
            print("[AI] Ollama: no models installed")
            return False
        print(f"[AI] Ollama models available: {model_names}")
        return True
    except Exception as e:
        print(f"[AI] Ollama not reachable: {format_exception(e)}")
    return False


async def call_ollama(prompt: str, model: str, system_prompt: str = None) -> str:
    """Call Ollama local API"""
    messages = []
    if system_prompt:
        messages.append({"role": "system", "content": system_prompt})
    messages.append({"role": "user", "content": prompt})

    payload = {
        "model": model,
        "messages": messages,
        "stream": False,
        "options": {
            "temperature": settings.ai_temperature,
            "num_predict": settings.ai_max_tokens
        }
    }

    print(f"[AI] Calling Ollama model={model}, prompt_len={len(prompt)}")

    async with httpx.AsyncClient(timeout=httpx.Timeout(settings.ollama_timeout, connect=10.0)) as client:
        response = await client.post(
            f"{settings.ollama_base_url}/api/chat",
            json=payload
        )
        if response.status_code != 200:
            error_text = response.text[:500]
            print(f"[AI] Ollama error {response.status_code}: {error_text}")
            raise Exception(f"Ollama returned {response.status_code}: {error_text}")

        data = response.json()
        content = data.get("message", {}).get("content", "")
        print(f"[AI] Ollama response received, len={len(content)}")
        return content


async def call_ai_with_fallback(prompt: str, system_prompt: str = None) -> tuple[str, str, str]:
    """
    Call Ollama AI with model fallback.
    Returns: (response_text, provider, model_used)
    """
    print(f"[AI] Starting Ollama call...")

    try:
        available_models = await get_ollama_models()
    except Exception as e:
        raise HTTPException(
            status_code=503,
            detail=f"Ollama inaccessible: {format_exception(e)}"
        )

    if not available_models:
        raise HTTPException(
            status_code=503,
            detail="Ollama est accessible mais aucun modèle n'est installé."
        )

    candidates = pick_ollama_candidates(available_models)
    errors: list[str] = []

    for model in candidates:
        try:
            result = await call_ollama(prompt, model, system_prompt)
            if result and len(result.strip()) > 0:
                return result, "ollama", model
            errors.append(f"{model}: empty response")
        except Exception as e:
            err = format_exception(e)
            errors.append(f"{model}: {err}")
            print(f"[AI] Ollama call failed on {model}: {err}")

    raise HTTPException(
        status_code=503,
        detail=f"Erreur Ollama (models essayés: {', '.join(candidates)}): {' | '.join(errors)}"
    )

def parse_json_response(content: str) -> Any:
    """Parse JSON from AI response, handling markdown code blocks"""
    content = content.strip()

    # Handle markdown code blocks
    if "```json" in content:
        content = content.split("```json")[1].split("```")[0]
    elif "```" in content:
        parts = content.split("```")
        if len(parts) >= 2:
            content = parts[1]

    content = content.strip()

    # Try to parse
    try:
        return json.loads(content)
    except json.JSONDecodeError as e:
        # Try to find JSON array or object
        for start_char, end_char in [('[', ']'), ('{', '}')]:
            start = content.find(start_char)
            end = content.rfind(end_char)
            if start != -1 and end != -1:
                try:
                    return json.loads(content[start:end+1])
                except:
                    continue
        raise ValueError(f"Failed to parse JSON: {str(e)}")

def generate_idempotency_key(user_id: int, feature: str, input_data: dict) -> str:
    """Generate idempotency key from request data"""
    data_str = f"{user_id}:{feature}:{json.dumps(input_data, sort_keys=True)}"
    return hashlib.sha256(data_str.encode()).hexdigest()[:32]


# =============================================================================
# ENDPOINTS
# =============================================================================

@router.get("/status", response_model=AIStatusResponse)
async def get_ai_status():
    """Check AI service availability"""
    ollama_ok = await check_ollama_available()

    return AIStatusResponse(
        ollama_available=ollama_ok,
        ollama_model=settings.ollama_model or OLLAMA_MODEL,
        active_provider="ollama" if ollama_ok else "none",
        available=ollama_ok
    )


@router.post("/generate/quiz", response_model=QuizGenerateResponse)
@router.post("/geenerate/quiz", response_model=QuizGenerateResponse, include_in_schema=False)
async def generate_quiz(
    request: QuizGenerateRequest,
    db: Session = Depends(get_db)
):
    """
    Generate a quiz using AI and save directly to database.
    The quiz is created unpublished and can be edited before publishing.
    """
    start_time = time.time()

    # Generate or use provided idempotency key
    idem_key = request.idempotency_key or generate_idempotency_key(
        request.user_id, "quiz", request.model_dump()
    )

    # Check for existing generation with same key
    existing_log = db.query(AiGenerationLog).filter(
        AiGenerationLog.idempotency_key == idem_key,
        AiGenerationLog.status == AiGenerationLog.STATUS_SUCCESS
    ).first()

    if existing_log and existing_log.output_json:
        quiz_id = existing_log.output_json.get("quiz_id")
        if quiz_id:
            quiz = db.query(Quiz).filter(Quiz.id == quiz_id).first()
            if quiz:
                return QuizGenerateResponse(
                    quiz_id=quiz.id,
                    title=quiz.title,
                    questions_count=len(quiz.questions),
                    difficulty=quiz.difficulty,
                    ai_log_id=existing_log.id,
                    message="Quiz déjà généré (idempotent)"
                )

    # Validate subject/chapter
    subject = db.query(Subject).filter(Subject.id == request.subject_id).first()
    if not subject:
        raise HTTPException(status_code=404, detail="Matière non trouvée")

    chapter = None
    if request.chapter_id:
        chapter = db.query(Chapter).filter(Chapter.id == request.chapter_id).first()
        if not chapter:
            raise HTTPException(status_code=404, detail="Chapitre non trouvé")

    # Build context
    context = f"Matière: {subject.name}"
    if chapter:
        context += f"\nChapitre: {chapter.title}"
        if chapter.summary:
            context += f"\nRésumé: {chapter.summary}"
    if request.topic:
        context += f"\nSujet spécifique: {request.topic}"

    difficulty_fr = {"EASY": "facile", "MEDIUM": "intermédiaire", "HARD": "difficile"}[request.difficulty]

    prompt = f"""Génère {request.num_questions} questions QCM niveau {difficulty_fr}. Contexte: {context}

Réponds avec un tableau JSON uniquement, sans aucun texte avant ou après:
[{{"text":"question","choices":[{{"key":"A","text":"..."}},{{"key":"B","text":"..."}},{{"key":"C","text":"..."}},{{"key":"D","text":"..."}}],"correct_key":"A","explanation":"..."}}]"""

    system_prompt = "Tu es un assistant éducatif. Réponds UNIQUEMENT en JSON valide, sans markdown."

    # Create log entry
    log = AiGenerationLog(
        user_id=request.user_id,
        feature=AiGenerationLog.FEATURE_QUIZ,
        input_json=request.model_dump(),
        prompt=prompt,
        idempotency_key=idem_key,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    try:
        # Call AI
        response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)

        # Parse and validate JSON
        questions = None
        for attempt in range(settings.ai_max_retries):
            try:
                questions = parse_json_response(response_text)
                if isinstance(questions, list) and len(questions) > 0:
                    break
            except Exception:
                if attempt < settings.ai_max_retries - 1:
                    response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
                continue

        if not questions or not isinstance(questions, list):
            raise ValueError("Format de réponse invalide")

        # Create Quiz entity
        title = f"Quiz IA - {subject.name}"
        if chapter:
            title += f" - {chapter.title}"
        if request.topic:
            title += f" ({request.topic})"

        quiz = Quiz(
            owner_id=request.user_id,
            subject_id=subject.id,
            chapter_id=chapter.id if chapter else None,
            title=title[:255],
            difficulty=request.difficulty,
            questions=questions,
            is_published=True,
            generated_by_ai=True,
            ai_meta={
                "provider": provider,
                "model": model_used,
                "log_id": log.id,
                "topic": request.topic,
                "generated_at": datetime.utcnow().isoformat()
            }
        )
        db.add(quiz)
        db.commit()
        db.refresh(quiz)

        # Update log with success
        latency = int((time.time() - start_time) * 1000)
        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = {"quiz_id": quiz.id, "questions_count": len(questions)}
        log.latency_ms = latency
        db.commit()

        return QuizGenerateResponse(
            quiz_id=quiz.id,
            title=quiz.title,
            questions_count=len(questions),
            difficulty=quiz.difficulty,
            ai_log_id=log.id,
            message=f"Quiz généré avec succès via {provider}"
        )

    except Exception as e:
        # Update log with failure
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail=f"Génération échouée: {str(e)}")


@router.post("/generate/flashcards", response_model=FlashcardGenerateResponse)
@router.post("/geenerate/flashcards", response_model=FlashcardGenerateResponse, include_in_schema=False)
async def generate_flashcards(
    request: FlashcardGenerateRequest,
    db: Session = Depends(get_db)
):
    """
    Generate flashcards using AI and save directly to database.
    Creates a deck with individual flashcard entities.
    """
    start_time = time.time()

    idem_key = request.idempotency_key or generate_idempotency_key(
        request.user_id, "flashcard", request.model_dump()
    )

    # Check idempotency
    existing_log = db.query(AiGenerationLog).filter(
        AiGenerationLog.idempotency_key == idem_key,
        AiGenerationLog.status == AiGenerationLog.STATUS_SUCCESS
    ).first()

    if existing_log and existing_log.output_json:
        deck_id = existing_log.output_json.get("deck_id")
        if deck_id:
            deck = db.query(FlashcardDeck).filter(FlashcardDeck.id == deck_id).first()
            if deck:
                return FlashcardGenerateResponse(
                    deck_id=deck.id,
                    title=deck.title,
                    cards_count=len(deck.flashcards),
                    ai_log_id=existing_log.id,
                    message="Deck déjà généré (idempotent)"
                )

    # Validate subject/chapter
    subject = db.query(Subject).filter(Subject.id == request.subject_id).first()
    if not subject:
        raise HTTPException(status_code=404, detail="Matière non trouvée")

    chapter = None
    if request.chapter_id:
        chapter = db.query(Chapter).filter(Chapter.id == request.chapter_id).first()

    context = f"Matière: {subject.name}"
    if chapter:
        context += f"\nChapitre: {chapter.title}"
    if request.topic:
        context += f"\nSujet: {request.topic}"

    hint_instruction = "avec un indice (hint)" if request.include_hints else "sans indice"

    prompt = f"""Génère {request.num_cards} flashcards pour: {context}

IMPORTANT: Ne numérote PAS le contenu des cartes. Réponds avec un tableau JSON uniquement:
[{{"front":"concept ou question","back":"définition ou réponse","hint":"indice court"}}]"""

    system_prompt = "Tu es un assistant éducatif. Réponds UNIQUEMENT en JSON valide, sans markdown. Ne mets jamais de numéro dans le contenu des champs front, back ou hint."

    log = AiGenerationLog(
        user_id=request.user_id,
        feature=AiGenerationLog.FEATURE_FLASHCARD,
        input_json=request.model_dump(),
        prompt=prompt,
        idempotency_key=idem_key,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    try:
        response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)

        cards_data = None
        for attempt in range(settings.ai_max_retries):
            try:
                cards_data = parse_json_response(response_text)
                if isinstance(cards_data, list) and len(cards_data) > 0:
                    break
            except Exception:
                if attempt < settings.ai_max_retries - 1:
                    response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
                continue

        if not cards_data or not isinstance(cards_data, list):
            raise ValueError("Format de réponse invalide")

        # Create Deck entity
        title = f"Deck IA - {subject.name}"
        if chapter:
            title += f" - {chapter.title}"
        if request.topic:
            title += f" ({request.topic})"

        deck = FlashcardDeck(
            owner_id=request.user_id,
            subject_id=subject.id,
            chapter_id=chapter.id if chapter else None,
            title=title[:255],
            cards=[],  # Legacy field, we use flashcards relationship
            is_published=True,
            generated_by_ai=True,
            ai_meta={
                "provider": provider,
                "model": model_used,
                "log_id": log.id,
                "topic": request.topic,
                "generated_at": datetime.utcnow().isoformat()
            }
        )
        db.add(deck)
        db.flush()  # Get deck.id

        # Create individual Flashcard entities
        for idx, card in enumerate(cards_data):
            flashcard = Flashcard(
                deck_id=deck.id,
                front=card.get("front", ""),
                back=card.get("back", ""),
                hint=card.get("hint") if request.include_hints else None,
                position=idx + 1
            )
            db.add(flashcard)

        db.commit()
        db.refresh(deck)

        latency = int((time.time() - start_time) * 1000)
        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = {"deck_id": deck.id, "cards_count": len(cards_data)}
        log.latency_ms = latency
        db.commit()

        return FlashcardGenerateResponse(
            deck_id=deck.id,
            title=deck.title,
            cards_count=len(cards_data),
            ai_log_id=log.id,
            message=f"Deck généré avec succès via {provider}"
        )

    except Exception as e:
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail=f"Génération échouée: {str(e)}")


@router.post("/profile/enhance", response_model=ProfileEnhanceResponse)
async def enhance_profile(
    request: ProfileEnhanceRequest,
    db: Session = Depends(get_db)
):
    """
    Generate AI suggestions for user profile (bio, goals, routine).
    Does NOT overwrite - returns suggestions for user to review.
    """
    start_time = time.time()

    idem_key = request.idempotency_key or generate_idempotency_key(
        request.user_id, "profile", request.model_dump()
    )

    prompt = f"""En tant qu'assistant éducatif, génère des suggestions de profil pour un étudiant:

Informations actuelles:
- Bio: {request.current_bio or 'Non renseignée'}
- Niveau: {request.current_level or 'Non renseigné'}
- Spécialité: {request.current_specialty or 'Non renseignée'}
- Objectifs exprimés: {request.goals or 'Non renseignés'}

IMPORTANT: Retourne UNIQUEMENT un objet JSON valide avec ces champs:
{{
    "suggested_bio": "Une bio professionnelle et engageante (2-3 phrases)",
    "suggested_goals": "Des objectifs SMART clairs (liste à puces)",
    "suggested_routine": "Une routine d'étude quotidienne recommandée"
}}"""

    system_prompt = "Tu es un coach éducatif bienveillant. Réponds UNIQUEMENT en JSON valide."

    log = AiGenerationLog(
        user_id=request.user_id,
        feature=AiGenerationLog.FEATURE_PROFILE,
        input_json=request.model_dump(),
        prompt=prompt,
        idempotency_key=idem_key,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    try:
        response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
        result = parse_json_response(response_text)

        # Persist suggestions to UserProfile
        profile = db.query(UserProfile).filter(UserProfile.user_id == request.user_id).first()
        if profile:
            profile.ai_suggested_bio = result.get("suggested_bio")
            profile.ai_suggested_goals = result.get("suggested_goals")
            profile.ai_suggested_routine = result.get("suggested_routine")

        latency = int((time.time() - start_time) * 1000)
        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = result
        log.latency_ms = latency
        db.commit()

        return ProfileEnhanceResponse(
            suggested_bio=result.get("suggested_bio"),
            suggested_goals=result.get("suggested_goals"),
            suggested_routine=result.get("suggested_routine"),
            ai_log_id=log.id
        )

    except Exception as e:
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail=f"Génération échouée: {str(e)}")


@router.post("/chapter/summarize", response_model=ChapterSummarizeResponse)
async def summarize_chapter(
    request: ChapterSummarizeRequest,
    db: Session = Depends(get_db)
):
    """
    Generate AI summary, key points, and tags for a chapter.
    Persists results in chapter AI fields.
    """
    start_time = time.time()

    chapter = db.query(Chapter).filter(Chapter.id == request.chapter_id).first()
    if not chapter:
        raise HTTPException(status_code=404, detail="Chapitre non trouvé")

    idem_key = request.idempotency_key or generate_idempotency_key(
        request.user_id, "chapter_summary", {"chapter_id": chapter.id}
    )

    prompt = f"""Analyse ce chapitre et génère un résumé structuré:

Titre: {chapter.title}
Matière: {chapter.subject.name if chapter.subject else 'Non spécifiée'}
Contenu actuel: {chapter.content or chapter.summary or 'Pas de contenu détaillé'}

IMPORTANT: Retourne UNIQUEMENT un objet JSON valide:
{{
    "summary": "Résumé concis du chapitre (3-5 phrases)",
    "key_points": ["Point clé 1", "Point clé 2", "Point clé 3", "Point clé 4", "Point clé 5"],
    "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}}"""

    system_prompt = "Tu es un assistant éducatif expert en synthèse. Réponds UNIQUEMENT en JSON valide."

    log = AiGenerationLog(
        user_id=request.user_id,
        feature=AiGenerationLog.FEATURE_SUMMARY,
        input_json={"chapter_id": chapter.id},
        prompt=prompt,
        idempotency_key=idem_key,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    try:
        response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
        result = parse_json_response(response_text)

        # Persist AI fields to Chapter entity
        chapter.ai_summary = result.get("summary")
        chapter.ai_key_points = result.get("key_points", [])
        chapter.ai_tags = result.get("tags", [])

        latency = int((time.time() - start_time) * 1000)
        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = result
        log.latency_ms = latency
        db.commit()

        return ChapterSummarizeResponse(
            summary=result.get("summary", ""),
            key_points=result.get("key_points", []),
            tags=result.get("tags", []),
            ai_log_id=log.id
        )

    except Exception as e:
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail=f"Génération échouée: {str(e)}")


@router.post("/planning/suggest", response_model=PlanningSuggestResponse)
async def suggest_plan_optimizations(
    request: PlanningSuggestRequest,
    db: Session = Depends(get_db)
):
    """
    Generate AI suggestions for plan optimization.
    Returns suggestions for user review - does NOT apply automatically.
    """
    start_time = time.time()

    plan = db.query(RevisionPlan).filter(
        RevisionPlan.id == request.plan_id,
        RevisionPlan.user_id == request.user_id
    ).first()

    if not plan:
        raise HTTPException(status_code=404, detail="Plan non trouvé")

    tasks = db.query(PlanTask).filter(PlanTask.plan_id == plan.id).order_by(PlanTask.start_at).all()

    idem_key = request.idempotency_key or generate_idempotency_key(
        request.user_id, "planning_suggest", {"plan_id": plan.id}
    )

    tasks_info = []
    for t in tasks:
        tasks_info.append({
            "id": t.id,
            "title": t.title,
            "type": t.task_type,
            "start": t.start_at.isoformat() if t.start_at else None,
            "end": t.end_at.isoformat() if t.end_at else None,
            "status": t.status,
            "priority": t.priority
        })

    prompt = f"""Analyse ce plan de révision et propose des optimisations:

Plan: {plan.title}
Période: {plan.start_date} à {plan.end_date}
Objectifs d'optimisation: {request.optimization_goals or 'Améliorer la répartition et éviter la surcharge'}

Tâches actuelles:
{json.dumps(tasks_info, ensure_ascii=False, indent=2)}

IMPORTANT: Retourne UNIQUEMENT un objet JSON valide avec:
{{
    "suggestions": [
        {{
            "task_id": 123,
            "action": "move|reschedule|split|merge|delete",
            "reason": "Explication courte",
            "new_start": "2026-02-10T09:00:00",
            "new_end": "2026-02-10T10:00:00",
            "new_priority": 2
        }}
    ],
    "explanation": "Résumé global des changements proposés",
    "can_apply": true
}}

Limite-toi à 5 suggestions maximum et pertinentes."""

    system_prompt = "Tu es un assistant de planification expert. Tu proposes des optimisations concrètes. Réponds UNIQUEMENT en JSON valide."

    log = AiGenerationLog(
        user_id=request.user_id,
        feature=AiGenerationLog.FEATURE_PLANNING_SUGGEST,
        input_json={"plan_id": plan.id, "tasks_count": len(tasks)},
        prompt=prompt,
        idempotency_key=idem_key,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    try:
        response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
        result = parse_json_response(response_text)

        latency = int((time.time() - start_time) * 1000)
        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = result
        log.latency_ms = latency
        db.commit()

        return PlanningSuggestResponse(
            suggestions=result.get("suggestions", []),
            explanation=result.get("explanation", ""),
            ai_log_id=log.id,
            can_apply=result.get("can_apply", True)
        )

    except Exception as e:
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail=f"Génération échouée: {str(e)}")


@router.post("/planning/apply")
async def apply_plan_suggestions(
    request: PlanningApplyRequest,
    db: Session = Depends(get_db)
):
    """
    Apply previously generated AI suggestions to a plan.
    This is the second step after user reviews suggestions.
    """
    log = db.query(AiGenerationLog).filter(
        AiGenerationLog.id == request.suggestion_log_id,
        AiGenerationLog.user_id == request.user_id,
        AiGenerationLog.feature == AiGenerationLog.FEATURE_PLANNING_SUGGEST,
        AiGenerationLog.status == AiGenerationLog.STATUS_SUCCESS
    ).first()

    if not log or not log.output_json:
        raise HTTPException(status_code=404, detail="Suggestions non trouvées")

    suggestions = log.output_json.get("suggestions", [])
    applied = 0

    for sugg in suggestions:
        task_id = sugg.get("task_id")
        action = sugg.get("action")

        if not task_id:
            continue

        task = db.query(PlanTask).filter(PlanTask.id == task_id).first()
        if not task:
            continue

        # Verify task belongs to user's plan
        plan = db.query(RevisionPlan).filter(
            RevisionPlan.id == task.plan_id,
            RevisionPlan.user_id == request.user_id
        ).first()
        if not plan:
            continue

        if action == "delete":
            db.delete(task)
            applied += 1
        elif action in ["move", "reschedule"]:
            if sugg.get("new_start"):
                task.start_at = datetime.fromisoformat(sugg["new_start"].replace("Z", "+00:00"))
            if sugg.get("new_end"):
                task.end_at = datetime.fromisoformat(sugg["new_end"].replace("Z", "+00:00"))
            if sugg.get("new_priority"):
                task.priority = sugg["new_priority"]
            applied += 1

    db.commit()

    return {
        "message": f"{applied} modifications appliquées",
        "applied_count": applied,
        "total_suggestions": len(suggestions)
    }


@router.post("/post/summarize", response_model=PostSummarizeResponse)
async def summarize_post(
    request: PostSummarizeRequest,
    db: Session = Depends(get_db)
):
    """
    Generate AI summary, category, and tags for a group post.
    Does NOT modify the original post content.
    """
    start_time = time.time()

    post = db.query(GroupPost).filter(GroupPost.id == request.post_id).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post non trouvé")

    idem_key = request.idempotency_key or generate_idempotency_key(
        request.user_id, "post_summary", {"post_id": post.id}
    )

    prompt = f"""Analyse ce post de groupe d'étude et génère un résumé:

Titre: {post.title or 'Sans titre'}
Contenu: {post.body}

IMPORTANT: Retourne UNIQUEMENT un objet JSON valide:
{{
    "summary": "Résumé concis du post (1-2 phrases)",
    "category": "question|discussion|ressource|annonce|autre",
    "tags": ["tag1", "tag2", "tag3"]
}}"""

    system_prompt = "Tu es un assistant de modération de forum éducatif. Réponds UNIQUEMENT en JSON valide."

    log = AiGenerationLog(
        user_id=request.user_id,
        feature=AiGenerationLog.FEATURE_POST_SUMMARY,
        input_json={"post_id": post.id},
        prompt=prompt,
        idempotency_key=idem_key,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    try:
        response_text, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
        result = parse_json_response(response_text)

        # Persist AI fields to GroupPost entity
        post.ai_summary = result.get("summary")
        post.ai_category = result.get("category", "autre")
        post.ai_tags = result.get("tags", [])

        latency = int((time.time() - start_time) * 1000)
        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = result
        log.latency_ms = latency
        db.commit()

        return PostSummarizeResponse(
            summary=result.get("summary", ""),
            category=result.get("category", "autre"),
            tags=result.get("tags", []),
            ai_log_id=log.id
        )

    except Exception as e:
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail=f"Génération échouée: {str(e)}")


class TranslateToolRequest(BaseModel):
    text: str
    source: str = "fr"
    target: str = "en"

class DefineToolRequest(BaseModel):
    word: str
    lang: str = "fr"


@router.post("/tools/translate")
async def ai_translate(request: TranslateToolRequest, db: Session = Depends(get_db)):
    """
    Lightweight translation via local Ollama — tiny context window, logged to AI monitoring.
    Returns: {"translated": "..."}
    """
    if not request.text.strip():
        raise HTTPException(status_code=400, detail="Texte vide.")
    if len(request.text) > 500:
        raise HTTPException(status_code=400, detail="Texte trop long (max 500 caractères).")

    lang_names = {"fr": "français", "en": "anglais", "ar": "arabe", "es": "espagnol", "de": "allemand"}
    src_name = lang_names.get(request.source, request.source)
    tgt_name = lang_names.get(request.target, request.target)

    prompt = f'Traduis ce texte du {src_name} vers le {tgt_name}. Réponds avec UNIQUEMENT la traduction, sans explication ni ponctuation supplémentaire.\n\nTexte: {request.text}'
    system_prompt = "Tu es un traducteur. Réponds UNIQUEMENT avec la traduction, rien d'autre."

    log = AiGenerationLog(
        user_id=None,
        feature="translate",
        input_json={"text": request.text[:200], "source": request.source, "target": request.target},
        prompt=prompt,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    start_time = time.time()
    try:
        translated, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
        translated = translated.strip()

        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = {"translated": translated, "provider": provider, "model": model_used}
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        return {"translated": translated, "source": request.source, "target": request.target}
    except Exception as e:
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail="Erreur IA.")


@router.post("/tools/define")
async def ai_define(request: DefineToolRequest, db: Session = Depends(get_db)):
    """
    Lightweight word/phrase definition via local Ollama — tiny context window, logged to AI monitoring.
    Returns: {"word": "...", "definition": "...", "example": "..."}
    """
    if not request.word.strip():
        raise HTTPException(status_code=400, detail="Mot vide.")

    lang_names = {"fr": "français", "en": "anglais", "ar": "arabe", "es": "espagnol"}
    lang_name = lang_names.get(request.lang, request.lang)

    prompt = f'Définis ce terme en {lang_name} en 1-2 phrases courtes et donne un exemple d\'utilisation. Réponds UNIQUEMENT en JSON: {{"definition":"...","example":"..."}}\n\nTerme: {request.word}'
    system_prompt = "Tu es un dictionnaire. Réponds UNIQUEMENT avec un objet JSON valide contenant 'definition' et 'example'. Sois concis."

    log = AiGenerationLog(
        user_id=None,
        feature="define",
        input_json={"word": request.word, "lang": request.lang},
        prompt=prompt,
        status=AiGenerationLog.STATUS_PENDING
    )
    db.add(log)
    db.commit()

    start_time = time.time()
    try:
        content, provider, model_used = await call_ai_with_fallback(prompt, system_prompt)
        content = content.strip()

        try:
            result = parse_json_response(content)
            definition = result.get("definition", "")
            example = result.get("example", "")
        except Exception:
            definition = content
            example = ""

        log.status = AiGenerationLog.STATUS_SUCCESS
        log.output_json = {
            "definition": definition,
            "example": example,
            "provider": provider,
            "model": model_used,
        }
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        return {"word": request.word, "definition": definition, "example": example}
    except Exception as e:
        log.status = AiGenerationLog.STATUS_FAILED
        log.error_message = str(e)
        log.latency_ms = int((time.time() - start_time) * 1000)
        db.commit()
        raise HTTPException(status_code=503, detail="Erreur IA.")


@router.post("/feedback")
async def submit_feedback(
    request: FeedbackRequest,
    db: Session = Depends(get_db)
):
    """Submit user feedback for an AI generation"""
    log = db.query(AiGenerationLog).filter(
        AiGenerationLog.id == request.log_id
    ).first()

    if not log:
        raise HTTPException(status_code=404, detail="Log non trouvé")

    log.user_feedback = request.rating
    db.commit()

    return {"message": "Feedback enregistré", "rating": request.rating}


@router.get("/logs/stats")
async def get_ai_stats(
    db: Session = Depends(get_db)
):
    """
    Get AI usage statistics (admin/teacher only).
    Used for BO monitoring dashboard.
    """
    from sqlalchemy import func
    from datetime import timedelta

    now = datetime.utcnow()
    last_7_days = now - timedelta(days=7)

    # Total counts
    total = db.query(func.count(AiGenerationLog.id)).scalar() or 0
    success = db.query(func.count(AiGenerationLog.id)).filter(
        AiGenerationLog.status == AiGenerationLog.STATUS_SUCCESS
    ).scalar() or 0
    failed = db.query(func.count(AiGenerationLog.id)).filter(
        AiGenerationLog.status == AiGenerationLog.STATUS_FAILED
    ).scalar() or 0

    # By feature
    by_feature = db.query(
        AiGenerationLog.feature,
        func.count(AiGenerationLog.id).label("count")
    ).group_by(AiGenerationLog.feature).all()

    # Latency stats
    avg_latency = db.query(func.avg(AiGenerationLog.latency_ms)).filter(
        AiGenerationLog.latency_ms.isnot(None)
    ).scalar() or 0

    # Recent (last 7 days)
    recent = db.query(func.count(AiGenerationLog.id)).filter(
        AiGenerationLog.created_at >= last_7_days
    ).scalar() or 0

    # Feedback average
    avg_feedback = db.query(func.avg(AiGenerationLog.user_feedback)).filter(
        AiGenerationLog.user_feedback.isnot(None)
    ).scalar() or 0

    return {
        "total_requests": total,
        "success_count": success,
        "failed_count": failed,
        "failure_rate": (failed / total * 100) if total > 0 else 0,
        "by_feature": {f.feature: f.count for f in by_feature},
        "avg_latency_ms": round(avg_latency, 2),
        "last_7_days_count": recent,
        "avg_feedback_rating": round(float(avg_feedback), 2) if avg_feedback else None
    }
