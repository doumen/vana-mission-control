#!/usr/bin/env python3
import paramiko
import time

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=60)

print("STEP 1: Deactivate and reactivate plugin...")
sftp = ssh.open_sftp()
remote_dir = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"

cmd1 = f"cd {remote_dir} && timeout 10 wp plugin deactivate vana-mission-control --allow-root 2>&1 || echo 'Deactivate done or timeout'"
_, stdout, _ = ssh.exec_command(cmd1, timeout=15)
out = stdout.read().decode('utf-8', 'ignore')
print(out)

time.sleep(1)

cmd2 = f"cd {remote_dir} && timeout 10 wp plugin activate vana-mission-control --allow-root 2>&1 || echo 'Activate done or timeout'"
_, stdout, _ = ssh.exec_command(cmd2, timeout=15)
out = stdout.read().decode('utf-8', 'ignore')
print(out)

print("\nSTEP 2: Flush rewrite rules...")
cmd3 = f"cd {remote_dir} && timeout 5 wp rewrite flush --hard --allow-root 2>&1 || echo 'Flush done or timeout'"
_, stdout, _ = ssh.exec_command(cmd3, timeout=10)
out = stdout.read().decode('utf-8', 'ignore')
print(out)

print("\nSTEP 3: Check post 359 status...")
cmd4 = f"cd {remote_dir} && wp post get 359 --field=post_status --allow-root 2>&1"
_, stdout, stderr = ssh.exec_command(cmd4, timeout=10)
out = stdout.read().decode('utf-8', 'ignore')
err = stderr.read().decode('utf-8', 'ignore')
print(f"Status: {out.strip() if out.strip() else '(timeout or error)'}")

print("\nSTEP 4: Test access by post ID...")
cmd5 = """curl -s -w "\\nHTTP_CODE: %{http_code}" 'http://149.62.37.117/beta_html/?p=359' | head -30"""
_, stdout, _ = ssh.exec_command(cmd5, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
print(out)

if 'vana-page-root' in out or '200' in out:
    print("\n✅ SUCCESS!")
else:
    print("\n❌ Still 404")

ssh.close()
