# Pydantic schemas (DTOs)
from app.schemas.auth import Token, TokenData, LoginRequest, RefreshRequest
from app.schemas.user import UserCreate, UserUpdate, UserResponse, UserProfileResponse
from app.schemas.subject import SubjectCreate, SubjectUpdate, SubjectResponse, ChapterCreate, ChapterResponse
from app.schemas.planning import PlanCreate, PlanUpdate, PlanResponse, TaskCreate, TaskUpdate, TaskResponse, PlanGenerateRequest
from app.schemas.group import GroupCreate, GroupResponse, PostCreate, PostResponse, CommentCreate
from app.schemas.training import QuizResponse, QuizAttemptStart, QuizSubmit, QuizResultResponse
from app.schemas.flashcard import DeckResponse, FlashcardResponse, ReviewGrade, ReviewStateResponse
from app.schemas.common import PaginatedResponse, MessageResponse

__all__ = [
    # Auth
    "Token", "TokenData", "LoginRequest", "RefreshRequest",
    # User
    "UserCreate", "UserUpdate", "UserResponse", "UserProfileResponse",
    # Subject
    "SubjectCreate", "SubjectUpdate", "SubjectResponse", "ChapterCreate", "ChapterResponse",
    # Planning
    "PlanCreate", "PlanUpdate", "PlanResponse", "TaskCreate", "TaskUpdate", "TaskResponse", "PlanGenerateRequest",
    # Group
    "GroupCreate", "GroupResponse", "PostCreate", "PostResponse", "CommentCreate",
    # Training
    "QuizResponse", "QuizAttemptStart", "QuizSubmit", "QuizResultResponse",
    # Flashcard
    "DeckResponse", "FlashcardResponse", "ReviewGrade", "ReviewStateResponse",
    # Common
    "PaginatedResponse", "MessageResponse"
]
