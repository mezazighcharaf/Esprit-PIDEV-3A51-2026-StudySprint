@echo off
echo ======================================
echo  StudySprint - Mercure Hub
echo  Port : 3000
echo  Hub  : http://localhost:3000/.well-known/mercure
echo ======================================
echo.

cd /d "%~dp0"

if not exist "mercure.exe" (
    echo [ERREUR] mercure.exe introuvable dans ce dossier !
    echo Telechargez-le sur : https://github.com/dunglas/mercure/releases/latest
    echo Fichier a telecharger : mercure_Windows_x86_64.zip
    pause
    exit /b 1
)

echo [OK] Demarrage du hub Mercure sur http://localhost:3000
echo [OK] Appuyez sur Ctrl+C pour arreter
echo.
mercure.exe run --config mercure.Caddyfile
pause
