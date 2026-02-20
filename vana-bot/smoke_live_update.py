import os
import time
import json
import hmac
import hashlib
import argparse
import requests

def sign_hex(secret: str, timestamp: int, raw_body: bytes) -> str:
    msg = str(timestamp).encode("utf-8") + b"." + raw_body
    return hmac.new(secret.encode("utf-8"), msg, hashlib.sha256).hexdigest()

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--wp-base", default=os.environ.get("WP_BASE", "").rstrip("/"), required=False)
    p.add_argument("--secret", default=os.environ.get("VANA_HMAC_SECRET", ""), required=False)
    p.add_argument("--visit-id", type=int, default=int(os.environ.get("DEFAULT_VISIT_ID", "0")))
    p.add_argument("--date-local", default=os.environ.get("DEFAULT_DATE_LOCAL", "2026-02-18"))
    p.add_argument("--event-id", default=os.environ.get("DEFAULT_EVENT_ID", "hero"))
    p.add_argument("--action", choices=["set_status", "set_stream", "set_alert"], default="set_status")
    p.add_argument("--status", choices=["scheduled", "delayed", "live", "done", "cancelled"], default="live")
    p.add_argument("--youtube-id", default="M7lc1UVf-VE")
    p.add_argument("--alert-type", choices=["info", "warning", "error"], default="warning")
    p.add_argument("--alert-message", default="SMOKE TEST: Mangala Arati em 10 min.")
    args = p.parse_args()

    if not args.wp_base:
        raise SystemExit("Faltou --wp-base (ou WP_BASE no env)")
    if not args.secret:
        raise SystemExit("Faltou --secret (ou VANA_HMAC_SECRET no env)")
    if args.visit_id <= 0:
        raise SystemExit("visit_id inválido (use --visit-id ou DEFAULT_VISIT_ID)")

    url = f"{args.wp_base}/wp-json/vana/v1/schedule-live-update"

    # Monta value conforme action
    if args.action == "set_status":
        value = args.status
    elif args.action == "set_stream":
        value = {
            "provider": "youtube",
            "video_id": args.youtube_id,
            "url": f"https://youtu.be/{args.youtube_id}",
        }
    else:
        value = {
            "type": args.alert_type,
            "message": args.alert_message,
            "active": True,
        }

    payload = {
        "visit_id": args.visit_id,
        "date_local": args.date_local,
        "event_id": args.event_id,
        "request_id": f"smoke_{int(time.time())}",
        "action": args.action,
        "value": value,
        "issued_by": {
            "system": "smoke_test",
            "telegram_user_id": 0,
            "telegram_username": "",
        },
    }

    raw_body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    ts = int(time.time())
    sig = sign_hex(args.secret, ts, raw_body)

    headers = {
        "Content-Type": "application/json",
        "X-Vana-Timestamp": str(ts),
        "X-Vana-Signature": sig,
    }

    print("== Request ==")
    print("POST", url)
    print("X-Vana-Timestamp:", ts)
    print("X-Vana-Signature:", sig)
    print("Body:", raw_body.decode("utf-8"))

    r = requests.post(url, data=raw_body, headers=headers, timeout=15)

    print("\n== Response ==")
    print("HTTP", r.status_code)
    print(r.text)

if __name__ == "__main__":
    main()
