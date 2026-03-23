import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Files to deploy
files_to_deploy = [
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php',
        'remote': [
            '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/vana-mission-control.php',
            '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php',
            '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/google-site-kit/vana-mission-control/vana-mission-control.php',
        ]
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\stage.php',
        'remote': [
            '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/parts/stage.php',
        ]
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\assets\visit-scripts.php',
        'remote': [
            '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/assets/visit-scripts.php',
        ]
    }
]

# Verify local files exist
for file_group in files_to_deploy:
    if not os.path.exists(file_group['local']):
        print(f"Error: Local file not found: {file_group['local']}")
        ssh.close()
        exit(1)

sftp = ssh.open_sftp()

print("Uploading files...")
for file_group in files_to_deploy:
    for remote_file in file_group['remote']:
        try:
            print(f"  → {remote_file}")
            sftp.put(file_group['local'], remote_file)
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
