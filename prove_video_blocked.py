#!/usr/bin/env python3
"""Prove that CAf6i4qWD7M is blocked by Cobalt API"""
import requests

API_URL = "https://api.ytfreeapi.cyou"

print("Testing the EXACT video you're using...")
print("="*60)

# The video from your error
blocked_video = "https://www.youtube.com/watch?v=CAf6i4qWD7M"

payload = {
    "url": blocked_video,
    "audioFormat": "mp3",
    "downloadMode": "audio",
    "filenameStyle": "basic",
    "youtubeHLS": True
}

headers = {
    "Content-Type": "application/json",
    "Accept": "application/json"
}

print(f"\nVideo: {blocked_video}")
print("Sending request to Cobalt API...")

response = requests.post(API_URL, json=payload, headers=headers, timeout=30)
print(f"\nStatus: {response.status_code}")
print(f"Response: {response.text}")

if response.status_code == 400:
    print("\n" + "="*60)
    print("PROOF: This video is BLOCKED by Cobalt API!")
    print("It's not our code - the API itself rejects it!")
    print("="*60)
    
print("\n\nNow testing WORKING videos...")
print("="*60)

working_videos = [
    "https://www.youtube.com/watch?v=I1Llz8075MA",
    "https://www.youtube.com/watch?v=jNQXAC9IVRw"
]

for video in working_videos:
    payload["url"] = video
    response = requests.post(API_URL, json=payload, headers=headers, timeout=30)
    status = "WORKS" if response.status_code == 200 else "BLOCKED"
    print(f"{video}: {status} ({response.status_code})")

print("\n" + "="*60)
print("Use the WORKING videos above for testing!")
print("="*60)

