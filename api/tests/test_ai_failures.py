"""
Tests de cas d'échec IA — Ollama indisponible, réponses invalides, timeouts
Execute: pytest api/tests/test_ai_failures.py -v
"""

import pytest
import requests
import json
import time
from unittest.mock import patch, AsyncMock, MagicMock

BASE_URL = "http://localhost:8001/api/v1"
TIMEOUT = 15


class TestAIFailures:
    """Test suite for AI failure scenarios"""

    @pytest.fixture(autouse=True)
    def setup(self):
        """Check if FastAPI is running"""
        try:
            response = requests.get(f"{BASE_URL}/ai/status", timeout=5)
            assert response.status_code == 200, "FastAPI not running"
        except requests.exceptions.RequestException as e:
            pytest.skip(f"FastAPI not accessible: {e}")

    # ─── Validation failures (Pydantic rejects bad input) ────────

    def test_quiz_generate_missing_user_id(self):
        """Quiz generation without user_id → 422 Unprocessable Entity"""
        payload = {
            "subject_id": 1,
            "num_questions": 3,
            "difficulty": "MEDIUM"
            # user_id manquant
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422
        data = response.json()
        assert "detail" in data

    def test_quiz_generate_invalid_difficulty(self):
        """Quiz generation with invalid difficulty → 422"""
        payload = {
            "user_id": 1,
            "subject_id": 1,
            "num_questions": 3,
            "difficulty": "IMPOSSIBLE"
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_quiz_generate_num_questions_too_high(self):
        """Quiz generation with num_questions > 20 → 422"""
        payload = {
            "user_id": 1,
            "subject_id": 1,
            "num_questions": 50,
            "difficulty": "EASY"
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_quiz_generate_num_questions_zero(self):
        """Quiz generation with num_questions < 1 → 422"""
        payload = {
            "user_id": 1,
            "subject_id": 1,
            "num_questions": 0,
            "difficulty": "EASY"
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_flashcard_generate_missing_subject(self):
        """Flashcard generation without subject_id → 422"""
        payload = {
            "user_id": 1,
            "num_cards": 5
            # subject_id manquant
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/flashcards",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_flashcard_generate_num_cards_too_high(self):
        """Flashcard generation with num_cards > 50 → 422"""
        payload = {
            "user_id": 1,
            "subject_id": 1,
            "num_cards": 100
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/flashcards",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_chapter_summarize_missing_chapter_id(self):
        """Chapter summarize without chapter_id → 422"""
        payload = {
            "user_id": 1
            # chapter_id manquant
        }
        response = requests.post(
            f"{BASE_URL}/ai/chapter/summarize",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_planning_suggest_missing_plan_id(self):
        """Planning suggest without plan_id → 422"""
        payload = {
            "user_id": 1
            # plan_id manquant
        }
        response = requests.post(
            f"{BASE_URL}/ai/planning/suggest",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_post_summarize_missing_post_id(self):
        """Post summarize without post_id → 422"""
        payload = {
            "user_id": 1
            # post_id manquant
        }
        response = requests.post(
            f"{BASE_URL}/ai/post/summarize",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_feedback_invalid_rating(self):
        """Feedback with rating > 5 → 422"""
        payload = {
            "user_id": 1,
            "log_id": 1,
            "rating": 10
        }
        response = requests.post(
            f"{BASE_URL}/ai/feedback",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_feedback_negative_rating(self):
        """Feedback with rating < 1 → 422"""
        payload = {
            "user_id": 1,
            "log_id": 1,
            "rating": 0
        }
        response = requests.post(
            f"{BASE_URL}/ai/feedback",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    # ─── Not Found failures (valid input, entity doesn't exist) ──

    def test_quiz_generate_nonexistent_subject(self):
        """Quiz generation with non-existent subject → 404"""
        payload = {
            "user_id": 1,
            "subject_id": 99999,
            "num_questions": 3,
            "difficulty": "EASY"
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 404

    def test_quiz_generate_nonexistent_chapter(self):
        """Quiz generation with non-existent chapter → 404"""
        payload = {
            "user_id": 1,
            "subject_id": 1,
            "chapter_id": 99999,
            "num_questions": 3,
            "difficulty": "EASY"
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 404

    def test_chapter_summarize_nonexistent_chapter(self):
        """Chapter summarize with non-existent chapter → 404"""
        payload = {
            "user_id": 1,
            "chapter_id": 99999
        }
        response = requests.post(
            f"{BASE_URL}/ai/chapter/summarize",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 404

    def test_planning_suggest_nonexistent_plan(self):
        """Planning suggest with non-existent plan → 404"""
        payload = {
            "user_id": 1,
            "plan_id": 99999
        }
        response = requests.post(
            f"{BASE_URL}/ai/planning/suggest",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 404

    def test_post_summarize_nonexistent_post(self):
        """Post summarize with non-existent post → 404"""
        payload = {
            "user_id": 1,
            "post_id": 99999
        }
        response = requests.post(
            f"{BASE_URL}/ai/post/summarize",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 404

    def test_feedback_nonexistent_log(self):
        """Feedback for non-existent log → 404"""
        payload = {
            "user_id": 1,
            "log_id": 99999,
            "rating": 3
        }
        response = requests.post(
            f"{BASE_URL}/ai/feedback",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 404

    def test_planning_apply_nonexistent_log(self):
        """Apply suggestions with non-existent log → 404"""
        payload = {
            "user_id": 1,
            "suggestion_log_id": 99999
        }
        response = requests.post(
            f"{BASE_URL}/ai/planning/apply",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 404

    # ─── Malformed request body ──────────────────────────────────

    def test_quiz_generate_empty_body(self):
        """Quiz generation with empty body → 422"""
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json={},
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_quiz_generate_invalid_json(self):
        """Quiz generation with invalid JSON → 422"""
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            data="not json",
            headers={"Content-Type": "application/json"},
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_flashcard_generate_empty_body(self):
        """Flashcard generation with empty body → 422"""
        response = requests.post(
            f"{BASE_URL}/ai/generate/flashcards",
            json={},
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_profile_enhance_empty_body(self):
        """Profile enhance with empty body → 422"""
        response = requests.post(
            f"{BASE_URL}/ai/profile/enhance",
            json={},
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    # ─── Type errors ─────────────────────────────────────────────

    def test_quiz_generate_string_as_num_questions(self):
        """num_questions as string → 422"""
        payload = {
            "user_id": 1,
            "subject_id": 1,
            "num_questions": "five",
            "difficulty": "EASY"
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_flashcard_generate_string_as_num_cards(self):
        """num_cards as string → 422"""
        payload = {
            "user_id": 1,
            "subject_id": 1,
            "num_cards": "ten"
        }
        response = requests.post(
            f"{BASE_URL}/ai/generate/flashcards",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422

    def test_feedback_string_as_rating(self):
        """rating as string → 422"""
        payload = {
            "user_id": 1,
            "log_id": 1,
            "rating": "excellent"
        }
        response = requests.post(
            f"{BASE_URL}/ai/feedback",
            json=payload,
            timeout=TIMEOUT
        )
        assert response.status_code == 422


class TestAIParseJsonEdgeCases:
    """Test the parse_json_response function with edge cases"""

    def test_parse_valid_json_array(self):
        from app.routers.ai import parse_json_response
        result = parse_json_response('[{"key": "value"}]')
        assert isinstance(result, list)
        assert result[0]["key"] == "value"

    def test_parse_valid_json_object(self):
        from app.routers.ai import parse_json_response
        result = parse_json_response('{"key": "value"}')
        assert isinstance(result, dict)
        assert result["key"] == "value"

    def test_parse_json_in_markdown_block(self):
        from app.routers.ai import parse_json_response
        content = '```json\n[{"q": "test"}]\n```'
        result = parse_json_response(content)
        assert isinstance(result, list)
        assert result[0]["q"] == "test"

    def test_parse_json_in_generic_code_block(self):
        from app.routers.ai import parse_json_response
        content = '```\n{"key": "val"}\n```'
        result = parse_json_response(content)
        assert isinstance(result, dict)

    def test_parse_json_with_surrounding_text(self):
        from app.routers.ai import parse_json_response
        content = 'Here is the result: [{"a": 1}] end of response'
        result = parse_json_response(content)
        assert isinstance(result, list)

    def test_parse_completely_invalid_json_raises(self):
        from app.routers.ai import parse_json_response
        with pytest.raises(ValueError):
            parse_json_response("This is not JSON at all")

    def test_parse_empty_string_raises(self):
        from app.routers.ai import parse_json_response
        with pytest.raises((ValueError, json.JSONDecodeError)):
            parse_json_response("")

    def test_parse_only_whitespace_raises(self):
        from app.routers.ai import parse_json_response
        with pytest.raises((ValueError, json.JSONDecodeError)):
            parse_json_response("   \n\t  ")


class TestIdempotencyKey:
    """Test the idempotency key generation"""

    def test_same_input_same_key(self):
        from app.routers.ai import generate_idempotency_key
        key1 = generate_idempotency_key(1, "quiz", {"subject_id": 1})
        key2 = generate_idempotency_key(1, "quiz", {"subject_id": 1})
        assert key1 == key2

    def test_different_input_different_key(self):
        from app.routers.ai import generate_idempotency_key
        key1 = generate_idempotency_key(1, "quiz", {"subject_id": 1})
        key2 = generate_idempotency_key(1, "quiz", {"subject_id": 2})
        assert key1 != key2

    def test_different_user_different_key(self):
        from app.routers.ai import generate_idempotency_key
        key1 = generate_idempotency_key(1, "quiz", {"subject_id": 1})
        key2 = generate_idempotency_key(2, "quiz", {"subject_id": 1})
        assert key1 != key2

    def test_different_feature_different_key(self):
        from app.routers.ai import generate_idempotency_key
        key1 = generate_idempotency_key(1, "quiz", {"id": 1})
        key2 = generate_idempotency_key(1, "flashcard", {"id": 1})
        assert key1 != key2

    def test_key_length_is_32(self):
        from app.routers.ai import generate_idempotency_key
        key = generate_idempotency_key(1, "quiz", {"subject_id": 1})
        assert len(key) == 32
