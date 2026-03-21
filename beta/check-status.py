import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Checando erros recentes no servidor...\n")

# Check debug log for errors
stdin, stdout, stderr = ssh.exec_command('tail -50 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/debug.log | grep -iE "error|warning|fatal|critical" 2>/dev/null || tail -50 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/debug.log')
result = stdout.read().decode('utf-8')
print("=== Debug.log (últimas 50 linhas) ===")
print(result if result.strip() else "(Sem erros detectados)")

# Check if WordPress is accessible
print("\n=== Teste de acesso ao WordPress ===")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && wp core is-installed --allow-root 2>&1')
result = stdout.read().decode('utf-8').strip()
print("WordPress instalado:", "SIM" if "Success" in result or "installed" in result else "NÃO")

# Check if the main theme is loaded
print("\n=== Tema ativo ===")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && wp theme list --allow-root 2>&1 | grep -i "active"')
result = stdout.read().decode('utf-8')
print(result if result.strip() else "Nenhum tema detectado")

# Check if vana plugin is active
print("\n=== Status do plugin Vana Mission Control ===")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && wp plugin list --allow-root 2>&1 | grep -i "vana"')
result = stdout.read().decode('utf-8')
print(result if result.strip() else "Plugin não encontrado")

ssh.close()
