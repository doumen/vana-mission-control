#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("=" * 80)
print("CHECK 1: Post 359 post_type")
print("=" * 80)

cmd1 = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e \
"SELECT ID, post_title, post_type, post_template FROM wp_posts WHERE ID = 359;" 2>/dev/null"""
_, stdout, _ = ssh.exec_command(cmd1, timeout=30)
result = stdout.read().decode('utf-8', 'ignore')
print(result)

print("\n" + "=" * 80)
print("CHECK 2: Post 359 _wp_page_template meta (template assignment)")
print("=" * 80)

cmd2 = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e \
"SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = 359 AND meta_key LIKE '%template%';" 2>/dev/null"""
_, stdout, _ = ssh.exec_command(cmd2, timeout=30)
result = stdout.read().decode('utf-8', 'ignore')
print(result)

print("\n" + "=" * 80)
print("CHECK 3: Raw HTML of post 359 (first 2000 chars)")
print("=" * 80)

cmd3 = """wget -q -O - 'http://149.62.37.117/beta_html/?p=359' 2>/dev/null | head -200"""
_, stdout, _ = ssh.exec_command(cmd3, timeout=30)
result = stdout.read().decode('utf-8', 'ignore')
print(result[:2000])

ssh.close()
