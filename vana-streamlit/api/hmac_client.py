# api/hmac_client.py
# -*- coding: utf-8 -*-
"""
Cliente HMAC para os endpoints /vana/v1/
Espelha exatamente a lógica do class-vana-hmac.php
"""
import time
import hmac
import hashlib
import secrets
import json
import requests
import streamlit as st


def _secret() -> str:
    return st.secrets["vana"]["ingest_secret"]


def _base() -> str:
    return st.secrets["vana"]["api_base"].rstrip("/")


# ── Assinatura ─────────────────────────────────────────────────────────────
def _sign_body(body_str: str) -> dict:
    """POST — assina timestamp + nonce + body."""
    timestamp = str(int(time.time()))
    nonce     = secrets.token_hex(16)
    message   = f"{timestamp}\n{nonce}\n{body_str}"
    signature = hmac.new(                          # ← BUG no original
        _secret().encode("utf-8"),
        message.encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()
    return {"vana_timestamp": timestamp,
            "vana_nonce":     nonce,
            "vana_signature": signature}


def _sign_empty() -> dict:
    """GET / DELETE — body vazio."""
    timestamp = str(int(time.time()))
    nonce     = secrets.token_hex(16)
    message   = f"{timestamp}\n{nonce}\n"
    signature = hmac.new(
        _secret().encode("utf-8"),
        message.encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()
    return {"vana_timestamp": timestamp,
            "vana_nonce":     nonce,
            "vana_signature": signature}


# ── POST genérico ──────────────────────────────────────────────────────────
def _post(endpoint: str, payload: dict) -> dict:
    body_str = json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
    params   = _sign_body(body_str)
    url      = f"{_base()}/vana/v1/{endpoint.lstrip('/')}"

    resp = requests.post(
        url,
        params=params,
        data=body_str.encode("utf-8"),
        headers={"Content-Type": "application/json"},
        timeout=30,
    )
    resp.raise_for_status()
    return resp.json()


# ── GET autenticado ────────────────────────────────────────────────────────
def _get(endpoint: str, extra_params: dict = None) -> dict:
    params = {**_sign_empty(), **(extra_params or {})}
    url    = f"{_base()}/vana/v1/{endpoint.lstrip('/')}"
    resp   = requests.get(url, params=params, timeout=15)
    resp.raise_for_status()
    return resp.json()


# ── DELETE autenticado ─────────────────────────────────────────────────────
def _delete(endpoint: str, force: bool = False) -> dict:
    params = {**_sign_empty(), "force": int(force)}
    url    = f"{_base()}/vana/v1/{endpoint.lstrip('/')}"
    resp   = requests.delete(url, params=params, timeout=15)
    resp.raise_for_status()
    return resp.json()


# ══════════════════════════════════════════════════════════════════════════
# API PÚBLICA
# ══════════════════════════════════════════════════════════════════════════

# ── Ingest genérico (tours, submissions, etc.) ─────────────────────────────
def ingest(kind: str, origin_key: str, data: dict) -> dict:
    """POST /vana/v1/ingest"""
    return _post("ingest", {
        "kind":       kind,
        "origin_key": origin_key,
        "data":       data,
    })


# ── Ingest Visit (endpoint dedicado) ──────────────────────────────────────
def ingest_visit(envelope: dict) -> dict:
    """
    POST /vana/v1/ingest-visit
    envelope = build_envelope() já montado (kind, origin_key, parent_origin_key, data)
    """
    return _post("ingest-visit", envelope)


# ── Visits ─────────────────────────────────────────────────────────────────
def list_visits(tour_key: str = "", page: int = 1, per_page: int = 20) -> dict:
    return _get("visits", {"tour": tour_key, "page": page, "per_page": per_page})


def get_visit(visit_id: int) -> dict:
    return _get(f"visits/{visit_id}")


def delete_visit(visit_id: int, force: bool = False) -> dict:
    return _delete(f"visits/{visit_id}", force=force)


# ── Tours ──────────────────────────────────────────────────────────────────
def list_tours(page: int = 1, per_page: int = 20) -> dict:
    return _get("tours", {"page": page, "per_page": per_page})


def get_tour(tour_id: int) -> dict:
    return _get(f"tours/{tour_id}")


def delete_tour(tour_id: int, force: bool = False) -> dict:
    return _delete(f"tours/{tour_id}", force=force)
