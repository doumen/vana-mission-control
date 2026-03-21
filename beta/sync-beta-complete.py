import paramiko
import os
import glob

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

local_base = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control'
remote_base_beta = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control'

print("Sincronizando includes para beta_html...\n")

# Sync includes files
includes_files = glob.glob(os.path.join(local_base, 'includes', '*.php'))
for local_file in includes_files:
    rel_path = os.path.relpath(local_file, local_base).replace(chr(92), '/')
    remote_file = f"{remote_base_beta}/{rel_path}"
    try:
        sftp.put(local_file, remote_file)
    except:
        pass

# Sync API files
api_files = glob.glob(os.path.join(local_base, 'api', '*.php'))
for local_file in api_files:
    rel_path = os.path.relpath(local_file, local_base).replace(chr(92), '/')
    remote_file = f"{remote_base_beta}/{rel_path}"
    try:
        sftp.put(local_file, remote_file)
    except:
        pass

# Sync key assets
key_assets = [
    'assets/css/vana-ui.visit-hub.css',
    'assets/js/vana-event-controller.js',
    'assets/js/oferenda-form.js',
]

print("Sincronizando assets...\n")
for asset in key_assets:
    local = os.path.join(local_base, asset)
    if os.path.exists(local):
        remote = f"{remote_base_beta}/{asset}"
        try:
            sftp.put(local, remote)
            print(f"  ✓ {asset}")
        except Exception as e:
            print(f"  ✗ {asset}: {e}")

# Sync templates
print("\nSincronizando templates...\n")
template_files = glob.glob(os.path.join(local_base, 'templates', '**', '*.php'), recursive=True)
for local_file in template_files:
    rel_path = os.path.relpath(local_file, local_base).replace(chr(92), '/')
    remote_file = f"{remote_base_beta}/{rel_path}"
    try:
        sftp.put(local_file, remote_file)
    except:
        pass

sftp.close()
print("\n✓ Sincronização beta_html completa!")

ssh.close()
