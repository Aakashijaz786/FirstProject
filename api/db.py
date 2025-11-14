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
        "SELECT site_name, site_url, site_email, jwt_secret, fastapi_auth_key "
        "FROM site_settings ORDER BY id ASC LIMIT 1"
    )
    profile = row or {
        "site_name": "TikTok Downloader",
        "site_url": "https://example.com",
        "jwt_secret": "change-me",
        "fastapi_auth_key": settings.fastapi_auth_key,
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
