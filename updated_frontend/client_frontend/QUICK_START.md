# Quick Start Guide

## Step 1: Start the Backend Server

```bash
cd backend
pip install -r requirements.txt
uvicorn main:app --reload
```

The API will be available at `http://localhost:8000`

## Step 2: Open the Frontend

1. Open `index.html` in your browser, OR
2. Use a local server:
   ```bash
   python -m http.server 8080
   ```
   Then open `http://localhost:8080`

## Step 3: Test Translation

1. Click on the language dropdown (e.g., "English")
2. Select any language (e.g., "Español", "Français", "हिन्दी")
3. The page content will be translated automatically via API

## Troubleshooting

### "Failed to translate page" error
- Make sure the backend is running on `http://localhost:8000`
- Check browser console for CORS errors
- Update `API_BASE_URL` in `js/script.js` if backend is on different port

### Translation not working
- Check backend logs for errors
- Verify LibreTranslate service is accessible
- For Google Translate, ensure API key is set in `.env`

## Production Deployment

1. Update `API_BASE_URL` in `js/script.js` to your production backend URL
2. Set CORS origins in `backend/main.py` to your frontend domain
3. Use environment variables for API keys
4. Consider using a reverse proxy (nginx) for the backend

