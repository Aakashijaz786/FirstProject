from __future__ import annotations

from typing import Optional
from urllib.parse import urlparse, urlunparse

from .db import db_connection


class ProxyRotator:
    """Round-robin proxy selector stored in `api_proxies`."""

    def __init__(self, provider_key: str):
        self.provider_key = provider_key

    def next_proxy(self) -> Optional[str]:
        row_data = None
        with db_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT id, proxy_uri, auth_username, auth_password "
                    "FROM api_proxies "
                    "WHERE provider_key=%s AND is_active=1 "
                    "ORDER BY COALESCE(last_used_at, '1970-01-01') ASC, id ASC "
                    "LIMIT 1",
                    (self.provider_key,),
                )
                row_data = cursor.fetchone()
                if not row_data:
                    return None
                cursor.execute("UPDATE api_proxies SET last_used_at=NOW() WHERE id=%s", (row_data["id"],))

        return format_proxy(row_data)


def format_proxy(row) -> str:
    proxy_uri: str = row["proxy_uri"]
    username = row.get("auth_username")
    password = row.get("auth_password")

    if username and password:
        parsed = urlparse(proxy_uri)
        netloc = parsed.netloc
        if "@" not in netloc:
            auth = f"{username}:{password}"
            host = parsed.hostname or ""
            if parsed.port:
                host = f"{host}:{parsed.port}"
            netloc = f"{auth}@{host}"
            proxy_uri = urlunparse(
                (parsed.scheme, netloc, parsed.path or "", parsed.params, parsed.query, parsed.fragment)
            )
    return proxy_uri
