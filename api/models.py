from __future__ import annotations

from datetime import datetime
from typing import Any, Dict, List, Literal, Optional

from pydantic import BaseModel, Field, HttpUrl


class SearchRequest(BaseModel):
    query: str = Field(..., min_length=1, max_length=500)
    provider: str = Field('ytdlp', description="Provider key (ytdlp|cobalt|iframe)")
    limit: int = Field(5, ge=1, le=25)
    locale: Optional[str] = Field(None, description="Language/locale code")
    prefer_audio: bool = Field(False, description="Hint to prioritize audio formats")


class SearchItem(BaseModel):
    id: Optional[str]
    title: Optional[str]
    url: Optional[str]
    duration: Optional[int]
    thumbnail: Optional[HttpUrl | str]
    author: Optional[str]
    provider: str
    extra: Dict[str, Any] = Field(default_factory=dict)


class SearchResponse(BaseModel):
    provider: str
    query: str
    items: List[SearchItem]


class DownloadRequest(BaseModel):
    url: str = Field(..., min_length=5, max_length=2048)
    provider: str = Field('ytdlp')
    format: Literal['mp3', 'mp4', 'audio', 'video'] = 'mp3'
    quality: Optional[str] = None
    title_override: Optional[str] = None
    locale: Optional[str] = None
    site_name: Optional[str] = None


class DownloadResponse(BaseModel):
    provider: str
    file_name: str
    file_size_bytes: int
    human_size: str
    mime_type: str
    download_token: str
    signature: str
    expires_at: datetime
    metadata: Dict[str, Any]
