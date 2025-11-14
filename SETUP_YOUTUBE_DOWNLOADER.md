# YouTube Downloader Setup Guide

## Quick Start

### Step 1: Install Python Dependencies
```powershell
cd api
pip install -r requirements.txt
```

If you get errors, try:
```powershell
python -m pip install --upgrade pip
pip install fastapi uvicorn[standard] yt-dlp mutagen httpx pymysql python-multipart orjson
```

### Step 2: Start FastAPI Backend
**Option A: Use the simple batch file**
```powershell
.\start_fastapi_simple.bat
```

**Option B: Manual start**
```powershell
cd api
python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001
```

The backend should show:
```
INFO:     Uvicorn running on http://127.0.0.1:8001
```

### Step 3: Verify Backend is Running
Open in browser: http://127.0.0.1:8001/health

You should see JSON response like:
```json
{
  "status": "ok",
  "app": "TikTokIO Media API",
  ...
}
```

### Step 4: Start PHP Server (if not already running)
```powershell
php -S localhost:8000 php_router.php
```

### Step 5: Test the Downloader
1. Open: http://localhost:8000/yt1s/
2. Paste YouTube URL: `https://youtu.be/-3KT1f7WZIo`
3. Click "Convert"
4. You should see:
   - Video thumbnail on the left
   - Download options table on the right
   - MP3/MP4 tabs
   - Quality options (320kbps, 256kbps, etc.)

## Configuration

### YouTube API Key
The API key is already configured in the code:
- Key: `AIzaSyBngprvHkjzJpiNHy5jdHIcpQ-bWDETxJE`
- Location: `api/providers/ytdlp_provider.py` (line 126)

To change it, edit the file or set environment variable:
```powershell
$env:YOUTUBE_API_KEY = "your-key-here"
```

### FastAPI URL
The FastAPI URL is configured in the database:
- Current: `http://127.0.0.1:8001`
- To update: Run `php update_fastapi_url.php`

## Troubleshooting

### Error: "Failed to connect to 127.0.0.1 port 8001"
**Solution:**
1. Make sure FastAPI backend is running (Step 2)
2. Check if port 8001 is available: `netstat -ano | findstr :8001`
3. If port is in use, change port in `start_fastapi_simple.bat` and update database

### Error: "Module not found" or Import errors
**Solution:**
```powershell
cd api
pip install -r requirements.txt
```

### Error: "YTDLP download failed"
**Solution:**
1. Make sure `yt-dlp` is installed: `pip install yt-dlp`
2. Update yt-dlp: `pip install --upgrade yt-dlp`
3. Check YouTube API key is valid

### Downloads not working
**Solution:**
1. Check FastAPI is running: http://127.0.0.1:8001/health
2. Check browser console for errors (F12)
3. Verify `fastapi_base_url` in database matches running port

## Features Implemented

✅ Convert button connected to backend
✅ YouTube URL processing
✅ Video thumbnail and info display
✅ Download options table (MP3/MP4 tabs)
✅ Multiple quality options:
   - MP3: 320kbps, 256kbps, 128kbps, 96kbps, 64kbps
   - MP4: 1080p, 720p, 480p, 360p
✅ YouTube API key integration
✅ Exact layout matching reference image

## File Structure

- `api_search.php` - Frontend API for searching YouTube videos
- `api_download.php` - Frontend API for preparing downloads
- `api/providers/ytdlp_provider.py` - YouTube download provider with API key
- `updated_frontend/client_frontend/js/script.js` - Frontend JavaScript with download UI
- `start_fastapi_simple.bat` - Simple script to start FastAPI backend

