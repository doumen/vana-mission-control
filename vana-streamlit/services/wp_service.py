# services/wp_service.py
# -*- coding: utf-8 -*-
import hmac as _hmac
import hashlib
import time
import json
import requests


class WPService:

    def __init__(self, base: str, secret: str):
        self.base   = base.rstrip("/")
        self.secret = secret

    def notify_publicada(self, visit_ref: str, editorial: dict) -> bool:
        payload = {
            "visit_ref":    visit_ref,
            "state":        "publicada",
            "title_pt":     editorial["meta"].get("title_pt"),
            "title_en":     editorial["meta"].get("title_en"),
            "preview_pt":   editorial["meta"].get("preview_pt"),
            "preview_en":   editorial["meta"].get("preview_en"),
            "cover_url":    editorial["exports"].get("cover_url"),
            "pdf_pt_url":   editorial["exports"].get("pdf_pt_url"),
            "pdf_en_url":   editorial["exports"].get("pdf_en_url"),
            "published_at": editorial["meta"].get("published_at"),
            "stats":        editorial.get("stats", {}),
        }
        r = requests.post(
            self.base + "/vana/v1/ingest-revista",
            json    = payload,
            headers = self._headers(payload),
            timeout = 15,
        )
        return r.status_code == 200

    def notify_state_change(self, visit_ref: str, state: str) -> bool:
        payload = {"visit_ref": visit_ref, "state": state}
        r = requests.post(
            self.base + "/vana/v1/ingest-revista",
            json    = payload,
            headers = self._headers(payload),
            timeout = 10,
        )
        return r.status_code == 200

    def _headers(self, payload: dict) -> dict:
        ts   = str(int(time.time()))
        body = json.dumps(payload, separators=(",", ":"))
        sig  = _hmac.new(
            self.secret.encode(),
            (ts + "." + body).encode(),
            hashlib.sha256,
        ).hexdigest()
        return {
            "X-Vana-Timestamp": ts,
            "X-Vana-Signature": sig,
            "Content-Type":     "application/json",
        }
