from __future__ import annotations

from typing import Any, Dict

from fastapi import HTTPException, status

from ..models import DownloadRequest, SearchRequest
from .base import DownloadResult, ProviderBase


class IframeProvider(ProviderBase):
    key = 'iframe'

    async def search(self, payload: SearchRequest) -> Dict[str, Any]:
        config = self.context.state.get('config') or {}
        iframe_url = config.get('iframe_url') or config.get('url')
        title = config.get('title') or 'Iframe Downloader'
        return {
            "provider": self.key,
            "query": payload.query,
            "items": [{
                "id": payload.query,
                "title": title,
                "url": iframe_url or payload.query,
                "duration": None,
                "thumbnail": None,
                "author": "iframe",
                "provider": self.key,
                "extra": {"iframe_url": iframe_url},
            }]
        }

    async def download(self, payload: DownloadRequest) -> DownloadResult:  # pragma: no cover
        raise HTTPException(
            status.HTTP_400_BAD_REQUEST,
            "Iframe provider does not produce downloadable files. "
            "Render the configured iframe_url on the frontend instead."
        )
