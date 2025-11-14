import os
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


def _load_inline_env() -> None:
    """
    Lightweight .env loader (avoids introducing python-dotenv dependency).
    """
    env_path = Path(__file__).resolve().parent / '.env'
    if not env_path.exists():
        return

    for raw_line in env_path.read_text(encoding='utf-8').splitlines():
        line = raw_line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, value = line.split('=', 1)
        key = key.strip()
        value = value.strip().strip('\'"')
        os.environ.setdefault(key, value)


_load_inline_env()


@dataclass
class Settings:
    """Runtime configuration for the FastAPI microservice."""

    app_name: str = os.getenv('FASTAPI_APP_NAME', 'TikTokIO Media API')
    fastapi_auth_key: str = os.getenv('FASTAPI_AUTH_KEY', 'change-me')

    db_host: str = os.getenv('FASTAPI_DB_HOST', os.getenv('DB_HOST', 'localhost'))
    db_port: int = int(os.getenv('FASTAPI_DB_PORT', os.getenv('DB_PORT', '3306')))
    db_name: str = os.getenv('FASTAPI_DB_NAME', os.getenv('DB_NAME', 'tiktokio.mobi'))
    db_user: str = os.getenv('FASTAPI_DB_USER', os.getenv('DB_USER', 'root'))
    db_password: str = os.getenv('FASTAPI_DB_PASSWORD', os.getenv('DB_PASSWORD', ''))

    storage_root: Path = field(
        default_factory=lambda: Path(
            os.getenv(
                'FASTAPI_STORAGE_DIR',
                Path(__file__).resolve().parent.parent / 'uploads' / 'api-cache'
            )
        )
    )
    download_ttl_seconds: int = int(os.getenv('FASTAPI_DOWNLOAD_TTL_SECONDS', '7200'))
    cleanup_frequency_seconds: int = int(os.getenv('FASTAPI_CLEANUP_FREQUENCY', '1800'))
    cleanup_grace_seconds: int = int(os.getenv('FASTAPI_CLEANUP_GRACE', '10800'))
    default_provider: str = os.getenv('FASTAPI_DEFAULT_PROVIDER', 'ytdlp')
    log_level: str = os.getenv('FASTAPI_LOG_LEVEL', 'info')

    cobalt_timeout_seconds: float = float(os.getenv('FASTAPI_COBALT_TIMEOUT', '30'))
    http_user_agent: str = os.getenv(
        'FASTAPI_HTTP_USER_AGENT',
        'Mozilla/5.0 (compatible; TikTokIO/1.0; +https://tiktokio.lol)'
    )

    def __post_init__(self) -> None:
        self.storage_root = Path(self.storage_root).resolve()
        self.storage_work = self.storage_root / 'work'
        self.storage_ready = self.storage_root / 'ready'
        self.storage_manifests = self.storage_root / 'manifests'
        for directory in (self.storage_root, self.storage_work, self.storage_ready, self.storage_manifests):
            directory.mkdir(parents=True, exist_ok=True)


settings = Settings()

__all__ = ['settings', 'Settings']
