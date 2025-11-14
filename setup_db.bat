@echo off
echo ========================================
echo Database Setup Script
echo ========================================
echo.
echo This script will:
echo 1. Create the database 'tiktokio.mobi'
echo 2. Create the user 'tiktokio.mobi' with password 'TfjfPrtjC4Z4wmBm'
echo 3. Grant privileges
echo 4. Import the SQL file
echo.
echo You will be prompted for your MySQL root password.
echo.
pause

echo.
echo Step 1: Creating database and user...
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS \`tiktokio.mobi\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; CREATE USER IF NOT EXISTS 'tiktokio.mobi'@'localhost' IDENTIFIED BY 'TfjfPrtjC4Z4wmBm'; GRANT ALL PRIVILEGES ON \`tiktokio.mobi\`.* TO 'tiktokio.mobi'@'localhost'; FLUSH PRIVILEGES;"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: Failed to create database/user. Please check your MySQL root password.
    pause
    exit /b 1
)

echo.
echo Step 2: Importing SQL file...
mysql -u root -p tiktokio.mobi < tiktokio_mobi.sql

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: Failed to import SQL file.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Database: tiktokio.mobi
echo User: tiktokio.mobi
echo Password: TfjfPrtjC4Z4wmBm
echo.
echo You can now access the admin portal at:
echo http://localhost:8000/admin/login.php
echo.
echo Username: admin
echo Password: Admin@2025!
echo.
pause

