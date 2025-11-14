# Provider Switching Fix ✅

## What Was Wrong

When you:
1. **Searched** with YTDLP (or any provider)
2. **Changed** the active provider in Admin to Cobalt or Iframe
3. **Tried to download** from the search results

The download was still using the **old cached provider** from the search session, not the current active provider from the database.

**Error you saw:**
```
Provider 'ytdlp' is disabled
```

This happened because `api_download.php` was reading the provider from the search session (which was set when you first searched), instead of checking the current active provider in the database.

## What I Fixed

**File:** `api_download.php` (line 94-96)

**Before:**
```php
$provider = $search_session['provider'];  // ❌ Uses old cached provider
```

**After:**
```php
// Use current active provider from database, not cached from search session
$provider = get_active_api_provider($conn);  // ✅ Always uses current active provider
```

## How to Test

1. **Go to Admin → API Settings**
2. **Disable YTDLP** (uncheck the Enabled switch, click Update)
3. **Enable Cobalt** (check Enabled, enter your Cobalt URL, click Update)
4. **Set Cobalt as Active Provider** (select Cobalt radio button, click "Save Default Provider")
5. **Go to homepage:** `http://localhost:8000/yt1s/`
6. **Paste a YouTube URL** and click **Convert**
7. **Click Download** (MP3 or MP4)

**Expected Result:** ✅ Download should work with Cobalt, no "Provider 'ytdlp' is disabled" error

## How It Works Now

- **Search:** Always uses current active provider from database ✅
- **Download:** Always uses current active provider from database ✅
- **Cache:** Automatically refreshes when you change provider in admin ✅

## Important Notes

- If you change providers **after** searching, the download will use the **new provider** (not the one used for search)
- This is intentional - downloads always use whatever is currently active
- If you want to use a specific provider, make sure it's set as active **before** searching

