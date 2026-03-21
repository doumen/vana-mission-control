import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Testando página de visita em beta...\n")

# Test if visit page loads
stdin, stdout, stderr = ssh.exec_command('curl -s -I https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ 2>&1 | head -1')
result = stdout.read().decode('utf-8').strip()
print(f"Status HTTP: {result}")

# Check last errors
print("\nÚltimos erros no debug.log:\n")
stdin, stdout, stderr = ssh.exec_command('tail -20 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null | grep -i "error\|parse"')
result = stdout.read().decode('utf-8')
if result.strip():
    print(result)
else:
    print("Nenhum erro recente detectado")

ssh.close()
