# PowerShell Database Setup Script
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Database Setup Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Get MySQL root password
$rootPassword = Read-Host "Enter MySQL root password" -AsSecureString
$rootPasswordPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($rootPassword)
)

Write-Host ""
Write-Host "Step 1: Creating database and user..." -ForegroundColor Yellow

$createDbQuery = @"
CREATE DATABASE IF NOT EXISTS \`tiktokio.mobi\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'tiktokio.mobi'@'localhost' IDENTIFIED BY 'TfjfPrtjC4Z4wmBm';
GRANT ALL PRIVILEGES ON \`tiktokio.mobi\`.* TO 'tiktokio.mobi'@'localhost';
FLUSH PRIVILEGES;
"@

$createDbQuery | & mysql -u root -p"$rootPasswordPlain" 2>&1

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "ERROR: Failed to create database/user. Please check your MySQL root password." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "✓ Database and user created successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Step 2: Importing SQL file..." -ForegroundColor Yellow

# Use PowerShell-compatible method to import SQL
Get-Content "tiktokio_mobi.sql" | & mysql -u root -p"$rootPasswordPlain" "tiktokio.mobi" 2>&1

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "ERROR: Failed to import SQL file." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "✓ SQL file imported successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Database: tiktokio.mobi" -ForegroundColor White
Write-Host "User: tiktokio.mobi" -ForegroundColor White
Write-Host "Password: TfjfPrtjC4Z4wmBm" -ForegroundColor White
Write-Host ""
Write-Host "You can now access the admin portal at:" -ForegroundColor Cyan
Write-Host "http://localhost:8000/admin/login.php" -ForegroundColor Yellow
Write-Host ""
Write-Host "Username: admin" -ForegroundColor White
Write-Host "Password: Admin@2025!" -ForegroundColor White
Write-Host ""
Read-Host "Press Enter to exit"

