@echo off
echo ========================================
echo Starting YT1s Translation API Backend
echo ========================================
echo.

REM Check if dependencies are installed
python -c "import fastapi" >nul 2>&1
if errorlevel 1 (
    echo Dependencies not found. Running setup...
    call setup_windows.bat
    if errorlevel 1 (
        pause
        exit /b 1
    )
)

echo Starting server on http://localhost:8000
echo.
echo API Documentation: http://localhost:8000/docs
echo Press Ctrl+C to stop the server
echo.
echo ========================================
echo.

python -m uvicorn main:app --reload

pause

