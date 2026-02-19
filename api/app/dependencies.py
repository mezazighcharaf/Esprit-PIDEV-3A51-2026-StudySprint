"""
FastAPI Dependencies - Authentication, Database, Rate Limiting
"""
from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.orm import Session
from typing import Optional

from app.database import get_db
from app.models.user import User
from app.services.auth import auth_service

# Security scheme for Swagger UI
security = HTTPBearer()


async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
) -> User:
    """
    Get current authenticated user from JWT token.

    Raises:
        HTTPException 401: If token is invalid or user not found
    """
    token = credentials.credentials
    token_data = auth_service.decode_token(token)

    if not token_data:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or expired token",
            headers={"WWW-Authenticate": "Bearer"}
        )

    user = auth_service.get_user_by_id(db, token_data.user_id)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="User not found",
            headers={"WWW-Authenticate": "Bearer"}
        )

    return user


async def get_current_user_optional(
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(HTTPBearer(auto_error=False)),
    db: Session = Depends(get_db)
) -> Optional[User]:
    """
    Get current user if authenticated, None otherwise.
    Useful for endpoints that work differently for authenticated users.
    """
    if not credentials:
        return None

    token_data = auth_service.decode_token(credentials.credentials)
    if not token_data:
        return None

    return auth_service.get_user_by_id(db, token_data.user_id)


async def get_admin_user(
    current_user: User = Depends(get_current_user)
) -> User:
    """
    Require admin role.

    Raises:
        HTTPException 403: If user is not admin
    """
    if not current_user.is_admin():
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Admin access required"
        )
    return current_user


async def get_teacher_or_admin(
    current_user: User = Depends(get_current_user)
) -> User:
    """
    Require teacher or admin role.

    Raises:
        HTTPException 403: If user is not teacher or admin
    """
    if current_user.user_type not in ["TEACHER", "ADMIN"] and not current_user.is_admin():
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Teacher or admin access required"
        )
    return current_user


def check_owner_or_admin(resource_user_id: int, current_user: User) -> bool:
    """
    Check if current user owns the resource or is admin.

    Args:
        resource_user_id: User ID who owns the resource
        current_user: Current authenticated user

    Returns:
        True if allowed, False otherwise
    """
    return current_user.id == resource_user_id or current_user.is_admin()


def require_owner_or_admin(resource_user_id: int, current_user: User):
    """
    Require ownership or admin role.

    Raises:
        HTTPException 403: If not owner and not admin
    """
    if not check_owner_or_admin(resource_user_id, current_user):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You don't have permission to access this resource"
        )
