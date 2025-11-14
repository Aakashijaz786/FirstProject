@echo off
echo ========================================
echo Fixing Dependency Conflicts
echo ========================================
echo.

echo Upgrading conflicting packages...
python -m pip install --upgrade requests>=2.32.2
python -m pip install --upgrade pydantic>=2.7.0

echo.
echo Reinstalling backend dependencies...
python -m pip install -r requirements.txt

echo.
echo ========================================
echo Done! Dependencies should be fixed now.
echo ========================================
echo.
pause

