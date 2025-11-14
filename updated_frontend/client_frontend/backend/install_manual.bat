@echo off
echo ========================================
echo Manual Installation Guide
echo ========================================
echo.
echo If pip is not recognized, try these commands:
echo.

echo Option 1: Use python -m pip
echo   python -m pip install -r requirements.txt
echo.

echo Option 2: Use py launcher
echo   py -m pip install -r requirements.txt
echo.

echo Option 3: Use python3
echo   python3 -m pip install -r requirements.txt
echo.

echo Option 4: Install packages individually
echo   python -m pip install fastapi
echo   python -m pip install uvicorn[standard]
echo   python -m pip install requests
echo   python -m pip install python-dotenv
echo   python -m pip install pydantic
echo.

echo ========================================
echo Running Option 1 (python -m pip)...
echo ========================================
echo.

python -m pip install --upgrade pip
python -m pip install fastapi uvicorn[standard] requests python-dotenv pydantic

if errorlevel 1 (
    echo.
    echo ========================================
    echo Option 1 failed. Trying Option 2...
    echo ========================================
    echo.
    py -m pip install --upgrade pip
    py -m pip install fastapi uvicorn[standard] requests python-dotenv pydantic
)

if errorlevel 1 (
    echo.
    echo ========================================
    echo Installation failed!
    echo ========================================
    echo.
    echo Please ensure Python is installed:
    echo 1. Download from: https://www.python.org/downloads/
    echo 2. During installation, check "Add Python to PATH"
    echo 3. Restart your terminal/command prompt
    echo 4. Try again
    echo.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Installation successful!
echo ========================================
echo.
pause

