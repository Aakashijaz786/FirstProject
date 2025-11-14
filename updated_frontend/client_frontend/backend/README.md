# YT1s Translation API Backend

FastAPI backend for dynamic translation of website content.

## Setup

### Windows Users
**Easiest way:** Double-click `setup_windows.bat` then `start_server.bat`

**Manual way:**
```cmd
python -m pip install -r requirements.txt
python -m uvicorn main:app --reload
```

### Linux/Mac Users
```bash
pip install -r requirements.txt
# Or if pip not found:
python3 -m pip install -r requirements.txt

uvicorn main:app --reload
# Or:
python -m uvicorn main:app --reload
```

### Configuration
1. Copy `.env.example` to `.env` (optional, works without it)
2. Configure translation service:
   - **LibreTranslate** (default, free, no API key needed)
   - **Google Translate** (requires API key in `.env`)

The API will be available at `http://localhost:8000`

## API Endpoints

### POST `/translate`
Translate a single text.

**Request:**
```json
{
  "text": "Hello World",
  "target_lang": "es",
  "source_lang": "en"
}
```

**Response:**
```json
{
  "translatedText": "Hola Mundo",
  "originalText": "Hello World"
}
```

### POST `/translate/batch`
Translate multiple texts at once.

**Request:**
```json
{
  "texts": {
    "heroTitle": "YT1S - YouTube Video Downloader",
    "convertBtn": "Convert"
  },
  "target_lang": "es",
  "source_lang": "en"
}
```

**Response:**
```json
{
  "translations": {
    "heroTitle": "YT1S - Descargador de Videos de YouTube",
    "convertBtn": "Convertir"
  }
}
```

### GET `/languages`
Get list of supported languages.

## Using Google Translate API

1. Get API key from [Google Cloud Console](https://cloud.google.com/translate/docs/setup)
2. Set in `.env`:
   ```
   TRANSLATION_SERVICE=google
   GOOGLE_API_KEY=your_api_key_here
   ```

