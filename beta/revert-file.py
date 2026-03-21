import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

# Use um dos fixes conhecidos como funcionando
print("Fazendo revert do vana-mission-control.php...\n")

# Copy the fix4 backup which likely worked
local_backup = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php.fix4_20260223_190834'

remote_files = [
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php',
]

for remote_file in remote_files:
    print(f"  → {remote_file.split('/')[-3]}")
    try:
        sftp.put(local_backup, remote_file)
        print(f"    ✓ Revert feito")
    except Exception as e:
        print(f"    ✗ {e}")

sftp.close()

print("\n✓ Arquivo restaurado!")

print("\nTestando página...\n")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp eval "echo \'OK\';" --allow-root 2>&1 | head -1')
result = stdout.read().decode('utf-8').strip()

if 'OK' in result or 'error' not in result.lower():
    print("✓ WordPress carregando")
else:
    print("Erro:")
    print(result[:200])

ssh.close()
