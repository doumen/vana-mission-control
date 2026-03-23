#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Fetching WP_DEBUG log...")

# Get debug.log
cmd = "tail -100 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null"
_, stdout, _ = ssh.exec_command(cmd, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')

if out.strip():
    print("═" * 80)
    print("LAST 100 LINES FROM DEBUG.LOG")
    print("═" * 80)
    print(out)
else:
    print("❌ No debug.log found or empty")
    
    # Check file existence
    cmd2 = "ls -lh /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log"
    _, stdout, _ = ssh.exec_command(cmd2, timeout=30)
    out = stdout.read().decode('utf-8', 'ignore')
    print(f"File check: {out}")
    
    print("\n" + "=" * 80)
    print("Enable WP_DEBUG...")
    print("=" * 80)
    
    # Check wp-config
    cmd3 = "grep 'WP_DEBUG' /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-config.php"
    _, stdout, _ = ssh.exec_command(cmd3, timeout=30)
    out = stdout.read().decode('utf-8', 'ignore')
    print(out)

ssh.close()
