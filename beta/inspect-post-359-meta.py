#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"

BASE = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
POST_ID = 359

print("=" * 80)
print("INVESTIGANDO POST 359 (Rick Astley) — META KEYS")
print("=" * 80)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)

# 1. Listar TODOS os meta keys do post 359
cmd = f"cd {BASE} && wp post meta list {POST_ID} --format=table"
_, stdout, stderr = ssh.exec_command(cmd, timeout=120)
out = stdout.read().decode("utf-8", errors="replace")
print("\nALL POST META KEYS:")
print(out if out else "(nenhuma meta)")
err = stderr.read().decode("utf-8", errors="replace")
if err:
    print("STDERR:", err)

# 2. Procura especificamente por keys com "vana", "visit", "timeline", "json"
cmd = f"cd {BASE} && wp post meta list {POST_ID} --format=table | grep -iE 'vana|visit|timeline|json|data'"
_, stdout, stderr = ssh.exec_command(cmd, timeout=120)
out = stdout.read().decode("utf-8", errors="replace")
print("\nFILTERED (vana/visit/timeline/json/data):")
print(out if out else "(nenhuma correspondência)")

ssh.close()
