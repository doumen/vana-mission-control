import paramiko
import os
import glob

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

local_base = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control'
remote_base = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt'

# Sync api/ folder
print("Sincronizando pasta api/...\n")
api_files = glob.glob(os.path.join(local_base, 'api', '**', '*.php'), recursive=True)
for local_file in api_files:
    rel_path = os.path.relpath(local_file, local_base).replace(chr(92), '/')
    remote_file = f"{remote_base}/{rel_path}"
    print(f"  → {rel_path}")
    try:
        sftp.put(local_file, remote_file)
        print(f"    ✓")
    except Exception as e:
        print(f"    ✗ {e}")

# Sync assets/ folder
print("\nSincronizando pasta assets/...\n")
asset_files = glob.glob(os.path.join(local_base, 'assets', '**', '*'), recursive=True)
asset_files = [f for f in asset_files if os.path.isfile(f)]
for local_file in asset_files[:50]:  # Limit to avoid too many files
    rel_path = os.path.relpath(local_file, local_base).replace(chr(92), '/')
    remote_file = f"{remote_base}/{rel_path}"
    print(f"  → {rel_path}")
    try:
        sftp.put(local_file, remote_file)
        print(f"    ✓")
    except Exception as e:
        print(f"    ✗ {e}")

# Sync root .php files
print("\nSincronizando arquivos raiz...")
root_files = glob.glob(os.path.join(local_base, '*.php'))
for local_file in root_files:
    rel_path = os.path.relpath(local_file, local_base).replace(chr(92), '/')
    remote_file = f"{remote_base}/{rel_path}"
    print(f"  → {rel_path}")
    try:
        sftp.put(local_file, remote_file)
        print(f"    ✓")
    except Exception as e:
        print(f"    ✗ {e}")

sftp.close()

print("\n✓ Sincronização completa!")

print("\nTestando site...\n")
stdin, stdout, stderr = ssh.exec_command('curl -s https://vanamadhuryamdaily.com/ | head -20')
result = stdout.read().decode('utf-8')
if '<!DOCTYPE' in result or '<html' in result:
    print("✓ Página carregando HTML")
else:
    print("✗ Resposta inesperada:")
    print(result[:200])

ssh.close()
