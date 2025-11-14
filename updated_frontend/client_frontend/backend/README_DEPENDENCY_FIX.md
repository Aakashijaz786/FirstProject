# Fixing Dependency Conflicts

## The Issue

You're seeing warnings like:
```
ERROR: pip's dependency resolver does not currently take into account all the packages that are installed.
datasets 3.6.0 requires requests>=2.32.2, but you have requests 2.31.0
pydantic-extra-types 2.9.0 requires pydantic>=2.5.2, but you have pydantic 2.5.0
```

## Good News

**The server should still work!** These are just warnings about conflicts with other packages you have installed (like `datasets`).

## Option 1: Ignore the Warnings (Recommended)

The FastAPI server will work fine with the current versions. Just start it:

```cmd
python -m uvicorn main:app --reload
```

## Option 2: Fix the Conflicts

If you want to fix the warnings, run:

```cmd
python -m pip install --upgrade requests pydantic
python -m pip install -r requirements.txt
```

Or use the fix script:
```cmd
fix_dependencies.bat
```

## Option 3: Use a Virtual Environment (Best Practice)

This isolates your project dependencies:

```cmd
python -m venv venv
venv\Scripts\activate
python -m pip install -r requirements.txt
python -m uvicorn main:app --reload
```

## Test the Server

Run `test_server.bat` to verify everything works, or manually:

```cmd
python -m uvicorn main:app --reload
```

Then open: http://localhost:8000

You should see: `{"message": "YT1s Translation API", "status": "running"}`

