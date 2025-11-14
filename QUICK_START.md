# Quick Start Guide - YouTube Downloader

## Starting the Application

### Step 1: Start PHP Server (Main Frontend)
```powershell
php -S localhost:8000 php_router.php
```
This serves the frontend website at: **http://localhost:8000/yt1s/**

### Step 2: Start FastAPI Backend (Required for Downloads)
You have two options:

**Option A: Use the batch file (Easiest)**
```powershell
.\start_fastapi.bat
```

**Option B: Manual start**
```powershell
cd api
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001
```

The FastAPI backend will run on: **http://127.0.0.1:8001**

## Testing the YouTube Downloader

1. Open your browser: **http://localhost:8000/yt1s/**
2. Paste a YouTube URL in the search box (e.g., `https://youtu.be/-3KT1f7WZIo`)
3. Click the **"Convert"** button
4. You should see:
   - Video thumbnail
   - Video title and author
   - Download options (MP3/MP4 tabs)
   - Quality options (320kbps, 256kbps, etc. for MP3)
5. Click any **Download** button to download the file

## Troubleshooting

### FastAPI Backend Not Starting
- Make sure Python 3.8+ is installed
- Install dependencies: `pip install -r api/requirements.txt`
- Check if port 8001 is available

### Downloads Not Working
- Verify FastAPI is running: Check `http://127.0.0.1:8001/health`
- Check browser console for errors
- Make sure `fastapi_base_url` in database is set to `http://127.0.0.1:8001`

### FAQ Formatting Issues
- Hard refresh the page (Ctrl+F5) to reload JavaScript
- Check browser console for errors

## Admin Portal

Access at: **http://localhost:8000/admin/login.php**
- Username: `admin`
- Password: `Admin@2025!`

Manage FAQs at: **http://localhost:8000/admin/yt_front_page.php?id=41&page=home**

