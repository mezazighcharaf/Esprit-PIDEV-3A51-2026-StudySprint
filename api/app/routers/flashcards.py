"""
Flashcards Router - Decks and SM-2 Review
"""
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from typing import Optional
from datetime import date, datetime

from app.database import get_db
from app.models.user import User
from app.models.flashcard import FlashcardDeck, Flashcard, FlashcardReviewState
from app.schemas.flashcard import (
    DeckResponse, DeckDetailResponse, FlashcardResponse,
    ReviewGrade, ReviewGradeResponse, ReviewStateResponse,
    CardToReview, DeckReviewResponse, DeckReviewStats
)
from app.schemas.common import PaginatedResponse, PaginationMeta
from app.dependencies import get_current_user, get_teacher_or_admin
from app.services.sm2_scheduler import sm2_service

router = APIRouter(prefix="/training/decks", tags=["Training - Flashcards"])


@router.get("", response_model=PaginatedResponse[DeckResponse])
async def list_decks(
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    subject_id: Optional[int] = Query(None),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    List published flashcard decks.
    """
    query = db.query(FlashcardDeck).filter(FlashcardDeck.is_published == True)

    if subject_id:
        query = query.filter(FlashcardDeck.subject_id == subject_id)

    total = query.count()
    decks = query.order_by(FlashcardDeck.created_at.desc()).offset((page - 1) * limit).limit(limit).all()

    data = []
    for d in decks:
        resp = DeckResponse.model_validate(d)
        resp.cards_count = len(d.flashcards)
        data.append(resp)

    total_pages = (total + limit - 1) // limit

    return PaginatedResponse(
        data=data,
        pagination=PaginationMeta(
            current_page=page,
            total_pages=total_pages,
            total_items=total,
            items_per_page=limit,
            has_next=page < total_pages,
            has_prev=page > 1
        )
    )


@router.get("/{deck_id}", response_model=DeckDetailResponse)
async def get_deck(
    deck_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get deck with flashcards.
    """
    deck = db.query(FlashcardDeck).filter(FlashcardDeck.id == deck_id).first()
    if not deck:
        raise HTTPException(status_code=404, detail="Deck not found")

    if not deck.is_published and deck.owner_id != current_user.id and not current_user.is_admin():
        raise HTTPException(status_code=403, detail="Deck not published")

    response = DeckDetailResponse.model_validate(deck)
    response.cards_count = len(deck.flashcards)
    response.flashcards = [FlashcardResponse.model_validate(f) for f in deck.flashcards]

    return response


@router.get("/{deck_id}/review", response_model=DeckReviewResponse)
async def get_review_cards(
    deck_id: int,
    limit: int = Query(20, ge=1, le=50),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get cards due for review in a deck.

    Uses SM-2 algorithm to determine which cards need review.
    """
    deck = db.query(FlashcardDeck).filter(FlashcardDeck.id == deck_id).first()
    if not deck:
        raise HTTPException(status_code=404, detail="Deck not found")

    if not deck.is_published and deck.owner_id != current_user.id:
        raise HTTPException(status_code=403, detail="Deck not published")

    # Get all flashcards in deck
    flashcards = deck.flashcards

    cards_to_review = []
    due_count = 0
    new_count = 0
    mastered_count = 0

    for flashcard in flashcards:
        # Get or create review state
        state = db.query(FlashcardReviewState).filter(
            FlashcardReviewState.user_id == current_user.id,
            FlashcardReviewState.flashcard_id == flashcard.id
        ).first()

        if not state:
            # Create initial state for new card
            state = sm2_service.create_initial_state(current_user, flashcard)
            db.add(state)
            db.flush()
            new_count += 1

        # Check if mastered (interval > 21 days)
        if state.interval_days > 21:
            mastered_count += 1

        # Check if due
        if state.due_at <= date.today():
            due_count += 1

            if len(cards_to_review) < limit:
                # Get next review dates
                next_dates = sm2_service.get_next_review_dates(state)

                cards_to_review.append(CardToReview(
                    state_id=state.id,
                    flashcard=FlashcardResponse.model_validate(flashcard),
                    review_state=ReviewStateResponse(
                        id=state.id,
                        flashcard_id=flashcard.id,
                        repetitions=state.repetitions,
                        interval_days=state.interval_days,
                        ease_factor=float(state.ease_factor),
                        due_at=state.due_at,
                        last_reviewed_at=state.last_reviewed_at,
                        is_due=True
                    )
                ))

    db.commit()

    return DeckReviewResponse(
        deck_id=deck.id,
        deck_title=deck.title,
        cards_to_review=cards_to_review,
        stats=DeckReviewStats(
            total_cards=len(flashcards),
            due_today=due_count,
            new_cards=new_count,
            mastered_cards=mastered_count,
            review_streak=0  # TODO: Calculate streak
        )
    )


# Review router (for grading)
review_router = APIRouter(prefix="/training/review", tags=["Training - Review"])


@review_router.post("/{state_id}/grade", response_model=ReviewGradeResponse)
async def grade_card(
    state_id: int,
    request: ReviewGrade,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Grade a flashcard review using SM-2 algorithm.

    - **quality**: again, hard, good, or easy
    """
    state = db.query(FlashcardReviewState).filter(
        FlashcardReviewState.id == state_id
    ).first()

    if not state:
        raise HTTPException(status_code=404, detail="Review state not found")

    if state.user_id != current_user.id:
        raise HTTPException(status_code=403, detail="Not your review state")

    # Convert button to quality
    quality = sm2_service.button_to_quality(request.quality.value)

    # Get predicted dates before update (for response)
    next_dates_before = sm2_service.get_next_review_dates(state)

    # Apply SM-2 algorithm
    state = sm2_service.apply_review(state, quality)

    db.commit()
    db.refresh(state)

    # Get new predicted dates
    next_dates = sm2_service.get_next_review_dates(state)

    return ReviewGradeResponse(
        state_id=state.id,
        new_repetitions=state.repetitions,
        new_ease_factor=float(state.ease_factor),
        new_interval_days=state.interval_days,
        next_due_at=state.due_at,
        estimated_next_reviews={
            k: v.isoformat() for k, v in next_dates.items()
        }
    )


@router.get("/{deck_id}/stats")
async def get_deck_stats(
    deck_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get user's review statistics for a deck.
    """
    deck = db.query(FlashcardDeck).filter(FlashcardDeck.id == deck_id).first()
    if not deck:
        raise HTTPException(status_code=404, detail="Deck not found")

    # Get all review states for user and deck
    states = db.query(FlashcardReviewState).join(Flashcard).filter(
        FlashcardReviewState.user_id == current_user.id,
        Flashcard.deck_id == deck_id
    ).all()

    total_cards = len(deck.flashcards)
    reviewed_cards = len(states)

    avg_ef = sum(float(s.ease_factor) for s in states) / len(states) if states else 2.5

    # Distribution of due dates
    due_distribution = {}
    for state in states:
        due_str = state.due_at.isoformat()
        due_distribution[due_str] = due_distribution.get(due_str, 0) + 1

    return {
        "deck_id": deck_id,
        "total_cards": total_cards,
        "cards_reviewed": reviewed_cards,
        "average_ease_factor": round(avg_ef, 2),
        "next_review_dates": due_distribution
    }
