#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Export meta direto para arquivo no servidor
sql_cmd = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e "
SELECT meta_value FROM wp_postmeta WHERE post_id = 359 AND meta_key = '_vana_visit_timeline_json';
" > /tmp/post-359-timeline.txt 2>/dev/null"""

ssh.exec_command(sql_cmd, timeout=30)

# Puxar o arquivo
sftp = ssh.open_sftp()
sftp.get('/tmp/post-359-timeline.txt', 'beta/post-359-timeline-raw.txt')
sftp.close()

print("=== FILE DOWNLOADED ===")
with open('beta/post-359-timeline-raw.txt', 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()
    print(f"Size: {len(content)} bytes")
    print(f"\nFirst 1000 chars:\n{content[:1000]}")
    print("\n...")
    print(f"\nLast 200 chars:\n{content[-200:]}")

ssh.close()
