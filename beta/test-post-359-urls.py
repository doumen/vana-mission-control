#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

urls_to_test = [
    'http://149.62.37.117/beta_html/?p=359',
    'http://149.62.37.117/beta_html/index.php?p=359',
    'http://149.62.37.117/beta_html/vrindavan-2026-02/',
]

for url in urls_to_test:
    print("=" * 80)
    print(f"Testing: {url}")
    print("=" * 80)
    
    cmd = f"curl -L -s -w '\\nHTTP_CODE: %{{http_code}}' '{url}' 2>/dev/null | head -30"
    _, stdout, _ = ssh.exec_command(cmd, timeout=30)
    out = stdout.read().decode('utf-8', 'ignore')
    
    # Extract HTTP code
    lines = out.split('\n')
    http_line = [l for l in lines if 'HTTP_CODE' in l]
    
    print('\n'.join(lines[:20]))
    if http_line:
        print(f"\n{http_line[0]}")
    
    print()

ssh.close()
