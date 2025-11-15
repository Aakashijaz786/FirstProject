@echo off
echo ================================================
echo FIXING ALL ISSUES NOW
echo ================================================
echo.

cd /d "D:\projects\MyProject\tiktokio.lol\tiktokio.lol"

echo Step 1: Enabling providers in database...
php -r "require 'admin/includes/config.php'; $conn->query('UPDATE api_providers SET is_enabled=1 WHERE provider_key=\"ytdlp\"'); $conn->query('UPDATE api_providers SET is_enabled=1 WHERE provider_key=\"cobalt\"'); $conn->query('UPDATE site_settings SET active_api_provider=\"ytdlp\"'); $r=$conn->query('SELECT provider_key, is_enabled FROM api_providers'); echo 'Providers:' . PHP_EOL; while($row=$r->fetch_assoc()){ echo '  ' . $row['provider_key'] . ': ' . ($row['is_enabled'] ? 'ENABLED' : 'DISABLED') . PHP_EOL; } $r=$conn->query('SELECT active_api_provider FROM site_settings LIMIT 1'); $row=$r->fetch_assoc(); echo 'Default: ' . $row['active_api_provider'] . PHP_EOL;"

echo.
echo Step 2: FFmpeg path detection added to code (already done)
echo.

echo ================================================
echo DONE! Now do these 2 things:
echo ================================================
echo.
echo 1. RESTART FASTAPI SERVER:
echo    - In the FastAPI terminal press Ctrl+C
echo    - Run: .\start_fastapi.bat
echo.
echo 2. CLEAR BROWSER CACHE:
echo    - Press Ctrl+F5 on http://localhost:8000
echo.
pause


