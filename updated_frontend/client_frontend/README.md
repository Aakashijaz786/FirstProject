# YT1s YouTube Downloader - Frontend & Backend

A multi-language YouTube downloader website with dynamic translation API.

## Project Structure

```
client_frontend/
├── index.html
├── youtube-to-mp3.html
├── youtube-to-mp4.html
├── css/
│   ├── styles.css
│   ├── features.css
│   └── responsive.css
├── js/
│   └── script.js (API-based translation)
├── images/
└── backend/
    ├── main.py (FastAPI server)
    ├── requirements.txt
    └── README.md
```

## Setup Instructions

### 1. Backend Setup (FastAPI)

```bash
cd backend
pip install -r requirements.txt
cp .env.example .env  # Edit .env if needed
uvicorn main:app --reload
```

The API will run on `http://localhost:8000`

### 2. Frontend Setup

1. Open `index.html` in a browser, OR
2. Use a local server:
   ```bash
   # Python
   python -m http.server 8080
   
   # Node.js
   npx http-server -p 8080
   ```

3. **Important**: Update the API URL in `js/script.js`:
   ```javascript
   const API_BASE_URL = 'http://localhost:8000';
   ```

### 3. Translation Service

The backend uses **LibreTranslate** by default (free, no API key needed).

To use Google Translate API:
1. Get API key from [Google Cloud Console](https://cloud.google.com/translate/docs/setup)
2. Update `backend/.env`:
   ```
   TRANSLATION_SERVICE=google
   GOOGLE_API_KEY=your_api_key_here
   ```

## Features

- ✅ Dynamic translation for all 21 languages
- ✅ API-based translation (no static files)
- ✅ Translation caching for performance
- ✅ Fallback to English on errors
- ✅ Persistent language selection

## Supported Languages

English, Spanish, French, German, Hindi, Arabic, Bengali, Indonesian, Italian, Japanese, Korean, Myanmar, Malay, Filipino, Portuguese, Russian, Thai, Turkish, Vietnamese, Simplified Chinese, Traditional Chinese

## API Endpoints

- `POST /translate` - Translate single text
- `POST /translate/batch` - Translate multiple texts
- `GET /languages` - Get supported languages

See `backend/README.md` for detailed API documentation.

