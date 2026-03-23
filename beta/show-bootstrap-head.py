#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"

WP_ROOT = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
BOOTSTRAP = WP_ROOT + "/wp-content/plugins/vana-mission-control/templates/visit/_bootstrap.php"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)

cmd = f"sed -n '1,55p' {BOOTSTRAP}"
_, stdout, stderr = ssh.exec_command(cmd, timeout=60)
out = stdout.read().decode("utf-8", errors="replace")
print(out)
ssh.close()
