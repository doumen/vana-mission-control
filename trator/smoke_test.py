import os
import time
import threading
from client import VanaClient
from dotenv import load_dotenv
load_dotenv()

VANA_API_URL = os.getenv("VANA_API_URL")
VANA_SECRET = os.getenv("VANA_SECRET")

def shape(resp: dict) -> str:
    if not isinstance(resp, dict):
        return "non_dict"
    if "success" in resp and "meta" in resp:
        return "legacy"
    if "code" in resp and isinstance(resp.get("data"), dict) and "status" in resp["data"]:
        return "wp_error"
    if "ok" in resp and "code" in resp:
        return "client_error"
    return "unknown"


def assert_fail(resp: dict, *, http_status: int | None = None, message_contains: str | None = None):
    s = shape(resp)
    assert s in ("legacy", "wp_error", "client_error"), f"Resposta inesperada: shape={s} resp={resp}"

    if s == "legacy":
        assert resp["success"] is False, f"Esperava success=False. resp={resp}"
        if http_status is not None:
            assert resp.get("http_status") == http_status, f"Esperava http_status={http_status}. resp={resp}"
        if message_contains:
            assert message_contains.lower() in str(resp.get("message", "")).lower(), f"message não contém {message_contains}. resp={resp}"
        return

    if s == "wp_error":
        # WP_Error sempre é falha
        if http_status is not None:
            assert int(resp["data"].get("status")) == http_status, f"Esperava status={http_status}. resp={resp}"
        if message_contains:
            assert message_contains.lower() in str(resp.get("message", "")).lower(), f"message não contém {message_contains}. resp={resp}"
        return

    if s == "client_error":
        # erros de rede/redirect/non-json do client
        assert resp["ok"] is False, f"Esperava ok=False. resp={resp}"
        if http_status is not None and resp.get("http_status") is not None:
            assert resp["http_status"] == http_status, f"Esperava http_status={http_status}. resp={resp}"
        if message_contains:
            assert message_contains.lower() in str(resp.get("message", "")).lower(), f"message não contém {message_contains}. resp={resp}"
        return


def assert_success(resp: dict, *, http_status: int | None = None):
    s = shape(resp)
    assert s == "legacy", f"Esperava contrato legado (success/message/data/meta). shape={s} resp={resp}"
    assert resp["success"] is True, f"Esperava success=True. resp={resp}"
    if http_status is not None:
        assert resp.get("http_status") == http_status, f"Esperava http_status={http_status}. resp={resp}"


def main():
    api_url = os.environ["VANA_API_URL"]  # ex: https://site.com/wp-json/vana/v1/ingest-visit
    secret = os.environ["VANA_SECRET"]
    client = VanaClient(api_url=api_url, secret=secret)

    ts = int(time.time())

    base_ok_visit_data = {
        "schema_version": "3.1",
        "updated_at": "2026-02-14T10:00:00Z",
        "days": [{"date_local": "2026-02-14"}],
    }

    def envelope(origin_key: str, data: dict, **kwargs) -> bytes:
        payload = {
            "kind": "visit",
            "origin_key": origin_key,
            "parent_origin_key": "tour:smoke",  # ajuste p/ uma tour real se quiser validar o roteamento
            "title": "Smoke Visit",
            "slug_suggestion": "smoke-visit",
            "data": data,
        }
        payload.update(kwargs)
        return client._dumps_deterministic(payload)

    print("1) SUCCESS (visit create/update)")
    r = client.send_raw(envelope(f"visit:smoke:{ts}", base_ok_visit_data))
    assert_success(r, http_status=201)  # pode ser 201 no create
    # roda de novo p/ virar update (200)
    r2 = client.send_raw(envelope(f"visit:smoke:{ts}", base_ok_visit_data))
    assert_success(r2)  # 200 ou 201 dependendo da sua lógica

    print("2) AUTH fail (tamper signature -> 401)")
    r = client.send_raw(envelope(f"visit:smoke:bad_sig:{ts}", base_ok_visit_data), tamper={"vana_signature": "0" * 64})
    assert_fail(r, http_status=401)

    print("3) AUTH fail (old timestamp -> 401)")
    r = client.send_raw(envelope(f"visit:smoke:old_ts:{ts}", base_ok_visit_data), tamper={"vana_timestamp": str(int(time.time()) - 99999)})
    assert_fail(r, http_status=401)

    print("4) INVALID_JSON (body truncado -> 400)")
    r = client.send_raw(b'{"kind":"visit","origin_key":"x","parent_origin_key":"tour:smoke","data":')
    assert_fail(r, http_status=400, message_contains="json")

    print("5) INVALID_ENVELOPE (missing parent_origin_key -> 422)")
    bad = client._dumps_deterministic({"kind": "visit", "origin_key": "visit:missing_parent", "data": base_ok_visit_data})
    r = client.send_raw(bad)
    assert_fail(r, http_status=422)

    print("6) INVALID_ENVELOPE (wrong kind -> 422)")
    bad2 = client._dumps_deterministic({"kind": "lesson", "origin_key": "x", "parent_origin_key": "tour:smoke", "data": {}})
    r = client.send_raw(bad2)
    assert_fail(r, http_status=422)

    print("7) INVALID_SCHEMA (wrong schema_version -> 422)")
    bad_schema = {
        "schema_version": "3.0",
        "updated_at": "2026-02-14T10:00:00Z",
        "days": [{"date_local": "2026-02-14"}],
    }
    r = client.send_raw(envelope(f"visit:smoke:bad_schema:{ts}", bad_schema))
    assert_fail(r, http_status=422)

    print("8) PAYLOAD_TOO_LARGE (>3MB -> 413)")
    big = {
        "schema_version": "3.1",
        "updated_at": "2026-02-14T10:00:00Z",
        "days": [{"date_local": "2026-02-14", "notes": "A" * (3 * 1024 * 1024)}],
    }
    r = client.send_raw(envelope(f"visit:smoke:big:{ts}", big))
    assert_fail(r, http_status=413)

    print("9) LOCKED (best-effort, 2 threads -> 409 provável)")
    locked_origin = f"visit:smoke:locked:{ts}"
    payload_bytes = envelope(locked_origin, base_ok_visit_data)

    results = []

    def worker():
        results.append(client.send_raw(payload_bytes))

    t1 = threading.Thread(target=worker)
    t2 = threading.Thread(target=worker)
    t1.start(); t2.start()
    t1.join(); t2.join()

    # Esperado: 1 success, e possivelmente 1 locked 409 (depende do timing)
    statuses = []
    for rr in results:
        if isinstance(rr, dict):
            if "http_status" in rr:
                statuses.append(rr.get("http_status"))
            elif "data" in rr and isinstance(rr["data"], dict) and "status" in rr["data"]:
                statuses.append(int(rr["data"]["status"]))
            else:
                statuses.append(None)

    if 409 in statuses:
        # valida que o 409 é falha
        for rr in results:
            if rr.get("http_status") == 409:
                assert_fail(rr, http_status=409)
    else:
        print("   (nota) LOCKED não disparou; normal por timing. Rode novamente para tentar forçar.")

    print("OK: smoke_test passou (8 + locked best-effort).")


if __name__ == "__main__":
    main()
