@echo off
echo ========================================
echo YT1s Translation API - Windows Setup
echo ========================================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo.
    echo Please install Python from: https://www.python.org/downloads/
    echo Make sure to check "Add Python to PATH" during installation
    echo.
    pause
    exit /b 1
)

echo Python found!
python --version
echo.

echo Installing dependencies...
python -m pip install --upgrade pip
python -m pip install -r requirements.txt

if errorlevel 1 (
    echo.
    echo ERROR: Failed to install dependencies
    echo.
    echo Try these alternatives:
    echo 1. python -m pip install fastapi uvicorn requests python-dotenv pydantic
    echo 2. py -m pip install -r requirements.txt
    echo 3. python3 -m pip install -r requirements.txt
    echo.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Setup complete!
echo ========================================
echo.
echo To start the server, run:
echo   python -m uvicorn main:app --reload
echo.
echo Or use: start_server.bat
echo.
pause

