import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

local_file = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php'

# Upload to BOTH locations
remote_files = [
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/vana-mission-control.php',
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php',
]

print("Fazendo upload via SFTP...\n")

for remote_file in remote_files:
    location = 'PROD' if 'vana-mission-control-gpt' in remote_file else 'BETA'
    print(f"  [{location}] {remote_file.split('/')[-3]}")
    
    try:
        # Remove old file first
        try:
            sftp.remove(remote_file)
        except:
            pass
        
        # Upload new file
        sftp.put(local_file, remote_file)
        
        # Verify file size
        stat = sftp.stat(remote_file)
        print(f"       Size: {stat.st_size} bytes ✓")
    except Exception as e:
        print(f"       Erro: {e}")

sftp.close()

print("\nVerificando sintaxe em ambos os locais...\n")

locations = [
    ('/home/u419701790/domains/vanamadhuryamdaily.com/public_html', 'PROD'),
    ('/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html', 'BETA'),
]

for location, label in locations:
    print(f"[{label}]")
    stdin, stdout, stderr = ssh.exec_command(f'cd {location} && wp eval "echo \'OK\';" --allow-root 2>&1')
    result = stdout.read().decode('utf-8').strip()
    
    if 'OK' in result or 'Success' in result:
        print("  ✓ Sintaxe OK\n")
    else:
        # Get first error line
        lines = result.split('\n')
        for line in lines:
            if 'error' in line.lower() or 'parse' in line.lower():
                print(f"  ✗ {line[:80]}\n")
                break

ssh.close()
