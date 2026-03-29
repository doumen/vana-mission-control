"""
state.py — Gerencia o estado de uma sessão de registro de vídeo.
Uma sessão = um vídeo sendo cadastrado pelo devoto via Telegram.
"""

from __future__ import annotations
from dataclasses import dataclass, field
from typing import Optional


# Estados possíveis da conversa
class S:
    IDLE           = "IDLE"            # aguardando /novo
    AWAIT_TITLE    = "AWAIT_TITLE"     # aguardando título
    AWAIT_CONFIRM  = "AWAIT_CONFIRM"   # aguardando confirmação da inferência
    AWAIT_PERIOD   = "AWAIT_PERIOD"    # inferência incompleta → pede period
    AWAIT_LOCATION = "AWAIT_LOCATION"  # inferência incompleta → pede location
    AWAIT_DATE     = "AWAIT_DATE"      # aguardando data
    AWAIT_LANG     = "AWAIT_LANG"      # aguardando idioma
    DONE           = "DONE"            # sessão finalizada


@dataclass
class Session:
    state:          str           = S.IDLE
    title:          Optional[str] = None
    description:    Optional[str] = None
    date_local:     Optional[str] = None   # "YYYY-MM-DD"
    lang:           Optional[str] = None   # "EN" | "PT"
    period:         Optional[str] = None
    location:       Optional[str] = None
    prk_seq:        Optional[int] = None
    confidence:     Optional[str] = None
    matched_rules:  list[str]     = field(default_factory=list)
    slug:           Optional[str] = None

    def reset(self) -> None:
        """Reinicia para uma nova sessão."""
        self.__init__()

    def to_summary(self) -> str:
        """Texto de resumo para confirmação no Telegram."""
        lines = [
            f"📋 *Resumo do Registro*",
            f"",
            f"📌 *Título:* `{self.title}`",
            f"📅 *Data:* `{self.date_local}`",
            f"🌐 *Idioma:* `{self.lang}`",
            f"🌅 *Period:* `{self.period or '—'}`",
            f"📍 *Location:* `{self.location or '—'}`",
            f"🎯 *Confidence:* `{self.confidence}`",
            f"📁 *Slug:* `{self.slug}`",
        ]
        return "\n".join(lines)
