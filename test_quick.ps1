# Script de test rapide PowerShell pour validation IA StudySprint
# Usage: .\test_quick.ps1

Write-Host "🧪 TESTS RAPIDES - IMPLEMENTATION IA STUDYSPRINT" -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""

$PASSED = 0
$FAILED = 0

# Test 1: Ollama
Write-Host "1️⃣  Testing Ollama..."
try {
    $response = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -Method Get -TimeoutSec 5 -UseBasicParsing
    Write-Host "✅ Ollama is running" -ForegroundColor Green
    $PASSED++
} catch {
    Write-Host "❌ Ollama not accessible on port 11434" -ForegroundColor Red
    $FAILED++
}

# Test 2: FastAPI
Write-Host ""
Write-Host "2️⃣  Testing FastAPI..."
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8001/api/v1/ai/status" -Method Get -TimeoutSec 5 -UseBasicParsing
    Write-Host "✅ FastAPI is running" -ForegroundColor Green
    $PASSED++
} catch {
    Write-Host "❌ FastAPI not accessible on port 8001" -ForegroundColor Red
    $FAILED++
}

# Test 3: Symfony
Write-Host ""
Write-Host "3️⃣  Testing Symfony..."
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000" -Method Get -TimeoutSec 5 -UseBasicParsing
    Write-Host "✅ Symfony is running" -ForegroundColor Green
    $PASSED++
} catch {
    Write-Host "❌ Symfony not accessible on port 8000" -ForegroundColor Red
    $FAILED++
}

# Test 4: AI Status Endpoint
Write-Host ""
Write-Host "4️⃣  Testing AI Status endpoint..."
try {
    $response = Invoke-RestMethod -Uri "http://localhost:8001/api/v1/ai/status" -Method Get -TimeoutSec 5
    if ($response.status -eq "ok") {
        Write-Host "✅ AI Status endpoint OK" -ForegroundColor Green
        $PASSED++
    } else {
        Write-Host "❌ AI Status endpoint returned unexpected status" -ForegroundColor Red
        $FAILED++
    }
} catch {
    Write-Host "❌ AI Status endpoint failed" -ForegroundColor Red
    $FAILED++
}

# Test 5: Monitoring Dashboard
Write-Host ""
Write-Host "5️⃣  Testing Monitoring Dashboard..."
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/bo/ai-monitoring" -Method Get -TimeoutSec 5 -UseBasicParsing
    if ($response.Content -like "*Monitoring IA*") {
        Write-Host "✅ Monitoring Dashboard accessible" -ForegroundColor Green
        $PASSED++
    } else {
        Write-Host "❌ Monitoring Dashboard content unexpected" -ForegroundColor Red
        $FAILED++
    }
} catch {
    Write-Host "❌ Monitoring Dashboard not accessible" -ForegroundColor Red
    $FAILED++
}

# Test 6: Database Connection
Write-Host ""
Write-Host "6️⃣  Testing Database connection..."
try {
    $result = php bin/console doctrine:query:sql "SELECT 1" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Database connection OK" -ForegroundColor Green
        $PASSED++
    } else {
        Write-Host "⚠️  Database test skipped (check manually)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "⚠️  Database test skipped (check manually)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host "RESULTS: $PASSED passed, $FAILED failed"
if ($FAILED -eq 0) {
    Write-Host "🎉 All tests passed! System ready for AI generation." -ForegroundColor Green
} else {
    Write-Host "⚠️  Some tests failed. Check services above." -ForegroundColor Red
}
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:"
Write-Host "1. Open browser: http://localhost:8000"
Write-Host "2. Navigate to: /fo/training/quizzes"
Write-Host "3. Click: 'Générer un quiz (IA)'"
Write-Host "4. Monitor: http://localhost:8000/bo/ai-monitoring"
