from __future__ import annotations

import json
import time
from contextlib import contextmanager
from typing import Any, Dict, Generator, Optional

import pymysql
from pymysql.cursors import DictCursor

from .settings import settings


@contextmanager
def db_connection() -> Generator[pymysql.connections.Connection, None, None]:
    conn = pymysql.connect(
        host=settings.db_host,
        port=settings.db_port,
        user=settings.db_user,
        password=settings.db_password,
        database=settings.db_name,
        autocommit=True,
        cursorclass=DictCursor,
    )
    try:
        yield conn
    finally:
        conn.close()


def fetch_one(query: str, params: Optional[tuple[Any, ...]] = None) -> Optional[Dict[str, Any]]:
    with db_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(query, params or ())
            row = cursor.fetchone()
            return row


def execute(query: str, params: Optional[tuple[Any, ...]] = None) -> None:
    with db_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(query, params or ())


_SITE_CACHE: Dict[str, Any] = {"value": None, "expires": 0}


def get_site_profile(force: bool = False) -> Dict[str, Any]:
    now = time.time()
    if not force and _SITE_CACHE["value"] and _SITE_CACHE["expires"] > now:
        return _SITE_CACHE["value"]

    row = fetch_one(
        "SELECT site_name, site_url, site_email, jwt_secret, fastapi_auth_key, active_api_provider "
        "FROM site_settings ORDER BY id ASC LIMIT 1"
    )
    profile = row or {
        "site_name": "TikTok Downloader",
        "site_url": "https://example.com",
        "jwt_secret": "change-me",
        "fastapi_auth_key": settings.fastapi_auth_key,
        "active_api_provider": settings.default_provider,
    }
    _SITE_CACHE["value"] = profile
    _SITE_CACHE["expires"] = now + 60
    return profile


def get_provider_state(provider_key: str) -> Optional[Dict[str, Any]]:
    row = fetch_one(
        "SELECT provider_key, display_name, is_enabled, config_payload "
        "FROM api_providers WHERE provider_key=%s LIMIT 1",
        (provider_key,),
    )
    if not row:
        return None
    payload = row.get("config_payload")
    row["config"] = json.loads(payload) if payload else {}
    return row


def _provider_is_enabled(provider_key: str) -> bool:
    if not provider_key:
        return False
    row = fetch_one(
        "SELECT is_enabled FROM api_providers WHERE provider_key=%s LIMIT 1",
        (provider_key,),
    )
    return bool(row and row.get("is_enabled"))


def _first_enabled_provider() -> Optional[str]:
    row = fetch_one(
        "SELECT provider_key FROM api_providers "
        "WHERE is_enabled=1 "
        "ORDER BY CASE provider_key "
        "    WHEN 'cobalt' THEN 1 "
        "    WHEN 'ytdlp' THEN 2 "
        "    WHEN 'iframe' THEN 3 "
        "    ELSE 4 "
        "END, provider_key ASC "
        "LIMIT 1"
    )
    if not row:
        return None
    return (row.get("provider_key") or "").strip().lower()


def get_active_provider_key() -> str:
    """
    Resolve the globally active provider while ensuring it is enabled.
    Falls back to the first enabled provider (preferring cobalt -> ytdlp -> iframe),
    and ultimately re-enables ytdlp if every provider has been disabled.
    """
    row = fetch_one(
        "SELECT active_api_provider FROM site_settings ORDER BY id ASC LIMIT 1"
    )
    candidate = (row.get("active_api_provider") or "").strip().lower() if row else ""

    if candidate and _provider_is_enabled(candidate):
        return candidate

    fallback = _first_enabled_provider()
    if fallback:
        if fallback != candidate:
            execute(
                "UPDATE site_settings SET active_api_provider=%s ORDER BY id ASC LIMIT 1",
                (fallback,),
            )
        return fallback

    # No provider is enabled – re-enable ytdlp as a last resort
    execute(
        "UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp' LIMIT 1"
    )
    execute(
        "UPDATE site_settings SET active_api_provider='ytdlp' ORDER BY id ASC LIMIT 1"
    )
    return 'ytdlp'
