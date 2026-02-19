"""
Training Router - Quizzes
"""
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from sqlalchemy import func
from typing import Optional
from datetime import datetime

from app.database import get_db
from app.models.user import User
from app.models.training import Quiz, QuizAttempt, QuizAttemptAnswer
from app.schemas.training import (
    QuizResponse, QuizDetailResponse, QuizAttemptStart, QuizQuestionStudent,
    QuizSubmit, QuizResultResponse, QuizAttemptResponse, QuizStatsResponse
)
from app.schemas.common import PaginatedResponse, PaginationMeta
from app.dependencies import get_current_user, get_teacher_or_admin, require_owner_or_admin
from app.services.quiz_scoring import quiz_scoring_service

router = APIRouter(prefix="/training/quizzes", tags=["Training - Quizzes"])


@router.get("", response_model=PaginatedResponse[QuizResponse])
async def list_quizzes(
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    subject_id: Optional[int] = Query(None),
    difficulty: Optional[str] = Query(None),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    List published quizzes.
    """
    query = db.query(Quiz).filter(Quiz.is_published == True)

    if subject_id:
        query = query.filter(Quiz.subject_id == subject_id)
    if difficulty:
        query = query.filter(Quiz.difficulty == difficulty)

    total = query.count()
    quizzes = query.order_by(Quiz.created_at.desc()).offset((page - 1) * limit).limit(limit).all()

    data = []
    for q in quizzes:
        resp = QuizResponse.model_validate(q)
        resp.questions_count = len(q.questions) if q.questions else 0
        data.append(resp)

    total_pages = (total + limit - 1) // limit

    return PaginatedResponse(
        data=data,
        pagination=PaginationMeta(
            current_page=page,
            total_pages=total_pages,
            total_items=total,
            items_per_page=limit,
            has_next=page < total_pages,
            has_prev=page > 1
        )
    )


@router.get("/history", response_model=list[QuizAttemptResponse])
async def get_attempt_history(
    limit: int = Query(20, ge=1, le=100),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get user's quiz attempt history.
    """
    attempts = db.query(QuizAttempt).filter(
        QuizAttempt.user_id == current_user.id
    ).order_by(QuizAttempt.started_at.desc()).limit(limit).all()

    data = []
    for a in attempts:
        quiz = db.query(Quiz).filter(Quiz.id == a.quiz_id).first()
        resp = QuizAttemptResponse(
            id=a.id,
            quiz_id=a.quiz_id,
            quiz_title=quiz.title if quiz else "Unknown",
            score=float(a.score) if a.score else None,
            correct_count=a.correct_count,
            total_questions=a.total_questions,
            started_at=a.started_at,
            completed_at=a.completed_at,
            is_completed=a.completed_at is not None
        )
        data.append(resp)

    return data


@router.get("/{quiz_id}", response_model=QuizResponse)
async def get_quiz(
    quiz_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get quiz details (without answers).
    """
    quiz = db.query(Quiz).filter(Quiz.id == quiz_id).first()
    if not quiz:
        raise HTTPException(status_code=404, detail="Quiz not found")

    if not quiz.is_published and quiz.owner_id != current_user.id and not current_user.is_admin():
        raise HTTPException(status_code=403, detail="Quiz not published")

    resp = QuizResponse.model_validate(quiz)
    resp.questions_count = len(quiz.questions) if quiz.questions else 0

    return resp


@router.post("/{quiz_id}/start", response_model=QuizAttemptStart)
async def start_quiz(
    quiz_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Start a quiz attempt.

    Returns questions without correct answers.
    """
    quiz = db.query(Quiz).filter(Quiz.id == quiz_id).first()
    if not quiz:
        raise HTTPException(status_code=404, detail="Quiz not found")

    if not quiz.is_published:
        raise HTTPException(status_code=403, detail="Quiz not published")

    # Check for incomplete attempt
    incomplete = db.query(QuizAttempt).filter(
        QuizAttempt.user_id == current_user.id,
        QuizAttempt.quiz_id == quiz_id,
        QuizAttempt.completed_at == None
    ).first()

    if incomplete:
        attempt = incomplete
    else:
        # Create new attempt
        attempt = QuizAttempt()
        attempt.user_id = current_user.id
        attempt.quiz_id = quiz_id
        attempt.total_questions = len(quiz.questions) if quiz.questions else 0
        attempt.started_at = datetime.utcnow()
        db.add(attempt)
        db.commit()
        db.refresh(attempt)

    # Prepare questions (without answers)
    questions = []
    for idx, q in enumerate(quiz.questions or []):
        choices = []
        for c in q.get("choices", []):
            if isinstance(c, dict):
                choices.append(c.get("text", str(c)))
            else:
                choices.append(str(c))

        questions.append(QuizQuestionStudent(
            index=idx,
            text=q.get("text", ""),
            choices=choices
        ))

    return QuizAttemptStart(
        attempt_id=attempt.id,
        quiz_id=quiz.id,
        quiz_title=quiz.title,
        total_questions=attempt.total_questions,
        started_at=attempt.started_at,
        questions=questions
    )


@router.post("/{quiz_id}/attempts/{attempt_id}/submit", response_model=QuizResultResponse)
async def submit_quiz(
    quiz_id: int,
    attempt_id: int,
    request: QuizSubmit,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Submit quiz answers.
    """
    attempt = db.query(QuizAttempt).filter(QuizAttempt.id == attempt_id).first()
    if not attempt:
        raise HTTPException(status_code=404, detail="Attempt not found")

    if attempt.user_id != current_user.id:
        raise HTTPException(status_code=403, detail="Not your attempt")

    if attempt.completed_at:
        raise HTTPException(status_code=400, detail="Attempt already completed")

    quiz = db.query(Quiz).filter(Quiz.id == quiz_id).first()
    if not quiz:
        raise HTTPException(status_code=404, detail="Quiz not found")

    # Score the attempt
    attempt, answers = quiz_scoring_service.score_attempt(attempt, request.answers, quiz)

    # Save answers
    for answer in answers:
        db.add(answer)

    db.commit()
    db.refresh(attempt)

    # Get detailed results
    results = quiz_scoring_service.get_detailed_results(attempt, quiz, answers)

    return QuizResultResponse(**results)


@router.get("/{quiz_id}/attempts/{attempt_id}/result", response_model=QuizResultResponse)
async def get_quiz_result(
    quiz_id: int,
    attempt_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get quiz attempt result.
    """
    attempt = db.query(QuizAttempt).filter(QuizAttempt.id == attempt_id).first()
    if not attempt:
        raise HTTPException(status_code=404, detail="Attempt not found")

    if attempt.user_id != current_user.id and not current_user.is_admin():
        raise HTTPException(status_code=403, detail="Not your attempt")

    if not attempt.completed_at:
        raise HTTPException(status_code=400, detail="Attempt not completed")

    quiz = db.query(Quiz).filter(Quiz.id == quiz_id).first()
    answers = db.query(QuizAttemptAnswer).filter(
        QuizAttemptAnswer.attempt_id == attempt_id
    ).all()

    results = quiz_scoring_service.get_detailed_results(attempt, quiz, answers)

    return QuizResultResponse(**results)


@router.get("/{quiz_id}/stats", response_model=QuizStatsResponse)
async def get_quiz_stats(
    quiz_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_teacher_or_admin)
):
    """
    Get quiz statistics (teacher/admin only).
    """
    quiz = db.query(Quiz).filter(Quiz.id == quiz_id).first()
    if not quiz:
        raise HTTPException(status_code=404, detail="Quiz not found")

    total_attempts = db.query(QuizAttempt).filter(
        QuizAttempt.quiz_id == quiz_id
    ).count()

    completed = db.query(QuizAttempt).filter(
        QuizAttempt.quiz_id == quiz_id,
        QuizAttempt.completed_at != None
    )

    completed_count = completed.count()

    avg_score = db.query(func.avg(QuizAttempt.score)).filter(
        QuizAttempt.quiz_id == quiz_id,
        QuizAttempt.completed_at != None
    ).scalar()

    pass_count = db.query(QuizAttempt).filter(
        QuizAttempt.quiz_id == quiz_id,
        QuizAttempt.score >= 50
    ).count()

    pass_rate = (pass_count / completed_count * 100) if completed_count > 0 else None

    avg_duration = db.query(func.avg(QuizAttempt.duration_seconds)).filter(
        QuizAttempt.quiz_id == quiz_id,
        QuizAttempt.completed_at != None
    ).scalar()

    return QuizStatsResponse(
        quiz_id=quiz_id,
        total_attempts=total_attempts,
        completed_attempts=completed_count,
        average_score=float(avg_score) if avg_score else None,
        pass_rate=pass_rate,
        avg_duration_seconds=int(avg_duration) if avg_duration else None
    )
