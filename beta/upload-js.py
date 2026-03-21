import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

# Direct file upload with absolute Windows path
print("Sincronizando arquivos JS...\n")

# Read file content and upload
with open(r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\vana-event-controller.js', 'rb') as f:
    print("  → vana-event-controller.js")
    sftp.putfo(f, '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/assets/js/vana-event-controller.js')
    print("    ✓")

with open(r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\oferenda-form.js', 'rb') as f:
    print("  → oferenda-form.js")
    sftp.putfo(f, '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/assets/js/oferenda-form.js')
    print("    ✓")

# Sync event-selector.php from includes/
with open(r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\inc\event-selector.php', 'rb') as f:
    print("  → event-selector.php")
    sftp.putfo(f, '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt/inc/event-selector.php')
    print("    ✓")

sftp.close()
print("\n✓ Sincronização completa!")

ssh.close()
