"""
Planning models - Maps to Symfony RevisionPlan and PlanTask entities
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, Date, ForeignKey, Boolean, JSON, Numeric
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database import Base


class RevisionPlan(Base):
    """Maps to Symfony App.Entity.RevisionPlan"""
    __tablename__ = "revision_plans"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    user_id = Column("user_id", Integer, ForeignKey("users.id"), nullable=False)
    subject_id = Column("subject_id", Integer, ForeignKey("subjects.id"), nullable=True)
    start_date = Column("start_date", Date, nullable=False)
    end_date = Column("end_date", Date, nullable=False)
    status = Column(String(20), default="DRAFT")  # DRAFT, ACTIVE, DONE
    generated_by_ai = Column("generated_by_ai", Boolean, default=False)
    ai_meta = Column("ai_meta", JSON, nullable=True)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    user = relationship("User", back_populates="revision_plans")
    subject = relationship("Subject", back_populates="revision_plans")
    tasks = relationship("PlanTask", back_populates="plan", cascade="all, delete-orphan")


class PlanTask(Base):
    """Maps to Symfony App.Entity.PlanTask"""
    __tablename__ = "plan_tasks"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String(255), nullable=False)
    plan_id = Column("plan_id", Integer, ForeignKey("revision_plans.id", ondelete="CASCADE"), nullable=False)
    task_type = Column("task_type", String(50), default="REVISION")  # REVISION, QUIZ, FLASHCARD, CUSTOM
    start_at = Column("start_at", DateTime, nullable=False)
    end_at = Column("end_at", DateTime, nullable=False)
    status = Column(String(20), default="TODO")  # TODO, DOING, DONE
    priority = Column(Integer, default=1)  # 1=high, 2=medium, 3=low
    notes = Column(Text, nullable=True)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    plan = relationship("RevisionPlan", back_populates="tasks")

    @property
    def duration_minutes(self) -> int:
        """Calculate duration in minutes"""
        if self.start_at and self.end_at:
            delta = self.end_at - self.start_at
            return int(delta.total_seconds() / 60)
        return 0
