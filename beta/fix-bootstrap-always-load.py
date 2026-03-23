#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Uploading fixed _bootstrap.php...")

sftp = ssh.open_sftp()
local_file = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\_bootstrap.php"
remote_file = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/_bootstrap.php"

sftp.put(local_file, remote_file)
sftp.close()

print(f"✅ Uploaded _bootstrap.php")

print("\nVerifying vana-stage.php is now ALWAYS loaded...")
cmd = """grep -A 6 'PRE-LOAD:' /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/_bootstrap.php"""
_, stdout, _ = ssh.exec_command(cmd, timeout=30)
print(stdout.read().decode('utf-8', 'ignore'))

print("\n" + "=" * 80)
print("Testing post 359 again...")
print("=" * 80)

cmd2 = """curl -s 'http://149.62.37.117/beta_html/?p=359' | head -50 | grep -E 'vana-page-root|vana-stage|This Page|Fatal|Error' | head -3"""
_, stdout, _ = ssh.exec_command(cmd2, timeout=30)
result = stdout.read().decode('utf-8', 'ignore')
if 'vana-page-root' in result:
    print("✅ SUCCESS! Page rendering correctly!\n" + result)
elif 'Fatal' in result or 'Error' in result:
    print("❌ Still have fatal errors:\n" + result)
else:
    print("Result:", result if result.strip() else "(no matches)")
    
    # Try fuller page check
    cmd3 = """curl -s 'http://149.62.37.117/beta_html/?p=359' | head -200 | tail -50"""
    _, stdout, _ = ssh.exec_command(cmd3, timeout=30)
    print("\nPage output:")
    print(stdout.read().decode('utf-8', 'ignore'))

ssh.close()
