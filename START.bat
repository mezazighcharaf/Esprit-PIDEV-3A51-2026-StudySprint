@echo off
title StudySprint - Lancement complet
color 0A

echo.
echo  ============================================
echo   STUDYSPRINT - Lancement de l'environnement
echo  ============================================
echo.
echo  [!] Assure-toi que XAMPP (Apache + MySQL) est deja lance
echo.
pause

cd /d "%~dp0"

:: ─── 1. FastAPI (AI Gateway) ─────────────────────────────────
echo [1/3] Lancement du gateway IA (FastAPI :8001)...
start "FastAPI Gateway" cmd /k "cd /d %~dp0api && python run.py"
timeout /t 3 /nobreak >nul

:: ─── 2. Mercure Hub ──────────────────────────────────────────
echo [2/3] Lancement de Mercure (notifications temps reel)...
start "Mercure Hub" cmd /k "cd /d %~dp0 && mercure.exe run --config mercure.Caddyfile"
timeout /t 2 /nobreak >nul

:: ─── 3. Symfony ──────────────────────────────────────────────
echo [3/3] Lancement du serveur Symfony (:8000)...
start "Symfony" cmd /k "cd /d %~dp0 && php -S localhost:8000 -t public"
timeout /t 3 /nobreak >nul

:: ─── Recap ───────────────────────────────────────────────────
echo.
echo  ============================================
echo   Tout est lance !
echo  ============================================
echo.
echo   App        : http://localhost:8000
echo   IA Gateway : http://localhost:8001
echo   Mercure    : http://localhost:3000
echo.
echo   Login admin : admin@studysprint.local / admin123
echo   Login Alice : alice.martin@studysprint.local / user123
echo.
echo  [Ferme cette fenetre quand tu as fini]
echo  ============================================
echo.
pause
