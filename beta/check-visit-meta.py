#!/usr/bin/env python3
import paramiko
import json

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=10)
    
    # Run WP CLI to get post meta for a visit
    stdin, stdout, stderr = client.exec_command(
        '''
        cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
        wp post list --post_type=vana_visit --posts_per_page=1 --field=ID 2>/dev/null | head -1 | xargs -I {} \
        wp post meta get {} _vana_visit_timeline_json 2>/dev/null | head -100 || echo "Meta not found or error"
        '''
    )
    
    output = stdout.read().decode().strip()
    
    print("_vana_visit_timeline_json content (first 100 lines):")
    print(output[:2000])
    
    if len(output) > 2000:
        print(f"\n[...truncated {len(output)-2000} chars...]")
    
except Exception as e:
    print(f"Error: {e}")
finally:
    client.close()
