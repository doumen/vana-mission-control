# vana_hmac_signer.py
# -*- coding: utf-8 -*-
"""
Gerador de assinatura HMAC para o endpoint /vana/v1/ingest
Espelha exatamente a lógica do class-vana-ingest-api.php
"""

import os
import time
import hmac
import hashlib
import secrets
import json
import requests

class VanaIngestClient:
    def __init__(self):
        self.wp_url  = os.getenv("WP_URL", "").rstrip('/')
        self.secret  = os.getenv("VANA_INGEST_SECRET", "")

        if not self.wp_url or not self.secret:
            raise EnvironmentError("❌ WP_URL ou VANA_INGEST_SECRET não definidos!")

    def _sign(self, body_str: str) -> dict:
        """
        Gera os parâmetros de assinatura HMAC.
        Espelha a lógica PHP:
          $message = $timestamp . "\n" . $nonce . "\n" . $body;
          $expected = hash_hmac('sha256', $message, $secret);
        """
        timestamp = str(int(time.time()))
        nonce     = secrets.token_hex(16)
        message   = f"{timestamp}\n{nonce}\n{body_str}"
        signature = hmac.new(
            self.secret.encode('utf-8'),
            message.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()

        return {
            "vana_timestamp": timestamp,
            "vana_nonce":     nonce,
            "vana_signature": signature,
        }

    def ingest(self, kind: str, origin_key: str, data: dict) -> dict:
        """
        Envia payload para /vana/v1/ingest com assinatura HMAC.
        """
        payload = {
            "kind":       kind,
            "origin_key": origin_key,
            "data":       data,
        }

        # Serializa exatamente como o PHP vai receber
        body_str = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))

        # Gera assinatura sobre o body serializado
        params = self._sign(body_str)

        url = f"{self.wp_url}/wp-json/vana/v1/ingest"

        print(f"📡 Enviando para: {url}")
        print(f"   kind={kind} | origin_key={origin_key}")

        resp = requests.post(
            url,
            params=params,           # ← assinatura vai na URL (query string)
            data=body_str.encode(),  # ← body raw (não re-serializa!)
            headers={"Content-Type": "application/json"},
            timeout=15
        )

        print(f"   STATUS: {resp.status_code}")
        print(f"   BODY:   {resp.text[:300]}")
        return resp.json() if resp.ok else {}
