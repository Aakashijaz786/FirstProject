@echo off
echo ========================================
echo Testing if server can start
echo ========================================
echo.

python -c "import fastapi; import uvicorn; import requests; print('All packages imported successfully!')"

if errorlevel 1 (
    echo ERROR: Some packages are missing
    pause
    exit /b 1
)

echo.
echo Packages are working! Starting server...
echo.
python -m uvicorn main:app --reload

