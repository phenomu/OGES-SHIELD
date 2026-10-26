"""AI client — wrapper GLM API via 9router (OpenAI-compatible).

Guardrail: AI_ROUTER_API_KEY hanya hidup di .env orchestrator —
TIDAK PERNAH dikirim ke PHP/frontend (lihat Bagian 2/9 PROMPT.md).
"""
from __future__ import annotations

import json
import time
from typing import Any

import httpx

from config import settings


class AIError(Exception):
    """Panggilan AI gagal setelah retry + fallback."""


class AIClient:
    """Client OpenAI-compatible untuk 9router → GLM-5.3 (fallback GLM-5.2)."""

    def __init__(self) -> None:
        self._base = settings.AI_BASE_URL
        self._key = settings.AI_ROUTER_API_KEY
        self._primary = settings.AI_MODEL_PRIMARY
        self._fallback = settings.AI_MODEL_FALLBACK
        self._timeout = 90.0

    @property
    def configured(self) -> bool:
        return bool(self._key)

    @staticmethod
    def _parse_body(text: str) -> dict:
        """9router kadang kirim body hybrid: JSON + 'data: [DONE]' (text/event-stream
        walau non-streaming request). Strip sufiks SSE lalu parse JSON."""
        t = text.strip()
        if t.endswith("data: [DONE]"):
            t = t[: t.rfind("data: [DONE]")].strip()
        # data pertama kalau router pakai format SSE penuh
        if t.startswith("data:"):
            t = t[len("data:"):].strip()
        return json.loads(t)

    def chat_with_tools(
        self,
        messages: list[dict],
        tools: list[dict] | None = None,
        tool_choice: str | dict = "auto",
        max_tokens: int = 700,
        temperature: float = 0.2,
    ) -> dict:
        """Satu panggilan chat. Return dict choices[0].message + model yang dipakai.

        Retry: primary → primary → fallback (fallback ketiga). Semua gagal → AIError.
        """
        if not self._key:
            raise AIError("AI_ROUTER_API_KEY tidak ter-set di .env orchestrator")

        order: list[str] = [self._primary, self._primary, self._fallback]
        last_err: Exception | None = None
        for attempt, model in enumerate(order):
            payload = {
                "model": model,
                "messages": messages,
                "temperature": temperature,
                "max_tokens": max_tokens,
            }
            if tools:
                payload["tools"] = tools
                payload["tool_choice"] = tool_choice

            try:
                r = httpx.post(
                    f"{self._base}/chat/completions",
                    headers={"Authorization": f"Bearer {self._key}"},
                    json=payload,
                    timeout=self._timeout,
                )
                if r.status_code == 200:
                    data = self._parse_body(r.text)
                    msg = data["choices"][0]["message"]
                    usage = data.get("usage", {})
                    return {
                        "message": msg,
                        "model": model,
                        "usage": {
                            "prompt_tokens": usage.get("prompt_tokens", 0),
                            "completion_tokens": usage.get("completion_tokens", 0),
                        },
                        "attempt": attempt + 1,
                    }
                # 4xx (kecuali 429) = salah konfigurasi → langsung coba fallback
                last_err = AIError(f"HTTP {r.status_code}: {r.text[:300]}")
                if r.status_code not in (429,):
                    continue
                time.sleep(2.0)
            except (httpx.TimeoutException, httpx.TransportError) as e:
                last_err = AIError(f"network: {e}")
                time.sleep(1.5)

        raise AIError(f"semua model gagal: {last_err}")

    def chat_simple(self, system: str, user: str, max_tokens: int = 500) -> str:
        """Panggilan ringan tanpa tools — untuk summary (Phase 5 overall summary)."""
        res = self.chat_with_tools(
            [{"role": "system", "content": system},
             {"role": "user", "content": user}],
            tools=None, max_tokens=max_tokens,
        )
        return str(res["message"].get("content") or "").strip()


# singleton
client = AIClient()
