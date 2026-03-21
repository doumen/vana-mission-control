import paramiko
import os
import glob

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

local_base = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control'

locations = [
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt',
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control',
]

print("Sincronizando includes para ambos os locais...\n")

# Get ALL php files from includes folder
all_includes = glob.glob(os.path.join(local_base, 'includes', '**', '*.php'), recursive=True)

for remote_base in locations:
    label = 'PROD' if 'vana-mission-control-gpt' in remote_base else 'BETA'
    print(f"[{label}] Sincronizando {len(all_includes)} arquivos...\n")
    
    for local_file in all_includes:
        rel_path = os.path.relpath(local_file, local_base)
        remote_file = os.path.join(remote_base, rel_path).replace(chr(92), '/')
        
        try:
            sftp.put(local_file, remote_file)
        except Exception as e:
            print(f"  ✗ {rel_path}")

print("\n✓ Sincronização completa!")

sftp.close()

print("\nVerificando WordPress...\n")

for location, label in zip(locations, ['PROD', 'BETA']):
    cd = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html' if label == 'PROD' else '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html'
    stdin, stdout, stderr = ssh.exec_command(f'cd {cd} && wp eval "echo \'WordPress OK\';" --allow-root 2>&1 | head -1')
    result = stdout.read().decode('utf-8').strip()
    status = '✓' if 'OK' in result else '✗'
    print(f"[{label}] {status}")

ssh.close()
