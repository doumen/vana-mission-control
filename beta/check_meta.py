#!/usr/bin/env python3
import paramiko
import sys

HOST = '149.62.37.117'
PORT = 65002
USER = 'u419701790'
PASS = 'Mga@4455'
REMOTE_BASE = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASS)

commands = [
    (f"cd {REMOTE_BASE} && wp post meta get 360 _vana_origin_key --allow-root 2>&1", "Tour 360 origin_key"),
    (f"cd {REMOTE_BASE} && wp post list --post_type=vana_visit --format=csv --fields=ID,post_title --allow-root | head -15", "Visitas"),
    (f"cd {REMOTE_BASE} && wp post meta get 359 _vana_tour_id --allow-root 2>&1", "Visit 359 (_vana_tour_id)"),
    (f"cd {REMOTE_BASE} && wp post meta get 359 _vana_parent_tour_origin_key --allow-root 2>&1", "Visit 359 (_vana_parent_tour_origin_key)"),
    (f"cd {REMOTE_BASE} && wp post get 359 --format=value --field=post_parent --allow-root", "Visit 359 (post_parent)"),
]

for cmd, label in commands:
    print(f"\n{'='*60}")
    print(f"{label}")
    print('='*60)
    stdin, stdout, stderr = ssh.exec_command(cmd)
    output = stdout.read().decode('utf-8', errors='replace').strip()
    error = stderr.read().decode('utf-8', errors='replace').strip()
    
    if output:
        print(output)
    else:
        print("(vazio)")
    
    if error and 'Warning' not in error:
        print(f"ERRO: {error}")

ssh.close()
