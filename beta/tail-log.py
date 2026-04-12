#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=10)
    
    stdin, stdout, stderr = client.exec_command(
        'tail -50 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null || echo "Log not found"'
    )
    
    output = stdout.read().decode()
    print("Recent error log entries:")
    print(output)
    
except Exception as e:
    print(f"Error: {e}")
finally:
    client.close()
