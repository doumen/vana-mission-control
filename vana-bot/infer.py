"""
infer.py — Vana Madhuryam Inference Engine v1.1
Infere period e location a partir do título/descrição de um vídeo.
"""

from __future__ import annotations

import re
import unicodedata
import yaml
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ──────────────────────────────────────────────
# Tipos
# ──────────────────────────────────────────────

@dataclass
class InferenceResult:
    period:        Optional[str] = None
    location:      Optional[str] = None
    prk_seq:       Optional[int] = None
    source:        str           = "inferred"
    confidence:    str           = "none"
    matched_rules: list[str]     = field(default_factory=list)

    def filename_slug(self, date_local: str, lang: str = "EN") -> str:
        parts = [date_local]
        if self.period:
            parts.append(self.period)
        if self.location:
            parts.append(self.location)
        parts.append(lang.upper())
        return "_".join(parts)


# ──────────────────────────────────────────────
# Carregamento de regras
# ──────────────────────────────────────────────

_RULES_CACHE: Optional[list[dict]] = None

def load_rules(path: str | Path | None = None) -> list[dict]:
    global _RULES_CACHE

    if path is not None or _RULES_CACHE is None:
        resolved = Path(path) if path else (
            Path(__file__).parent.parent / "config" / "inference_rules.yaml"
        )
        with resolved.open(encoding="utf-8") as f:
            data = yaml.safe_load(f)
        _RULES_CACHE = sorted(data["rules"], key=lambda r: r["weight"])

    return _RULES_CACHE


# ──────────────────────────────────────────────
# Normalização de texto
# ──────────────────────────────────────────────

def _normalize(text: str) -> str:
    """
    Lowercase + remove acentos + normaliza separadores + colapsa espaços.
    'Manhã' → 'manha' | 'ção' → 'cao' | '—' → ' '
    """
    text = text.lower()
    text = unicodedata.normalize("NFD", text)
    text = "".join(c for c in text if unicodedata.category(c) != "Mn")
    text = re.sub(r"[–—\|/\\]", " ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def _match_keywords(text: str, keywords: list[str]) -> bool:
    for kw in sorted(keywords, key=len, reverse=True):
        if kw in text:
            return True
    return False


# ──────────────────────────────────────────────
# Engine principal
# ──────────────────────────────────────────────

def infer(
    title: str,
    description: str        = "",
    lang: str               = "en",
    existing_prk_count: int = 0,
    override_period: str    = None,
    override_location: str  = None,
    rules_path: str | Path  = None,
) -> InferenceResult:

    rules  = load_rules(rules_path)
    result = InferenceResult()

    # ── Overrides manuais ────────────────────────────────────────
    if override_period:
        result.period = override_period.upper()
        result.source = "manual"
    if override_location:
        result.location = override_location.upper()
        result.source   = "manual"

    if result.period and result.location:
        result.confidence = "high"
        return result

    # ── Normalização ─────────────────────────────────────────────
    lang_key = f"keywords_{'pt' if lang.lower() in ('pt', 'pt-br') else 'en'}"
    text = _normalize(f"{title} {description}")

    # ── Varredura de regras ───────────────────────────────────────
    for rule in rules:
        keywords = rule.get(lang_key) or rule.get("keywords_en", [])
        # Normaliza as keywords também (remove acentos do YAML se houver)
        keywords = [_normalize(kw) for kw in keywords]

        if not _match_keywords(text, keywords):
            continue

        rule_id  = rule["id"]
        category = rule["category"]
        result.matched_rules.append(rule_id)

        if category == "dual":
            if not result.period:
                result.period = rule["maps_to_period"]
            if not result.location:
                result.location = rule["maps_to_location"]

        elif category == "period" and not result.period:
            result.period = rule_id

        elif category == "location" and not result.location:
            if rule.get("indexed"):
                seq = existing_prk_count + 1
                result.location = f"{rule_id}-{seq:02d}"
                result.prk_seq  = seq
            else:
                result.location = rule_id

        if result.period and result.location:
            break

    # ── Confidence ───────────────────────────────────────────────
    has_dual = any(
        r["category"] == "dual"
        for r in rules
        if r["id"] in result.matched_rules
    )
    both_filled = result.period is not None and result.location is not None
    n = len(result.matched_rules)

    if n == 0:
        result.confidence = "none"
    elif has_dual and both_filled:
        result.confidence = "high"
    elif n == 1:
        result.confidence = "low"
    elif n == 2:
        result.confidence = "medium"
    else:
        result.confidence = "high"

    return result
