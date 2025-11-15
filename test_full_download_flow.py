#!/usr/bin/env python3
"""Test complete download flow through FastAPI"""
import requests
import json
import pymysql

# Test configuration
FASTAPI_URL = "http://127.0.0.1:8001"
AUTH_KEY = "my-super-secret-fastapi-key-2024"
MYSQL_PASS = "qwerty"

# Working video URLs from previous test
WORKING_VIDEOS = [
    "https://www.youtube.com/watch?v=I1Llz8075MA",  # Works
    "https://www.youtube.com/watch?v=jNQXAC9IVRw",  # Works (Me at the zoo)
]

print("="*60)
print("Testing Full Download Flow")
print("="*60)

# Step 1: Check database
print("\n1. Checking database providers...")
try:
    conn = pymysql.connect(
        host='localhost',
        user='root',
        password=MYSQL_PASS,
        database='tiktokio.mobi',
        cursorclass=pymysql.cursors.DictCursor
    )
    with conn.cursor() as cursor:
        cursor.execute("SELECT provider_key, is_enabled FROM api_providers WHERE provider_key IN ('ytdlp', 'cobalt')")
        providers = cursor.fetchall()
        for p in providers:
            status = 'ENABLED' if p['is_enabled'] else 'DISABLED'
            print(f"   {p['provider_key']}: {status}")
    conn.close()
except Exception as e:
    print(f"   Database error: {e}")

# Step 2: Check FastAPI health
print("\n2. Checking FastAPI health...")
try:
    response = requests.get(f"{FASTAPI_URL}/health", timeout=5)
    if response.status_code == 200:
        data = response.json()
        print(f"   OK - Default provider: {data.get('default_provider')}")
    else:
        print(f"   ERROR: Status {response.status_code}")
except Exception as e:
    print(f"   ERROR: {e}")
    print("   Make sure FastAPI is running!")

# Step 3: Test Cobalt search
print("\n3. Testing Cobalt SEARCH...")
for video_url in WORKING_VIDEOS[:1]:  # Test with first working video
    try:
        search_payload = {
            "query": video_url,
            "provider": "cobalt",
            "limit": 1,
            "prefer_audio": True
        }
        response = requests.post(
            f"{FASTAPI_URL}/search",
            json=search_payload,
            headers={"X-Internal-Key": AUTH_KEY},
            timeout=30
        )
        print(f"   Status: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print(f"   Provider: {data.get('provider')}")
            print(f"   Items: {len(data.get('items', []))}")
        else:
            print(f"   Error: {response.text[:200]}")
    except Exception as e:
        print(f"   Exception: {e}")

# Step 4: Test Cobalt download (MP3)
print("\n4. Testing Cobalt DOWNLOAD (MP3)...")
for video_url in WORKING_VIDEOS[:1]:
    try:
        download_payload = {
            "url": video_url,
            "provider": "cobalt",
            "format": "mp3",
            "quality": None,
            "title_override": None,
            "site_name": "Test Site"
        }
        response = requests.post(
            f"{FASTAPI_URL}/download",
            json=download_payload,
            headers={"X-Internal-Key": AUTH_KEY},
            timeout=120
        )
        print(f"   Status: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print(f"   SUCCESS!")
            print(f"   URL: {data.get('url', 'N/A')[:80]}")
            print(f"   Filename: {data.get('filename', 'N/A')}")
        else:
            print(f"   FAILED: {response.text[:300]}")
    except Exception as e:
        print(f"   Exception: {e}")

# Step 5: Test Cobalt download (MP4)
print("\n5. Testing Cobalt DOWNLOAD (MP4)...")
for video_url in WORKING_VIDEOS[:1]:
    try:
        download_payload = {
            "url": video_url,
            "provider": "cobalt",
            "format": "mp4",
            "quality": "1080",
            "title_override": None,
            "site_name": "Test Site"
        }
        response = requests.post(
            f"{FASTAPI_URL}/download",
            json=download_payload,
            headers={"X-Internal-Key": AUTH_KEY},
            timeout=120
        )
        print(f"   Status: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print(f"   SUCCESS!")
            print(f"   URL: {data.get('url', 'N/A')[:80]}")
            print(f"   Filename: {data.get('filename', 'N/A')}")
        else:
            print(f"   FAILED: {response.text[:300]}")
    except Exception as e:
        print(f"   Exception: {e}")

print("\n" + "="*60)
print("SUMMARY")
print("="*60)
print("If downloads succeed, Cobalt is fully working!")
print("If they fail, check FastAPI logs for detailed errors.")
print("="*60)

