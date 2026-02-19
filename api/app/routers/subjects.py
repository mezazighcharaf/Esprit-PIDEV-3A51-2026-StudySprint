"""
Subjects and Chapters Router
"""
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from typing import Optional

from app.database import get_db
from app.models.user import User
from app.models.subject import Subject, Chapter
from app.schemas.subject import (
    SubjectCreate, SubjectUpdate, SubjectResponse, SubjectDetailResponse,
    ChapterCreate, ChapterUpdate, ChapterResponse, ChapterReorder
)
from app.schemas.common import PaginatedResponse, PaginationMeta
from app.dependencies import get_current_user, get_teacher_or_admin, require_owner_or_admin

router = APIRouter(prefix="/subjects", tags=["Subjects"])


@router.get("", response_model=PaginatedResponse[SubjectResponse])
async def list_subjects(
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    q: Optional[str] = Query(None, description="Search by name or code"),
    sort: str = Query("name", description="Sort field"),
    dir: str = Query("asc", description="Sort direction"),
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    List subjects with pagination and search.
    """
    query = db.query(Subject)

    if q:
        search = f"%{q}%"
        query = query.filter(
            (Subject.name.ilike(search)) | (Subject.code.ilike(search))
        )

    # Sort
    sort_column = getattr(Subject, sort, Subject.name)
    if dir.lower() == "desc":
        sort_column = sort_column.desc()
    query = query.order_by(sort_column)

    total = query.count()
    subjects = query.offset((page - 1) * limit).limit(limit).all()

    # Add chapters count
    data = []
    for s in subjects:
        resp = SubjectResponse.model_validate(s)
        resp.chapters_count = len(s.chapters)
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


@router.get("/{subject_id}", response_model=SubjectDetailResponse)
async def get_subject(
    subject_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get subject with its chapters.
    """
    subject = db.query(Subject).filter(Subject.id == subject_id).first()
    if not subject:
        raise HTTPException(status_code=404, detail="Subject not found")

    response = SubjectDetailResponse.model_validate(subject)
    response.chapters_count = len(subject.chapters)
    response.chapters = [ChapterResponse.model_validate(c) for c in subject.chapters]

    return response


@router.post("", response_model=SubjectResponse, status_code=201)
async def create_subject(
    request: SubjectCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_teacher_or_admin)
):
    """
    Create a new subject (teacher/admin only).
    """
    existing = db.query(Subject).filter(Subject.code == request.code).first()
    if existing:
        raise HTTPException(status_code=409, detail="Subject code already exists")

    subject = Subject()
    subject.name = request.name
    subject.code = request.code
    subject.description = request.description
    subject.created_by_id = current_user.id

    db.add(subject)
    db.commit()
    db.refresh(subject)

    return SubjectResponse.model_validate(subject)


