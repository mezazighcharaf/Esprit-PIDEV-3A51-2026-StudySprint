"""
StudySprint FastAPI Application
Main entry point
"""
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from datetime import datetime
import time

from app.config import settings
from app.database import engine, Base
from app.schemas.common import HealthResponse

# Import routers
from app.routers.auth import router as auth_router
from app.routers.users import router as users_router
from app.routers.subjects import router as subjects_router, chapters_router
from app.routers.planning import router as planning_router
from app.routers.groups import router as groups_router, posts_router
from app.routers.training import router as training_router
from app.routers.flashcards import router as flashcards_router, review_router
from app.routers.ai import router as ai_router

# Create FastAPI app
app = FastAPI(
    title="StudySprint API",
    description="""
## StudySprint REST API

API REST pour la plateforme d'apprentissage StudySprint.

### Modules disponibles:
- **Auth**: Authentification JWT (login, refresh, logout)
- **Users**: Gestion des utilisateurs et profils
- **Subjects**: Matières et chapitres
- **Planning**: Plans de révision et tâches
- **Groups**: Groupes d'étude et posts
- **Training**: Quiz et tentatives
- **Flashcards**: Decks et révision SM-2
- **AI**: Génération de contenu par IA

### Authentification
Utilisez le endpoint `/api/v1/auth/login` pour obtenir un JWT token.
Incluez le token dans l'en-tête `Authorization: Bearer <token>`.
    """,
    version="1.0.0",
    docs_url="/api/docs",
    redoc_url="/api/redoc",
    openapi_url="/api/openapi.json"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["X-Total-Count", "X-Page", "X-Per-Page"]
)


# Request timing middleware
@app.middleware("http")
async def add_process_time_header(request: Request, call_next):
    start_time = time.time()
    response = await call_next(request)
    process_time = time.time() - start_time
    response.headers["X-Process-Time"] = str(round(process_time * 1000, 2)) + "ms"
    return response


# Global exception handler
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    return JSONResponse(
        status_code=500,
        content={
            "error": "Internal server error",
            "code": "INTERNAL_ERROR",
            "detail": str(exc) if settings.debug else "An unexpected error occurred",
            "timestamp": datetime.utcnow().isoformat()
        }
    )


# Include routers with API prefix
api_prefix = settings.api_v1_prefix

app.include_router(auth_router, prefix=api_prefix)
app.include_router(users_router, prefix=api_prefix)
app.include_router(subjects_router, prefix=api_prefix)
app.include_router(chapters_router, prefix=api_prefix)
app.include_router(planning_router, prefix=api_prefix)
app.include_router(groups_router, prefix=api_prefix)
app.include_router(posts_router, prefix=api_prefix)
app.include_router(training_router, prefix=api_prefix)
app.include_router(flashcards_router, prefix=api_prefix)
app.include_router(review_router, prefix=api_prefix)
app.include_router(ai_router, prefix=api_prefix)


# Health check endpoint
@app.get("/health", response_model=HealthResponse, tags=["System"])
async def health_check():
    """
    Health check endpoint.
    Returns API status and database connectivity.
    """
    db_status = "connected"
    try:
        # Quick DB check
        from app.database import SessionLocal
        db = SessionLocal()
        db.execute("SELECT 1")
        db.close()
    except Exception:
        db_status = "disconnected"

    return HealthResponse(
        status="healthy",
        version="1.0.0",
        environment=settings.environment,
        database=db_status,
        timestamp=datetime.utcnow()
    )


# Root endpoint
@app.get("/", tags=["System"])
async def root():
    """
    API root endpoint.
    """
    return {
        "name": "StudySprint API",
        "version": "1.0.0",
        "docs": "/api/docs",
        "health": "/health"
    }


# Startup event
@app.on_event("startup")
async def startup_event():
    """
    Application startup tasks.
    """
    print(f"🚀 StudySprint API starting...")
    print(f"📚 Environment: {settings.environment}")
    print(f"🔗 Database: {settings.database_url.split('@')[-1] if '@' in settings.database_url else 'configured'}")
    print(f"📖 Docs: http://localhost:8001/api/docs")


# Shutdown event
@app.on_event("shutdown")
async def shutdown_event():
    """
    Application shutdown tasks.
    """
    print("👋 StudySprint API shutting down...")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8001,
        reload=settings.debug
    )
