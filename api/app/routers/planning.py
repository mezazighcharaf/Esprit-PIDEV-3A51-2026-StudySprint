"""
Planning Router - Revision Plans and Tasks
"""
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from typing import Optional
from datetime import date

from app.database import get_db
from app.models.user import User
from app.models.planning import RevisionPlan, PlanTask
from app.models.subject import Subject
from app.schemas.planning import (
    PlanCreate, PlanUpdate, PlanResponse, PlanDetailResponse,
    TaskCreate, TaskUpdate, TaskResponse, TaskStatusUpdate,
    PlanGenerateRequest, PlanOverlapResponse, PlanStatsResponse
)
from app.schemas.common import PaginatedResponse, PaginationMeta
from app.dependencies import get_current_user, require_owner_or_admin
from app.services.plan_generator import plan_generator_service

router = APIRouter(prefix="/planning", tags=["Planning"])


@router.get("/plans", response_model=PaginatedResponse[PlanResponse])
async def list_plans(
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    status_filter: Optional[str] = Query(None, alias="status"),
    subject_id: Optional[int] = Query(None),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    List user's revision plans.
    """
    query = db.query(RevisionPlan).filter(RevisionPlan.user_id == current_user.id)

    if status_filter:
        query = query.filter(RevisionPlan.status == status_filter)
    if subject_id:
        query = query.filter(RevisionPlan.subject_id == subject_id)

    total = query.count()
    plans = query.order_by(RevisionPlan.start_date.desc()).offset((page - 1) * limit).limit(limit).all()

    data = []
    for p in plans:
        resp = PlanResponse.model_validate(p)
        resp.tasks_count = len(p.tasks)
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


@router.get("/plans/{plan_id}", response_model=PlanDetailResponse)
async def get_plan(
    plan_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get plan with its tasks.
    """
    plan = db.query(RevisionPlan).filter(RevisionPlan.id == plan_id).first()
    if not plan:
        raise HTTPException(status_code=404, detail="Plan not found")

    require_owner_or_admin(plan.user_id, current_user)

    response = PlanDetailResponse.model_validate(plan)
    response.tasks_count = len(plan.tasks)
    response.tasks = [TaskResponse.model_validate(t) for t in plan.tasks]

    return response


@router.post("/plans", response_model=PlanResponse, status_code=201)
async def create_plan(
    request: PlanCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Create a manual revision plan.
    """
    plan = RevisionPlan()
    plan.user_id = current_user.id
    plan.title = request.title
    plan.start_date = request.start_date
    plan.end_date = request.end_date
    plan.status = request.status.value
    plan.subject_id = request.subject_id

    db.add(plan)
    db.commit()
    db.refresh(plan)

    return PlanResponse.model_validate(plan)


@router.post("/plans/generate", response_model=PlanDetailResponse, status_code=201)
async def generate_plan(
    request: PlanGenerateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Auto-generate a revision plan.

    - **subject_id**: Subject to create plan for
    - **start_date**: Plan start date
    - **end_date**: Plan end date
    - **sessions_per_day**: Number of sessions per day (1-5)
    - **skip_weekends**: Skip Saturday and Sunday
    - **replace_existing**: If true, replace overlapping plan
    """
    subject = db.query(Subject).filter(Subject.id == request.subject_id).first()
    if not subject:
        raise HTTPException(status_code=404, detail="Subject not found")

    # Check for overlap
    existing = plan_generator_service.find_overlapping_plan(
        db, current_user, subject, request.start_date, request.end_date
    )

    if existing and not request.replace_existing:
        raise HTTPException(
            status_code=409,
            detail=f"Overlapping plan exists: {existing.title} ({existing.start_date} - {existing.end_date})"
        )

    if existing and request.replace_existing:
        plan = plan_generator_service.replace_plan(
            db, existing,
            request.start_date, request.end_date,
            request.sessions_per_day, request.skip_weekends
        )
    else:
        plan = plan_generator_service.generate_plan(
            db, current_user, subject,
            request.start_date, request.end_date,
            request.sessions_per_day, request.skip_weekends
        )

    db.commit()
    db.refresh(plan)

    response = PlanDetailResponse.model_validate(plan)
    response.tasks_count = len(plan.tasks)
    response.tasks = [TaskResponse.model_validate(t) for t in plan.tasks]

    return response


@router.put("/plans/{plan_id}", response_model=PlanResponse)
async def update_plan(
    plan_id: int,
    request: PlanUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Update a revision plan.
    """
    plan = db.query(RevisionPlan).filter(RevisionPlan.id == plan_id).first()
    if not plan:
        raise HTTPException(status_code=404, detail="Plan not found")

    require_owner_or_admin(plan.user_id, current_user)

    if request.title is not None:
        plan.title = request.title
    if request.start_date is not None:
        plan.start_date = request.start_date
    if request.end_date is not None:
        plan.end_date = request.end_date
    if request.status is not None:
        plan.status = request.status.value
    if request.subject_id is not None:
        plan.subject_id = request.subject_id

    db.commit()
    db.refresh(plan)

    return PlanResponse.model_validate(plan)


@router.delete("/plans/{plan_id}", status_code=204)
async def delete_plan(
    plan_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Delete a revision plan.
    """
    plan = db.query(RevisionPlan).filter(RevisionPlan.id == plan_id).first()
    if not plan:
        raise HTTPException(status_code=404, detail="Plan not found")

    require_owner_or_admin(plan.user_id, current_user)

    db.delete(plan)
    db.commit()


# Task routes
@router.get("/tasks", response_model=PaginatedResponse[TaskResponse])
async def list_tasks(
    page: int = Query(1, ge=1),
    limit: int = Query(20, ge=1, le=100),
    status_filter: Optional[str] = Query(None, alias="status"),
    start_date: Optional[date] = Query(None),
    end_date: Optional[date] = Query(None),
    task_type: Optional[str] = Query(None),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    List user's tasks with filters.
    """
    query = db.query(PlanTask).join(RevisionPlan).filter(
        RevisionPlan.user_id == current_user.id
    )

    if status_filter:
        query = query.filter(PlanTask.status == status_filter)
    if task_type:
        query = query.filter(PlanTask.task_type == task_type)
    if start_date:
        query = query.filter(PlanTask.start_at >= start_date)
    if end_date:
        query = query.filter(PlanTask.end_at <= end_date)

    total = query.count()
    tasks = query.order_by(PlanTask.start_at).offset((page - 1) * limit).limit(limit).all()

    total_pages = (total + limit - 1) // limit

    return PaginatedResponse(
        data=[TaskResponse.model_validate(t) for t in tasks],
        pagination=PaginationMeta(
            current_page=page,
            total_pages=total_pages,
            total_items=total,
            items_per_page=limit,
            has_next=page < total_pages,
            has_prev=page > 1
        )
    )


@router.post("/tasks", response_model=TaskResponse, status_code=201)
async def create_task(
    request: TaskCreate,
    plan_id: int = Query(..., description="Plan ID to add task to"),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Create a task in a plan.
    """
    plan = db.query(RevisionPlan).filter(RevisionPlan.id == plan_id).first()
    if not plan:
        raise HTTPException(status_code=404, detail="Plan not found")

    require_owner_or_admin(plan.user_id, current_user)

    task = PlanTask()
    task.plan_id = plan_id
    task.title = request.title
    task.task_type = request.task_type.value
    task.start_at = request.start_at
    task.end_at = request.end_at
    task.status = request.status.value
    task.priority = request.priority
    task.notes = request.notes

    db.add(task)
    db.commit()
    db.refresh(task)

    return TaskResponse.model_validate(task)


@router.put("/tasks/{task_id}", response_model=TaskResponse)
async def update_task(
    task_id: int,
    request: TaskUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Update a task.
    """
    task = db.query(PlanTask).filter(PlanTask.id == task_id).first()
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")

    plan = task.plan
    require_owner_or_admin(plan.user_id, current_user)

    if request.title is not None:
        task.title = request.title
    if request.task_type is not None:
        task.task_type = request.task_type.value
    if request.start_at is not None:
        task.start_at = request.start_at
    if request.end_at is not None:
        task.end_at = request.end_at
    if request.status is not None:
        task.status = request.status.value
    if request.priority is not None:
        task.priority = request.priority
    if request.notes is not None:
        task.notes = request.notes

    db.commit()
    db.refresh(task)

    return TaskResponse.model_validate(task)


@router.patch("/tasks/{task_id}/status", response_model=TaskResponse)
async def update_task_status(
    task_id: int,
    request: TaskStatusUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Quick status update for a task.
    """
    task = db.query(PlanTask).filter(PlanTask.id == task_id).first()
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")

    plan = task.plan
    require_owner_or_admin(plan.user_id, current_user)

    task.status = request.status.value
    db.commit()
    db.refresh(task)

    return TaskResponse.model_validate(task)


@router.delete("/tasks/{task_id}", status_code=204)
async def delete_task(
    task_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Delete a task.
    """
    task = db.query(PlanTask).filter(PlanTask.id == task_id).first()
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")

    plan = task.plan
    require_owner_or_admin(plan.user_id, current_user)

    db.delete(task)
    db.commit()
