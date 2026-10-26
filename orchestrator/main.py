"""OGES-SHIELD orchestrator — entrypoint FastAPI.

Service internal (localhost only): dieksekusi oleh PHP backend,
JANGAN pernah diekspos langsung ke browser.
"""
from fastapi import FastAPI

from config import settings

app = FastAPI(title="OGES-SHIELD Orchestrator", version="0.1.0")


@app.get("/health")
def health() -> dict:
    """Liveness probe — Phase 0 DoD: return 200."""
    return {
        "status": "ok",
        "service": "oges-shield-orchestrator",
        "version": "0.1.0",
        "db_configured": bool(settings.DB_NAME),
        "ai_configured": bool(settings.AI_ROUTER_API_KEY),
    }
