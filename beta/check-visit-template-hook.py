#!/usr/bin/env python3
import paramiko
import time

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=60)

# Simple direct command via wp root
print("Checking post 359 basic info via WP-CLI...")
cmd = """cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
wp post get 359 --field=post_type --allow-root 2>&1 || echo 'ERROR'"""

_, stdout, stderr = ssh.exec_command(cmd, timeout=15)
out = stdout.read().decode('utf-8', 'ignore').strip()
err = stderr.read().decode('utf-8', 'ignore').strip()

print(f"POST TYPE: {out}")
if err:
    print(f"STDERR: {err[:200]}")

print("\n" + "=" * 80)
print("Checking if vana_visit_template hook is loaded...")
cmd2 = """cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
grep -r "vana_visit_template\\|visit-template.php\\|vana-mission-control/templates" wp-content/plugins/vana-mission-control/ 2>/dev/null | head -5"""

_, stdout, stderr = ssh.exec_command(cmd2, timeout=15)
out = stdout.read().decode('utf-8', 'ignore')
print("Template hooks found:")
print(out if out.strip() else "(no results)")

ssh.close()
