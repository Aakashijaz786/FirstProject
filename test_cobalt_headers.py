#!/usr/bin/env python3
"""Test Cobalt API with different headers to find what works"""
import requests
import json

API_URL = "https://api.ytfreeapi.cyou"

test_payload = {
    "url": "https://www.youtube.com/watch?v=jNQXAC9IVRw",
    "audioFormat": "mp3",
    "downloadMode": "audio",
    "filenameStyle": "basic",
    "youtubeHLS": True
}

print("="*60)
print("Testing different Accept headers")
print("="*60)

# Test 1: No Accept header
print("\n1. No Accept header:")
try:
    response = requests.post(API_URL, json=test_payload, headers={
        "Content-Type": "application/json"
    }, timeout=30)
    print(f"Status: {response.status_code}")
    print(f"Response: {response.text[:200]}")
except Exception as e:
    print(f"Error: {e}")

# Test 2: Accept: application/json
print("\n2. Accept: application/json:")
try:
    response = requests.post(API_URL, json=test_payload, headers={
        "Content-Type": "application/json",
        "Accept": "application/json"
    }, timeout=30)
    print(f"Status: {response.status_code}")
    print(f"Response: {response.text[:200]}")
except Exception as e:
    print(f"Error: {e}")

# Test 3: Accept: */*
print("\n3. Accept: */*:")
try:
    response = requests.post(API_URL, json=test_payload, headers={
        "Content-Type": "application/json",
        "Accept": "*/*"
    }, timeout=30)
    print(f"Status: {response.status_code}")
    if response.status_code == 200:
        print(f"✓ SUCCESS! Response: {response.text[:200]}")
    else:
        print(f"Response: {response.text[:200]}")
except Exception as e:
    print(f"Error: {e}")

# Test 4: Like browser/Postman
print("\n4. Accept: */* with User-Agent:")
try:
    response = requests.post(API_URL, json=test_payload, headers={
        "Content-Type": "application/json",
        "Accept": "*/*",
        "User-Agent": "PostmanRuntime/7.26.8"
    }, timeout=30)
    print(f"Status: {response.status_code}")
    if response.status_code == 200:
        print(f"✓ SUCCESS! Response: {response.text[:200]}")
    else:
        print(f"Response: {response.text[:200]}")
except Exception as e:
    print(f"Error: {e}")

print("\n" + "="*60)

