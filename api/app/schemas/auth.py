"""
Authentication schemas
"""
from pydantic import BaseModel, EmailStr
from typing import Optional, List


class LoginRequest(BaseModel):
    """Login request payload"""
    email: EmailStr
    password: str

    class Config:
        json_schema_extra = {
            "example": {
                "email": "alice.martin@studysprint.local",
                "password": "user123"
            }
        }


class RefreshRequest(BaseModel):
    """Token refresh request"""
    refresh_token: str


class Token(BaseModel):
    """JWT Token response"""
    access_token: str
    refresh_token: str
    token_type: str = "bearer"
    expires_in: int
    user: "UserBasic"


class TokenData(BaseModel):
    """Decoded token data"""
    user_id: Optional[int] = None
    email: Optional[str] = None
    roles: List[str] = []


class UserBasic(BaseModel):
    """Basic user info in token response"""
    id: int
    email: str
    full_name: str
    roles: List[str]

    class Config:
        from_attributes = True


# Update forward reference
Token.model_rebuild()
