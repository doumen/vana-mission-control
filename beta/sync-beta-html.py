import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

local_file = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php'

remote_files = [
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/vana-mission-control.php',
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php',
]

print("Atualizando vana-mission-control.php em ambas as instalações...\n")

for remote_file in remote_files:
    try:
        print(f"  → {remote_file.split('/')[-4]}")
        sftp.put(local_file, remote_file)
        print(f"    ✓")
    except Exception as e:
        print(f"    ✗ {e}")

sftp.close()
print("\n✓ Atualização completa!")

print("\nVerificando erros em beta_html...\n")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp eval "echo \'OK\';" --allow-root 2>&1')
result = stdout.read().decode('utf-8').strip()
error = stderr.read().decode('utf-8').strip()

if 'OK' in result or 'Success' in result:
    print("✓ PHP syntax OK em beta_html!")
else:
    print("✗ Ainda há erro:")
    print(result[:300] if result else error[:300])

ssh.close()
