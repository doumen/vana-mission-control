#!/usr/bin/env python3
import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Uploading updated vana-mission-control.php to remove old api/ reference...")

# Upload via SFTP
sftp = ssh.open_sftp()
local_file = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php"
remote_file = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php"

sftp.put(local_file, remote_file)
sftp.close()

print(f"✅ Uploaded {local_file}")
print(f"   To: {remote_file}")

# Verify by checking the checkin-api line
print("\nVerifying correct line in remote file...")
cmd = """grep -n 'class-vana-checkin-api' /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php"""
_, stdout, _ = ssh.exec_command(cmd, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
print(f"Remote file now contains:\n{out.strip()}")

# Clear plugin cache
print("\nClearing WordPress cache...")
cmd2 = """rm -rf /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/*cache* 2>/dev/null; echo 'Cache cleared'"""
_, stdout, _ = ssh.exec_command(cmd2, timeout=30)
print(stdout.read().decode('utf-8', 'ignore'))

print("\n" + "=" * 80)
print("Now testing post 359 access...")
print("=" * 80)

cmd3 = """curl -s 'http://149.62.37.117/beta_html/index.php?p=359' | grep -o 'vana-page-root\\|This Page Does Not Exist' | head -1"""
_, stdout, _ = ssh.exec_command(cmd3, timeout=30)
result = stdout.read().decode('utf-8', 'ignore').strip()
if 'vana-page-root' in result:
    print("✅ PAGE FIXED! vana-page-root div found!")
elif 'This Page' in result:
    print("❌ Still getting 404 page...")
else:
    print(f"Result: {result}")

ssh.close()
