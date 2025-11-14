@echo off
cd /d %~dp0api
python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001

