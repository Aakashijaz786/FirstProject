#!/usr/bin/env python3
"""Direct test of Cobalt API to see exact error"""
import requests
import json

API_URL = "https://api.ytfreeapi.cyou"

# Test MP4 download (like in Postman)
mp4_payload = {
    "url": "https://www.youtube.com/watch?v=I1Llz8075MA",
    "audioFormat": "mp3",
    "videoQuality": "1080",
    "filenameStyle": "basic",
    "youtubeHLS": True
}

# Test MP3 download
mp3_payload = {
    "url": "https://www.youtube.com/watch?v=I1Llz8075MA",
    "audioFormat": "mp3",
    "downloadMode": "audio",
    "filenameStyle": "basic",
    "youtubeHLS": True
}

print("="*60)
print("Testing Cobalt API Directly")
print("="*60)

print("\n1. Testing MP4 Download...")
print(f"URL: {API_URL}")
print(f"Payload: {json.dumps(mp4_payload, indent=2)}")
try:
    response = requests.post(API_URL, json=mp4_payload, timeout=30)
    print(f"\nStatus: {response.status_code}")
    print(f"Response: {json.dumps(response.json(), indent=2)}")
except Exception as e:
    print(f"\nError: {e}")
    if hasattr(e, 'response') and e.response is not None:
        print(f"Status: {e.response.status_code}")
        print(f"Body: {e.response.text[:500]}")

print("\n" + "="*60)
print("2. Testing MP3 Download...")
print(f"Payload: {json.dumps(mp3_payload, indent=2)}")
try:
    response = requests.post(API_URL, json=mp3_payload, timeout=30)
    print(f"\nStatus: {response.status_code}")
    print(f"Response: {json.dumps(response.json(), indent=2)}")
except Exception as e:
    print(f"\nError: {e}")
    if hasattr(e, 'response') and e.response is not None:
        print(f"Status: {e.response.status_code}")
        print(f"Body: {e.response.text[:500]}")

print("\n" + "="*60)
print("Testing with different video...")
test_payload = {
    "url": "https://www.youtube.com/watch?v=jNQXAC9IVRw",
    "audioFormat": "mp3",
    "downloadMode": "audio",
    "filenameStyle": "basic",
    "youtubeHLS": True
}
print(f"Payload: {json.dumps(test_payload, indent=2)}")
try:
    response = requests.post(API_URL, json=test_payload, timeout=30)
    print(f"\nStatus: {response.status_code}")
    print(f"Response: {json.dumps(response.json(), indent=2)}")
except Exception as e:
    print(f"\nError: {e}")
    if hasattr(e, 'response') and e.response is not None:
        print(f"Status: {e.response.status_code}")
        print(f"Body: {e.response.text[:500]}")

print("\n" + "="*60)
print("If all tests fail with 400, the API might be:")
print("- Rate limiting your IP")
print("- Requiring a different request format")
print("- Having temporary issues")
print("\nCheck Postman to confirm it still works!")
print("="*60)

