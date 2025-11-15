from __future__ import annotations

import asyncio
import shutil
import uuid
import logging
from pathlib import Path
from typing import Any, Dict, Optional
from urllib.parse import parse_qs, urlparse

import httpx
from fastapi import HTTPException, status

from ..models import DownloadRequest, SearchRequest
from ..utils import apply_branding_metadata, brand_file_name, derive_mime
from .base import DownloadResult, ProviderBase

logger = logging.getLogger(__name__)


class CobaltProvider(ProviderBase):
    key = 'cobalt'

    AUDIO_BITRATES = ["320", "256", "128", "96", "64", "8"]
    VIDEO_QUALITIES = ["max", "4320", "2160", "1440", "1080", "720", "480", "360", "240", "144"]
    AUDIO_FORMATS = ["best", "mp3", "ogg", "wav", "opus"]
    FILENAME_STYLES = ["classic", "pretty", "basic", "nerdy"]
    DOWNLOAD_MODES = ["auto", "audio", "mute"]

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

        base_timeout = httpx.Timeout(self.settings.cobalt_timeout_seconds, connect=10)
        async with httpx.AsyncClient(timeout=base_timeout) as client:
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

            api_status = data.get('status', '')
            download_url = self._resolve_download_url(api_status, data)
            
            if not download_url:
                raise HTTPException(
                    status.HTTP_502_BAD_GATEWAY, 
                    f"Cobalt API did not return a download url. Status: {api_status}, Response: {data}"
                )

            # Get filename from API response or use title
            api_filename = self._resolve_filename(data)
            extension = (Path(api_filename).suffix[1:] if api_filename else None) or data.get('ext')
            extension = extension or self._infer_extension(download_url, payload.format)
            site_name = payload.site_name or self.context.site_profile.get('site_name') or 'TikTok Downloader'
            title = self._resolve_title(data, payload, api_filename)
            final_name = brand_file_name(site_name, title, extension)
            tmp_path = self.settings.storage_work / f"cobalt-{uuid.uuid4().hex}.{extension}"
            final_path = self.settings.storage_ready / final_name

            download_headers = self._build_headers(
                config,
                accept='*/*',
                include_content_type=False,
            )

            try:
                async with client.stream('GET', download_url, headers=download_headers, timeout=None) as stream:
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
        output_meta = data.get('output', {})
        if isinstance(output_meta, dict):
            metadata.update({
                "output": output_meta,
                "service": data.get('service')
            })

        return DownloadResult(
            file_path=final_path,
            file_name=final_name,
            mime_type=derive_mime(extension),
            metadata=metadata,
        )

    def _build_request_body(self, payload: DownloadRequest) -> Dict[str, Any]:
        """
        Map DownloadRequest into cobalt's API schema.
        """
        config = self.context.state.get('config') or {}
        is_audio = payload.format in ('mp3', 'audio')
        url = self._normalize_media_url(payload.url)

        filename_style = self._choose_option(
            self.FILENAME_STYLES,
            config,
            ['filename_style', 'filenameStyle'],
            default='basic',
        )
        download_mode = self._determine_download_mode(config, is_audio)

        request: Dict[str, Any] = {
            "url": url,
            "filenameStyle": filename_style,
            "downloadMode": download_mode,
        }

        codec = self._config_str(config, ['youtube_video_codec', 'youtubeVideoCodec'])
        if codec:
            request['youtubeVideoCodec'] = codec

        if not is_audio:
            container = self._config_str(config, ['youtube_video_container', 'youtubeVideoContainer'])
            if container:
                request['youtubeVideoContainer'] = container

        if download_mode == 'audio':
            request['audioFormat'] = self._choose_option(
                self.AUDIO_FORMATS,
                config,
                ['audio_format', 'audioFormat'],
                default='mp3' if payload.format in ('mp3', 'audio') else 'best',
            )
            request['audioBitrate'] = self._resolve_audio_bitrate(
                payload.quality,
                config.get('audio_bitrate') or config.get('audioBitrate')
            )
        else:
            request['videoQuality'] = self._resolve_video_quality(
                payload.quality,
                config.get('video_quality_default') or config.get('videoQuality') or '1080'
            )
            language = self._config_str(config, ['youtube_dub_lang', 'youtubeDubLang'])
            if language:
                request['youtubeDubLang'] = language
            subtitle_lang = self._config_str(config, ['subtitle_lang', 'subtitleLang'])
            if subtitle_lang:
                request['subtitleLang'] = subtitle_lang

        # Remove keys with falsy/None values to avoid incompatible fields on older instances
        return {key: value for key, value in request.items() if value not in (None, '')}

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
        payload = self._safe_json(exc.response)
        error_code = None
        if payload:
            error = payload.get('error')
            if isinstance(error, dict):
                error_code = error.get('code')
        if error_code == 'error.api.content.video.unavailable':
            fallback = dict(current_body)
            if fallback.get('downloadMode') == 'audio':
                bitrate = fallback.get('audioBitrate')
                degraded = self._lower_audio_bitrate(bitrate)
                if degraded and degraded != bitrate:
                    fallback['audioBitrate'] = degraded
                    return fallback
            else:
                quality = fallback.get('videoQuality')
                degraded = self._lower_video_quality(quality)
                if degraded and degraded != quality:
                    fallback['videoQuality'] = degraded
                    return fallback
        return None

    def _resolve_download_url(self, status_label: str, payload: Dict[str, Any]) -> str:
        if status_label in ('tunnel', 'redirect'):
            download_url = payload.get('url') or payload.get('download_url')
        elif status_label == 'local-processing':
            tunnels = payload.get('tunnel') or []
            download_url = tunnels[0] if tunnels else None
        elif status_label == 'error':
            error = payload.get('error') or {}
            message = error.get('code', 'cobalt_error')
            raise HTTPException(status.HTTP_502_BAD_GATEWAY, f"Cobalt API reported error: {message}")
        elif status_label == 'picker':
            raise HTTPException(
                status.HTTP_400_BAD_REQUEST,
                "Cobalt returned multiple media items (picker). Please provide a direct video URL."
            )
        else:
            download_url = payload.get('url') or payload.get('download_url')

        if not download_url:
            raise HTTPException(
                status.HTTP_502_BAD_GATEWAY,
                f"Cobalt API did not return a download url. Status: {status_label}, Response: {payload}"
            )
        return download_url

    @staticmethod
    def _resolve_filename(payload: Dict[str, Any]) -> Optional[str]:
        if payload.get('filename'):
            return payload['filename']
        output = payload.get('output')
        if isinstance(output, dict):
            filename = output.get('filename')
            if filename:
                return filename
        return None

    @staticmethod
    def _resolve_title(payload: Dict[str, Any], request: DownloadRequest, fallback_filename: Optional[str]) -> str:
        return (
            payload.get('title')
            or (payload.get('output') or {}).get('metadata', {}).get('title')
            or request.title_override
            or fallback_filename
            or 'media'
        )

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

    @staticmethod
    def _choose_option(options: list[str], config: Dict[str, Any], names: list[str], default: str) -> str:
        value = None
        for name in names:
            if name in config and config[name] not in (None, ''):
                value = config[name]
                break
        if isinstance(value, str):
            candidate = value.strip().lower()
            for option in options:
                if candidate == option.lower():
                    return option
        elif value in options:
            return value
        return default

    @staticmethod
    def _config_str(config: Dict[str, Any], names: list[str], default: Optional[str] = None) -> Optional[str]:
        for name in names:
            if name in config and config[name]:
                val = config[name]
                if isinstance(val, str):
                    stripped = val.strip()
                    if stripped:
                        return stripped
                else:
                    return str(val)
        return default

    def _determine_download_mode(self, config: Dict[str, Any], is_audio: bool) -> str:
        if is_audio:
            return 'audio'
        preferred = self._config_str(config, ['download_mode', 'downloadMode'], 'auto')
        preferred = (preferred or 'auto').lower()
        return preferred if preferred in self.DOWNLOAD_MODES else 'auto'

    def _resolve_audio_bitrate(self, requested_quality: Optional[str], configured_default: Optional[str]) -> str:
        candidates = []
        if requested_quality:
            digits = self._extract_digits(requested_quality)
            candidates.append(digits)
        if configured_default:
            candidates.append(self._extract_digits(configured_default) or configured_default)
        candidates.append('128')

        for candidate in candidates:
            if not candidate:
                continue
            candidate = str(candidate)
            if candidate in self.AUDIO_BITRATES:
                return candidate
        return '128'

    def _resolve_video_quality(self, requested_quality: Optional[str], configured_default: Optional[str]) -> str:
        candidates = []
        if requested_quality:
            digits = self._extract_digits(requested_quality)
            candidates.append(digits or requested_quality)
        if configured_default:
            candidates.append(self._extract_digits(configured_default) or configured_default)
        candidates.append('1080')

        for candidate in candidates:
            if not candidate:
                continue
            text = str(candidate).lower()
            if text == 'max':
                return 'max'
            if text in self.VIDEO_QUALITIES:
                return text
            digits = self._extract_digits(text)
            if digits and digits in self.VIDEO_QUALITIES:
                return digits
        return '1080'

    @staticmethod
    def _extract_digits(value: Optional[str]) -> Optional[str]:
        if not value:
            return None
        digits = ''.join(ch for ch in str(value) if ch.isdigit())
        return digits or None

    def _lower_audio_bitrate(self, current: Optional[str]) -> Optional[str]:
        if not current or current not in self.AUDIO_BITRATES:
            return None
        idx = self.AUDIO_BITRATES.index(current)
        if idx == len(self.AUDIO_BITRATES) - 1:
            return None
        return self.AUDIO_BITRATES[idx + 1]

    def _lower_video_quality(self, current: Optional[str]) -> Optional[str]:
        if not current:
            return None
        current = current.lower()
        if current not in self.VIDEO_QUALITIES:
            return None
        idx = self.VIDEO_QUALITIES.index(current)
        if idx == len(self.VIDEO_QUALITIES) - 1:
            return None
        return self.VIDEO_QUALITIES[idx + 1]


