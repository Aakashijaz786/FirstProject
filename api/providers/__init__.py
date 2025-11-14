from __future__ import annotations

from typing import Dict, Type

from fastapi import HTTPException, status

from ..db import get_active_provider_key, get_provider_state
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
    """
    Resolve the effective provider to use for a request.

    Priority:
    1. Explicit provider_key from the request, if it exists AND is enabled
    2. Globally active provider from site_settings (admin panel)
    3. FastAPI default provider from settings

    This makes the system resilient if the PHP frontend or another client
    accidentally sends a disabled provider key (e.g. 'ytdlp' when it has
    been turned off in the admin UI).
    """
    # Normalise user-supplied key (may be empty or stale)
    requested = (provider_key or "").strip().lower()

    # Try the requested provider first (if any)
    state = None
    normalized = requested or ""
    if normalized:
        state = get_provider_state(normalized)
        if state and state.get("is_enabled"):
            # Happy path: requested provider exists and is enabled
            pass
        else:
            # Either not found or disabled – fall back to the active provider
            normalized = ""

    # If nothing selected yet, use the active provider from site_settings
    if not normalized:
        normalized = get_active_provider_key()
        state = get_provider_state(normalized)

    # Final safety net: fall back to FastAPI default provider
    if (not state or not state.get("is_enabled")) and normalized != "ytdlp":
        normalized = "ytdlp"
        state = get_provider_state(normalized)

    # If we still don't have a valid provider, raise a clear error
    if not state:
        # If nothing resolved, expose a clear error
        raise HTTPException(
            status.HTTP_404_NOT_FOUND,
            f"Provider '{normalized or requested or 'unknown'}' not registered",
        )

    if not state.get("is_enabled"):
        raise HTTPException(
            status.HTTP_400_BAD_REQUEST,
            f"Provider '{normalized}' is disabled",
        )

    cls = PROVIDER_MAP.get(normalized)
    if not cls:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, f"Provider '{normalized}' has no handler")

    context = ProviderContext(provider_key=normalized, state=state, site_profile=site_profile)
    return cls(context)


__all__ = ['build_provider']
