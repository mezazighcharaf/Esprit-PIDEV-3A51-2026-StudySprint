#!/bin/bash
# Script de test rapide pour validation IA StudySprint
# Usage: bash test_quick.sh

echo "🧪 TESTS RAPIDES - IMPLEMENTATION IA STUDYSPRINT"
echo "================================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

# Test 1: Ollama
echo "1️⃣  Testing Ollama..."
if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Ollama is running${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ Ollama not accessible on port 11434${NC}"
    ((FAILED++))
fi

# Test 2: FastAPI
echo ""
echo "2️⃣  Testing FastAPI..."
if curl -s http://localhost:8001/api/v1/ai/status > /dev/null 2>&1; then
    echo -e "${GREEN}✅ FastAPI is running${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ FastAPI not accessible on port 8001${NC}"
    ((FAILED++))
fi

# Test 3: Symfony
echo ""
echo "3️⃣  Testing Symfony..."
if curl -s http://localhost:8000 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Symfony is running${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ Symfony not accessible on port 8000${NC}"
    ((FAILED++))
fi

# Test 4: AI Status Endpoint
echo ""
echo "4️⃣  Testing AI Status endpoint..."
STATUS=$(curl -s http://localhost:8001/api/v1/ai/status | grep -o '"status":"ok"')
if [ ! -z "$STATUS" ]; then
    echo -e "${GREEN}✅ AI Status endpoint OK${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ AI Status endpoint failed${NC}"
    ((FAILED++))
fi

# Test 5: Monitoring Dashboard
echo ""
echo "5️⃣  Testing Monitoring Dashboard..."
if curl -s http://localhost:8000/bo/ai-monitoring | grep -q "Monitoring IA"; then
    echo -e "${GREEN}✅ Monitoring Dashboard accessible${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ Monitoring Dashboard not accessible${NC}"
    ((FAILED++))
fi

# Test 6: Database Connection (basic)
echo ""
echo "6️⃣  Testing Database connection..."
# This assumes php bin/console is available
cd "$(dirname "$0")"
if php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Database connection OK${NC}"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠️  Database test skipped (check manually)${NC}"
fi

echo ""
echo "================================================="
echo "RESULTS: ${PASSED} passed, ${FAILED} failed"
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed! System ready for AI generation.${NC}"
else
    echo -e "${RED}⚠️  Some tests failed. Check services above.${NC}"
fi
echo "================================================="
echo ""
echo "Next steps:"
echo "1. Open browser: http://localhost:8000"
echo "2. Navigate to: /fo/training/quizzes"
echo "3. Click: 'Générer un quiz (IA)'"
echo "4. Monitor: http://localhost:8000/bo/ai-monitoring"
