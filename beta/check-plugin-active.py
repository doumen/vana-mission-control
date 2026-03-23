#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("=" * 80)
print("CHECK 1: Is vana-mission-control plugin ACTIVE?")
print("=" * 80)

cmd1 = """cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
wp plugin list --status=active --allow-root 2>&1 | grep -i 'vana\\|mission'"""
_, stdout, _ = ssh.exec_command(cmd1, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
print(out if out.strip() else "(no output - plugin may not be active)")

print("\n" + "=" * 80)
print("CHECK 2: List all plugins")
print("=" * 80)

cmd2 = """cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
wp plugin list --allow-root 2>&1 | head -20"""
_, stdout, _ = ssh.exec_command(cmd2, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
print(out.strip())

print("\n" + "=" * 80)
print("CHECK 3: Check wp_options for active plugins")
print("=" * 80)

cmd3 = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e \
"SELECT option_id, option_name, LENGTH(option_value) as option_size FROM wp_options WHERE option_name = 'active_plugins';" 2>/dev/null"""
_, stdout, _ = ssh.exec_command(cmd3, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
print(out)

print("\n" + "=" * 80)
print("CHECK 4: Extract active plugins to see vana-mission-control")
print("=" * 80)

cmd4 = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e \
"SELECT option_value FROM wp_options WHERE option_name = 'active_plugins';" 2>/dev/null | grep -o 'vana[^"]*'"""
_, stdout, _ = ssh.exec_command(cmd4, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
print(out if out.strip() else "(vana-mission-control not found in active_plugins)")

ssh.close()
