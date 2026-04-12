import os
import time
import json
from client import VanaClient
from dotenv import load_dotenv

def main():
    # 1. Carregar Variáveis de Ambiente
    load_dotenv()
    api_url = os.getenv("VANA_API_URL")
    secret = os.getenv("VANA_SECRET")

    if not api_url or not secret:
        print("❌ ERRO: VANA_API_URL ou VANA_SECRET ausentes no .env")
        return

    client = VanaClient(api_url=api_url, secret=secret)
    ts = int(time.time())

    # ==========================================
    # O JSON DA VISITA COM GEOLOCALIZAÇÃO MÁXIMA
    # ==========================================
    visit_data = {
        "schema_version": "3.1", # Mantido 3.1 para passar no seu backend
        "updated_at": "2026-02-18T12:00:00Z",
        
        # 1. LOCALIZAÇÃO BASE (Usada como fallback se as aulas não tiverem GPS exato)
        "location_meta": {
            "city_ref": "Śrī Vṛndāvana Dhāma, IN",
            "lat": 27.5706,
            "lng": 77.6911
        },
        
        "days": [
            {
                "date_local": "2026-02-18",
                
                # 2. AULA PRINCIPAL (HERO) COM LOCALIZAÇÃO ESPECÍFICA
                "hero": {
                    "title_pt": "Aula Principal em Radha Damodara",
                    "title_en": "Main Class at Radha Damodara",
                    "description_pt": "Nesta aula vemos a localização exata do Templo Radha Damodara no mapa.",
                    "provider": "youtube",
                    "video_id": "dQw4w9WgXcQ",
                    
                    # O Mapa 2-Click vai ler daqui quando esta aula for a ativa!
                    "location": {
                        "name": "Templo Sri Sri Radha Damodara",
                        "lat": 27.5815,
                        "lng": 77.6997
                    }
                },
                
                # 3. LISTA DE AULAS EXTRAS (VOD) TAMBÉM COM LOCALIZAÇÕES
                "vods": [
                    {
                        "title_pt": "Parikrama em Manasi Ganga",
                        "title_en": "Parikrama at Manasi Ganga",
                        "provider": "drive",
                        "url": "https://drive.google.com/file/d/1A2B3C4D5E6F7G8H9I0J/preview",
                        
                        # Se o devoto clicar nesta aula no VOD, o mapa muda para Govardhana!
                        "location": {
                            "name": "Mānasi-gaṅgā, Govardhana",
                            "lat": 27.4988,
                            "lng": 77.4649
                        }
                    },
                    {
                        "title_pt": "Darshan em Varsana",
                        "title_en": "Darshan in Varsana",
                        "provider": "youtube",
                        "video_id": "dQw4w9WgXcQ",
                        
                        # Localização de Varsana
                        "location": {
                            "name": "Templo Sriji, Varsana",
                            "lat": 27.6528,
                            "lng": 77.3789
                        }
                    }
                ]
            }
        ]
    }

    # ==========================================
    # O ENVELOPE (Contrato de Ingestão)
    # ==========================================
    origin_key = f"visit:geo_test_{ts}"
    payload = {
        "kind": "visit", # Obrigatório
        "origin_key": origin_key, # Obrigatório e deve começar com visit:
        "parent_origin_key": "tour:smoke", # Obrigatório e deve começar com tour:
        "title": "Visita Geo Teste Total",
        "slug_suggestion": "visita-geo-teste",
        "data": visit_data
    }

    # Transforma em bytes (exigência do VanaClient)
    payload_bytes = client._dumps_deterministic(payload)
    
    print(f"🚀 Enviando Visita com Geolocalização: {origin_key}")
    
    # Envia para a API usando o método seguro
    response = client.send_raw(payload_bytes)
    
    print("\n📦 Resposta do Servidor:")
    print(json.dumps(response, indent=2, ensure_ascii=False))
    
    if response.get("success"):
        print(f"\n✅ SUCESSO! Visita com GPS injetada.")
        print(f"🔗 ID da Visita: {response.get('data', {}).get('visit_id')}")
        print("Vá ao Frontend desta visita. Verifique o mapa no topo. Clique nas aulas da playlist lateral e veja o nome/mapa mudar consoante a localização de cada aula!")
    else:
        print(f"\n❌ FALHA: Algo correu mal. Verifique se o secret e a URL estão corretos.")

if __name__ == "__main__":
    main()
