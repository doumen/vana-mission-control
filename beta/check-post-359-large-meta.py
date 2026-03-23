#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Query para pegar metas grandes (JSON data)
sql_cmd = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e "
SELECT 'LARGE META (>100 bytes):' as section;
SELECT meta_key, LENGTH(meta_value) as size FROM wp_postmeta WHERE post_id = 359 AND LENGTH(meta_value) > 100 ORDER BY LENGTH(meta_value) DESC;

SELECT 'FULL META VALUES FOR TIMELINE:' as section;
SELECT meta_key, LENGTH(meta_value) as size, SUBSTRING(meta_value, 1, 200) as snippet FROM wp_postmeta WHERE post_id = 359 AND (meta_key LIKE '%timeline%' OR meta_key LIKE '%json%') ORDER BY meta_key;
" 2>/dev/null"""

_, stdout, stderr = ssh.exec_command(sql_cmd, timeout=120)
out = stdout.read().decode('utf-8', 'ignore')
err = stderr.read().decode('utf-8', 'ignore')

print(out if out else "(no stdout)")
if err.strip():
    print("\nSTDERR:", err[:500])

ssh.close()
