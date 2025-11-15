#!/usr/bin/env python3
"""Test iframe provider NOW"""
import requests
import json
import pymysql

FASTAPI_URL = "http://127.0.0.1:8001"
AUTH_KEY = "my-super-secret-fastapi-key-2024"
MYSQL_PASS = "qwerty"
TEST_VIDEO = "https://www.youtube.com/watch?v=nWbj7W_pD9U"

print("="*60)
print("Testing Iframe Provider - FULL TEST")
print("="*60)

# Step 1: Verify database
print("\n1. Checking database...")
try:
    conn = pymysql.connect(
        host='localhost',
        user='root',
        password=MYSQL_PASS,
        database='tiktokio.mobi',
        cursorclass=pymysql.cursors.DictCursor
    )
    with conn.cursor() as cursor:
        cursor.execute("SELECT provider_key, is_enabled FROM api_providers WHERE provider_key='iframe'")
        result = cursor.fetchone()
        if result:
            print(f"   Iframe: {'ENABLED' if result['is_enabled'] else 'DISABLED'}")
            if not result['is_enabled']:
                cursor.execute("UPDATE api_providers SET is_enabled=1 WHERE provider_key='iframe'")
                conn.commit()
                print("   Enabled iframe provider")
        else:
            print("   Iframe provider not found in database")
    conn.close()
except Exception as e:
    print(f"   Error: {e}")

# Step 2: Test search
print("\n2. Testing SEARCH...")
try:
    response = requests.post(
        f"{FASTAPI_URL}/search",
        json={
            "query": TEST_VIDEO,
            "provider": "iframe",
            "limit": 1
        },
        headers={"X-Internal-Key": AUTH_KEY},
        timeout=30
    )
    print(f"   Status: {response.status_code}")
    if response.status_code == 200:
        data = response.json()
        print(f"   SUCCESS - Provider: {data.get('provider')}")
    else:
        print(f"   FAILED: {response.text[:200]}")
except Exception as e:
    print(f"   Exception: {e}")

# Step 3: Test MP3 download
print("\n3. Testing DOWNLOAD (MP3)...")
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
        print(f"   ✓✓✓ SUCCESS! ✓✓✓")
        print(f"   Filename: {data.get('file_name', 'N/A')}")
        print(f"   Size: {data.get('human_size', 'N/A')}")
        print(f"   Provider: {data.get('provider')}")
    else:
        print(f"   ✗ FAILED: {response.text[:400]}")
except Exception as e:
    print(f"   ✗ Exception: {e}")

# Step 4: Test MP4 download
print("\n4. Testing DOWNLOAD (MP4)...")
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
        print(f"   ✓✓✓ SUCCESS! ✓✓✓")
        print(f"   Filename: {data.get('file_name', 'N/A')}")
        print(f"   Size: {data.get('human_size', 'N/A')}")
        print(f"   Provider: {data.get('provider')}")
    else:
        print(f"   ✗ FAILED: {response.text[:400]}")
except Exception as e:
    print(f"   ✗ Exception: {e}")

print("\n" + "="*60)
print("TEST COMPLETE")
print("="*60)
print("\nIf both downloads succeeded, iframe provider is WORKING!")
print("If they failed, check FastAPI logs for details.")
print("="*60)

