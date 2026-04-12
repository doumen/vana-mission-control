#!/usr/bin/env python3
import paramiko
import os

# SSH settings
REMOTE_HOST = 'vanamadhuryamdaily.com'
REMOTE_USER = 'u419701790'
REMOTE_PASS = 'SH0p*Hostinger@2026'
REMOTE_BASE = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/'

# Files to upload
files_to_upload = [
    ('wp-content/plugins/vana-mission-control/templates/visit/visit-template.php', 'templates/visit/visit-template.php'),
]

def upload_files():
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        client.connect(REMOTE_HOST, username=REMOTE_USER, password=REMOTE_PASS)
        print(f"Connected to {REMOTE_HOST}")
        
        sftp = client.open_sftp()
        print("Uploading files...")
        
        for local_path, remote_path in files_to_upload:
            full_local = os.path.abspath(local_path)
            full_remote = REMOTE_BASE + remote_path
            
            if os.path.exists(full_local):
                sftp.put(full_local, full_remote)
                print(f"  Uploading: {full_remote}")
                print(f"    OK")
            else:
                print(f"  ERROR: File not found: {full_local}")
        
        sftp.close()
        print("\nUpload complete!")
        
    except Exception as e:
        print(f"Error: {e}")
    finally:
        client.close()

if __name__ == '__main__':
    upload_files()
