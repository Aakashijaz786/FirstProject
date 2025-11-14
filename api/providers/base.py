from __future__ import annotations

import abc
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict

from fastapi import HTTPException, status

from ..models import DownloadRequest, SearchRequest
from ..settings import settings


@dataclass
class ProviderContext:
    provider_key: str
    state: Dict[str, Any]
    site_profile: Dict[str, Any]


@dataclass
class DownloadResult:
    file_path: Path
    file_name: str
    mime_type: str
    metadata: Dict[str, Any]

    @property
    def file_size(self) -> int:
        try:
            return self.file_path.stat().st_size
        except FileNotFoundError:
            return 0


class ProviderBase(abc.ABC):
    key = 'base'

    def __init__(self, context: ProviderContext):
        self.context = context
        self.settings = settings

    @abc.abstractmethod
    async def search(self, payload: SearchRequest) -> Dict[str, Any]:
        raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED, "Provider does not implement search()")

    @abc.abstractmethod
    async def download(self, payload: DownloadRequest) -> DownloadResult:
        raise HTTPException(status.HTTP_501_NOT_IMPLEMENTED, "Provider does not implement download()")
