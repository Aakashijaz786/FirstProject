#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Test iframe provider"""
import requests
import json
import sys

FASTAPI_URL = "http://127.0.0.1:8001"
AUTH_KEY = "my-super-secret-fastapi-key-2024"
TEST_VIDEO = "https://www.youtube.com/watch?v=nWbj7W_pD9U"

print("="*60)
print("Testing Iframe Provider")
print("="*60)

# Test MP3 download
print("\n1. Testing MP3 Download...")
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
    print(f"Status: {response.status_code}")
    if response.status_code == 200:
        data = response.json()
        print("SUCCESS!")
        print(f"Filename: {data.get('file_name', 'N/A')}")
        print(f"Size: {data.get('human_size', 'N/A')}")
    else:
        print(f"FAILED: {response.text[:500]}")
        sys.exit(1)
except Exception as e:
    print(f"Exception: {e}")
    sys.exit(1)

# Test MP4 download
print("\n2. Testing MP4 Download...")
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
    print(f"Status: {response.status_code}")
    if response.status_code == 200:
        data = response.json()
        print("SUCCESS!")
        print(f"Filename: {data.get('file_name', 'N/A')}")
        print(f"Size: {data.get('human_size', 'N/A')}")
    else:
        print(f"FAILED: {response.text[:500]}")
        sys.exit(1)
except Exception as e:
    print(f"Exception: {e}")
    sys.exit(1)

print("\n" + "="*60)
print("ALL TESTS PASSED!")
print("="*60)

