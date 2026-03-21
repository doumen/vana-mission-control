import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Diagnosticando página de visita em beta...\n")

# Check debug.log for visit-related errors
print("=== Erros recentes (últimos 100 linhas) ===\n")
stdin, stdout, stderr = ssh.exec_command('tail -100 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null')
result = stdout.read().decode('utf-8')
print(result[-1500:] if len(result) > 1500 else result)

# Check WordPress in beta_html
print("\n\n=== Status do WordPress em beta_html ===\n")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp core is-installed --allow-root 2>&1')
result = stdout.read().decode('utf-8').strip()
print(result)

# Check active plugins
print("\n\n=== Plugins ativos ===\n")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp plugin list --allow-root --status=active 2>&1 | grep -i "vana"')
result = stdout.read().decode('utf-8')
print(result if result.strip() else "Nenhum plugin Vana ativo")

# Check if visit CPT is registered
print("\n\n=== Verificando CPT de Visita ===\n")
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp post-type list --allow-root 2>&1 | grep -i "visit"')
result = stdout.read().decode('utf-8')
print(result if result.strip() else "CPT Visit não registrado")

ssh.close()
