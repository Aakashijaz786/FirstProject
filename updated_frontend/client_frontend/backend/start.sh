#!/bin/bash
echo "Starting YT1s Translation API Backend..."
echo ""
echo "Installing dependencies..."
pip install -r requirements.txt
echo ""
echo "Starting server on http://localhost:8000"
echo "Press Ctrl+C to stop"
echo ""
uvicorn main:app --reload

