from __future__ import annotations

import asyncio
import shutil
import uuid
from typing import Any, Dict
from urllib.parse import urlparse

import httpx
from fastapi import HTTPException, status

from ..models import DownloadRequest, SearchRequest
from ..utils import apply_branding_metadata, brand_file_name, derive_mime
from .base import DownloadResult, ProviderBase


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
        headers = self._build_headers(config)

        async with httpx.AsyncClient(timeout=self.settings.cobalt_timeout_seconds) as client:
            try:
                response = await client.post(base_url, json=request_body, headers=headers)
                response.raise_for_status()
            except httpx.HTTPError as exc:
                raise HTTPException(status.HTTP_502_BAD_GATEWAY, f"Cobalt API error: {exc}") from exc

            data = response.json()
            download_url = data.get('url') or data.get('audio') or data.get('video') or data.get('download_url')
            if not download_url:
                raise HTTPException(status.HTTP_502_BAD_GATEWAY, "Cobalt API did not return a download url")

            extension = data.get('ext') or self._infer_extension(download_url, payload.format)
            site_name = payload.site_name or self.context.site_profile.get('site_name') or 'TikTok Downloader'
            title = data.get('title') or payload.title_override or 'media'
            final_name = brand_file_name(site_name, title, extension)
            tmp_path = self.settings.storage_work / f"cobalt-{uuid.uuid4().hex}.{extension}"
            final_path = self.settings.storage_ready / final_name

            try:
                async with client.stream('GET', download_url, headers=headers) as stream:
                    stream.raise_for_status()
                    with tmp_path.open('wb') as handle:
                        async for chunk in stream.aiter_bytes():
                            handle.write(chunk)
            except httpx.HTTPError as exc:
                if tmp_path.exists():
                    tmp_path.unlink(missing_ok=True)
                raise HTTPException(status.HTTP_502_BAD_GATEWAY, f"Cobalt asset download failed: {exc}") from exc

        shutil.move(str(tmp_path), final_path)
        apply_branding_metadata(final_path, site_name, title)

        metadata = {
            "title": title,
            "source_url": download_url,
            "provider": self.key,
            "api_payload": request_body,
        }

        return DownloadResult(
            file_path=final_path,
            file_name=final_name,
            mime_type=derive_mime(extension),
            metadata=metadata,
        )

    def _build_request_body(self, payload: DownloadRequest) -> Dict[str, Any]:
        download_type = 'audio' if payload.format in ('mp3', 'audio') else 'video'
        quality = payload.quality or ('192' if download_type == 'audio' else '720')
        body = {
            "url": payload.url,
            "dType": download_type,
            "vQuality": quality if download_type == 'video' else None,
            "aFormat": 'mp3' if download_type == 'audio' else 'mp4',
            "isAudioOnly": download_type == 'audio',
        }
        return {k: v for k, v in body.items() if v is not None}

    @staticmethod
    def _build_headers(config: Dict[str, Any]) -> Dict[str, str]:
        headers = {}
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
