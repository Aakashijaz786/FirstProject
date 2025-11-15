#!/usr/bin/env python3
"""Test Cobalt API - simple version"""
import requests
import json

API_URL = "https://api.ytfreeapi.cyou"

test_videos = [
    "https://www.youtube.com/watch?v=I1Llz8075MA",
    "https://www.youtube.com/watch?v=CAf6i4qWD7M",
    "https://www.youtube.com/watch?v=jNQXAC9IVRw",
]

print("Testing Cobalt API")
print("="*60)

for video_url in test_videos:
    print(f"\nVideo: {video_url}")
    
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
        print(f"MP3 Status: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print(f"SUCCESS - Status: {data.get('status')}")
            print(f"URL: {str(data.get('url', 'N/A'))[:80]}")
        else:
            print(f"FAILED: {response.text[:200]}")
    except Exception as e:
        print(f"ERROR: {str(e)[:100]}")

print("\n" + "="*60)

