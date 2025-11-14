from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Dict, Optional
import requests
import os
import time
from collections import deque
from dotenv import load_dotenv

load_dotenv()

app = FastAPI(title="YT1s Translation API")

# Enable CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify your frontend domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Translation service configuration
# Using LibreTranslate (free) or Google Translate API
TRANSLATION_SERVICE = os.getenv("TRANSLATION_SERVICE", "libretranslate")  # or "google"
LIBRETRANSLATE_URL = os.getenv("LIBRETRANSLATE_URL", "https://libretranslate.com/translate")
GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY", "")

# Rate limiting for LibreTranslate (10 requests per minute)
RATE_LIMIT_REQUESTS = 8  # Keep it under 10 to be safe
RATE_LIMIT_WINDOW = 60  # 60 seconds
request_times = deque()

class TranslationRequest(BaseModel):
    text: str
    target_lang: str
    source_lang: Optional[str] = "en"

class BatchTranslationRequest(BaseModel):
    texts: Dict[str, str]  # {key: text} pairs
    target_lang: str
    source_lang: Optional[str] = "en"

# Language code mapping
LANGUAGE_MAP = {
    "en": "en",
    "es": "es",
    "fr": "fr",
    "de": "de",
    "hi": "hi",
    "ar": "ar",
    "bn": "bn",
    "id": "id",
    "it": "it",
    "ja": "ja",
    "ko": "ko",
    "my": "my",
    "ms": "ms",
    "tl": "tl",
    "pt": "pt",
    "ru": "ru",
    "th": "th",
    "tr": "tr",
    "vi": "vi",
    "zh-cn": "zh",
    "zh-tw": "zh-TW"
}

def wait_for_rate_limit():
    """Wait if we're hitting rate limits"""
    global request_times
    current_time = time.time()
    
    # Remove requests older than the rate limit window
    while request_times and request_times[0] < current_time - RATE_LIMIT_WINDOW:
        request_times.popleft()
    
    # If we're at the limit, wait
    if len(request_times) >= RATE_LIMIT_REQUESTS:
        sleep_time = RATE_LIMIT_WINDOW - (current_time - request_times[0]) + 1
        if sleep_time > 0:
            print(f"Rate limit reached. Waiting {sleep_time:.1f} seconds...")
            time.sleep(sleep_time)
            # Clean up again after waiting
            while request_times and request_times[0] < time.time() - RATE_LIMIT_WINDOW:
                request_times.popleft()
    
    # Record this request
    request_times.append(time.time())

def translate_with_libretranslate(text: str, target_lang: str, source_lang: str = "en", retry_count: int = 3) -> str:
    """Translate using LibreTranslate (free, no API key needed) with rate limiting"""
    # Wait for rate limit
    wait_for_rate_limit()
    
    try:
        # Map language codes
        target = LANGUAGE_MAP.get(target_lang, target_lang)
        source = LANGUAGE_MAP.get(source_lang, source_lang)
        
        response = requests.post(
            LIBRETRANSLATE_URL,
            json={
                "q": text,
                "source": source,
                "target": target,
                "format": "text"
            },
            timeout=10
        )
        
        if response.status_code == 200:
            result = response.json()
            return result.get("translatedText", text)
        elif response.status_code == 429:
            # Rate limited - wait and retry
            if retry_count > 0:
                wait_time = 60  # Wait 60 seconds for rate limit
                print(f"Rate limited (429). Waiting {wait_time} seconds before retry...")
                time.sleep(wait_time)
                return translate_with_libretranslate(text, target_lang, source_lang, retry_count - 1)
            else:
                print(f"LibreTranslate rate limit error after retries: {response.text}")
                return text
        else:
            print(f"LibreTranslate error: {response.status_code} - {response.text}")
            return text
    except Exception as e:
        print(f"Translation error: {str(e)}")
        return text

def translate_with_google(text: str, target_lang: str, source_lang: str = "en") -> str:
    """Translate using Google Translate API (requires API key)"""
    if not GOOGLE_API_KEY:
        raise HTTPException(status_code=500, detail="Google API key not configured")
    
    try:
        url = "https://translation.googleapis.com/language/translate/v2"
        params = {
            "key": GOOGLE_API_KEY,
            "q": text,
            "target": target_lang,
            "source": source_lang
        }
        
        response = requests.post(url, params=params, timeout=10)
        
        if response.status_code == 200:
            result = response.json()
            return result["data"]["translations"][0]["translatedText"]
        else:
            raise HTTPException(status_code=response.status_code, detail=response.text)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Translation error: {str(e)}")

@app.get("/")
def read_root():
    return {"message": "YT1s Translation API", "status": "running"}

@app.post("/translate")
def translate_text(request: TranslationRequest):
    """Translate a single text"""
    try:
        if TRANSLATION_SERVICE == "google":
            translated = translate_with_google(request.text, request.target_lang, request.source_lang)
        else:
            translated = translate_with_libretranslate(request.text, request.target_lang, request.source_lang)
        
        return {"translatedText": translated, "originalText": request.text}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/translate/batch")
def translate_batch(request: BatchTranslationRequest):
    """Translate multiple texts at once with rate limiting"""
    try:
        translated_dict = {}
        total_texts = len(request.texts)
        
        for index, (key, text) in enumerate(request.texts.items(), 1):
            if TRANSLATION_SERVICE == "google":
                translated = translate_with_google(text, request.target_lang, request.source_lang)
            else:
                translated = translate_with_libretranslate(text, request.target_lang, request.source_lang)
                # Add small delay between requests to respect rate limits
                if index < total_texts:
                    time.sleep(0.5)  # 500ms delay between requests
            
            translated_dict[key] = translated
        
        return {"translations": translated_dict}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/languages")
def get_languages():
    """Get list of supported languages"""
    return {
        "languages": [
            {"code": "en", "name": "English"},
            {"code": "es", "name": "Español"},
            {"code": "fr", "name": "Français"},
            {"code": "de", "name": "Deutsch"},
            {"code": "hi", "name": "हिन्दी"},
            {"code": "ar", "name": "عربي"},
            {"code": "bn", "name": "বাঙালি"},
            {"code": "id", "name": "Indonesian"},
            {"code": "it", "name": "Italiano"},
            {"code": "ja", "name": "日本語"},
            {"code": "ko", "name": "한국어"},
            {"code": "my", "name": "Myanmar"},
            {"code": "ms", "name": "Malay"},
            {"code": "tl", "name": "Filipino"},
            {"code": "pt", "name": "Português"},
            {"code": "ru", "name": "Русский"},
            {"code": "th", "name": "ไทย"},
            {"code": "tr", "name": "Türkçe"},
            {"code": "vi", "name": "Tiếng Việt"},
            {"code": "zh-cn", "name": "简体中文"},
            {"code": "zh-tw", "name": "繁體中文"}
        ]
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)

