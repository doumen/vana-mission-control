import paramiko
import os

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

files_to_sync = [
    (r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\vana-event-controller.js',
     '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/assets/js/vana-event-controller.js'),
    (r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\oferenda-form.js',
     '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/assets/js/oferenda-form.js'),
    (r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\event-selector.php',
     '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/templates/event-selector.php'),
]

print("Sincronizando arquivos JS e templates...\n")
for local_file, remote_file in files_to_sync:
    if os.path.exists(local_file):
        print(f"  → {os.path.basename(local_file)}")
        try:
            sftp.put(local_file, remote_file)
            print(f"    ✓")
        except Exception as e:
            print(f"    ✗ {e}")
    else:
        print(f"  ✗ {local_file} não encontrado")

sftp.close()
print("\n✓ Done!")

ssh.close()
