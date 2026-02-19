"""
Subject and Chapter schemas
"""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime


class ChapterBase(BaseModel):
    """Base chapter fields"""
    title: str = Field(..., min_length=1, max_length=255)
    order_no: int = Field(default=1, ge=1)
    summary: Optional[str] = None
    content: Optional[str] = None


class ChapterCreate(ChapterBase):
    """Chapter creation payload"""
    pass


class ChapterUpdate(BaseModel):
    """Chapter update payload"""
    title: Optional[str] = Field(None, min_length=1, max_length=255)
    order_no: Optional[int] = Field(None, ge=1)
    summary: Optional[str] = None
    content: Optional[str] = None


class ChapterResponse(ChapterBase):
    """Chapter response"""
    id: int
    subject_id: int
    created_at: datetime

    class Config:
        from_attributes = True


class ChapterReorder(BaseModel):
    """Reorder chapter request"""
    new_order_no: int = Field(..., ge=1)


# Subject schemas
class SubjectBase(BaseModel):
    """Base subject fields"""
    name: str = Field(..., min_length=1, max_length=255)
    code: str = Field(..., min_length=2, max_length=50, pattern=r'^[A-Z0-9]+$')
    description: Optional[str] = None


class SubjectCreate(SubjectBase):
    """Subject creation payload"""
    class Config:
        json_schema_extra = {
            "example": {
                "name": "Physique Générale",
                "code": "PHYS101",
                "description": "Introduction à la physique classique"
            }
        }


class SubjectUpdate(BaseModel):
    """Subject update payload"""
    name: Optional[str] = Field(None, min_length=1, max_length=255)
    code: Optional[str] = Field(None, min_length=2, max_length=50, pattern=r'^[A-Z0-9]+$')
    description: Optional[str] = None


class SubjectResponse(SubjectBase):
    """Subject response"""
    id: int
    created_by_id: Optional[int] = None
    created_at: datetime
    chapters_count: int = 0

    class Config:
        from_attributes = True


class SubjectDetailResponse(SubjectResponse):
    """Subject with chapters"""
    chapters: List[ChapterResponse] = []


class CreatorInfo(BaseModel):
    """Creator basic info"""
    id: int
    full_name: str

    class Config:
        from_attributes = True


class SubjectWithCreatorResponse(SubjectResponse):
    """Subject with creator info"""
    created_by: Optional[CreatorInfo] = None
