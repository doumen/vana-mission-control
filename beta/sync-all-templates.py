import paramiko
import os
import glob

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

local_base = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control'
remote_base = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control'

print("Sincronizando TODOS os templates para BETA...\n")

# Get all template files
template_files = glob.glob(os.path.join(local_base, 'templates', '**', '*.php'), recursive=True)

count = 0
for local_file in template_files:
    rel_path = os.path.relpath(local_file, local_base)
    remote_file = os.path.join(remote_base, rel_path).replace(chr(92), '/')
    
    try:
        sftp.put(local_file, remote_file)
        count += 1
        if count % 5 == 0:
            print(f"  ✓ {count} arquivos sincronizados")
    except Exception as e:
        print(f"  ✗ {rel_path}: {str(e)[:50]}")

sftp.close()
print(f"\n✓ Total: {count} arquivos de template sincronizados!")

print("\nTestando página novamente...\n")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | grep -c "vana-visit-main"')
result = stdout.read().decode('utf-8').strip()

if int(result) > 0:
    print("✓ Conteúdo principal agora está presente!")
else:
    stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | head -100 | tail -50')
    result = stdout.read().decode('utf-8')
    print("Status da página:")
    print(result[:400])

ssh.close()
