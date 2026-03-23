import paramiko
import os
import subprocess

# --- CONFIGURAÇÕES ---
HOST = '149.62.37.117'
PORT = 65002
USER = 'u419701790'
PASS = 'Mga@4455'

# Mapeamento de caminhos
LOCAL_REPO_ROOT = r'C:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode'
PLUGIN_REL_PATH = 'wp-content/plugins/vana-mission-control'
REMOTE_BASE = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html'

# Extensões da "Família WP"
WP_EXTENSIONS = ('.php', '.js', '.css', '.json', '.sql', '.svg', '.png', '.jpg')

def get_wp_modified_files():
    """Identifica arquivos modificados via Git filtrando pela família WP."""
    try:
        # Pega modificados (m), não rastreados (o) e deletados (d)
        cmd = ["git", "ls-files", "-m", "-o", "-d", "--exclude-standard", PLUGIN_REL_PATH]
        result = subprocess.run(cmd, cwd=LOCAL_REPO_ROOT, capture_output=True, text=True, check=True)
        
        # Filtra apenas pelas extensões desejadas
        files = [f for f in result.stdout.splitlines() if f.lower().endswith(WP_EXTENSIONS)]
        return list(set(files))
    except Exception as e:
        print(f"Erro ao acessar Git: {e}")
        return []

def main():
    files = get_wp_modified_files()
    
    if not files:
        print("[-] Nenhum arquivo da família WP modificado.")
        return

    print(f"[*] Preparando deploy para {len(files)} arquivos:")
    for f in files: print(f"  -> {f}")
    
    if input("\nConfirmar deploy para staging? (s/n): ").lower() != 's': return

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        ssh.connect(HOST, port=PORT, username=USER, password=PASS)
        sftp = ssh.open_sftp()
        
        for file_path in files:
            local_full = os.path.join(LOCAL_REPO_ROOT, file_path.replace('/', os.sep))
            remote_full = os.path.join(REMOTE_BASE, file_path).replace('\\', '/')
            
            # Se o arquivo foi deletado localmente, removemos remotamente (opcional)
            if not os.path.exists(local_full):
                try: 
                    sftp.remove(remote_full)
                    print(f"[X] Removido no servidor: {file_path}")
                except: pass
                continue

            # Garantir diretório remoto e fazer upload
            remote_dir = os.path.dirname(remote_full)
            ssh.exec_command(f"mkdir -p {remote_dir}")
            
            sftp.put(local_full, remote_full)
            print(f"[+] Deploy: {file_path}")

        # Pós-deploy: Limpeza de cache e verificação
        print("\n[*] Finalizando: Purge LiteSpeed...")
        ssh.exec_command(f"cd {REMOTE_BASE} && wp litespeed-purge all --allow-root")
        print("[OK] Deploy Staging concluido com sucesso!")

    finally:
        ssh.close()

if __name__ == "__main__":
    main()