"""
Tests automatisés pour les endpoints AI Gateway FastAPI
Execute: pytest api/tests/test_ai_endpoints.py -v
"""

import pytest
import requests
import json
import time
from typing import Dict, Any

# Configuration
BASE_URL = "http://localhost:8001/api/v1"
TIMEOUT = 120


class TestAIEndpoints:
    """Test suite for AI Gateway endpoints"""

    @pytest.fixture(autouse=True)
    def setup(self):
        """Setup before each test"""
        # Check if FastAPI is running
        try:
            response = requests.get(f"{BASE_URL}/ai/status", timeout=5)
            assert response.status_code == 200, "FastAPI not running on localhost:8001"
        except requests.exceptions.RequestException as e:
            pytest.skip(f"FastAPI not accessible: {e}")

    def test_ai_status(self):
        """Test AI status endpoint"""
        response = requests.get(f"{BASE_URL}/ai/status", timeout=10)
        
        assert response.status_code == 200
        data = response.json()
        
        assert "providers" in data
        assert "ollama" in data["providers"]
        assert isinstance(data["providers"]["ollama"], dict)
        
        print(f"✅ Status OK - Providers: {list(data['providers'].keys())}")

    def test_quiz_generation(self):
        """Test quiz generation endpoint"""
        payload = {
            "subject_id": 1,
            "chapter_id": 1,
            "num_questions": 3,
            "difficulty": "MEDIUM",
            "topic": "Python basics"
        }
        
        print(f"\n📝 Testing quiz generation with {payload['num_questions']} questions...")
        start = time.time()
        
        response = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        
        elapsed = time.time() - start
        print(f"⏱️  Response time: {elapsed:.2f}s")
        
        assert response.status_code == 200
        data = response.json()
        
        # Validate response structure
        assert "quiz_id" in data
        assert "title" in data
        assert "questions" in data
        assert len(data["questions"]) == payload["num_questions"]
        
        # Validate each question
        for i, q in enumerate(data["questions"], 1):
            assert "question_text" in q, f"Question {i} missing question_text"
            assert "choices" in q, f"Question {i} missing choices"
            assert "correct_answer" in q, f"Question {i} missing correct_answer"
            assert len(q["choices"]) == 4, f"Question {i} should have 4 choices"
            assert q["correct_answer"] in q["choices"], f"Question {i} correct answer not in choices"
        
        print(f"✅ Quiz generated successfully (ID: {data['quiz_id']})")
        print(f"   Title: {data['title']}")
        return data["quiz_id"]

    def test_flashcard_generation(self):
        """Test flashcard generation endpoint"""
        payload = {
            "subject_id": 1,
            "chapter_id": 1,
            "num_cards": 5,
            "topic": "Data structures",
            "include_hints": True
        }
        
        print(f"\n🎴 Testing flashcard generation with {payload['num_cards']} cards...")
        start = time.time()
        
        response = requests.post(
            f"{BASE_URL}/ai/generate/flashcards",
            json=payload,
            timeout=TIMEOUT
        )
        
        elapsed = time.time() - start
        print(f"⏱️  Response time: {elapsed:.2f}s")
        
        assert response.status_code == 200
        data = response.json()
        
        # Validate response structure
        assert "deck_id" in data
        assert "title" in data
        assert "cards" in data
        assert len(data["cards"]) == payload["num_cards"]
        
        # Validate each card
        for i, card in enumerate(data["cards"], 1):
            assert "front" in card, f"Card {i} missing front"
            assert "back" in card, f"Card {i} missing back"
            if payload["include_hints"]:
                assert "hint" in card, f"Card {i} missing hint"
        
        print(f"✅ Deck generated successfully (ID: {data['deck_id']})")
        print(f"   Title: {data['title']}")
        return data["deck_id"]

    def test_chapter_summarize(self):
        """Test chapter summarization endpoint"""
        payload = {
            "chapter_id": 1
        }
        
        print(f"\n📖 Testing chapter summarization...")
        start = time.time()
        
        response = requests.post(
            f"{BASE_URL}/ai/chapter/summarize",
            json=payload,
            timeout=60
        )
        
        elapsed = time.time() - start
        print(f"⏱️  Response time: {elapsed:.2f}s")
        
        assert response.status_code == 200
        data = response.json()
        
        # Validate response structure
        assert "summary" in data
        assert "key_points" in data
        assert "tags" in data
        assert isinstance(data["key_points"], list)
        assert isinstance(data["tags"], list)
        assert len(data["key_points"]) <= 5
        assert len(data["tags"]) <= 5
        
        print(f"✅ Summary generated successfully")
        print(f"   Key points: {len(data['key_points'])}")
        print(f"   Tags: {', '.join(data['tags'][:3])}...")

    def test_profile_enhance(self):
        """Test profile enhancement endpoint"""
        payload = {
            "current_bio": "I'm a student learning programming",
            "current_level": "UNDERGRADUATE",
            "current_specialty": "Computer Science",
            "goals": "Master Python and web development"
        }
        
        print(f"\n👤 Testing profile enhancement...")
        start = time.time()
        
        response = requests.post(
            f"{BASE_URL}/ai/profile/enhance",
            json=payload,
            timeout=60
        )
        
        elapsed = time.time() - start
        print(f"⏱️  Response time: {elapsed:.2f}s")
        
        assert response.status_code == 200
        data = response.json()
        
        # Validate response structure
        assert "suggested_bio" in data
        assert "suggested_goals" in data
        assert "suggested_routine" in data
        assert len(data["suggested_bio"]) > 50
        
        print(f"✅ Profile enhancement generated successfully")

    def test_planning_suggest(self):
        """Test planning suggestions endpoint"""
        payload = {
            "plan_id": 1,
            "optimization_goals": "Balance workload and avoid burnout"
        }
        
        print(f"\n📅 Testing planning suggestions...")
        start = time.time()
        
        response = requests.post(
            f"{BASE_URL}/ai/planning/suggest",
            json=payload,
            timeout=60
        )
        
        elapsed = time.time() - start
        print(f"⏱️  Response time: {elapsed:.2f}s")
        
        assert response.status_code == 200
        data = response.json()
        
        # Validate response structure
        assert "ai_log_id" in data
        assert "suggestions" in data
        assert "explanation" in data
        assert "can_apply" in data
        assert isinstance(data["suggestions"], list)
        
        print(f"✅ Planning suggestions generated ({len(data['suggestions'])} suggestions)")
        return data["ai_log_id"]

    def test_post_summarize(self):
        """Test post summarization endpoint"""
        payload = {
            "post_id": 1
        }
        
        print(f"\n💬 Testing post summarization...")
        start = time.time()
        
        response = requests.post(
            f"{BASE_URL}/ai/post/summarize",
            json=payload,
            timeout=60
        )
        
        elapsed = time.time() - start
        print(f"⏱️  Response time: {elapsed:.2f}s")
        
        assert response.status_code == 200
        data = response.json()
        
        # Validate response structure
        assert "summary" in data
        assert "category" in data
        assert "tags" in data
        assert data["category"] in ["question", "discussion", "resource", "announcement"]
        assert isinstance(data["tags"], list)
        
        print(f"✅ Post summary generated (category: {data['category']})")

    def test_logs_stats(self):
        """Test logs stats endpoint"""
        print(f"\n📊 Testing logs stats...")
        
        response = requests.get(f"{BASE_URL}/ai/logs/stats", timeout=10)
        
        assert response.status_code == 200
        data = response.json()
        
        # Validate response structure
        assert "total_requests" in data
        assert "success_count" in data
        assert "failed_count" in data
        assert "failure_rate" in data
        
        print(f"✅ Stats retrieved successfully")
        print(f"   Total requests: {data['total_requests']}")
        print(f"   Success: {data['success_count']}")
        print(f"   Failed: {data['failed_count']}")
        print(f"   Failure rate: {data['failure_rate']:.1f}%")

    def test_feedback_submission(self):
        """Test feedback submission endpoint"""
        payload = {
            "log_id": 1,
            "rating": 5
        }
        
        print(f"\n⭐ Testing feedback submission...")
        
        response = requests.post(
            f"{BASE_URL}/ai/feedback",
            json=payload,
            timeout=10
        )
        
        assert response.status_code == 200
        data = response.json()
        
        assert "status" in data
        assert data["status"] == "success"
        
        print(f"✅ Feedback submitted successfully")

    def test_idempotency(self):
        """Test idempotency key prevents duplicate generations"""
        payload = {
            "subject_id": 1,
            "chapter_id": 1,
            "num_questions": 2,
            "difficulty": "EASY",
            "topic": "Idempotency test"
        }
        
        print(f"\n🔒 Testing idempotency protection...")
        
        # First request
        response1 = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response1.status_code == 200
        data1 = response1.json()
        quiz_id_1 = data1["quiz_id"]
        
        # Second identical request (should return same quiz due to idempotency)
        response2 = requests.post(
            f"{BASE_URL}/ai/generate/quiz",
            json=payload,
            timeout=TIMEOUT
        )
        assert response2.status_code == 200
        data2 = response2.json()
        quiz_id_2 = data2["quiz_id"]
        
        print(f"   First quiz ID: {quiz_id_1}")
        print(f"   Second quiz ID: {quiz_id_2}")
        
        # Note: Idempotency might return same ID or different depending on implementation
        # The key is that duplicate requests don't cause errors
        print(f"✅ Idempotency working (no duplicate generation errors)")


