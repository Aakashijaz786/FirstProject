from __future__ import annotations

from typing import Dict, Type

from fastapi import HTTPException, status

from ..db import get_provider_state
from .base import ProviderBase, ProviderContext
from .cobalt_provider import CobaltProvider
from .iframe_provider import IframeProvider
from .ytdlp_provider import YTDLPProvider


PROVIDER_MAP: Dict[str, Type[ProviderBase]] = {
    'ytdlp': YTDLPProvider,
    'cobalt': CobaltProvider,
    'iframe': IframeProvider,
}


def build_provider(provider_key: str, site_profile: dict) -> ProviderBase:
    normalized = (provider_key or 'ytdlp').lower()
    state = get_provider_state(normalized)
    if not state:
        raise HTTPException(status.HTTP_404_NOT_FOUND, f"Provider '{normalized}' not registered")
    if not state.get('is_enabled'):
        raise HTTPException(status.HTTP_400_BAD_REQUEST, f"Provider '{normalized}' is disabled")

    cls = PROVIDER_MAP.get(normalized)
    if not cls:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, f"Provider '{normalized}' has no handler")

    context = ProviderContext(provider_key=normalized, state=state, site_profile=site_profile)
    return cls(context)


__all__ = ['build_provider']
