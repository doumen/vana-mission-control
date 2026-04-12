#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect(
        hostname='vanamadhuryamdaily.com',
        username='u419701790',
        password='SH0p*Hostinger@2026',
        timeout=10
    )
    
    # Read debug.log
    stdin, stdout, stderr = client.exec_command(
        'tail -100 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log'
    )
    
    output = stdout.read().decode()
    print(output)
    
except Exception as e:
    print(f"Error: {e}")
finally:
    client.close()
