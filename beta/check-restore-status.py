import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Verificando status após restauração...\n")

# Test WordPress
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp eval "echo \'WordPress OK\';" --allow-root 2>&1 | head -1')
result = stdout.read().decode('utf-8').strip()
print(f"WordPress: {result}")

# Test page load
stdin, stdout, stderr = ssh.exec_command('curl -s -w "\\nHTTP %{http_code}\\n" https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ 2>&1 | tail -1')
result = stdout.read().decode('utf-8').strip()
print(f"Página HTTP Status: {result}")

# Check errors
stdin, stdout, stderr = ssh.exec_command('tail -5 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null')
result = stdout.read().decode('utf-8')
if 'error' in result.lower() or 'parse' in result.lower():
    print(f"\nÚltimo erro:\n{result[-200:]}")
else:
    print("✓ Sem erros recentes")

ssh.close()
