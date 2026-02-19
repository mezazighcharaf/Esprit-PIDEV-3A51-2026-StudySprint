"""
AI models - Maps to Symfony AiModel and AiGenerationLog entities
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, ForeignKey, JSON
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database import Base


class AiModel(Base):
    """Maps to Symfony App.Entity.AiModel"""
    __tablename__ = "ai_models"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    provider = Column(String(100), nullable=False)  # ollama, openai, anthropic
    base_url = Column("base_url", String(500), nullable=False)
    is_default = Column("is_default", Integer, default=0)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    generation_logs = relationship("AiGenerationLog", back_populates="model")


class AiGenerationLog(Base):
    """Maps to Symfony App.Entity.AiGenerationLog - Tracks all AI operations"""
    __tablename__ = "ai_generation_logs"

    # Status constants
    STATUS_PENDING = "pending"
    STATUS_SUCCESS = "success"
    STATUS_FAILED = "failed"

    # Feature constants
    FEATURE_QUIZ = "quiz"
    FEATURE_FLASHCARD = "flashcard"
    FEATURE_REVISION_PLAN = "revision_plan"
    FEATURE_SUMMARY = "summary"
    FEATURE_PROFILE = "profile"
    FEATURE_POST_SUMMARY = "post_summary"
    FEATURE_PLANNING_SUGGEST = "planning_suggest"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column("user_id", Integer, ForeignKey("users.id", ondelete="SET NULL"), nullable=True)
    model_id = Column("model_id", Integer, ForeignKey("ai_models.id", ondelete="SET NULL"), nullable=True)
    feature = Column(String(100), nullable=False)
    input_json = Column("input_json", JSON, default=dict)
    prompt = Column(Text, nullable=False)
    output_json = Column("output_json", JSON, nullable=True)
    status = Column(String(50), default=STATUS_PENDING)
    error_message = Column("error_message", Text, nullable=True)
    latency_ms = Column("latency_ms", Integer, nullable=True)
    idempotency_key = Column("idempotency_key", String(100), nullable=True, index=True)
    user_feedback = Column("user_feedback", Integer, nullable=True)  # 1-5 rating
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    model = relationship("AiModel", back_populates="generation_logs")
