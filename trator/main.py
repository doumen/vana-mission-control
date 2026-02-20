# -*- coding: utf-8 -*-
import os
import sys
import json
from pathlib import Path
from dotenv import load_dotenv

# Importamos o nosso novo cliente robusto
from client import VanaClient

load_dotenv()

# Adaptação para aceitar as nomenclaturas antigas ou novas do .env
API_URL = os.getenv("VANA_API_URL") or os.getenv("WP_API_URL")
SECRET = os.getenv("VANA_SECRET") or os.getenv("VANA_INGEST_SECRET")

if not SECRET or not API_URL:
    print("❌ ERRO: Variáveis de ambiente não configuradas no .env")
    print("Certifique-se de ter VANA_API_URL e VANA_SECRET.")
    sys.exit(1)

print(f"✅ Configuração Carregada")
print(f"   URL: {API_URL}")
print(f"   Secret: {len(SECRET)} chars\n")

# Instanciamos o cliente oficial (que já tem retries e HMAC embutidos)
client = VanaClient(api_url=API_URL, secret=SECRET)

def test_ping():
    """Testa healthcheck"""
    print("="*60)
    print("🏥 Testando /ping")
    
    ping_url = API_URL.replace("/ingest", "/ping")
    
    try:
        r = client.session.get(ping_url, timeout=10)
        
        # Se for 404, pode ser porque o /ping não está implementado na nova API V1. 
        # Não bloqueamos a execução por causa disso.
        if r.status_code == 404:
            print("   ⚠️ Endpoint /ping não encontrado. A saltar verificação...")
            return True

        data = r.json()
        print(f"   Status: {r.status_code}")
        print(f"   OK: {data.get('ok')}")
        return data.get('ok', False)
        
    except Exception as e:
        print(f"   ⚠️ Aviso no /ping: {e}")
        # Retornamos True para não bloquear o script se o site apenas não tiver a rota de ping
        return True

def load_payload(json_file: Path) -> dict:
    """Carrega payload de um arquivo JSON"""
    with open(json_file, 'r', encoding='utf-8') as f:
        payload = json.load(f)
    return payload

def send_ingest(payload: dict, filename: str):
    """Envia payload para WordPress usando o VanaClient"""
    print("="*60)
    print(f"🚀 Enviando ingest: {filename}")
    
    kind = payload.get("kind", "Desconhecido")
    origin_key = payload.get("origin_key", "Desconhecido")
    
    print(f"   Tipo: {kind.upper()}")
    print(f"   Origin Key: {origin_key}")
    print(f"   Título: {payload.get('title', 'Sem título')}")
    
    # O client já faz o dump determinístico e assina o payload
    payload_bytes = client._dumps_deterministic(payload)
    
    print("\n⏳ A comunicar com o servidor...")
    response = client.send_raw(payload_bytes)
    
    print(f"📊 Resposta do Servidor:")
    print(json.dumps(response, indent=2, ensure_ascii=False))
    
    if response.get("success") or response.get("ok"):
        print("\n✅ SUCESSO ABSOLUTO!")
        if "data" in response and "permalink" in response["data"]:
            print(f"🌐 Acesse: {response['data']['permalink']}")
    else:
        print("\n❌ FALHA NA INGESTÃO.")

def main():
    """Função principal"""
    print("\n🚜 VANA TRATOR UNIVERSAL - Sistema de Ingestão\n")
    
    # 1. Healthcheck
    test_ping()
    
    # 2. Processa argumentos ou modo interativo
    payloads_dir = Path(__file__).parent / "payloads" # Mudamos de 'tours' para 'payloads' (serve para tudo)
    
    if len(sys.argv) > 1:
        # Modo Automático: python main.py meu_arquivo.json
        file_path = Path(sys.argv[1])
        if not file_path.is_absolute():
            file_path = payloads_dir / file_path
    else:
        # Modo Interativo
        if not payloads_dir.exists():
            print(f"📂 Criando diretório {payloads_dir.name}/")
            payloads_dir.mkdir()
            print("   Adicione arquivos JSON neste diretório e corra o script novamente.")
            sys.exit(0)
        
        json_files = list(payloads_dir.glob("*.json"))
        
        if not json_files:
            print(f"❌ Nenhum ficheiro JSON encontrado na pasta {payloads_dir.name}/")
            sys.exit(1)
        
        print("📋 Payloads disponíveis para envio:\n")
        for i, f in enumerate(json_files, 1):
            print(f"   {i}. {f.name}")
        
        try:
            choice = int(input("\nEscolha um ficheiro (número): "))
            file_path = json_files[choice - 1]
        except (ValueError, IndexError):
            print("❌ Escolha inválida")
            sys.exit(1)
    
    # 3. Carrega e envia
    try:
        if not file_path.exists():
            print(f"❌ Ficheiro não encontrado: {file_path}")
            sys.exit(1)
            
        payload = load_payload(file_path)
        
        # Validação básica de envelope
        if "kind" not in payload or "origin_key" not in payload or "data" not in payload:
            print("❌ ERRO: O JSON deve ter a estrutura de Envelope (kind, origin_key, data).")
            sys.exit(1)
            
        send_ingest(payload, file_path.name)
        
    except json.JSONDecodeError as e:
        print(f"❌ JSON inválido: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()