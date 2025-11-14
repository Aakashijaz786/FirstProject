from __future__ import annotations

import asyncio
import logging
import os
import shutil
import uuid
from pathlib import Path
from typing import Any, Dict, List
from urllib.parse import urlparse

from fastapi import HTTPException, status
from yt_dlp import DownloadError, YoutubeDL

logger = logging.getLogger(__name__)

from ..models import DownloadRequest, SearchRequest
from ..proxies import ProxyRotator
from ..utils import apply_branding_metadata, brand_file_name, derive_mime
from .base import DownloadResult, ProviderBase


class YTDLPProvider(ProviderBase):
    key = 'ytdlp'

    def __init__(self, context):
        super().__init__(context)
        self.rotator = ProxyRotator(self.key)

    async def search(self, payload: SearchRequest) -> Dict[str, Any]:
        return await asyncio.to_thread(self._search_sync, payload)

    async def download(self, payload: DownloadRequest) -> DownloadResult:
        return await asyncio.to_thread(self._download_sync, payload)

    # --------------------
    # Internal helpers
    # --------------------
    def _search_sync(self, payload: SearchRequest) -> Dict[str, Any]:
        query = payload.query.strip()
        limit = payload.limit
        request = query if self._looks_like_url(query) else f"ytsearch{limit}:{query}"
        ydl_opts = self._base_opts(skip_download=True)
        with YoutubeDL(ydl_opts) as ydl:
            try:
                info = ydl.extract_info(request, download=False)
            except DownloadError as exc:
                raise HTTPException(status.HTTP_400_BAD_REQUEST, f"YTDLP search failed: {exc}") from exc

        items = self._normalize_search_results(info)
        return {
            "provider": self.key,
            "query": query,
            "items": items[:limit],
        }

    def _download_sync(self, payload: DownloadRequest) -> DownloadResult:
        target_format = payload.format or 'mp3'
        desired_ext = 'mp3' if target_format in ('mp3', 'audio') else 'mp4'
        job_id = uuid.uuid4().hex
        work_dir = self.settings.storage_work / job_id
        work_dir.mkdir(parents=True, exist_ok=True)
        outtmpl = str(work_dir / '%(id)s.%(ext)s')
        ydl_opts = self._base_opts(skip_download=False)
        ydl_opts.update({
            'outtmpl': outtmpl,
            'writethumbnail': False,
            'format': self._format_for(target_format, payload.quality),
            'postprocessors': self._postprocessors_for(target_format, payload.quality),
        })

        with YoutubeDL(ydl_opts) as ydl:
            try:
                info = ydl.extract_info(payload.url.strip(), download=True)
            except DownloadError as exc:
                shutil.rmtree(work_dir, ignore_errors=True)
                error_msg = str(exc)
                # Check if it's an FFmpeg error
                if 'ffmpeg' in error_msg.lower() or 'ffprobe' in error_msg.lower():
                    raise HTTPException(
                        status.HTTP_500_INTERNAL_SERVER_ERROR,
                        f"FFmpeg is required for conversion but not found. Error: {error_msg}"
                    ) from exc
                raise HTTPException(status.HTTP_400_BAD_REQUEST, f"YTDLP download failed: {error_msg}") from exc
            except Exception as exc:
                shutil.rmtree(work_dir, ignore_errors=True)
                error_msg = str(exc)
                logger.error(f"Unexpected error during download: {error_msg}")
                raise HTTPException(
                    status.HTTP_500_INTERNAL_SERVER_ERROR,
                    f"Download failed: {error_msg}"
                ) from exc

        candidate = Path(ydl.prepare_filename(info))
        if desired_ext == 'mp3':
            candidate = candidate.with_suffix('.mp3')
        elif desired_ext == 'mp4':
            candidate = candidate.with_suffix('.mp4') if candidate.suffix.lower() != '.mp4' else candidate

        if not candidate.exists():
            # Fallback: grab the newest file from work dir
            files = sorted(work_dir.glob('*'), key=lambda p: p.stat().st_mtime, reverse=True)
            if not files:
                raise HTTPException(status.HTTP_500_INTERNAL_SERVER_ERROR, "No output file produced by yt-dlp")
            candidate = files[0]

        site_name = payload.site_name or self.context.site_profile.get('site_name') or 'TikTok Downloader'
        title = payload.title_override or info.get('title') or 'media'
        final_name = brand_file_name(site_name, title, desired_ext)
        final_path = self.settings.storage_ready / final_name
        final_path.parent.mkdir(parents=True, exist_ok=True)
        shutil.move(str(candidate), final_path)
        shutil.rmtree(work_dir, ignore_errors=True)

        apply_branding_metadata(final_path, site_name, info.get('title') or final_path.stem)

        metadata = {
            "title": info.get('title'),
            "duration": info.get('duration'),
            "thumbnail": info.get('thumbnail'),
            "source_url": info.get('webpage_url', payload.url),
            "uploader": info.get('uploader'),
            "provider": self.key,
        }

        return DownloadResult(
            file_path=final_path,
            file_name=final_name,
            mime_type=derive_mime(final_path.suffix),
            metadata=metadata,
        )

    def _base_opts(self, skip_download: bool) -> Dict[str, Any]:
        proxy = self.rotator.next_proxy()
        opts = {
            'quiet': True,
            'no_warnings': True,
            'noplaylist': True,
            'nocheckcertificate': True,
            'geo_bypass': True,
            'retries': 2,
            'user_agent': self.settings.http_user_agent,
        }
        # Add FFmpeg location if available in project bin folder
        # Try project root/bin first, then api/bin
        project_root = Path(__file__).resolve().parent.parent.parent
        api_root = Path(__file__).resolve().parent.parent
        ffmpeg_path = None
        if (project_root / 'bin' / 'ffmpeg.exe').exists():
            ffmpeg_path = project_root / 'bin' / 'ffmpeg.exe'
        elif (api_root / 'bin' / 'ffmpeg.exe').exists():
            ffmpeg_path = api_root / 'bin' / 'ffmpeg.exe'
        if ffmpeg_path and ffmpeg_path.exists():
            opts['ffmpeg_location'] = str(ffmpeg_path.parent)
        # Add YouTube API key if available
        youtube_api_key = os.getenv('YOUTUBE_API_KEY', 'AIzaSyBngprvHkjzJpiNHy5jdHIcpQ-bWDETxJE')
        if youtube_api_key and youtube_api_key != 'change-me':
            opts['extractor_args'] = {
                'youtube': {
                    'api_key': youtube_api_key,
                }
            }
        if proxy:
            opts['proxy'] = proxy
        if skip_download:
            opts['skip_download'] = True
        return opts

    def _format_for(self, target_format: str, quality: str | None) -> str:
        if target_format in ('mp3', 'audio'):
            return 'bestaudio/best'
        if target_format in ('mp4', 'video'):
            if quality and quality.isdigit():
                return f"bestvideo[height<={quality}]+bestaudio/best"
            return 'bestvideo+bestaudio/best'
        return 'best'

    def _postprocessors_for(self, target_format: str, quality: str | None = None) -> List[Dict[str, Any]]:
        processors: List[Dict[str, Any]] = []
        
        # Only add FFmpeg processors if we really need conversion
        # For MP3, we need to extract audio
        if target_format in ('mp3', 'audio'):
            # Map quality string to bitrate
            quality_map = {
                '320': '320',
                '256': '256',
                '192': '192',
                '128': '128',
                '96': '96',
                '64': '64',
            }
            preferred_quality = '192'  # default
            if quality:
                # Extract number from quality string (e.g., "320kbps" -> "320", "320" -> "320")
                quality_num = ''.join(filter(str.isdigit, str(quality)))
                if quality_num in quality_map:
                    preferred_quality = quality_map[quality_num]
            
            processors.append({
                'key': 'FFmpegExtractAudio',
                'preferredcodec': 'mp3',
                'preferredquality': preferred_quality,
            })
            # Add metadata processor
            processors.append({'key': 'FFmpegMetadata'})
        elif target_format in ('mp4', 'video'):
            # For MP4, try to get best format directly, only convert if needed
            processors.append({'key': 'FFmpegVideoConvertor', 'preferedformat': 'mp4'})
            processors.append({'key': 'FFmpegMetadata'})
        else:
            # Just add metadata for other formats
            processors.append({'key': 'FFmpegMetadata'})
        
        return processors

    @staticmethod
    def _looks_like_url(value: str) -> bool:
        try:
            parsed = urlparse(value)
            return bool(parsed.scheme and parsed.netloc)
        except ValueError:
            return False

    @staticmethod
    def _normalize_search_results(info: Dict[str, Any]) -> List[Dict[str, Any]]:
        entries = info.get('entries') if isinstance(info, dict) else []
        if not entries:
            entries = [info]
        results = []
        for entry in entries:
            if not entry:
                continue
            results.append({
                "id": entry.get('id') or entry.get('video_id'),
                "title": entry.get('title'),
                "url": entry.get('webpage_url') or entry.get('url'),
                "duration": entry.get('duration'),
                "thumbnail": entry.get('thumbnail'),
                "author": entry.get('uploader'),
                "provider": 'ytdlp',
            })
        return results
