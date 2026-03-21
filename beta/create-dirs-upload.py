import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

base = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt'

# Create directories
dirs = [
    f'{base}/assets',
    f'{base}/assets/js',
    f'{base}/assets/css',
    f'{base}/inc'
]

print("Criando diretórios...\n")
for d in dirs:
    try:
        sftp.stat(d)
        print(f"  {d} já existe")
    except:
        sftp.mkdir(d)
        print(f"  ✓ {d}")

sftp.close()

# Now upload files
sftp = ssh.open_sftp()

print("\nSincronizando arquivos JS...\n")

with open(r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\vana-event-controller.js', 'rb') as f:
    print("  → vana-event-controller.js")
    sftp.putfo(f, f'{base}/assets/js/vana-event-controller.js')
    print("    ✓")

with open(r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\oferenda-form.js', 'rb') as f:
    print("  → oferenda-form.js")
    sftp.putfo(f, f'{base}/assets/js/oferenda-form.js')
    print("    ✓")

with open(r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\inc\event-selector.php', 'rb') as f:
    print("  → event-selector.php")
    sftp.putfo(f, f'{base}/inc/event-selector.php')
    print("    ✓")

sftp.close()
print("\n✓ Sincronização completa!")

ssh.close()
