#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=10)
    
    # List all vana_visit posts
    stdin, stdout, stderr = client.exec_command(
        '''
        cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
        wp post list --post_type=vana_visit --field=ID,title,name --limit=20 2>/dev/null
        '''
    )
    
    output = stdout.read().decode()
    print("All vana_visit posts:")
    print(output)
    
except Exception as e:
    print(f"Error: {e}")
finally:
    client.close()
