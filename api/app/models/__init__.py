# SQLAlchemy models matching Symfony entities
from app.models.user import User, UserProfile
from app.models.subject import Subject, Chapter
from app.models.planning import RevisionPlan, PlanTask
from app.models.group import StudyGroup, GroupMember, GroupPost
from app.models.training import Quiz, QuizAttempt, QuizAttemptAnswer
from app.models.flashcard import FlashcardDeck, Flashcard, FlashcardReviewState
from app.models.ai import AiModel, AiGenerationLog

__all__ = [
    "User", "UserProfile",
    "Subject", "Chapter",
    "RevisionPlan", "PlanTask",
    "StudyGroup", "GroupMember", "GroupPost",
    "Quiz", "QuizAttempt", "QuizAttemptAnswer",
    "FlashcardDeck", "Flashcard", "FlashcardReviewState",
    "AiModel", "AiGenerationLog"
]
