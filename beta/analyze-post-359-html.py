#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Fetch post 359 page completely
cmd = """wget -q -O /tmp/post-359-page.html 'http://149.62.37.117/beta_html/?p=359' 2>&1"""
ssh.exec_command(cmd, timeout=30)

# Download to local
sftp = ssh.open_sftp()
sftp.get('/tmp/post-359-page.html', 'beta/post-359-page.html')
sftp.close()

print("Page downloaded. Analyzing...")

with open('beta/post-359-page.html', 'r', encoding='utf-8', errors='ignore') as f:
    html = f.read()
    
print(f"Total HTML size: {len(html)} bytes")
print(f"\nSearching for key patterns:\n")

patterns = {
    'vana-page-root': 'vana-page-root',
    'vana-stage': 'id="vana-stage"',
    'plugin-template-start': '<!-- VANA-PLUGIN-TEMPLATE-START -->',
    'template-error': 'Template not found',
    'fatal-error': 'Fatal error',
    'wp-die': 'wp_die',
    'post-title': '<title>',
}

for name, pattern in patterns.items():
    count = html.count(pattern)
    print(f"  {name:25} {'✓' if count > 0 else '✗'} ({count} found)")

print(f"\n--- FIRST 2000 CHARS ---")
print(html[:2000])

ssh.close()
