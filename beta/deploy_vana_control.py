import paramiko
import os
import sys

# --- CONFIGURAÇÕES ---
HOST = '149.62.37.117'
PORT = 65002
USER = 'u419701790'
PASS = 'Mga@4455' # Recomenda-se usar variáveis de ambiente por segurança

# Caminhos locais e remotos
LOCAL_PLUGIN_ROOT = r'C:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control'
REMOTE_PLUGIN_PATH = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control'

# Lista de subpastas e arquivos específicos para deploy
# Deixe vazio para subir apenas o arquivo principal ou liste para deploy completo
DEPLOY_ITEMS = [
    'vana-mission-control.php',
    'templates/visit/parts/stage.php',
    'templates/visit/assets/visit-style.php',
    'templates/visit/assets/visit-scripts.php',
    'assets/js/VanaEventController.js',
]

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        print(f"[*] Conectando a {HOST}:{PORT}...")
        ssh.connect(HOST, port=PORT, username=USER, password=PASS, timeout=30)
        sftp = ssh.open_sftp()
        
        for item in DEPLOY_ITEMS:
            local_path = os.path.join(LOCAL_PLUGIN_ROOT, item.replace('/', os.sep))
            remote_path = os.path.join(REMOTE_PLUGIN_PATH, item).replace('\\', '/')
            
            if not os.path.exists(local_path):
                print(f"[!] Erro: Arquivo local não encontrado: {local_path}")
                continue

            # Garante que a pasta remota existe
            remote_dir = os.path.dirname(remote_path)
            try:
                sftp.stat(remote_dir)
            except IOError:
                print(f"[*] Criando diretório remoto: {remote_dir}")
                ssh.exec_command(f"mkdir -p {remote_dir}")

            print(f"[▲] Uploading: {item}...")
            sftp.put(local_path, remote_path)
            print(f"    ✓ Sucesso")

        sftp.close()
        
        # --- VERIFICAÇÃO PÓS-DEPLOY ---
        print("\n[*] Validando sintaxe PHP no servidor...")
        # Corrigindo o erro de verificação anterior, garantindo comando WP-CLI 
        cmd_check = f"cd {os.path.dirname(REMOTE_PLUGIN_PATH)} && wp eval 'echo \"WordPress OK\";' --allow-root"
        stdin, stdout, stderr = ssh.exec_command(cmd_check)
        
        result = stdout.read().decode('utf-8').strip()
        if 'WordPress OK' in result:
            print("    ✓ WordPress e Plugin estão funcionais!")
        else:
            print(f"    ✗ Erro detectado: {stderr.read().decode('utf-8')}")

    except Exception as e:
        print(f"[X] ERRO CRÍTICO: {e}")
    finally:
        ssh.close()
        print("\n[*] Conexão encerrada.")

if __name__ == "__main__":
    main()