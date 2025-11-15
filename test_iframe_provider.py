#!/usr/bin/env python3
"""Test iframe provider with freeapi.cyou"""
import requests
import json
import pymysql

FASTAPI_URL = "http://127.0.0.1:8001"
AUTH_KEY = "my-super-secret-fastapi-key-2024"
MYSQL_PASS = "qwerty"
TEST_VIDEO = "https://www.youtube.com/watch?v=nWbj7W_pD9U"

print("="*60)
print("Testing Iframe Provider with freeapi.cyou")
print("="*60)

# Step 1: Enable iframe provider
print("\n1. Enabling iframe provider in database...")
try:
    conn = pymysql.connect(
        host='localhost',
        user='root',
        password=MYSQL_PASS,
        database='tiktokio.mobi',
        cursorclass=pymysql.cursors.DictCursor
    )
    with conn.cursor() as cursor:
        cursor.execute("UPDATE api_providers SET is_enabled=1 WHERE provider_key='iframe'")
        cursor.execute("SELECT provider_key, is_enabled FROM api_providers WHERE provider_key='iframe'")
        result = cursor.fetchone()
        print(f"   Iframe: {'ENABLED' if result['is_enabled'] else 'DISABLED'}")
    conn.close()
except Exception as e:
    print(f"   Error: {e}")

# Step 2: Test search
print("\n2. Testing iframe SEARCH...")
try:
    response = requests.post(
        f"{FASTAPI_URL}/search",
        json={
            "query": TEST_VIDEO,
            "provider": "iframe",
            "limit": 1,
            "prefer_audio": True
        },
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

# Step 3: Test MP3 download
print("\n3. Testing iframe DOWNLOAD (MP3)...")
try:
    response = requests.post(
        f"{FASTAPI_URL}/download",
        json={
            "url": TEST_VIDEO,
            "provider": "iframe",
            "format": "mp3",
            "quality": None,
            "title_override": None,
            "site_name": "Test Site"
        },
        headers={"X-Internal-Key": AUTH_KEY},
        timeout=180
    )
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        data = response.json()
        print(f"   SUCCESS!")
        print(f"   Filename: {data.get('file_name', 'N/A')}")
        print(f"   Size: {data.get('human_size', 'N/A')}")
        print(f"   Token: {data.get('download_token', 'N/A')[:20]}...")
    else:
        print(f"   FAILED: {response.text[:300]}")
except Exception as e:
    print(f"   Exception: {e}")

# Step 4: Test MP4 download
print("\n4. Testing iframe DOWNLOAD (MP4)...")
try:
    response = requests.post(
        f"{FASTAPI_URL}/download",
        json={
            "url": TEST_VIDEO,
            "provider": "iframe",
            "format": "mp4",
            "quality": "1080",
            "title_override": None,
            "site_name": "Test Site"
        },
        headers={"X-Internal-Key": AUTH_KEY},
        timeout=180
    )
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        data = response.json()
        print(f"   SUCCESS!")
        print(f"   Filename: {data.get('file_name', 'N/A')}")
        print(f"   Size: {data.get('human_size', 'N/A')}")
        print(f"   Token: {data.get('download_token', 'N/A')[:20]}...")
    else:
        print(f"   FAILED: {response.text[:300]}")
except Exception as e:
    print(f"   Exception: {e}")

print("\n" + "="*60)
print("SUMMARY")
print("="*60)
print("If both MP3 and MP4 downloads succeed, iframe provider is working!")
print("="*60)

