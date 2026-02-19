"""
Subject and Chapter models - Maps to Symfony entities
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, ForeignKey, UniqueConstraint, JSON
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database import Base


class Subject(Base):
    """Maps to Symfony App.Entity.Subject"""
    __tablename__ = "subjects"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    code = Column(String(50), unique=True, nullable=False, index=True)
    description = Column(Text, nullable=True)
    created_by_id = Column("created_by_id", Integer, ForeignKey("users.id"))
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    created_by = relationship("User", back_populates="subjects")
    chapters = relationship("Chapter", back_populates="subject", order_by="Chapter.order_no")
    quizzes = relationship("Quiz", back_populates="subject")
    flashcard_decks = relationship("FlashcardDeck", back_populates="subject")
    revision_plans = relationship("RevisionPlan", back_populates="subject")


class Chapter(Base):
    """Maps to Symfony App.Entity.Chapter"""
    __tablename__ = "chapters"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    order_no = Column("order_no", Integer, nullable=False, default=1)
    summary = Column(Text, nullable=True)
    content = Column(Text, nullable=True)
    ai_summary = Column("ai_summary", Text, nullable=True)
    ai_key_points = Column("ai_key_points", JSON, nullable=True)
    ai_tags = Column("ai_tags", JSON, nullable=True)
    subject_id = Column(Integer, ForeignKey("subjects.id"), nullable=False)
    created_by_id = Column("created_by_id", Integer, ForeignKey("users.id"))
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    subject = relationship("Subject", back_populates="chapters")
    quizzes = relationship("Quiz", back_populates="chapter")
    flashcard_decks = relationship("FlashcardDeck", back_populates="chapter")

    __table_args__ = (
        UniqueConstraint('subject_id', 'order_no', name='unique_subject_order'),
    )