@router.put("/{subject_id}", response_model=SubjectResponse)
async def update_subject(
    subject_id: int,
    request: SubjectUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Update a subject (owner/admin only).
    """
    subject = db.query(Subject).filter(Subject.id == subject_id).first()
    if not subject:
        raise HTTPException(status_code=404, detail="Subject not found")

    require_owner_or_admin(subject.created_by_id, current_user)

    if request.name is not None:
        subject.name = request.name
    if request.code is not None:
        existing = db.query(Subject).filter(
            Subject.code == request.code,
            Subject.id != subject_id
        ).first()
        if existing:
            raise HTTPException(status_code=409, detail="Subject code already exists")
        subject.code = request.code
    if request.description is not None:
        subject.description = request.description

    db.commit()
    db.refresh(subject)

    return SubjectResponse.model_validate(subject)


@router.delete("/{subject_id}", status_code=204)
async def delete_subject(
    subject_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Delete a subject (owner/admin only).
    """
    subject = db.query(Subject).filter(Subject.id == subject_id).first()
    if not subject:
        raise HTTPException(status_code=404, detail="Subject not found")

    require_owner_or_admin(subject.created_by_id, current_user)

    db.delete(subject)
    db.commit()


# Chapter routes
@router.get("/{subject_id}/chapters", response_model=list[ChapterResponse])
async def list_chapters(
    subject_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get chapters for a subject.
    """
    chapters = db.query(Chapter).filter(
        Chapter.subject_id == subject_id
    ).order_by(Chapter.order_no).all()

    return [ChapterResponse.model_validate(c) for c in chapters]


@router.post("/{subject_id}/chapters", response_model=ChapterResponse, status_code=201)
async def create_chapter(
    subject_id: int,
    request: ChapterCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Create a chapter in a subject.
    """
    subject = db.query(Subject).filter(Subject.id == subject_id).first()
    if not subject:
        raise HTTPException(status_code=404, detail="Subject not found")

    require_owner_or_admin(subject.created_by_id, current_user)

    # Check unique constraint
    existing = db.query(Chapter).filter(
        Chapter.subject_id == subject_id,
        Chapter.order_no == request.order_no
    ).first()
    if existing:
        raise HTTPException(status_code=409, detail="A chapter with this order already exists")

    chapter = Chapter()
    chapter.subject_id = subject_id
    chapter.title = request.title
    chapter.order_no = request.order_no
    chapter.summary = request.summary
    chapter.content = request.content
    chapter.created_by_id = current_user.id

    db.add(chapter)
    db.commit()
    db.refresh(chapter)

    return ChapterResponse.model_validate(chapter)


# Chapter endpoints (without subject prefix)
chapters_router = APIRouter(prefix="/chapters", tags=["Chapters"])


@chapters_router.put("/{chapter_id}", response_model=ChapterResponse)
async def update_chapter(
    chapter_id: int,
    request: ChapterUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Update a chapter.
    """
    chapter = db.query(Chapter).filter(Chapter.id == chapter_id).first()
    if not chapter:
        raise HTTPException(status_code=404, detail="Chapter not found")

    subject = chapter.subject
    require_owner_or_admin(subject.created_by_id, current_user)

    if request.title is not None:
        chapter.title = request.title
    if request.order_no is not None:
        # Check unique constraint
        existing = db.query(Chapter).filter(
            Chapter.subject_id == chapter.subject_id,
            Chapter.order_no == request.order_no,
            Chapter.id != chapter_id
        ).first()
        if existing:
            raise HTTPException(status_code=409, detail="A chapter with this order already exists")
        chapter.order_no = request.order_no
    if request.summary is not None:
        chapter.summary = request.summary
    if request.content is not None:
        chapter.content = request.content

    db.commit()
    db.refresh(chapter)

    return ChapterResponse.model_validate(chapter)


@chapters_router.delete("/{chapter_id}", status_code=204)
async def delete_chapter(
    chapter_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Delete a chapter.
    """
    chapter = db.query(Chapter).filter(Chapter.id == chapter_id).first()
    if not chapter:
        raise HTTPException(status_code=404, detail="Chapter not found")

    subject = chapter.subject
    require_owner_or_admin(subject.created_by_id, current_user)

    db.delete(chapter)
    db.commit()


@chapters_router.post("/{chapter_id}/reorder", response_model=ChapterResponse)
async def reorder_chapter(
    chapter_id: int,
    request: ChapterReorder,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Reorder a chapter (transactional swap).
    """
    chapter = db.query(Chapter).filter(Chapter.id == chapter_id).first()
    if not chapter:
        raise HTTPException(status_code=404, detail="Chapter not found")

    subject = chapter.subject
    require_owner_or_admin(subject.created_by_id, current_user)

    # Find chapter at target position
    target = db.query(Chapter).filter(
        Chapter.subject_id == chapter.subject_id,
        Chapter.order_no == request.new_order_no
    ).first()

    if target and target.id != chapter.id:
        # Swap positions
        old_order = chapter.order_no
        chapter.order_no = -1  # Temporary
        db.flush()
        target.order_no = old_order
        db.flush()
        chapter.order_no = request.new_order_no
    else:
        chapter.order_no = request.new_order_no

    db.commit()
    db.refresh(chapter)

    return ChapterResponse.model_validate(chapter)
