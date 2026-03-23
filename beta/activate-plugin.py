#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# ACTIVATE PLUGIN via MySQL direct
# We'll update wp_options to add vana-mission-control to active_plugins array

print("Checking current active_plugins...")
cmd_check = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e "SELECT option_value FROM wp_options WHERE option_name = 'active_plugins';" 2>/dev/null"""
_, stdout, _ = ssh.exec_command(cmd_check, timeout=30)
current = stdout.read().decode('utf-8', 'ignore').strip()
print(f"Current active_plugins:\n{current}\n")

# Try activation via wp-cli with timeout
print("Attempting plugin activation via wp-cli (with timeout)...")
cmd_activate = """timeout 5 /usr/local/bin/wp plugin activate vana-mission-control --allow-root --path=/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html 2>&1 || echo "Command timed out or failed"
"""
_, stdout, stderr = ssh.exec_command(cmd_activate, timeout=10)
out = stdout.read().decode('utf-8', 'ignore')
err = stderr.read().decode('utf-8', 'ignore')
print(f"Output: {out}")
if err:
    print(f"Error: {err}")

print("\nVerifying plugin activation...")
cmd_verify = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e "SELECT option_value FROM wp_options WHERE option_name = 'active_plugins';" 2>/dev/null"""
_, stdout, _ = ssh.exec_command(cmd_verify, timeout=30)
result = stdout.read().decode('utf-8', 'ignore').strip()
if 'vana-mission-control' in result:
    print("✅ Plugin activated!")
else:
    print("❌ Plugin activation failed")
print(f"Active plugins now:\n{result[:200]}...")

ssh.close()
