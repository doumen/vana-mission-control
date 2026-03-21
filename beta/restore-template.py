import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

local_file = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\visit-template.php'

remote_files = [
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/visit-template.php',
]

print("Fazendo upload do visit-template.php...\n")

for remote_file in remote_files:
    label = 'BETA'
    print(f"  [{label}] Uploading...")
    
    try:
        sftp.put(local_file, remote_file)
        stat = sftp.stat(remote_file)
        print(f"       ✓ Size: {stat.st_size} bytes")
    except Exception as e:
        print(f"       ✗ {e}")

sftp.close()

print("\nTestando página...\n")
stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | grep -o "vana-visit" | head -1')
result = stdout.read().decode('utf-8').strip()

if 'vana-visit' in result:
    print("✓ Página agora tem conteúdo!")
else:
    print("⚠ Verificando HTML...")
    stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | head -150 | tail -50')
    result = stdout.read().decode('utf-8')
    if '<main' in result or 'vana-visit' in result or 'hero-header' in result:
        print("✓ Conteúdo detectado!")
    else:
        print("Primeiras linhas do body:")
        print(result[:300])

ssh.close()
