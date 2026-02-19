"""
Group models - Maps to Symfony StudyGroup, GroupMember, GroupPost entities
"""
from sqlalchemy import Column, Integer, String, Text, DateTime, ForeignKey, UniqueConstraint, JSON
from sqlalchemy.orm import relationship
from datetime import datetime
from app.database import Base


class StudyGroup(Base):
    """Maps to Symfony App.Entity.StudyGroup"""
    __tablename__ = "study_groups"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)
    privacy = Column(String(20), default="PUBLIC")  # PUBLIC, PRIVATE
    created_by_id = Column("created_by_id", Integer, ForeignKey("users.id"), nullable=False)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    members = relationship("GroupMember", back_populates="group", cascade="all, delete-orphan")
    posts = relationship("GroupPost", back_populates="group", cascade="all, delete-orphan")


class GroupMember(Base):
    """Maps to Symfony App.Entity.GroupMember"""
    __tablename__ = "group_members"

    id = Column(Integer, primary_key=True, index=True)
    group_id = Column("group_id", Integer, ForeignKey("study_groups.id", ondelete="CASCADE"), nullable=False)
    user_id = Column("user_id", Integer, ForeignKey("users.id"), nullable=False)
    member_role = Column("member_role", String(20), default="member")  # owner, admin, member
    joined_at = Column("joined_at", DateTime, default=datetime.utcnow)

    # Relationships
    group = relationship("StudyGroup", back_populates="members")

    __table_args__ = (
        UniqueConstraint('group_id', 'user_id', name='unique_group_member'),
    )


class GroupPost(Base):
    """Maps to Symfony App.Entity.GroupPost"""
    __tablename__ = "group_posts"

    id = Column(Integer, primary_key=True, index=True)
    group_id = Column("group_id", Integer, ForeignKey("study_groups.id", ondelete="CASCADE"), nullable=False)
    author_id = Column("author_id", Integer, ForeignKey("users.id"), nullable=False)
    parent_post_id = Column("parent_post_id", Integer, ForeignKey("group_posts.id"), nullable=True)
    post_type = Column("post_type", String(20), default="POST")  # POST, COMMENT
    title = Column(String(255), nullable=True)
    body = Column(Text, nullable=False)
    attachment_url = Column("attachment_url", String(500), nullable=True)
    ai_summary = Column("ai_summary", Text, nullable=True)
    ai_category = Column("ai_category", String(100), nullable=True)
    ai_tags = Column("ai_tags", JSON, nullable=True)
    created_at = Column("created_at", DateTime, default=datetime.utcnow)

    # Relationships
    group = relationship("StudyGroup", back_populates="posts")
    replies = relationship("GroupPost", backref="parent", remote_side=[id])
