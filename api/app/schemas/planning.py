"""
Planning schemas - RevisionPlan and PlanTask
"""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime, date
from enum import Enum


class PlanStatus(str, Enum):
    DRAFT = "DRAFT"
    ACTIVE = "ACTIVE"
    DONE = "DONE"


class TaskType(str, Enum):
    REVISION = "REVISION"
    QUIZ = "QUIZ"
    FLASHCARD = "FLASHCARD"
    CUSTOM = "CUSTOM"


class TaskStatus(str, Enum):
    TODO = "TODO"
    DOING = "DOING"
    DONE = "DONE"


# Task schemas
class TaskBase(BaseModel):
    """Base task fields"""
    title: str = Field(..., min_length=1, max_length=255)
    task_type: TaskType = TaskType.REVISION
    start_at: datetime
    end_at: datetime
    priority: int = Field(default=1, ge=1, le=3)
    notes: Optional[str] = None


class TaskCreate(TaskBase):
    """Task creation payload"""
    status: TaskStatus = TaskStatus.TODO


class TaskUpdate(BaseModel):
    """Task update payload"""
    title: Optional[str] = Field(None, min_length=1, max_length=255)
    task_type: Optional[TaskType] = None
    start_at: Optional[datetime] = None
    end_at: Optional[datetime] = None
    status: Optional[TaskStatus] = None
    priority: Optional[int] = Field(None, ge=1, le=3)
    notes: Optional[str] = None


class TaskStatusUpdate(BaseModel):
    """Quick status update"""
    status: TaskStatus


class TaskResponse(TaskBase):
    """Task response"""
    id: int
    plan_id: int
    status: TaskStatus
    created_at: datetime
    duration_minutes: int = 0

    class Config:
        from_attributes = True


# Plan schemas
class PlanBase(BaseModel):
    """Base plan fields"""
    title: str = Field(..., min_length=1, max_length=255)
    start_date: date
    end_date: date
    subject_id: Optional[int] = None


class PlanCreate(PlanBase):
    """Plan creation payload"""
    status: PlanStatus = PlanStatus.DRAFT


class PlanUpdate(BaseModel):
    """Plan update payload"""
    title: Optional[str] = Field(None, min_length=1, max_length=255)
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    status: Optional[PlanStatus] = None
    subject_id: Optional[int] = None


class PlanResponse(PlanBase):
    """Plan response"""
    id: int
    user_id: int
    status: PlanStatus
    generated_by_ai: bool = False
    created_at: datetime
    tasks_count: int = 0

    class Config:
        from_attributes = True


class PlanDetailResponse(PlanResponse):
    """Plan with tasks"""
    tasks: List[TaskResponse] = []


class PlanGenerateRequest(BaseModel):
    """Auto-generate plan request"""
    subject_id: int
    start_date: date
    end_date: date
    sessions_per_day: int = Field(default=2, ge=1, le=5)
    skip_weekends: bool = False
    replace_existing: bool = False

    class Config:
        json_schema_extra = {
            "example": {
                "subject_id": 1,
                "start_date": "2026-02-10",
                "end_date": "2026-02-28",
                "sessions_per_day": 2,
                "skip_weekends": True,
                "replace_existing": False
            }
        }


class PlanOverlapResponse(BaseModel):
    """Overlap detection response"""
    has_overlap: bool
    existing_plan: Optional[PlanResponse] = None
    message: str


class PlanStatsResponse(BaseModel):
    """Plan statistics"""
    total_tasks: int
    todo_count: int
    doing_count: int
    done_count: int
    total_minutes: int
    completion_rate: float
