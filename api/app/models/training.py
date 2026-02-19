"""
Training models - Maps to Symfony Quiz, QuizAttempt, QuizAttemptAnswer entities
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, ForeignKey, Boolean, JSON, Numeric
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database import Base


class Quiz(Base):
    """Maps to Symfony App.Entity.Quiz"""
    __tablename__ = "quizzes"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    owner_id = Column("owner_id", Integer, ForeignKey("users.id"), nullable=False)
    subject_id = Column("subject_id", Integer, ForeignKey("subjects.id"), nullable=True)
    chapter_id = Column("chapter_id", Integer, ForeignKey("chapters.id"), nullable=True)
    difficulty = Column(String(20), default="MEDIUM")  # EASY, MEDIUM, HARD
    template_key = Column("template_key", String(100), nullable=True)
    questions = Column(JSON, nullable=False, default=list)  # Array of question objects
    is_published = Column("is_published", Boolean, default=False)
    generated_by_ai = Column("generated_by_ai", Boolean, default=False)
    ai_meta = Column("ai_meta", JSON, nullable=True)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)
    updated_at = Column("updated_at", DateTime, nullable=True, onupdate=datetime.utcnow)

    # Relationships
    subject = relationship("Subject", back_populates="quizzes")
    chapter = relationship("Chapter", back_populates="quizzes")
    attempts = relationship("QuizAttempt", back_populates="quiz", cascade="all, delete-orphan")

    @property
    def questions_count(self) -> int:
        return len(self.questions) if self.questions else 0


class QuizAttempt(Base):
    """Maps to Symfony App.Entity.QuizAttempt"""
    __tablename__ = "quiz_attempts"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column("user_id", Integer, ForeignKey("users.id"), nullable=False)
    quiz_id = Column("quiz_id", Integer, ForeignKey("quizzes.id", ondelete="CASCADE"), nullable=False)
    started_at = Column("started_at", DateTime, default=datetime.utcnow)
    completed_at = Column("completed_at", DateTime, nullable=True)
    score = Column(Numeric(5, 2), nullable=True)
    total_questions = Column("total_questions", Integer, nullable=False)
    correct_count = Column("correct_count", Integer, default=0)
    duration_seconds = Column("duration_seconds", Integer, nullable=True)

    # Relationships
    user = relationship("User", back_populates="quiz_attempts")
    quiz = relationship("Quiz", back_populates="attempts")
    answers = relationship("QuizAttemptAnswer", back_populates="attempt", cascade="all, delete-orphan")

    @property
    def is_completed(self) -> bool:
        return self.completed_at is not None


class QuizAttemptAnswer(Base):
    """Maps to Symfony App.Entity.QuizAttemptAnswer"""
    __tablename__ = "quiz_attempt_answers"

    id = Column(Integer, primary_key=True, index=True)
    attempt_id = Column("attempt_id", Integer, ForeignKey("quiz_attempts.id", ondelete="CASCADE"), nullable=False)
    question_index = Column("question_index", Integer, nullable=False)
    selected_choice_key = Column("selected_choice_key", String(50), nullable=True)
    is_correct = Column("is_correct", Boolean, default=False)

    # Relationships
    attempt = relationship("QuizAttempt", back_populates="answers")
