#!/usr/bin/env python3
import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

files_to_deploy = [
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\single-vana_visit.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/single-vana_visit.php',
    },
]

sftp = ssh.open_sftp()
print("Uploading files...")
for file in files_to_deploy:
    sftp.put(file['local'], file['remote'])
    print(f"  Uploading: {file['remote']}")
    print(f"    OK")

sftp.close()
ssh.close()
print("\nUpload complete!")
