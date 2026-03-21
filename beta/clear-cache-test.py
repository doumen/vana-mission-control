import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("1. Limpando cache do LiteSpeed...\n")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp litespeed-purge all --allow-root 2>&1 || echo "Cache limpo"')
result = stdout.read().decode('utf-8').strip()
print(result[:200])

print("\n2. Testando acesso...\n")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp eval "echo \'WordPress OK\';" --allow-root 2>&1 | head -1')
result = stdout.read().decode('utf-8').strip()
if 'OK' in result or 'Success' in result:
    print("✓ WordPress funcionando")
else:
    # Show error
    print("✗ Erro ao carregar:")
    print(result[:300])

print("\n3. Teste da página...\n")
stdin, stdout, stderr = ssh.exec_command('curl -s --compressed https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ 2>&1 | wc -c')
result = stdout.read().decode('utf-8').strip()
print(f"Tamanho da página: {result} bytes")

if int(result) > 1000:
    print("✓ Página tem conteúdo")
    # Check for specific content
    stdin, stdout, stderr = ssh.exec_command('curl -s --compressed https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | grep -c "vana-visit"')
    count = stdout.read().decode('utf-8').strip()
    print(f"Classe 'vana-visit' encontrada: {count} vezes")
else:
    print("✗ Página muito pequena")

ssh.close()
