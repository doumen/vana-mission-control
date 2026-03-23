#!/usr/bin/env python3
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
BASE = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)

cmd = f"cd {BASE} && wp post meta list 359 --format=table 2>&1"
_, stdout, stderr = ssh.exec_command(cmd, timeout=120)

out = stdout.read().decode("utf-8", errors="replace")
err = stderr.read().decode("utf-8", errors="replace")

with open(r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\post-359-meta.txt", "w") as f:
    f.write("=== POST 359 META KEYS ===\n")
    f.write(out if out else "(no stdout)\n")
    if err:
        f.write("\n=== STDERR ===\n")
        f.write(err)

print("Saved to post-359-meta.txt")
for line in out.split('\n')[:50]:
    print(line)

ssh.close()
