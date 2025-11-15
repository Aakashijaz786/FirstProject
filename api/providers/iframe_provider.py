from __future__ import annotations

import asyncio
import logging
import shutil
import uuid
from typing import Any, Dict
from urllib.parse import urlparse, parse_qs, urlencode

import httpx
from fastapi import HTTPException, status

from ..models import DownloadRequest, SearchRequest
from ..utils import apply_branding_metadata, brand_file_name, derive_mime
from .base import DownloadResult, ProviderBase

logger = logging.getLogger(__name__)


class IframeProvider(ProviderBase):
    key = 'iframe'

    async def search(self, payload: SearchRequest) -> Dict[str, Any]:
        config = self.context.state.get('config') or {}
        iframe_url = config.get('iframe_url') or config.get('url') or 'https://freeapi.cyou'
        title = config.get('title') or 'Iframe Downloader'
        return {
            "provider": self.key,
            "query": payload.query,
            "items": [{
                "id": payload.query,
                "title": title,
                "url": payload.query if self._looks_like_url(payload.query) else payload.query,
                "duration": None,
                "thumbnail": None,
                "author": "iframe",
                "provider": self.key,
                "extra": {"iframe_url": iframe_url},
            }]
        }

    async def download(self, payload: DownloadRequest) -> DownloadResult:
        config = self.context.state.get('config') or {}
        base_url = config.get('iframe_url') or config.get('url') or 'https://freeapi.cyou'
        
        # Clean the URL
        clean_url = self._clean_youtube_url(payload.url)
        if not clean_url.startswith('http'):
            clean_url = 'https://' + clean_url

        # Determine format
        is_audio = payload.format in ('mp3', 'audio')
        format_type = 'mp3' if is_audio else 'mp4'
        
        # Build request body for freeapi.cyou
        request_body = {
            "url": clean_url,
            "format": format_type
        }
        
        logger.info(f"Iframe API Request to {base_url}")
        logger.info(f"Request Body: {request_body}")

        async with httpx.AsyncClient(timeout=120.0) as client:
            try:
                # POST request to get download URL
                response = await client.post(
                    base_url,
                    json=request_body,
                    headers={
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    }
                )
                logger.info(f"Iframe API Response Status: {response.status_code}")
                
                if response.status_code != 200:
                    error_text = response.text[:300]
                    logger.error(f"Iframe API Error: {error_text}")
                    raise HTTPException(
                        status.HTTP_502_BAD_GATEWAY,
                        f"Iframe API error ({response.status_code}): {error_text}"
                    )
                
                data = response.json()
                logger.info(f"Iframe API Success Response: {data}")
                
                # Get download URL from response
                download_url = data.get('url') or data.get('download_url')
                if not download_url:
                    raise HTTPException(
                        status.HTTP_502_BAD_GATEWAY,
                        f"Iframe API did not return a download URL. Response: {data}"
                    )
                
                # Get filename from API or use title
                api_filename = data.get('filename', '')
                extension = format_type
                site_name = payload.site_name or self.context.site_profile.get('site_name') or 'TikTok Downloader'
                title = payload.title_override or api_filename or 'media'
                final_name = brand_file_name(site_name, title, extension)
                tmp_path = self.settings.storage_work / f"iframe-{uuid.uuid4().hex}.{extension}"
                final_path = self.settings.storage_ready / final_name

                # Download the file
                try:
                    async with client.stream('GET', download_url, timeout=None) as stream:
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
                        f"Iframe download failed ({exc.response.status_code}): {snippet or str(exc)}"
                    ) from exc
                except httpx.RequestError as exc:
                    if tmp_path.exists():
                        tmp_path.unlink(missing_ok=True)
                    raise HTTPException(
                        status.HTTP_502_BAD_GATEWAY,
                        f"Iframe download failed: {exc}"
                    ) from exc

            except httpx.RequestError as exc:
                raise HTTPException(
                    status.HTTP_502_BAD_GATEWAY,
                    f"Iframe API request failed: {exc}"
                ) from exc

        shutil.move(str(tmp_path), final_path)
        apply_branding_metadata(final_path, site_name, title)

        metadata = {
            "title": title,
            "source_url": download_url,
            "provider": self.key,
            "api_response": data,
        }

        return DownloadResult(
            file_path=final_path,
            file_name=final_name,
            mime_type=derive_mime(extension),
            metadata=metadata,
        )
    
    @staticmethod
    def _clean_youtube_url(url: str) -> str:
        """Remove timestamp and other problematic parameters from YouTube URLs"""
        try:
            parsed = urlparse(url)
            if 'youtube.com' in parsed.netloc or 'youtu.be' in parsed.netloc:
                query_params = parse_qs(parsed.query)
                if 'v' in query_params:
                    clean_query = urlencode({'v': query_params['v'][0]})
                    clean_url = f"{parsed.scheme}://{parsed.netloc}{parsed.path}?{clean_query}"
                    if clean_url != url:
                        logger.info(f"Cleaned YouTube URL: {url} -> {clean_url}")
                    return clean_url
            return url
        except Exception as e:
            logger.warning(f"Failed to clean URL {url}: {e}")
            return url
    
    @staticmethod
    def _looks_like_url(value: str) -> bool:
        try:
            parsed = urlparse(value)
            return bool(parsed.scheme and parsed.netloc)
        except ValueError:
            return False
