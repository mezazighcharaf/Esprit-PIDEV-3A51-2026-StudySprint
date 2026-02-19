"""
Application Configuration - Self-contained (does NOT read Symfony .env)
"""
from functools import lru_cache
from pydantic_settings import BaseSettings
from typing import List
import json


class Settings(BaseSettings):
    # Database (pymysql driver for SQLAlchemy)
    database_url: str = "mysql+pymysql://root:@127.0.0.1:3306/studysprint"

    # JWT
    jwt_secret_key: str = "your-super-secret-key-change-in-production"
    jwt_algorithm: str = "HS256"
    jwt_access_token_expire_minutes: int = 60
    jwt_refresh_token_expire_days: int = 7

    # API
    api_v1_prefix: str = "/api/v1"
    debug: bool = True
    environment: str = "development"

    # CORS
    cors_origins: str = '["http://localhost:8000","http://localhost:3000"]'

    @property
    def cors_origins_list(self) -> List[str]:
        return json.loads(self.cors_origins)

    # AI Gateway - Ollama (Local only)
    ollama_base_url: str = "http://localhost:11434"
    ollama_timeout: int = 120

    # AI Generation settings
    ai_temperature: float = 0.7
    ai_max_tokens: int = 4000
    ai_max_retries: int = 3

    # Rate Limiting
    rate_limit_per_minute: int = 60
    rate_limit_auth_per_minute: int = 5

    class Config:
        case_sensitive = False


@lru_cache()
def get_settings() -> Settings:
    return Settings()


settings = get_settings()
