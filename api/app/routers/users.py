"""
Users Router - CRUD operations for users
"""
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from typing import List, Optional

from app.database import get_db
from app.models.user import User, UserProfile
from app.schemas.user import (
    UserCreate, UserUpdate, UserResponse, UserWithProfileResponse,
    UserProfileBase, UserProfileResponse
)
from app.schemas.common import PaginatedResponse, PaginationMeta
from app.services.auth import auth_service
from app.dependencies import get_current_user, get_admin_user, require_owner_or_admin

router = APIRouter(prefix="/users", tags=["Users"])


@router.get("", response_model=PaginatedResponse[UserResponse])
async def list_users(
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    q: Optional[str] = Query(None, description="Search by name or email"),
    user_type: Optional[str] = Query(None, description="Filter by user type"),
    db: Session = Depends(get_db),
    admin: User = Depends(get_admin_user)
):
    """
    List all users (admin only).

    - **page**: Page number
    - **limit**: Items per page (max 100)
    - **q**: Search term
    - **user_type**: Filter by STUDENT, TEACHER, ADMIN
    """
    query = db.query(User)

    # Search
    if q:
        search = f"%{q}%"
        query = query.filter(
            (User.full_name.ilike(search)) | (User.email.ilike(search))
        )

    # Filter by type
    if user_type:
        query = query.filter(User.user_type == user_type)

    # Count total
    total = query.count()

    # Paginate
    users = query.order_by(User.id.desc()).offset((page - 1) * limit).limit(limit).all()

    total_pages = (total + limit - 1) // limit

    return PaginatedResponse(
        data=[UserResponse.model_validate(u) for u in users],
        pagination=PaginationMeta(
            current_page=page,
            total_pages=total_pages,
            total_items=total,
            items_per_page=limit,
            has_next=page < total_pages,
            has_prev=page > 1
        )
    )


@router.get("/{user_id}", response_model=UserWithProfileResponse)
async def get_user(
    user_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get user by ID.

    - Admin can view any user
    - Users can only view themselves
    """
    # Check permission (BOLA protection)
    require_owner_or_admin(user_id, current_user)

    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )

    response = UserWithProfileResponse.model_validate(user)
    if user.profile:
        response.profile = UserProfileResponse.model_validate(user.profile)

    return response


@router.post("", response_model=UserResponse, status_code=status.HTTP_201_CREATED)
async def create_user(
    request: UserCreate,
    db: Session = Depends(get_db),
    admin: User = Depends(get_admin_user)
):
    """
    Create a new user (admin only).
    """
    # Check email uniqueness
    existing = db.query(User).filter(User.email == request.email).first()
    if existing:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="Email already exists"
        )

    # Create user
    user = User()
    user.email = request.email
    user.full_name = request.full_name
    user.user_type = request.user_type.value
    user.password = auth_service.hash_password(request.password)
    user.roles = [role.value for role in request.roles]

    db.add(user)
    db.commit()
    db.refresh(user)

    return UserResponse.model_validate(user)


@router.put("/{user_id}", response_model=UserResponse)
async def update_user(
    user_id: int,
    request: UserUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Update user.

    - Admin can update any user
    - Users can only update themselves (except roles)
    """
    require_owner_or_admin(user_id, current_user)

    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )

    # Only admin can change roles
    if request.roles is not None and not current_user.is_admin():
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only admin can change roles"
        )

    # Update fields
    if request.email is not None:
        # Check email uniqueness
        existing = db.query(User).filter(
            User.email == request.email,
            User.id != user_id
        ).first()
        if existing:
            raise HTTPException(
                status_code=status.HTTP_409_CONFLICT,
                detail="Email already exists"
            )
        user.email = request.email

    if request.full_name is not None:
        user.full_name = request.full_name
    if request.user_type is not None:
        user.user_type = request.user_type.value
    if request.password is not None:
        user.password = auth_service.hash_password(request.password)
    if request.roles is not None:
        user.roles = [role.value for role in request.roles]

    db.commit()
    db.refresh(user)

    return UserResponse.model_validate(user)


@router.delete("/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_user(
    user_id: int,
    db: Session = Depends(get_db),
    admin: User = Depends(get_admin_user)
):
    """
    Delete user (admin only).
    """
    if user_id == admin.id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete yourself"
        )

    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )

    db.delete(user)
    db.commit()


# Profile endpoints
@router.get("/{user_id}/profile", response_model=UserProfileResponse)
async def get_user_profile(
    user_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Get user profile.
    """
    profile = db.query(UserProfile).filter(UserProfile.user_id == user_id).first()
    if not profile:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Profile not found"
        )

    return UserProfileResponse.model_validate(profile)


@router.put("/{user_id}/profile", response_model=UserProfileResponse)
async def update_user_profile(
    user_id: int,
    request: UserProfileBase,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Update user profile (self only).
    """
    require_owner_or_admin(user_id, current_user)

    profile = db.query(UserProfile).filter(UserProfile.user_id == user_id).first()

    if not profile:
        # Create profile if doesn't exist
        profile = UserProfile()
        profile.user_id = user_id
        db.add(profile)

    if request.level is not None:
        profile.level = request.level
    if request.specialty is not None:
        profile.specialty = request.specialty
    if request.bio is not None:
        profile.bio = request.bio
    if request.avatar_url is not None:
        profile.avatar_url = request.avatar_url

    db.commit()
    db.refresh(profile)

    return UserProfileResponse.model_validate(profile)
