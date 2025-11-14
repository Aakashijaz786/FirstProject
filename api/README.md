# TikTokIO FastAPI Service

This microservice handles all heavy-lift download/search logic (yt-dlp, proxy
rotation, metadata stamping) so the legacy PHP frontend can remain lightweight.

## Getting started

```bash
cd api
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# update .env with the same DB credentials + FASTAPI_AUTH_KEY stored in `site_settings`
uvicorn main:app --reload --port 8000
```

The PHP layer talks to this service using the `X-Internal-Key` header. Make sure
the `FASTAPI_AUTH_KEY` value matches `site_settings.fastapi_auth_key`.

## Endpoints

- `GET /health` – sanity check + configuration snapshot.
- `POST /search` – normalized search/metadata fetch (supports URL or keywords).
- `POST /download` – triggers a download job and returns a signed token.
- `GET /download/{token}` – serves the prepared media file when given the
  provided `sig` query parameter.

All mutating endpoints require the internal key header. Public users never see
the FastAPI URLs directly; PHP proxies them and issues JWT-protected routes.
