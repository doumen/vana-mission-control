#!/usr/bin/env python3
import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

files_to_deploy = [
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/vana-mission-control.php',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\agenda-drawer.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/parts/agenda-drawer.php',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\includes\class-vana-assets.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/includes/class-vana-assets.php',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\VanaAgendaController.js',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/assets/js/VanaAgendaController.js',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\vana-day-selector.js',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/assets/js/vana-day-selector.js',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\_hero-day-selector.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/parts/_hero-day-selector.php',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\hero-header.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/parts/hero-header.php',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\visit-template.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/visit-template.php',
    },
    {
        'local': r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\_bootstrap.php',
        'remote': '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/_bootstrap.php',
    },
]

sftp = ssh.open_sftp()

print("Uploading files...")
for f in files_to_deploy:
    if not os.path.exists(f['local']):
        print("  [" + f['local'] + "] NOT FOUND")
        continue
    try:
        print("  Uploading: " + f['remote'])
        sftp.put(f['local'], f['remote'])
        print("    OK")
    except Exception as e:
        print("    ERROR: " + str(e))

sftp.close()
print("\nUpload complete!")

ssh.close()
