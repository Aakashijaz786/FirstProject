from __future__ import annotations

import asyncio
import shutil
import uuid
import logging
from typing import Any, Dict
from urllib.parse import parse_qs, urlparse

import httpx
from fastapi import HTTPException, status

from ..models import DownloadRequest, SearchRequest
from ..utils import apply_branding_metadata, brand_file_name, derive_mime
from .base import DownloadResult, ProviderBase

logger = logging.getLogger(__name__)


class CobaltProvider(ProviderBase):
    key = 'cobalt'

    async def search(self, payload: SearchRequest) -> Dict[str, Any]:
        # Cobalt is primarily a direct-download API. Return a normalized stub.
        query = payload.query.strip()
        return {
            "provider": self.key,
            "query": query,
            "items": [{
                "id": query,
                "title": query if self._looks_like_url(query) else f"Cobalt request for \"{query}\"",
                "url": query,
                "duration": None,
                "thumbnail": None,
                "author": "Cobalt",
                "provider": self.key,
            }]
        }

    async def download(self, payload: DownloadRequest) -> DownloadResult:
        config = self.context.state.get('config') or {}
        base_url = config.get('base_url')
        if not base_url:
            raise HTTPException(status.HTTP_400_BAD_REQUEST, "Cobalt base_url missing in admin config")

        request_body = self._build_request_body(payload)
        request_headers = self._build_headers(config)

        final_request_body = request_body

        async with httpx.AsyncClient(timeout=self.settings.cobalt_timeout_seconds) as client:
            while True:
                try:
                    response = await client.post(base_url, json=final_request_body, headers=request_headers)
                    response.raise_for_status()
                    data = response.json()
                    break
                except httpx.HTTPStatusError as exc:
                    fallback_body = self._fallback_body_for_error(final_request_body, exc)
                    if fallback_body:
                        logger.warning(
                            "Cobalt reported video unavailable. Retrying with youtubeHLS=false for %s",
                            final_request_body.get('url'),
                        )
                        final_request_body = fallback_body
                        continue
                    self._raise_cobalt_http_error(exc)
                except httpx.RequestError as exc:
                    raise HTTPException(
                        status.HTTP_502_BAD_GATEWAY,
                        f"Cobalt API request failed: {exc}"
                    ) from exc

            # Handle different response formats
            # Tunnel response: {"status": "tunnel", "url": "...", "filename": "..."}
            # Direct response: {"url": "...", "filename": "..."}
            api_status = data.get('status', '')
            download_url = data.get('url') or data.get('audio') or data.get('video') or data.get('download_url')
            
            if not download_url:
                raise HTTPException(
                    status.HTTP_502_BAD_GATEWAY, 
                    f"Cobalt API did not return a download url. Status: {api_status}, Response: {data}"
                )

            # Get filename from API response or use title
            api_filename = data.get('filename', '')
            extension = data.get('ext') or self._infer_extension(download_url, payload.format)
            site_name = payload.site_name or self.context.site_profile.get('site_name') or 'TikTok Downloader'
            title = data.get('title') or payload.title_override or api_filename or 'media'
            final_name = brand_file_name(site_name, title, extension)
            tmp_path = self.settings.storage_work / f"cobalt-{uuid.uuid4().hex}.{extension}"
            final_path = self.settings.storage_ready / final_name

            download_headers = self._build_headers(
                config,
                accept='*/*',
                include_content_type=False,
            )

            try:
                async with client.stream('GET', download_url, headers=download_headers) as stream:
                    stream.raise_for_status()
                    with tmp_path.open('wb') as handle:
                        async for chunk in stream.aiter_bytes():
                            handle.write(chunk)
            except httpx.HTTPStatusError as exc:
                if tmp_path.exists():
                    tmp_path.unlink(missing_ok=True)
                body = exc.response.text if exc.response is not None else ''
                snippet = body[:200]
                raise HTTPException(
                    status.HTTP_502_BAD_GATEWAY,
                    f"Cobalt asset download failed ({exc.response.status_code}): {snippet or str(exc)}"
                ) from exc
            except httpx.RequestError as exc:
                if tmp_path.exists():
                    tmp_path.unlink(missing_ok=True)
                raise HTTPException(
                    status.HTTP_502_BAD_GATEWAY,
                    f"Cobalt asset download failed: {exc}"
                ) from exc

        shutil.move(str(tmp_path), final_path)
        apply_branding_metadata(final_path, site_name, title)

        metadata = {
            "title": title,
            "source_url": download_url,
            "provider": self.key,
            "api_payload": final_request_body,
        }

        return DownloadResult(
            file_path=final_path,
            file_name=final_name,
            mime_type=derive_mime(extension),
            metadata=metadata,
        )

    def _build_request_body(self, payload: DownloadRequest) -> Dict[str, Any]:
        """
        Match the payload structure expected by https://api.ytfreeapi.cyou.

        Audio (mp3):
        {
            "url": "...",
            "audioFormat": "mp3",
            "downloadMode": "audio",
            "filenameStyle": "basic",
            "youtubeHLS": true
        }

        Video (mp4):
        {
            "url": "...",
            "audioFormat": "mp3",
            "videoQuality": "1080",
            "filenameStyle": "basic",
            "youtubeHLS": true
        }
        
        Note: downloadMode is ONLY for audio, not video!
        """
        config = self.context.state.get('config') or {}
        filename_style = config.get('filename_style', 'basic')
        youtube_hls = config.get('youtube_hls', True)
        audio_format = config.get('audio_format', 'mp3')
        default_video_quality = str(config.get('video_quality_default', '1080'))

        is_audio = payload.format in ('mp3', 'audio')
        requested_quality = payload.quality or (None if is_audio else default_video_quality)

        body: Dict[str, Any] = {
            "url": self._normalize_media_url(payload.url),
            "audioFormat": audio_format,
            "filenameStyle": filename_style,
            "youtubeHLS": bool(youtube_hls),
        }

        if is_audio:
            # Only add downloadMode for audio requests
            body["downloadMode"] = "audio"
        else:
            # For video, only add videoQuality (no downloadMode)
            body["videoQuality"] = str(requested_quality or default_video_quality)

        return body

    @staticmethod
    def _safe_json(response: httpx.Response | None) -> Dict[str, Any] | None:
        if response is None:
            return None
        try:
            data = response.json()
            return data if isinstance(data, dict) else None
        except ValueError:
            return None

    def _fallback_body_for_error(
        self,
        current_body: Dict[str, Any],
        exc: httpx.HTTPStatusError,
    ) -> Dict[str, Any] | None:
        if not current_body.get('youtubeHLS'):
            return None
        payload = self._safe_json(exc.response)
        error_code = None
        if payload:
            error = payload.get('error')
            if isinstance(error, dict):
                error_code = error.get('code')
        if error_code == 'error.api.content.video.unavailable':
            fallback = dict(current_body)
            fallback['youtubeHLS'] = False
            return fallback
        return None

    @staticmethod
    def _raise_cobalt_http_error(exc: httpx.HTTPStatusError) -> None:
        body = exc.response.text if exc.response is not None else ''
        snippet = body[:200]
        raise HTTPException(
            status.HTTP_502_BAD_GATEWAY,
            f"Cobalt API error ({exc.response.status_code}): {snippet or str(exc)}",
        ) from exc

    @staticmethod
    def _build_headers(
        config: Dict[str, Any],
        *,
        accept: str = "application/json",
        include_content_type: bool = True,
    ) -> Dict[str, str]:
        headers = {"Accept": accept}
        if include_content_type:
            headers["Content-Type"] = "application/json"
        token = config.get('token') or config.get('api_key')
        if token:
            headers['Authorization'] = f"Bearer {token}"
        if config.get('forward_headers'):
            headers.update(config['forward_headers'])
        return headers

    @staticmethod
    def _infer_extension(download_url: str, requested_format: str | None) -> str:
        parsed = urlparse(download_url)
        path = parsed.path or ''
        if '.' in path:
            ext = path.split('.')[-1]
            if len(ext) <= 4:
                return ext
        if requested_format in ('mp3', 'audio'):
            return 'mp3'
        return 'mp4'

    @staticmethod
    def _looks_like_url(value: str) -> bool:
        try:
            parsed = urlparse(value)
            return bool(parsed.scheme and parsed.netloc)
        except ValueError:
            return False


    @staticmethod
    def _normalize_media_url(raw_url: str | None) -> str:
        """
        Strip timestamp/short-link noise from YouTube URLs so cobalt does not
        respond with video unavailable for perfectly valid videos.
        """
        if not raw_url:
            return ''

        url = raw_url.strip()
        parsed = urlparse(url)
        host = parsed.netloc.lower()
        is_youtube = 'youtube.com' in host or 'youtu.be' in host

        if not is_youtube:
            return url

        query = parse_qs(parsed.query)
        video_id: str | None = None

        if query.get('v'):
            video_id = query['v'][0]
        elif host.endswith('youtu.be') and parsed.path:
            video_id = parsed.path.strip('/').split('/')[-1]
        elif parsed.path.startswith('/shorts/'):
            video_id = parsed.path.split('/')[-1]

        if not video_id:
            return url

        normalized = f"https://www.youtube.com/watch?v={video_id}"
        if query.get('list'):
            normalized += f"&list={query['list'][0]}"
        return normalized


