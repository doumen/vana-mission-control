#!/usr/bin/env python3
# Dry-run publish using trator.run_trator and .env values
import json
import os
from pathlib import Path
from dataclasses import asdict

# ensure repo root on sys.path
REPO_ROOT = Path(__file__).resolve().parents[1]
import sys
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from vana_trator import run_trator

ENV_PATH = Path(__file__).parent / '.env'

def load_env(path: Path) -> dict:
    d = {}
    if not path.exists():
        return d
    for line in path.read_text(encoding='utf-8').splitlines():
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        if '=' not in line:
            continue
        k, v = line.split('=', 1)
        v = v.strip().strip('"').strip("'")
        d[k.strip()] = v
    return d

env = load_env(ENV_PATH)
wp_url = env.get('VANA_API_URL') or env.get('WP_API_URL') or os.getenv('VANA_API_URL')
wp_secret = env.get('VANA_SECRET') or env.get('VANA_INGEST_SECRET') or os.getenv('VANA_INGEST_SECRET') or os.getenv('VANA_SECRET')

if not wp_url or not wp_secret:
    print('ERROR: wp_url or wp_secret not found in trator/.env or environment')
    print('Found:', {'wp_url': bool(wp_url), 'wp_secret': bool(wp_secret)})
    raise SystemExit(1)

# choose a sample payload
payload_file = Path(__file__).parent / 'payloads' / '2_visit_vrindavan_dia1.json'
if not payload_file.exists():
    # fallback: pick first JSON in payloads
    files = list((Path(__file__).parent / 'payloads').glob('*.json'))
    if not files:
        print('No payload files found in trator/payloads')
        raise SystemExit(1)
    payload_file = files[0]

print('Using payload:', payload_file)
with payload_file.open('r', encoding='utf-8-sig') as f:
    payload = json.load(f)

print('Calling run_trator(dry_run=True)...')
result = run_trator(payload, wp_url=wp_url, wp_secret=wp_secret, tour_key='tour:india-2026', dry_run=True)

# dataclass -> dict
try:
    out = asdict(result)
except Exception:
    # fallback
    out = {k: getattr(result, k) for k in ('success','errors','warnings','wp_action','wp_id','wp_url','processed') if hasattr(result, k)}

print(json.dumps(out, ensure_ascii=False, indent=2))
