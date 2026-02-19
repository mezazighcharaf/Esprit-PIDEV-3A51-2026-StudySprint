"""
User models - Maps to Symfony User and UserProfile entities
"""
from sqlalchemy import Column, Integer, String, JSON, DateTime, Text, ForeignKey
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database import Base


class User(Base):
    """Maps to Symfony App.Entity.User"""
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    email = Column(String(180), unique=True, nullable=False, index=True)
    password = Column(String(255), nullable=False)
    roles = Column(JSON, default=list)
    full_name = Column("full_name", String(255), nullable=False)
    user_type = Column("user_type", String(50), default="STUDENT")
    created_at = Column("created_at", DateTime, default=datetime.utcnow)
    updated_at = Column("updated_at", DateTime, nullable=True, onupdate=datetime.utcnow)

    # Relationships
    profile = relationship("UserProfile", back_populates="user", uselist=False)
    subjects = relationship("Subject", back_populates="created_by")
    revision_plans = relationship("RevisionPlan", back_populates="user")
    quiz_attempts = relationship("QuizAttempt", back_populates="user")
    flashcard_review_states = relationship("FlashcardReviewState", back_populates="user")

    def get_roles(self) -> list:
        """Get roles with ROLE_USER always included"""
        roles = self.roles or []
        if "ROLE_USER" not in roles:
            roles.append("ROLE_USER")
        return list(set(roles))

    def is_admin(self) -> bool:
        return "ROLE_ADMIN" in self.get_roles()


class UserProfile(Base):
    """Maps to Symfony App.Entity.UserProfile"""
    __tablename__ = "user_profiles"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), unique=True)
    level = Column(String(100), nullable=True)
    specialty = Column(String(255), nullable=True)
    bio = Column(Text, nullable=True)
    avatar_url = Column("avatar_url", String(500), nullable=True)
    ai_suggested_bio = Column("ai_suggested_bio", Text, nullable=True)
    ai_suggested_goals = Column("ai_suggested_goals", Text, nullable=True)
    ai_suggested_routine = Column("ai_suggested_routine", Text, nullable=True)

    # Relationships
    user = relationship("User", back_populates="profile")
