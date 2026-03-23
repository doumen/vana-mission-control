#!/usr/bin/env python3
import paramiko
import json

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Query para pegar o conteúdo completo da meta
sql_cmd = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e "
SELECT meta_value FROM wp_postmeta WHERE post_id = 359 AND meta_key = '_vana_visit_timeline_json';
" 2>/dev/null"""

_, stdout, stderr = ssh.exec_command(sql_cmd, timeout=120)
out = stdout.read().decode('utf-8', 'ignore')
err = stderr.read().decode('utf-8', 'ignore')

lines = out.strip().split('\n')
if len(lines) > 1:
    json_str = lines[1]  # Skip header
    try:
        data = json.loads(json_str)
        print("=== POST 359 TIMELINE JSON (PARSED) ===")
        print(json.dumps(data, indent=2)[:2000])
        print("\n[... JSON truncated for brevity ...]")
        
        # Analyze structure
        print("\n=== STRUCTURE ===")
        print(f"Root keys: {list(data.keys()) if isinstance(data, dict) else 'array'}")
        if isinstance(data, dict):
            for key in list(data.keys())[:5]:
                val = data[key]
                if isinstance(val, (list, dict)):
                    print(f"  {key}: {type(val).__name__} ({len(val)} items)")
                else:
                    print(f"  {key}: {type(val).__name__}")
    except json.JSONDecodeError as e:
        print("ERROR: Failed to parse JSON")
        print(f"Raw content: {json_str[:500]}")
else:
    print("No results from query")

ssh.close()
