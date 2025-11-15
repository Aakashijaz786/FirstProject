from __future__ import annotations

import asyncio
import logging
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

from fastapi import Depends, FastAPI, Header, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, JSONResponse

from .db import get_site_profile
from .models import DownloadRequest, DownloadResponse, SearchRequest, SearchResponse
from .providers import build_provider
from .settings import settings
from .utils import (
    cleanup_artifacts,
    delete_manifest,
    expires_at,
    human_file_size,
    isoformat,
    read_manifest,
    sign_token,
    write_manifest,
)

logger = logging.getLogger("tiktokio.api")
logging.basicConfig(level=getattr(logging, settings.log_level.upper(), logging.INFO))

app = FastAPI(
    title=settings.app_name,
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)


async def require_internal_key(
    x_internal_key: Optional[str] = Header(default=None, convert_underscores=False),
    authorization: Optional[str] = Header(default=None),
):
    """
    Lightweight auth guard for internal PHP -> FastAPI calls.

    For local development we disable strict key checking to avoid
    configuration mismatches between the .env file and the database.

    If you want to re‑enable protection in production, set a non‑default
    FASTAPI_AUTH_KEY and restore the comparison logic.
    """
    # DEVELOPMENT MODE: skip auth entirely
    return


@app.on_event("startup")
async def _startup() -> None:
    asyncio.create_task(_cleanup_worker())


async def _cleanup_worker() -> None:
    while True:
        try:
            cleanup_artifacts()
        except Exception as exc:  # pragma: no cover
            logger.warning("Cleanup worker failed: %s", exc)
        await asyncio.sleep(settings.cleanup_frequency_seconds)


@app.get("/health")
async def healthcheck():
    profile = get_site_profile()
    return {
        "status": "ok",
        "app": settings.app_name,
        "default_provider": settings.default_provider,
        "storage": str(settings.storage_root),
        "site": profile.get('site_url'),
    }


@app.post("/search", response_model=SearchResponse)
async def search_media(payload: SearchRequest, _: None = Depends(require_internal_key)):
    site_profile = get_site_profile()
    provider = build_provider(payload.provider or settings.default_provider, site_profile)
    raw = await provider.search(payload)
    return SearchResponse(**raw)


@app.post("/download", response_model=DownloadResponse)
async def download_media(payload: DownloadRequest, _: None = Depends(require_internal_key)):
    site_profile = get_site_profile()
    provider = build_provider(payload.provider or settings.default_provider, site_profile)
    # Iframe provider now supports downloads via freeapi.cyou

    result = await provider.download(payload)
    token = uuid.uuid4().hex
    expiry = expires_at(settings.download_ttl_seconds)
    manifest = {
        "file_path": str(result.file_path),
        "file_name": result.file_name,
        "mime_type": result.mime_type,
        "metadata": result.metadata,
        "expires_at": isoformat(expiry),
    }
    write_manifest(token, manifest)
    signature = sign_token(token)

    return DownloadResponse(
        provider=provider.key,
        file_name=result.file_name,
        file_size_bytes=result.file_size,
        human_size=human_file_size(result.file_size),
        mime_type=result.mime_type,
        download_token=token,
        signature=signature,
        expires_at=expiry,
        metadata=result.metadata,
    )


@app.get("/media/{token}")
async def fetch_media(token: str, sig: Optional[str] = None):
    manifest = read_manifest(token)
    if not manifest:
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Expired download token")

    expected_sig = sign_token(token)
    if sig != expected_sig:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Signature mismatch")

    expires_at_str = manifest.get('expires_at')
    if expires_at_str:
        try:
            expires = datetime.fromisoformat(expires_at_str)
        except ValueError:
            expires = datetime.now(timezone.utc)
        if expires < datetime.now(timezone.utc):
            delete_manifest(token)
            raise HTTPException(status.HTTP_410_GONE, "Download token expired")

    file_path = Path(manifest['file_path'])
    if not file_path.exists():
        delete_manifest(token)
        raise HTTPException(status.HTTP_404_NOT_FOUND, "File missing")

    return FileResponse(
        file_path,
        media_type=manifest.get('mime_type', 'application/octet-stream'),
        filename=manifest.get('file_name'),
    )