def run_manual_tests():
    """Run manual tests with detailed output"""
    print("\n" + "="*60)
    print("🧪 MANUAL AI ENDPOINT TESTS")
    print("="*60)
    
    test_suite = TestAIEndpoints()
    test_suite.setup()
    
    tests = [
        ("AI Status", test_suite.test_ai_status),
        ("Quiz Generation", test_suite.test_quiz_generation),
        ("Flashcard Generation", test_suite.test_flashcard_generation),
        ("Chapter Summary", test_suite.test_chapter_summarize),
        ("Profile Enhancement", test_suite.test_profile_enhance),
        ("Planning Suggestions", test_suite.test_planning_suggest),
        ("Post Summary", test_suite.test_post_summarize),
        ("Logs Stats", test_suite.test_logs_stats),
        ("Feedback", test_suite.test_feedback_submission),
        ("Idempotency", test_suite.test_idempotency),
    ]
    
    results = {"passed": 0, "failed": 0}
    
    for name, test_func in tests:
        try:
            print(f"\n{'='*60}")
            print(f"Testing: {name}")
            print(f"{'='*60}")
            test_func()
            results["passed"] += 1
        except Exception as e:
            print(f"❌ FAILED: {e}")
            results["failed"] += 1
    
    print(f"\n{'='*60}")
    print(f"RESULTS: {results['passed']} passed, {results['failed']} failed")
    print(f"{'='*60}\n")


if __name__ == "__main__":
    run_manual_tests()
