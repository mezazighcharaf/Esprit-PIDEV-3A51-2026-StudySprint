"""
Flashcard models - Maps to Symfony FlashcardDeck, Flashcard, FlashcardReviewState entities
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, Date, ForeignKey, Boolean, JSON, Numeric, UniqueConstraint
from sqlalchemy.orm import relationship
from datetime import datetime, date
from app.database import Base


class FlashcardDeck(Base):
    """Maps to Symfony App.Entity.FlashcardDeck"""
    __tablename__ = "flashcard_decks"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    owner_id = Column("owner_id", Integer, ForeignKey("users.id"), nullable=False)
    subject_id = Column("subject_id", Integer, ForeignKey("subjects.id"), nullable=True)
    chapter_id = Column("chapter_id", Integer, ForeignKey("chapters.id"), nullable=True)
    template_key = Column("template_key", String(100), nullable=True)
    cards = Column(JSON, nullable=True)  # Legacy JSON cards
    is_published = Column("is_published", Boolean, default=False)
    generated_by_ai = Column("generated_by_ai", Boolean, default=False)
    ai_meta = Column("ai_meta", JSON, nullable=True)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    subject = relationship("Subject", back_populates="flashcard_decks")
    chapter = relationship("Chapter", back_populates="flashcard_decks")
    flashcards = relationship("Flashcard", back_populates="deck", cascade="all, delete-orphan", order_by="Flashcard.position")


class Flashcard(Base):
    """Maps to Symfony App.Entity.Flashcard"""
    __tablename__ = "flashcards"

    id = Column(Integer, primary_key=True, index=True)
    deck_id = Column("deck_id", Integer, ForeignKey("flashcard_decks.id", ondelete="CASCADE"), nullable=False)
    front = Column(Text, nullable=False)  # Question side
    back = Column(Text, nullable=False)   # Answer side
    hint = Column(Text, nullable=True)
    position = Column(Integer, default=0)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    deck = relationship("FlashcardDeck", back_populates="flashcards")
    review_states = relationship("FlashcardReviewState", back_populates="flashcard", cascade="all, delete-orphan")


class FlashcardReviewState(Base):
    """
    Maps to Symfony App.Entity.FlashcardReviewState
    Implements SM-2 Spaced Repetition Algorithm
    """
    __tablename__ = "flashcard_review_states"

    # Constants
    MIN_EASE_FACTOR = 1.3
    DEFAULT_EASE_FACTOR = 2.5

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column("user_id", Integer, ForeignKey("users.id"), nullable=False)
    flashcard_id = Column("flashcard_id", Integer, ForeignKey("flashcards.id", ondelete="CASCADE"), nullable=False)
    repetitions = Column(Integer, default=0)
    interval_days = Column("interval_days", Integer, default=1)
    ease_factor = Column("ease_factor", Numeric(4, 2), default=DEFAULT_EASE_FACTOR)
    due_at = Column("due_at", Date, nullable=False)
    last_reviewed_at = Column("last_reviewed_at", DateTime, nullable=True)

    # Relationships
    user = relationship("User", back_populates="flashcard_review_states")
    flashcard = relationship("Flashcard", back_populates="review_states")

    __table_args__ = (
        UniqueConstraint('user_id', 'flashcard_id', name='unique_user_flashcard'),
    )

    @property
    def is_due(self) -> bool:
        """Check if card is due for review"""
        return self.due_at <= date.today()

    @property
    def ease_factor_float(self) -> float:
        """Get ease factor as float"""
        return float(self.ease_factor) if self.ease_factor else self.DEFAULT_EASE_FACTOR
