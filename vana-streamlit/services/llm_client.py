# services/llm_client.py
# -*- coding: utf-8 -*-
"""
Abstração para chamadas LLM (Anthropic / OpenAI).
Centraliza autenticação, retry e parsing básico.
"""

import json
import time
from typing import Optional

import requests
import streamlit as st


class LLMClient:
    """Cliente LLM genérico com suporte a múltiplos provedores."""

    PROVIDERS = {
        "anthropic": {
            "url": "https://api.anthropic.com/v1/messages",
            "header_key": "x-api-key",
            "model_default": "claude-sonnet-4-20250514",
        },
        "openai": {
            "url": "https://api.openai.com/v1/chat/completions",
            "header_key": "Authorization",
            "model_default": "gpt-4o",
        },
    }

    def __init__(
        self,
        provider: str = "anthropic",
        api_key: str = "",
        model: str = "",
        max_retries: int = 2,
        timeout: int = 120,
    ):
        self.provider = provider
        self.api_key = api_key
        self.model = model or self.PROVIDERS[provider]["model_default"]
        self.max_retries = max_retries
        self.timeout = timeout

    def ask(
        self,
        system_prompt: str,
        user_message: str,
        temperature: float = 0.3,
        max_tokens: int = 4096,
    ) -> dict:
        if self.provider == "anthropic":
            return self._ask_anthropic(system_prompt, user_message, temperature, max_tokens)
        elif self.provider == "openai":
            return self._ask_openai(system_prompt, user_message, temperature, max_tokens)
        else:
            raise ValueError(f"Provider desconhecido: {self.provider}")

    def _ask_anthropic(self, system: str, user: str, temperature: float, max_tokens: int) -> dict:
        cfg = self.PROVIDERS["anthropic"]
        headers = {
            cfg["header_key"]: self.api_key,
            "Content-Type": "application/json",
            "anthropic-version": "2023-06-01",
        }
        payload = {
            "model": self.model,
            "max_tokens": max_tokens,
            "temperature": temperature,
            "system": system,
            "messages": [{"role": "user", "content": user}],
        }

        return self._do_request(cfg["url"], headers, payload)

    def _ask_openai(self, system: str, user: str, temperature: float, max_tokens: int) -> dict:
        cfg = self.PROVIDERS["openai"]
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }
        payload = {
            "model": self.model,
            "max_tokens": max_tokens,
            "temperature": temperature,
            "messages": [
                {"role": "system", "content": system},
                {"role": "user", "content": user},
            ],
        }

        return self._do_request(cfg["url"], headers, payload)

    def _do_request(self, url: str, headers: dict, payload: dict) -> dict:
        last_error = None

        for attempt in range(self.max_retries + 1):
            try:
                t0 = time.time()
                resp = requests.post(url, headers=headers, json=payload, timeout=self.timeout)
                latency = time.time() - t0

                if resp.status_code == 429:
                    wait = min(2 ** attempt * 5, 30)
                    time.sleep(wait)
                    continue

                resp.raise_for_status()
                data = resp.json()

                if self.provider == "anthropic":
                    text = data.get("content", [{}])[0].get("text", "")
                    usage = data.get("usage", {})
                else:
                    text = data.get("choices", [{}])[0].get("message", {}).get("content", "")
                    usage = data.get("usage", {})

                parsed_json = None
                try:
                    if "```json" in text:
                        json_str = text.split("```json")[1].split("```")[0].strip()
                        parsed_json = json.loads(json_str)
                    elif text.strip().startswith("{"):
                        parsed_json = json.loads(text.strip())
                except (json.JSONDecodeError, IndexError):
                    parsed_json = None

                return {
                    "text": text,
                    "json": parsed_json,
                    "usage": usage,
                    "model": self.model,
                    "latency_s": round(latency, 2),
                }

            except requests.exceptions.RequestException as e:
                last_error = e
                if attempt < self.max_retries:
                    time.sleep(2 ** attempt)

        raise ConnectionError(f"LLM request failed after {self.max_retries + 1} attempts: {last_error}")


def get_llm_client() -> Optional[LLMClient]:
    try:
        cfg = st.secrets.get("llm", {})
        if not cfg.get("api_key"):
            return None
        return LLMClient(
            provider=cfg.get("provider", "anthropic"),
            api_key=cfg["api_key"],
            model=cfg.get("model", ""),
        )
    except Exception:
        return None
