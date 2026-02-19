"""
Flashcard schemas - FlashcardDeck, Flashcard, FlashcardReviewState
"""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime, date
from enum import Enum


class ReviewButton(str, Enum):
    """SM-2 review quality buttons"""
    AGAIN = "again"  # Quality 0 - Complete failure
    HARD = "hard"    # Quality 3 - Correct with difficulty
    GOOD = "good"    # Quality 4 - Correct after hesitation
    EASY = "easy"    # Quality 5 - Perfect response


# Flashcard schemas
class FlashcardBase(BaseModel):
    """Base flashcard fields"""
    front: str = Field(..., min_length=1, max_length=2000)
    back: str = Field(..., min_length=1, max_length=2000)
    hint: Optional[str] = Field(None, max_length=500)


class FlashcardCreate(FlashcardBase):
    """Flashcard creation payload"""
    position: int = 0


class FlashcardUpdate(BaseModel):
    """Flashcard update payload"""
    front: Optional[str] = Field(None, min_length=1, max_length=2000)
    back: Optional[str] = Field(None, min_length=1, max_length=2000)
    hint: Optional[str] = Field(None, max_length=500)
    position: Optional[int] = None


class FlashcardResponse(FlashcardBase):
    """Flashcard response"""
    id: int
    deck_id: int
    position: int
    created_at: datetime

    class Config:
        from_attributes = True


# Review state schemas
class ReviewStateResponse(BaseModel):
    """Review state response"""
    id: int
    flashcard_id: int
    repetitions: int
    interval_days: int
    ease_factor: float
    due_at: date
    last_reviewed_at: Optional[datetime] = None
    is_due: bool = False

    class Config:
        from_attributes = True


class ReviewGrade(BaseModel):
    """Grade a card review"""
    quality: ReviewButton

    class Config:
        json_schema_extra = {
            "example": {
                "quality": "good"
            }
        }


class ReviewGradeResponse(BaseModel):
    """Response after grading"""
    state_id: int
    new_repetitions: int
    new_ease_factor: float
    new_interval_days: int
    next_due_at: date
    estimated_next_reviews: dict  # {again: date, hard: date, good: date, easy: date}


class CardToReview(BaseModel):
    """Card ready for review"""
    state_id: int
    flashcard: FlashcardResponse
    review_state: ReviewStateResponse


# Deck schemas
class DeckBase(BaseModel):
    """Base deck fields"""
    title: str = Field(..., min_length=1, max_length=255)
    subject_id: Optional[int] = None
    chapter_id: Optional[int] = None


class DeckCreate(DeckBase):
    """Deck creation payload"""
    flashcards: List[FlashcardCreate] = []
    is_published: bool = False


class DeckUpdate(BaseModel):
    """Deck update payload"""
    title: Optional[str] = Field(None, min_length=1, max_length=255)
    subject_id: Optional[int] = None
    chapter_id: Optional[int] = None
    is_published: Optional[bool] = None


class DeckResponse(DeckBase):
    """Deck response"""
    id: int
    owner_id: int
    is_published: bool
    cards_count: int = 0
    created_at: datetime

    class Config:
        from_attributes = True


class DeckDetailResponse(DeckResponse):
    """Deck with flashcards"""
    flashcards: List[FlashcardResponse] = []


class DeckReviewResponse(BaseModel):
    """Review session for a deck"""
    deck_id: int
    deck_title: str
    cards_to_review: List[CardToReview]
    stats: "DeckReviewStats"


class DeckReviewStats(BaseModel):
    """Review statistics for a deck"""
    total_cards: int
    due_today: int
    new_cards: int
    mastered_cards: int  # interval_days > 21
    review_streak: int = 0


class DeckStatsResponse(BaseModel):
    """Overall deck statistics"""
    deck_id: int
    total_cards: int
    cards_reviewed: int
    average_ease_factor: float
    next_review_dates: dict  # Distribution of due dates
