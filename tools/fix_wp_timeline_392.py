#!/usr/bin/env python3
import re
import json

print('Loading WP client...')
try:
    from api.wp_client import get_visit_timeline, patch_visit_timeline
except Exception as e:
    print('Failed to import WP client:', e)
    raise

wp_id = 392
print(f'Fetching timeline for wp_id={wp_id}...')
try:
    tl = get_visit_timeline(wp_id)
except Exception as e:
    print('Error fetching timeline:', e)
    raise

print('Before schema_version:', tl.get('schema_version'))
changed = []
pattern = re.compile(r'^vod-(\d{4})-(\d{2})-(\d{2})-(\d+)$')
for di, day in enumerate(tl.get('days', [])):
    for ei, event in enumerate(day.get('events', [])):
        for vi, vod in enumerate(event.get('vods', [])):
            old = vod.get('vod_key', '')
            new = pattern.sub(r'vod-\1\2\3-\4', old)
            if new != old:
                changed.append((old, new))
                vod['vod_key'] = new

# Force schema_version 6.1
if tl.get('schema_version') != '6.1':
    print('Updating schema_version to 6.1')
    tl['schema_version'] = '6.1'

print('Patching timeline back to WP...')
try:
    patch_visit_timeline(wp_id, tl)
    print('Patched successfully.')
except Exception as e:
    print('Error patching timeline:', e)
    raise

print('Changed VOD keys:')
for o,n in changed:
    print(f'  {o} -> {n}')

print('Done.')
