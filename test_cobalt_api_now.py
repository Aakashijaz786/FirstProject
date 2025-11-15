#!/usr/bin/env python3
"""Test Cobalt API with exact URLs from user"""
import requests
import json

API_URL = "https://api.ytfreeapi.cyou"

# Test with user's video
test_videos = [
    "https://www.youtube.com/watch?v=I1Llz8075MA",
    "https://www.youtube.com/watch?v=CAf6i4qWD7M",  # From terminal error
    "https://www.youtube.com/watch?v=jNQXAC9IVRw",  # Me at the zoo
]

print("="*60)
print("Testing Cobalt API with actual videos")
print("="*60)

for video_url in test_videos:
    print(f"\nTesting: {video_url}")
    
    # Test MP3
    mp3_payload = {
        "url": video_url,
        "audioFormat": "mp3",
        "downloadMode": "audio",
        "filenameStyle": "basic",
        "youtubeHLS": True
    }
    
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }
    
    try:
        response = requests.post(API_URL, json=mp3_payload, headers=headers, timeout=30)
        print(f"  MP3 Status: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print(f"  ✓ SUCCESS! Status: {data.get('status')}")
            print(f"  URL: {data.get('url', 'N/A')[:80]}")
        else:
            print(f"  ✗ FAILED: {response.text[:150]}")
    except Exception as e:
        print(f"  ✗ ERROR: {e}")
    
    # Test MP4
    mp4_payload = {
        "url": video_url,
        "audioFormat": "mp3",
        "videoQuality": "1080",
        "filenameStyle": "basic",
        "youtubeHLS": True
    }
    
    try:
        response = requests.post(API_URL, json=mp4_payload, headers=headers, timeout=30)
        print(f"  MP4 Status: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print(f"  ✓ SUCCESS! Status: {data.get('status')}")
            print(f"  URL: {data.get('url', 'N/A')[:80]}")
        else:
            print(f"  ✗ FAILED: {response.text[:150]}")
    except Exception as e:
        print(f"  ✗ ERROR: {e}")

print("\n" + "="*60)
print("If videos fail, try testing in Postman to see if API itself")
print("is having issues with these specific videos.")
print("="*60)

