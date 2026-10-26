"""Orchestrator config — baca .env sekali, expose via `settings` (single object)."""
import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / ".env")


@dataclass(frozen=True)
class Settings:
    # --- DB ---
    DB_HOST: str
    DB_PORT: int
    DB_NAME: str
    DB_USER: str
    DB_PASS: str

    # --- AI router (9router, OpenAI-compatible) ---
    AI_BASE_URL: str
    AI_ROUTER_API_KEY: str
    AI_MODEL_PRIMARY: str
    AI_MODEL_FALLBACK: str

    # --- paths ---
    BIN_DIR: Path
    TOOLS_JSON: Path
    WORKFLOW_TEMPLATES_JSON: Path
    REPORTS_DIR: Path
    EVIDENCE_DIR: Path

    # --- safety limits (wajib — cegah infinite loop) ---
    MAX_TOOL_CALLS_PER_PHASE: int
    MAX_TIME_PER_PHASE_SECONDS: int


settings = Settings(
    DB_HOST=os.getenv("DB_HOST", "127.0.0.1"),
    DB_PORT=int(os.getenv("DB_PORT", "3306")),
    DB_NAME=os.getenv("DB_NAME", "oges_shield"),
    DB_USER=os.getenv("DB_USER", "oges"),
    DB_PASS=os.getenv("DB_PASS", ""),
    AI_BASE_URL=os.getenv("AI_BASE_URL", "https://api.ate.my.id/v1").rstrip("/"),
    AI_ROUTER_API_KEY=os.getenv("AI_ROUTER_API_KEY", ""),
    AI_MODEL_PRIMARY=os.getenv("AI_MODEL_PRIMARY", "glm-5.3"),
    AI_MODEL_FALLBACK=os.getenv("AI_MODEL_FALLBACK", "glm-5.2"),
    BIN_DIR=Path(os.getenv("BIN_DIR") or (BASE_DIR.parent / "bin")),
    TOOLS_JSON=BASE_DIR / "config" / "tools.json",
    WORKFLOW_TEMPLATES_JSON=BASE_DIR / "config" / "workflow_templates.json",
    REPORTS_DIR=BASE_DIR.parent / "storage" / "reports",
    EVIDENCE_DIR=BASE_DIR.parent / "storage" / "evidence",
    MAX_TOOL_CALLS_PER_PHASE=int(os.getenv("MAX_TOOL_CALLS_PER_PHASE", "10")),
    MAX_TIME_PER_PHASE_SECONDS=int(os.getenv("MAX_TIME_PER_PHASE_SECONDS", "900")),
)
