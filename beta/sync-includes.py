import paramiko
import os
import glob

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

# Local and remote base paths
local_base = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control'
remote_base = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt'

print("Sincronizando arquivos da pasta includes/...\n")

# Get all files from includes directory
includes_files = glob.glob(os.path.join(local_base, 'includes', '*.php'))
includes_files += glob.glob(os.path.join(local_base, 'includes', 'cli', '*.php'))
includes_files += glob.glob(os.path.join(local_base, 'includes', 'rest', '*.php'))

for local_file in includes_files:
    # Calculate relative path
    rel_path = os.path.relpath(local_file, local_base)
    remote_file = f"{remote_base}/{rel_path.replace(chr(92), '/')}"
    
    print(f"  → {rel_path}")
    
    try:
        sftp.put(local_file, remote_file)
        print(f"    ✓")
    except Exception as e:
        print(f"    ✗ {e}")

sftp.close()
print("\n✓ Sincronização completa!")

print("\nTestando WordPress novamente...")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && wp core is-installed --allow-root 2>&1')
result = stdout.read().decode('utf-8').strip()
print(f"Status: {result}")

ssh.close()
