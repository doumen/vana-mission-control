import zipfile
import os
import paramiko
import shutil
from pathlib import Path

# 1. Extrair o ZIP localmente
print("1. Extraindo backup...\n")

zip_file = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\vana-mission-control-Galeria-Sangha_Nos-modais.zip'
extract_dir = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\backup_restore'

# Remover diretório anterior se existir
if os.path.exists(extract_dir):
    shutil.rmtree(extract_dir)

os.makedirs(extract_dir)

with zipfile.ZipFile(zip_file, 'r') as zip_ref:
    zip_ref.extractall(extract_dir)

print(f"✓ Extraído em {extract_dir}")

# 2. Listar conteúdo
files = []
for root, dirs, filelist in os.walk(extract_dir):
    for file in filelist:
        files.append(os.path.join(root, file))

print(f"✓ Total de {len(files)} arquivos\n")

# 3. Conectar ao servidor
print("2. Fazendo upload para o servidor...\n")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

remote_base = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control'

# Upload de cada arquivo
uploaded = 0
for local_file in files:
    # Calcular caminho relativo
    rel_path = os.path.relpath(local_file, extract_dir)
    remote_file = os.path.join(remote_base, rel_path).replace(chr(92), '/')
    
    try:
        sftp.put(local_file, remote_file)
        uploaded += 1
        if uploaded % 10 == 0:
            print(f"  ✓ {uploaded}/{len(files)} arquivos")
    except Exception as e:
        print(f"  ✗ {rel_path}")

sftp.close()
print(f"\n✓ Total enviado: {uploaded} arquivos")

print("\n3. Testando WordPress...\n")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp eval "echo \'WordPress OK\';" --allow-root 2>&1 | head -1')
result = stdout.read().decode('utf-8').strip()

if 'OK' in result or 'WordPress' in result:
    print("✓ WordPress funcionando!")
else:
    print(f"⚠ Status: {result[:100]}")

ssh.close()
print("\n✓ Restauração completa!")
