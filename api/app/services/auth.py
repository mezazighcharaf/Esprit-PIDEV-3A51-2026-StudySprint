"""
Authentication Service - JWT handling
"""
from datetime import datetime, timedelta
from typing import Optional
from jose import JWTError, jwt
from passlib.context import CryptContext
from sqlalchemy.orm import Session

from app.config import settings
from app.models.user import User
from app.schemas.auth import TokenData

# Password hashing context (compatible with Symfony's bcrypt)
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")


class AuthService:
    """Handle authentication and JWT tokens"""

    def __init__(self):
        self.secret_key = settings.jwt_secret_key
        self.algorithm = settings.jwt_algorithm
        self.access_token_expire = settings.jwt_access_token_expire_minutes
        self.refresh_token_expire = settings.jwt_refresh_token_expire_days

    def verify_password(self, plain_password: str, hashed_password: str) -> bool:
        """Verify password against hash"""
        return pwd_context.verify(plain_password, hashed_password)

    def hash_password(self, password: str) -> str:
        """Hash a password"""
        return pwd_context.hash(password)

    def create_access_token(self, user: User) -> str:
        """Create JWT access token"""
        expire = datetime.utcnow() + timedelta(minutes=self.access_token_expire)
        payload = {
            "sub": str(user.id),
            "email": user.email,
            "roles": user.get_roles(),
            "exp": expire,
            "type": "access"
        }
        return jwt.encode(payload, self.secret_key, algorithm=self.algorithm)

    def create_refresh_token(self, user: User) -> str:
        """Create JWT refresh token"""
        expire = datetime.utcnow() + timedelta(days=self.refresh_token_expire)
        payload = {
            "sub": str(user.id),
            "exp": expire,
            "type": "refresh"
        }
        return jwt.encode(payload, self.secret_key, algorithm=self.algorithm)

    def decode_token(self, token: str) -> Optional[TokenData]:
        """Decode and validate JWT token"""
        try:
            payload = jwt.decode(token, self.secret_key, algorithms=[self.algorithm])
            user_id = payload.get("sub")
            if user_id is None:
                return None
            return TokenData(
                user_id=int(user_id),
                email=payload.get("email"),
                roles=payload.get("roles", [])
            )
        except JWTError:
            return None

    def authenticate_user(self, db: Session, email: str, password: str) -> Optional[User]:
        """Authenticate user with email and password"""
        user = db.query(User).filter(User.email == email).first()
        if not user:
            return None
        if not self.verify_password(password, user.password):
            return None
        return user

    def get_user_by_id(self, db: Session, user_id: int) -> Optional[User]:
        """Get user by ID"""
        return db.query(User).filter(User.id == user_id).first()

    def get_current_user(self, db: Session, token: str) -> Optional[User]:
        """Get current user from token"""
        token_data = self.decode_token(token)
        if not token_data:
            return None
        return self.get_user_by_id(db, token_data.user_id)


# Singleton instance
auth_service = AuthService()
