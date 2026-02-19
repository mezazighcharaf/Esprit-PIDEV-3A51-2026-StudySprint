"""
Training schemas - Quiz, QuizAttempt
"""
from pydantic import BaseModel, Field
from typing import Optional, List, Dict, Any
from datetime import datetime
from enum import Enum


class Difficulty(str, Enum):
    EASY = "EASY"
    MEDIUM = "MEDIUM"
    HARD = "HARD"


# Question schemas (for JSON structure)
class QuizChoice(BaseModel):
    """Quiz choice option"""
    text: str
    is_correct: Optional[bool] = None  # Hidden from students


class QuizQuestion(BaseModel):
    """Quiz question"""
    text: str
    choices: List[QuizChoice]
    correct_index: Optional[int] = None  # Hidden from students
    explanation: Optional[str] = None


class QuizQuestionStudent(BaseModel):
    """Question shown to students (no answers)"""
    index: int
    text: str
    choices: List[str]  # Only text, no is_correct


# Quiz schemas
class QuizBase(BaseModel):
    """Base quiz fields"""
    title: str = Field(..., min_length=1, max_length=255)
    difficulty: Difficulty = Difficulty.MEDIUM
    subject_id: Optional[int] = None
    chapter_id: Optional[int] = None


class QuizCreate(QuizBase):
    """Quiz creation payload"""
    questions: List[QuizQuestion]
    is_published: bool = False


class QuizUpdate(BaseModel):
    """Quiz update payload"""
    title: Optional[str] = Field(None, min_length=1, max_length=255)
    difficulty: Optional[Difficulty] = None
    questions: Optional[List[QuizQuestion]] = None
    is_published: Optional[bool] = None


class QuizResponse(QuizBase):
    """Quiz response (for listing)"""
    id: int
    owner_id: int
    is_published: bool
    questions_count: int
    created_at: datetime

    class Config:
        from_attributes = True


class QuizDetailResponse(QuizResponse):
    """Quiz detail (with questions for owner/admin)"""
    questions: List[QuizQuestion] = []


# Attempt schemas
class QuizAttemptStart(BaseModel):
    """Start attempt response"""
    attempt_id: int
    quiz_id: int
    quiz_title: str
    total_questions: int
    started_at: datetime
    questions: List[QuizQuestionStudent]


class QuizAnswer(BaseModel):
    """Single answer submission"""
    question_index: int
    selected_choice: int  # Index of selected choice


class QuizSubmit(BaseModel):
    """Submit quiz answers"""
    answers: Dict[str, str]  # {"0": "1", "1": "0"} - question_index: choice_index

    class Config:
        json_schema_extra = {
            "example": {
                "answers": {
                    "0": "0",
                    "1": "2",
                    "2": "1"
                }
            }
        }


class QuizResultDetail(BaseModel):
    """Detailed result for one question"""
    question_index: int
    question_text: str
    user_answer: Optional[int] = None
    correct_answer: int
    is_correct: bool
    explanation: Optional[str] = None


class QuizResultResponse(BaseModel):
    """Quiz attempt result"""
    attempt_id: int
    quiz_id: int
    score: float
    correct_count: int
    total_questions: int
    passed: bool
    duration_seconds: Optional[int] = None
    completed_at: datetime
    details: List[QuizResultDetail] = []


class QuizAttemptResponse(BaseModel):
    """Quiz attempt history item"""
    id: int
    quiz_id: int
    quiz_title: str
    score: Optional[float] = None
    correct_count: int
    total_questions: int
    started_at: datetime
    completed_at: Optional[datetime] = None
    is_completed: bool

    class Config:
        from_attributes = True


class QuizStatsResponse(BaseModel):
    """Quiz statistics (for BO)"""
    quiz_id: int
    total_attempts: int
    completed_attempts: int
    average_score: Optional[float] = None
    pass_rate: Optional[float] = None
    avg_duration_seconds: Optional[int] = None
