import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

sftp = ssh.open_sftp()

base = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt'

# Create directories
dirs = [
    f'{base}/templates',
    f'{base}/templates/visit',
    f'{base}/templates/visit/parts',
]

print("Criando diretórios de templates...\n")
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

print("\nSincronizando templates...\n")

with open(r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\event-selector.php', 'rb') as f:
    print("  → event-selector.php")
    sftp.putfo(f, f'{base}/templates/visit/parts/event-selector.php')
    print("    ✓")

sftp.close()
print("\n✓ Templates sincronizados!")

print("\nTestando site...\n")
stdin, stdout, stderr = ssh.exec_command('curl -s -w "\\n%{http_code}\\n" https://vanamadhuryamdaily.com/ 2>&1 | tail -2')
result = stdout.read().decode('utf-8').strip()
print(f"Status HTTP: {result}")

ssh.close()
