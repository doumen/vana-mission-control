import os
import sys
import json
import argparse
from dotenv import load_dotenv
from client import VanaClient

def main():
    # Configurar argumentos da linha de comandos
    parser = argparse.ArgumentParser(description="🚜 Trator de Ingestão de Visitas Vana")
    parser.add_argument("json_file", help="Caminho para o ficheiro JSON contendo os dados da visita (o campo 'data')")
    parser.add_argument("--origin", required=True, help="Origin Key da visita (ex: visit:india_2026:vrindavan_01)")
    parser.add_argument("--parent", required=True, help="Origin Key da Tour pai (ex: tour:india_2026)")
    parser.add_argument("--title", required=True, help="Título da visita no WordPress (ex: 'Dia 1 - Vrindavan')")
    
    args = parser.parse_args()

    # 1. Carregar Variáveis de Ambiente
    load_dotenv()
    api_url = os.getenv("VANA_API_URL")
    secret = os.getenv("VANA_SECRET")

    if not api_url or not secret:
        print("❌ ERRO: Variáveis VANA_API_URL ou VANA_SECRET não encontradas no .env")
        sys.exit(1)

    # 2. Ler o ficheiro JSON
    if not os.path.isfile(args.json_file):
        print(f"❌ ERRO: Ficheiro '{args.json_file}' não encontrado.")
        sys.exit(1)

    try:
        with open(args.json_file, 'r', encoding='utf-8') as f:
            visit_data = json.load(f)
    except json.JSONDecodeError as e:
        print(f"❌ ERRO: O ficheiro '{args.json_file}' tem um JSON inválido.\nDetalhes: {e}")
        sys.exit(1)

    # 3. Montar o Envelope de Ingestão
    payload = {
        "kind": "visit",
        "origin_key": args.origin,
        "parent_origin_key": args.parent,
        "title": args.title,
        "data": visit_data  # O conteúdo do JSON entra aqui!
    }

    print(f"🚜 Iniciando Ingestão...")
    print(f"📌 Origin Key: {args.origin}")
    print(f"📁 Ficheiro lido: {args.json_file}")
    print(f"🌍 Destino: {api_url}")

    # 4. Enviar para a API
    client = VanaClient(api_url=api_url, secret=secret)
    
    try:
        payload_bytes = client._dumps_deterministic(payload)
        response = client.send_raw(payload_bytes)
        
        # 5. Analisar a Resposta
        if response.get("success"):
            print(f"\n✅ SUCESSO! Visita ingerida com perfeição.")
            print(f"🔗 ID da Visita no WP: {response.get('data', {}).get('visit_id')}")
            print(f"📝 Título: {args.title}")
        else:
            print(f"\n❌ FALHA NA INGESTÃO.")
            print("Resposta da API:")
            print(json.dumps(response, indent=2, ensure_ascii=False))
            
    except Exception as e:
        print(f"\n❌ ERRO DE CONEXÃO OU EXECUÇÃO: {e}")

if __name__ == "__main__":
    main()