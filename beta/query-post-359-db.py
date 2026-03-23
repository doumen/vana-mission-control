#!/usr/bin/env python3
import paramiko
import re

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# 1. Extrair credenciais
print("=" * 80)
print("EXTRACTING WP-CONFIG CREDENTIALS")
print("=" * 80)

cmd_wp_config = """grep -E "DB_NAME|DB_USER|DB_PASSWORD|DB_HOST" \
/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-config.php | grep -v '//'"""

_, stdout, _ = ssh.exec_command(cmd_wp_config, timeout=30)
wp_config_out = stdout.read().decode('utf-8', 'ignore')
print(wp_config_out)

# Parse das credenciais - buscar dentro de define('KEY', 'VALUE')
db_name_match = re.search(r"define\(\s*'DB_NAME'\s*,\s*'([^']+)'", wp_config_out)
db_user_match = re.search(r"define\(\s*'DB_USER'\s*,\s*'([^']+)'", wp_config_out)
db_pass_match = re.search(r"define\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'", wp_config_out)
db_host_match = re.search(r"define\(\s*'DB_HOST'\s*,\s*'([^']+)'", wp_config_out)

db_name = db_name_match.group(1) if db_name_match else "unknown"
db_user = db_user_match.group(1) if db_user_match else "unknown"
db_pass = db_pass_match.group(1) if db_pass_match else "unknown"
db_host = db_host_match.group(1) if db_host_match else "localhost"

print(f"\n=== PARSED CREDENTIALS ===")
print(f"DB_NAME: {db_name}")
print(f"DB_USER: {db_user}")
print(f"DB_HOST: {db_host}")

# 2. Query MySQL
print("\n" + "=" * 80)
print("QUERYING POST 359 + META")
print("=" * 80)

sql_cmd = f"""mysql -u{db_user} -p{db_pass} -h{db_host} {db_name} -e "
SELECT 'POST 359 DATA:' as section;
SELECT ID, post_title, post_name, post_type, post_status FROM wp_posts WHERE ID = 359;

SELECT 'POST 359 META KEYS:' as section;
SELECT meta_key, LENGTH(meta_value) as value_bytes, LEFT(meta_value, 120) as preview FROM wp_postmeta WHERE post_id = 359 ORDER BY meta_key;

SELECT 'ALL VISIT POSTS (for context):' as section;
SELECT ID, post_title, post_name, post_status FROM wp_posts WHERE post_type = 'vana_visit' AND ID IN (358, 359, 360) ORDER BY ID;
" 2>/dev/null"""

_, stdout, stderr = ssh.exec_command(sql_cmd, timeout=120)
out = stdout.read().decode('utf-8', 'ignore')
err = stderr.read().decode('utf-8', 'ignore')

print(out if out else "(no stdout)")
if err:
    err_str = err[:500]
    if err_str.strip():
        print("STDERR:", err_str)

ssh.close()
