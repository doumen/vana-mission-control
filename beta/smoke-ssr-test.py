#!/usr/bin/env python3
"""
Smoke test: VanaEventController.js deploy + SSR do event_key.
"""
import urllib.request
import urllib.error

BASE_BETA = "https://beta.vanamadhuryamdaily.com"

TESTS = [
    {
        "id": "#1 — JS acessível (200)",
        "url": f"{BASE_BETA}/wp-content/plugins/vana-mission-control/assets/js/VanaEventController.js",
        "check": lambda status, body: (
            "PASS" if status == 200 else f"FAIL (HTTP {status})",
            f"HTTP {status}"
        ),
    },
    {
        "id": "#2 — Enqueue no HTML da página",
        "url": f"{BASE_BETA}/visit/dia-1-vrindavan/?lang=pt",
        "check": lambda status, body: (
            "PASS" if "VanaEventController" in body else "FAIL (tag não encontrada)",
            next((t for t in body.split('"') if "VanaEventController" in t), "(não encontrado)")
        ),
    },
    {
        "id": "#3 — SSR evento b (vídeo 9bZkp7q19f0)",
        "url": f"{BASE_BETA}/visit/dia-1-vrindavan/?event_key=vrindavan-353-b&lang=pt",
        "check": lambda status, body: (
            "PASS" if "9bZkp7q19f0" in body else "FAIL (vídeo não encontrado)",
            "9bZkp7q19f0 encontrado" if "9bZkp7q19f0" in body else "(ausente)"
        ),
    },
]


def fetch(url: str, timeout: int = 30):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (smoke-test)"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, r.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        return e.code, ""
    except Exception as e:
        return 0, str(e)


def main():
    print("=" * 60)
    print("SMOKE TEST — VanaEventController SSR")
    print("=" * 60)
    all_pass = True
    for t in TESTS:
        status, body = fetch(t["url"])
        result, detail = t["check"](status, body)
        icon = "✅" if result == "PASS" else "❌"
        print(f"\n{icon} {t['id']}")
        print(f"   URL   : {t['url']}")
        print(f"   Status: HTTP {status}")
        print(f"   Result: {result}")
        print(f"   Detail: {detail}")
        if result != "PASS":
            all_pass = False
    print("\n" + "=" * 60)
    print("RESULTADO FINAL:", "✅ TODOS PASSARAM" if all_pass else "❌ FALHOU")
    print("=" * 60)


if __name__ == "__main__":
    main()
