from __future__ import annotations

import asyncio
import logging
import os
import shutil
import uuid
from pathlib import Path
from typing import Any, Dict, List, Optional
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
        self.ffmpeg_location = self._resolve_ffmpeg_location()

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
            'restrictfilenames': True,
            'windowsfilenames': True,
        }
        if self.ffmpeg_location:
            opts['ffmpeg_location'] = self.ffmpeg_location
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

    def _resolve_ffmpeg_location(self) -> Optional[str]:
        """
        Auto-detect FFmpeg/FFprobe so yt-dlp conversions do not fail on fresh systems.
        """
        config = self.context.state.get('config') or {}
        candidate_paths: List[Optional[str]] = [
            config.get('ffmpeg_location'),
            config.get('ffmpeg_path'),
            os.getenv('FASTAPI_FFMPEG_PATH'),
            os.getenv('FASTAPI_FFMPEG_DIR'),
            os.getenv('FFMPEG_PATH'),
            os.getenv('FFMPEG_BINARY'),
            os.getenv('FFMPEG_LOCATION'),
        ]

        ffmpeg_from_path = shutil.which('ffmpeg')
        if ffmpeg_from_path:
            candidate_paths.append(str(Path(ffmpeg_from_path).parent))

        repo_root = Path(__file__).resolve().parents[2]
        bundled_candidates = [
            repo_root / 'api' / 'bin' / 'ffmpeg' / 'bin',
            repo_root / 'api' / 'bin' / 'ffmpeg',
            repo_root / 'bin' / 'ffmpeg' / 'bin',
            repo_root / 'bin' / 'ffmpeg',
        ]
        candidate_paths.extend(str(path) for path in bundled_candidates)

        windows_defaults = [
            Path('C:/ffmpeg/bin'),
            Path('C:/Program Files/ffmpeg/bin'),
            Path('C:/Program Files (x86)/ffmpeg/bin'),
            Path('D:/ffmpeg/bin'),
        ]
        unix_defaults = [
            Path('/usr/bin'),
            Path('/usr/local/bin'),
            Path('/opt/homebrew/bin'),
        ]
        candidate_paths.extend(str(path) for path in windows_defaults + unix_defaults)

        seen: set[str] = set()
        for raw_path in candidate_paths:
            normalized = self._validate_ffmpeg_location(raw_path)
            if normalized and normalized not in seen:
                seen.add(normalized)
                logger.info("Using FFmpeg binaries from %s", normalized)
                return normalized

        logger.warning("FFmpeg executable not detected automatically. yt-dlp conversions may fail until FFmpeg is installed.")
        return None

    @staticmethod
    def _validate_ffmpeg_location(raw_path: Optional[str]) -> Optional[str]:
        if not raw_path:
            return None
        candidate = Path(str(raw_path).strip()).expanduser()
        if not candidate.exists():
            return None
        directory = candidate.parent if candidate.is_file() else candidate
        if not directory.exists():
            return None
        ffmpeg_binary = YTDLPProvider._find_binary(directory, 'ffmpeg')
        ffprobe_binary = YTDLPProvider._find_binary(directory, 'ffprobe')
        if ffmpeg_binary and ffprobe_binary:
            return str(directory)
        return None

    @staticmethod
    def _find_binary(directory: Path, name: str) -> Optional[Path]:
        candidates = [
            directory / name,
            directory / f"{name}.exe",
            directory / f"{name}.bat",
        ]
        for path in candidates:
            if path.exists():
                return path
        return None

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
