"""
Common schemas used across the API
"""
from pydantic import BaseModel
from typing import Generic, TypeVar, List, Optional
from datetime import datetime

T = TypeVar('T')


class MessageResponse(BaseModel):
    """Simple message response"""
    message: str
    success: bool = True


class PaginationMeta(BaseModel):
    """Pagination metadata"""
    current_page: int
    total_pages: int
    total_items: int
    items_per_page: int
    has_next: bool
    has_prev: bool


class PaginatedResponse(BaseModel, Generic[T]):
    """Generic paginated response wrapper"""
    data: List[T]
    pagination: PaginationMeta


class ErrorDetail(BaseModel):
    """Error detail for validation errors"""
    field: str
    message: str


class ErrorResponse(BaseModel):
    """Standardized error response"""
    error: str
    code: str
    details: Optional[List[ErrorDetail]] = None
    timestamp: datetime = datetime.utcnow()


class HealthResponse(BaseModel):
    """Health check response"""
    status: str
    version: str
    environment: str
    database: str
    timestamp: datetime
