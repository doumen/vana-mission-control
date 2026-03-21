import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Verificando arquivos no servidor...\n")

files_to_check = [
    'assets/js/vana-event-controller.js',
    'assets/js/oferenda-form.js',
    'assets/css/vana-ui.visit-hub.css',
    'includes/class-vana-hmac.php',
    'vana-mission-control.php'
]

remote_base = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control-gpt'

for file in files_to_check:
    remote_file = f"{remote_base}/{file}"
    stdin, stdout, stderr = ssh.exec_command(f'test -f "{remote_file}" && echo "✓" || echo "✗"')
    result = stdout.read().decode('utf-8').strip()
    print(f"  {file}: {result}")

ssh.close()
