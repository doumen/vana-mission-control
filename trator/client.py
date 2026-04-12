import time
import uuid
import hmac
import hashlib
import json
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry


class VanaClient:
    def __init__(self, api_url: str, secret: str, timeout: int = 30):
        self.api_url = api_url.rstrip("/")
        self.secret = secret.encode("utf-8")
        self.timeout = timeout

        retry_kwargs = dict(
            total=3,
            backoff_factor=1,
            status_forcelist=[409, 500, 502, 503, 504],
            raise_on_status=False,
        )

        try:
            retry_strategy = Retry(allowed_methods=frozenset(["POST"]), **retry_kwargs)
        except TypeError:
            retry_strategy = Retry(method_whitelist=frozenset(["POST"]), **retry_kwargs)

        adapter = HTTPAdapter(max_retries=retry_strategy)
        self.session = requests.Session()
        self.session.mount("https://", adapter)
        self.session.mount("http://", adapter)

    def _sign(self, payload_bytes: bytes) -> dict:
        timestamp = str(int(time.time()))
        nonce = uuid.uuid4().hex

        prefix = f"{timestamp}\n{nonce}\n".encode("utf-8")
        message = prefix + payload_bytes
        signature = hmac.new(self.secret, message, hashlib.sha256).hexdigest()

        return {
            "vana_timestamp": timestamp,
            "vana_nonce": nonce,
            "vana_signature": signature,
        }

    @staticmethod
    def _dumps_deterministic(obj: dict) -> bytes:
        return json.dumps(
            obj,
            ensure_ascii=False,
            separators=(",", ":"),
        ).encode("utf-8")

    def send_raw(self, payload_bytes: bytes, *, tamper: dict | None = None) -> dict:
        """
        Envia bytes já serializados. Permite tamper em query params para testes.
        """
        auth_params = self._sign(payload_bytes)
        if tamper:
            auth_params.update(tamper)

        headers = {
            "Content-Type": "application/json; charset=utf-8",
            "User-Agent": "VanaTrator/2.0",
        }

        try:
            resp = self.session.post(
                self.api_url,
                data=payload_bytes,
                headers=headers,
                params=auth_params,
                timeout=self.timeout,
                allow_redirects=False,
            )
        except requests.RequestException as e:
            return {
                "ok": False,
                "code": "NETWORK_ERROR",
                "message": str(e),
                "data": {"exception": e.__class__.__name__},
                "http_status": None,
            }

        # redirect bloqueado para não mascarar quebra de HMAC
        if resp.status_code in (301, 302, 307, 308):
            return {
                "ok": False,
                "code": "REDIRECT_BLOCKED",
                "message": f"HTTP {resp.status_code} redirect bloqueado (verifique URL sem trailing slash e HTTPS).",
                "data": {"location": resp.headers.get("Location")},
                "http_status": resp.status_code,
            }

        try:
            out = resp.json()
            if isinstance(out, dict):
                out.setdefault("http_status", resp.status_code)
                return out
        except ValueError:
            pass

        return {
            "ok": False,
            "code": "NON_JSON_RESPONSE",
            "message": f"HTTP {resp.status_code}: {resp.text[:300]}",
            "data": None,
            "http_status": resp.status_code,
        }

    def send_envelope(self, kind: str, origin_key: str, data: dict, **kwargs) -> dict:
        payload = {
            "kind": kind,
            "origin_key": origin_key,
            "data": data,
        }
        payload.update(kwargs)
        payload_bytes = self._dumps_deterministic(payload)
        return self.send_raw(payload_bytes)
