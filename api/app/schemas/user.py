"""
User schemas
"""
from pydantic import BaseModel, EmailStr, Field
from typing import Optional, List
from datetime import datetime
from enum import Enum


class UserType(str, Enum):
    STUDENT = "STUDENT"
    TEACHER = "TEACHER"
    ADMIN = "ADMIN"


class UserRole(str, Enum):
    ROLE_USER = "ROLE_USER"
    ROLE_ADMIN = "ROLE_ADMIN"


class UserBase(BaseModel):
    """Base user fields"""
    email: EmailStr
    full_name: str = Field(..., min_length=2, max_length=120)
    user_type: UserType = UserType.STUDENT


class UserCreate(UserBase):
    """User creation payload"""
    password: str = Field(..., min_length=6, max_length=100)
    roles: List[UserRole] = [UserRole.ROLE_USER]

    class Config:
        json_schema_extra = {
            "example": {
                "email": "nouveau@example.com",
                "full_name": "Nouveau Utilisateur",
                "password": "motdepasse123",
                "user_type": "STUDENT",
                "roles": ["ROLE_USER"]
            }
        }


class UserUpdate(BaseModel):
    """User update payload (all fields optional)"""
    email: Optional[EmailStr] = None
    full_name: Optional[str] = Field(None, min_length=2, max_length=120)
    user_type: Optional[UserType] = None
    password: Optional[str] = Field(None, min_length=6, max_length=100)
    roles: Optional[List[UserRole]] = None


class UserResponse(BaseModel):
    """User response (no password!)"""
    id: int
    email: str
    full_name: str
    user_type: str
    roles: List[str]
    created_at: datetime
    updated_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class UserProfileBase(BaseModel):
    """User profile fields"""
    level: Optional[str] = None
    specialty: Optional[str] = None
    bio: Optional[str] = None
    avatar_url: Optional[str] = None


class UserProfileResponse(UserProfileBase):
    """User profile response"""
    id: int
    user_id: int

    class Config:
        from_attributes = True


class UserWithProfileResponse(UserResponse):
    """User with profile details"""
    profile: Optional[UserProfileResponse] = None
