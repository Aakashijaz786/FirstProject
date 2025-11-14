# Windows Setup Guide

## Quick Setup (Recommended)

1. **Double-click `setup_windows.bat`**
   - This will check Python installation
   - Install all required packages
   - Verify everything is working

2. **Double-click `start_server.bat`**
   - This will start the translation API server
   - Server runs on `http://localhost:8000`

## Manual Setup

If the batch files don't work, follow these steps:

### Step 1: Check Python Installation

Open Command Prompt and run:
```cmd
python --version
```

If you see an error:
- Download Python from: https://www.python.org/downloads/
- **Important**: During installation, check ✅ "Add Python to PATH"
- Restart Command Prompt after installation

### Step 2: Install Dependencies

Try these commands in order:

**Option 1 (Most Common):**
```cmd
python -m pip install -r requirements.txt
```

**Option 2 (If Option 1 fails):**
```cmd
py -m pip install -r requirements.txt
```

**Option 3 (If both fail):**
```cmd
python3 -m pip install -r requirements.txt
```

**Option 4 (Install individually):**
```cmd
python -m pip install fastapi
python -m pip install uvicorn[standard]
python -m pip install requests
python -m pip install python-dotenv
python -m pip install pydantic
```

### Step 3: Start the Server

```cmd
python -m uvicorn main:app --reload
```

Or use:
```cmd
py -m uvicorn main:app --reload
```

## Troubleshooting

### "python is not recognized"
- Python is not installed or not in PATH
- Reinstall Python with "Add to PATH" checked
- Restart Command Prompt

### "pip is not recognized"
- Use `python -m pip` instead of just `pip`
- This is the recommended way on Windows

### "ModuleNotFoundError"
- Dependencies not installed
- Run: `python -m pip install -r requirements.txt`

### Port 8000 already in use
- Another application is using port 8000
- Change port: `python -m uvicorn main:app --reload --port 8001`
- Update `API_BASE_URL` in `js/script.js` to match

## Verify Installation

1. Start the server
2. Open browser: http://localhost:8000
3. You should see: `{"message": "YT1s Translation API", "status": "running"}`
4. API docs: http://localhost:8000/docs

## Need Help?

1. Make sure Python 3.7+ is installed
2. Use `python -m pip` instead of `pip`
3. Run `setup_windows.bat` for automatic setup
4. Check that all files are in the `backend` folder

