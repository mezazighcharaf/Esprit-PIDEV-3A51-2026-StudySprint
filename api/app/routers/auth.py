"""
Authentication Router - Login, Logout, Token Refresh
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.database import get_db
from app.schemas.auth import LoginRequest, Token, UserBasic, RefreshRequest
from app.services.auth import auth_service
from app.dependencies import get_current_user
from app.models.user import User
from app.config import settings

router = APIRouter(prefix="/auth", tags=["Authentication"])


@router.post("/login", response_model=Token)
async def login(
    request: LoginRequest,
    db: Session = Depends(get_db)
):
    """
    Authenticate user and return JWT tokens.

    - **email**: User email
    - **password**: User password

    Returns access_token, refresh_token, and user info.
    """
    user = auth_service.authenticate_user(db, request.email, request.password)

    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect email or password",
            headers={"WWW-Authenticate": "Bearer"}
        )

    access_token = auth_service.create_access_token(user)
    refresh_token = auth_service.create_refresh_token(user)

    return Token(
        access_token=access_token,
        refresh_token=refresh_token,
        token_type="bearer",
        expires_in=settings.jwt_access_token_expire_minutes * 60,
        user=UserBasic(
            id=user.id,
            email=user.email,
            full_name=user.full_name,
            roles=user.get_roles()
        )
    )


@router.post("/refresh", response_model=Token)
async def refresh_token(
    request: RefreshRequest,
    db: Session = Depends(get_db)
):
    """
    Refresh access token using refresh token.

    - **refresh_token**: Valid refresh token

    Returns new access_token and refresh_token.
    """
    token_data = auth_service.decode_token(request.refresh_token)

    if not token_data:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or expired refresh token"
        )

    user = auth_service.get_user_by_id(db, token_data.user_id)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="User not found"
        )

    access_token = auth_service.create_access_token(user)
    new_refresh_token = auth_service.create_refresh_token(user)

    return Token(
        access_token=access_token,
        refresh_token=new_refresh_token,
        token_type="bearer",
        expires_in=settings.jwt_access_token_expire_minutes * 60,
        user=UserBasic(
            id=user.id,
            email=user.email,
            full_name=user.full_name,
            roles=user.get_roles()
        )
    )


@router.post("/logout")
async def logout(
    current_user: User = Depends(get_current_user)
):
    """
    Logout current user.

    Note: With stateless JWT, this is mainly for client-side token removal.
    For full logout, implement token blacklist.
    """
    return {"message": "Successfully logged out", "success": True}


@router.get("/me", response_model=UserBasic)
async def get_current_user_info(
    current_user: User = Depends(get_current_user)
):
    """
    Get current authenticated user info.

    Returns user details from the JWT token.
    """
    return UserBasic(
        id=current_user.id,
        email=current_user.email,
        full_name=current_user.full_name,
        roles=current_user.get_roles()
    )
