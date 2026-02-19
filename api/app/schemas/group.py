"""
Group schemas - StudyGroup, GroupMember, GroupPost
"""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime
from enum import Enum


class GroupPrivacy(str, Enum):
    PUBLIC = "PUBLIC"
    PRIVATE = "PRIVATE"


class MemberRole(str, Enum):
    OWNER = "owner"
    ADMIN = "admin"
    MEMBER = "member"


class PostType(str, Enum):
    POST = "POST"
    COMMENT = "COMMENT"


# Author info for posts
class AuthorInfo(BaseModel):
    """Author basic info"""
    id: int
    full_name: str

    class Config:
        from_attributes = True


# Post schemas
class PostBase(BaseModel):
    """Base post fields"""
    title: Optional[str] = Field(None, max_length=255)
    body: str = Field(..., min_length=1, max_length=5000)


class PostCreate(PostBase):
    """Post creation payload"""
    class Config:
        json_schema_extra = {
            "example": {
                "title": "Question sur les intégrales",
                "body": "Bonjour, j'ai du mal à comprendre..."
            }
        }


class CommentCreate(BaseModel):
    """Comment creation payload"""
    body: str = Field(..., min_length=1, max_length=2000)


class PostResponse(BaseModel):
    """Post response"""
    id: int
    group_id: int
    post_type: PostType
    title: Optional[str] = None
    body: str
    author_id: int
    author: Optional[AuthorInfo] = None
    parent_post_id: Optional[int] = None
    created_at: datetime
    comments_count: int = 0

    class Config:
        from_attributes = True


class PostWithCommentsResponse(PostResponse):
    """Post with its comments"""
    comments: List[PostResponse] = []


# Member schemas
class MemberResponse(BaseModel):
    """Group member response"""
    id: int
    user_id: int
    member_role: MemberRole
    joined_at: datetime
    user: Optional[AuthorInfo] = None

    class Config:
        from_attributes = True


# Group schemas
class GroupBase(BaseModel):
    """Base group fields"""
    name: str = Field(..., min_length=1, max_length=255)
    description: Optional[str] = None
    privacy: GroupPrivacy = GroupPrivacy.PUBLIC


class GroupCreate(GroupBase):
    """Group creation payload"""
    class Config:
        json_schema_extra = {
            "example": {
                "name": "Groupe Maths Avancées",
                "description": "Pour les passionnés de mathématiques",
                "privacy": "PUBLIC"
            }
        }


class GroupUpdate(BaseModel):
    """Group update payload"""
    name: Optional[str] = Field(None, min_length=1, max_length=255)
    description: Optional[str] = None
    privacy: Optional[GroupPrivacy] = None


class GroupResponse(GroupBase):
    """Group response"""
    id: int
    created_by_id: int
    created_at: datetime
    members_count: int = 0
    posts_count: int = 0

    class Config:
        from_attributes = True


class GroupDetailResponse(GroupResponse):
    """Group with members and recent posts"""
    members: List[MemberResponse] = []
    is_member: bool = False
    user_role: Optional[MemberRole] = None


class GroupFeedResponse(BaseModel):
    """Group feed with paginated posts"""
    group: GroupResponse
    posts: List[PostWithCommentsResponse]
    pagination: "PaginationMeta"


# Import at end to avoid circular
from app.schemas.common import PaginationMeta
GroupFeedResponse.model_rebuild()
