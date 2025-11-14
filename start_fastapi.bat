@echo off
echo ========================================
echo Starting FastAPI Backend
echo ========================================
echo.

cd /d %~dp0api

echo Checking Python installation...
python --version
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Python is not installed or not in PATH
    pause
    exit /b 1
)

echo.
echo Checking virtual environment...
if not exist venv (
    echo Creating virtual environment...
    python -m venv venv
    if %ERRORLEVEL% NEQ 0 (
        echo ERROR: Failed to create virtual environment
        pause
        exit /b 1
    )
)

echo Activating virtual environment...
call venv\Scripts\activate.bat

echo.
echo Installing/updating dependencies...
pip install -r requirements.txt --quiet
if %ERRORLEVEL% NEQ 0 (
    echo WARNING: Some dependencies may have failed to install
)

echo.
echo ========================================
echo Starting FastAPI server on http://127.0.0.1:8001
echo ========================================
echo.
echo The server will reload automatically on code changes.
echo Press Ctrl+C to stop the server.
echo.

python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001

pause
