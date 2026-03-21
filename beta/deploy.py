import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

local_file = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php'

# All remote locations
remote_files = [
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/vana-mission-control.php',
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php',
    '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/google-site-kit/vana-mission-control/vana-mission-control.php',
]

# Verify local file exists
if not os.path.exists(local_file):
    print(f"Error: Local file not found: {local_file}")
    ssh.close()
    exit(1)

sftp = ssh.open_sftp()

print("Uploading vana-mission-control.php to all locations...")
for remote_file in remote_files:
    try:
        print(f"  → {remote_file}")
        sftp.put(local_file, remote_file)
        print(f"    ✓ Done")
    except Exception as e:
        print(f"    ✗ Error: {e}")

sftp.close()
print("\n✓ All uploads complete!")

print("\nVerifying PHP syntax on production...")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && wp eval "echo \'WordPress OK\';" --allow-root')
result = stdout.read().decode('utf-8').strip()
error = stderr.read().decode('utf-8').strip()

if 'WordPress OK' in result:
    print("✓ WordPress is functional!")
else:
    print("✗ Error detected:")
    if error:
        print(f"  {error[:300]}")
    else:
        print(f"  {result[:300]}")

ssh.close()

print("\nVerifying PHP syntax...")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && wp eval "echo \'WordPress OK\';" --allow-root')
result = stdout.read().decode('utf-8').strip()
error = stderr.read().decode('utf-8').strip()

if 'WordPress OK' in result:
    print("✓ WordPress is functional!")
else:
    print("✗ Error:", error if error else result)

ssh.close()
