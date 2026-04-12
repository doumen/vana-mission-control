# services/conflict_guard.py
# -*- coding: utf-8 -*-
"""
Proteção contra conflito de escrita no visit.json.
Usa revision_id (hash do conteúdo) como optimistic lock.
"""

import hashlib
import json
from datetime import datetime, timezone
from typing import Optional


def compute_revision(visit: dict) -> str:
    """
    Gera hash determinístico do conteúdo da visita.
    Ignora campos de controle (_revision, _last_sync, etc.)
    """
    clean = {k: v for k, v in visit.items() if not str(k).startswith("_")}
    canonical = json.dumps(clean, sort_keys=True, ensure_ascii=False)
    return hashlib.sha256(canonical.encode()).hexdigest()[:12]


def stamp_revision(visit: dict, source: str, editor: str) -> dict:
    """
    Adiciona metadados de revisão ao visit.json.
    """
    visit.setdefault("_revision_log", [])
    visit["_revision"] = {
        "id": compute_revision(visit),
        "at": datetime.now(timezone.utc).isoformat(),
        "by": editor,
        "source": source,
    }
    return visit


def check_conflict(local_visit: dict, remote_visit: dict) -> dict:
    """
    Compara revisão local vs remota.
    """
    local_rev = local_visit.get("_revision", {}) or {}
    remote_rev = remote_visit.get("_revision", {}) or {}

    local_id = local_rev.get("id", "")
    remote_id = remote_rev.get("id", "")

    if not remote_id:
        return {"conflict": False, "local_rev": local_id, "remote_rev": ""}

    if local_id == remote_id:
        return {"conflict": False, "local_rev": local_id, "remote_rev": remote_id}

    return {
        "conflict": True,
        "local_rev": local_id,
        "remote_rev": remote_id,
        "remote_by": remote_rev.get("by", "?"),
        "remote_source": remote_rev.get("source", "?"),
        "remote_at": remote_rev.get("at", "?"),
        "detail": (
            f"Visita modificada por {remote_rev.get('by', '?')} "
            f"via {remote_rev.get('source', '?')} "
            f"em {remote_rev.get('at', '?')[:19]}"
        ),
    }


def diff_visits(local: dict, remote: dict) -> list[dict]:
    """
    Gera um diff simplificado entre duas versões.
    Foca nos campos editoriais.
    """
    diffs = []

    def _compare(path: str, a, b):
        if isinstance(a, dict) and isinstance(b, dict):
            all_keys = set(list(a.keys()) + list(b.keys()))
            for k in sorted(all_keys):
                if str(k).startswith("_"):
                    continue
                _compare(f"{path}.{k}" if path else k, a.get(k), b.get(k))
        elif isinstance(a, list) and isinstance(b, list):
            if len(a) != len(b):
                diffs.append({"path": path, "type": "list_length", "local": len(a), "remote": len(b)})
            for i in range(min(len(a), len(b))):
                _compare(f"{path}[{i}]", a[i], b[i])
        elif a != b:
            diffs.append({"path": path, "type": "value", "local": a, "remote": b})

    _compare("", local, remote)
    return diffs
