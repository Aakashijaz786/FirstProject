# Install FFmpeg for YouTube Downloads

## Why FFmpeg is Required

FFmpeg is **required** for converting YouTube videos to MP3 and MP4 formats. Without it, you'll get a 500 error when trying to download.

## Windows Installation

### Option 1: Download Pre-built Binary (Easiest)

1. **Download FFmpeg:**
   - Go to: https://www.gyan.dev/ffmpeg/builds/
   - Click "Download Build" (the green button)
   - Download `ffmpeg-release-essentials.zip`

2. **Extract and Install:**
   - Extract the zip file
   - Copy the `ffmpeg.exe` and `ffprobe.exe` files from the `bin` folder
   - Paste them into: `C:\Windows\System32\` (or any folder in your PATH)

3. **Verify Installation:**
   ```powershell
   ffmpeg -version
   ```
   You should see version information.

### Option 2: Using Chocolatey (If you have it)

```powershell
choco install ffmpeg
```

### Option 3: Using Scoop (If you have it)

```powershell
scoop install ffmpeg
```

## After Installation

1. **Restart FastAPI Backend:**
   - Stop the current FastAPI server (Ctrl+C)
   - Start it again:
     ```powershell
     cd D:\100DaysPython\tiktokio.lol
     python -m uvicorn api.main:app --reload --host 127.0.0.1 --port 8001
     ```

2. **Test Download:**
   - Go to: http://localhost:8000/yt1s/
   - Paste a YouTube URL and try downloading

## Verify FFmpeg is Working

Run this command:
```powershell
ffmpeg -version
```

You should see output like:
```
ffmpeg version 6.x.x ...
```

If you see "command not found", FFmpeg is not in your PATH. Add it to your system PATH or restart your terminal.

## Troubleshooting

### Still Getting 500 Error?

1. Check FastAPI logs - look for FFmpeg-related errors
2. Verify FFmpeg is accessible:
   ```powershell
   where ffmpeg
   ```
3. Make sure you restarted the FastAPI server after installing FFmpeg

### Alternative: Use Static FFmpeg Build

If system-wide installation doesn't work, you can use a static build:

1. Download from: https://github.com/yt-dlp/FFmpeg-Builds/releases
2. Extract to a folder (e.g., `D:\ffmpeg`)
3. Update `api/providers/ytdlp_provider.py` to specify FFmpeg location:
   ```python
   ydl_opts['ffmpeg_location'] = r'D:\ffmpeg\bin\ffmpeg.exe'
   ```

