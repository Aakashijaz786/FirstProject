#!/usr/bin/env python3
"""Test freeapi.cyou endpoint to understand its API"""
import requests
import json

BASE_URL = "https://freeapi.cyou"
TEST_VIDEO = "https://www.youtube.com/watch?v=nWbj7W_pD9U"

print("="*60)
print("Testing freeapi.cyou endpoint")
print("="*60)

# Test 1: Check if it's a web page or API
print("\n1. Testing GET request...")
try:
    response = requests.get(f"{BASE_URL}/?url={TEST_VIDEO}", timeout=30)
    print(f"Status: {response.status_code}")
    print(f"Content-Type: {response.headers.get('Content-Type', 'N/A')}")
    print(f"Response length: {len(response.text)}")
    if 'application/json' in response.headers.get('Content-Type', ''):
        print(f"JSON Response: {json.dumps(response.json(), indent=2)[:500]}")
    else:
        print(f"HTML Response (first 500 chars): {response.text[:500]}")
except Exception as e:
    print(f"Error: {e}")

# Test 2: Try POST request
print("\n2. Testing POST request...")
try:
    response = requests.post(
        BASE_URL,
        json={"url": TEST_VIDEO, "format": "mp3"},
        timeout=30
    )
    print(f"Status: {response.status_code}")
    print(f"Response: {response.text[:500]}")
except Exception as e:
    print(f"Error: {e}")

# Test 3: Try with format parameter in URL
print("\n3. Testing with format parameter...")
for format_type in ["mp3", "mp4"]:
    try:
        url = f"{BASE_URL}/?url={TEST_VIDEO}&format={format_type}"
        response = requests.get(url, timeout=30)
        print(f"Format {format_type}: Status {response.status_code}")
        if response.status_code == 200:
            content_type = response.headers.get('Content-Type', '')
            if 'application/json' in content_type:
                data = response.json()
                print(f"  JSON: {json.dumps(data, indent=2)[:200]}")
            elif 'audio' in content_type or 'video' in content_type:
                print(f"  Direct download! Content-Type: {content_type}")
            else:
                print(f"  HTML/Other: {content_type}")
    except Exception as e:
        print(f"  Error: {e}")

# Test 4: Check if there's an /api endpoint
print("\n4. Testing /api endpoint...")
try:
    response = requests.post(
        f"{BASE_URL}/api",
        json={"url": TEST_VIDEO, "format": "mp3"},
        headers={"Content-Type": "application/json"},
        timeout=30
    )
    print(f"Status: {response.status_code}")
    print(f"Response: {response.text[:500]}")
except Exception as e:
    print(f"Error: {e}")

print("\n" + "="*60)
print("Based on results, we'll implement the iframe provider")
print("="*60)

