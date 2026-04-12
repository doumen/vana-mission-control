# 🚜 Vana Trator

Conjunto de scripts Python para **ingestão e processamento de conteúdo** da missão Vana Madhuryam Daily.  
Envia tours, visitas e kathas para o WordPress via API HMAC autenticada, além de completar traduções automaticamente com Gemini.

---

## Scripts Disponíveis

| Script | Descrição |
|---|---|
| `main.py` | CLI universal — ingere qualquer payload da pasta `payloads/` |
| `ingest_visit.py` | Ingesta uma visita (schema 3.1) com `origin_key` e tour pai |
| `ingest_katha.py` | Ingesta uma katha (schema 3.2) no endpoint dedicado |
| `complete_katha.py` | Completa os campos `_en` de um JSON de katha via Gemini |
| `preview_katha.py` | Visualiza no terminal os campos `_en` de um JSON completado |
| `smoke_test.py` | Suite de testes do endpoint `ingest-visit` (auth, schema, tamanho, etc.) |
| `test_geo_visit.py` | Testa ingestão de visita com geolocalização |
| `vapid-gen.py` | Gera par de chaves VAPID para Web Push |

---

## Estrutura

```
trator/
├── main.py                  # CLI universal de ingestão
├── client.py                # VanaClient — HMAC, retries, serialização
├── vana_hmac_signer.py      # Implementação alternativa do assinador HMAC
├── ingest_visit.py          # Ingesta visita (schema 3.1)
├── ingest_katha.py          # Ingesta katha (schema 3.2)
├── complete_katha.py        # Tradução automática PT → EN via Gemini
├── preview_katha.py         # Preview dos campos _en no terminal
├── smoke_test.py            # Suite de testes do endpoint
├── test_geo_visit.py        # Teste de visita com geolocalização
├── vapid-gen.py             # Gerador de chaves VAPID
│
└── payloads/
    ├── GUIA_CAMPOS.md       # Referência rápida de campos do schema
    ├── visit-sample.json    # Exemplo completo de visita (schema 2.6/3.1)
    ├── 1_tour_india_2026.json
    ├── 2_visit_vrindavan_dia1.json
    └── ...                  # Outros payloads reais
```

---

## Pré-requisitos

- Python 3.11+
- Dependências:

```bash
pip install requests python-dotenv google-generativeai
```

Para geração de chaves VAPID (`vapid-gen.py`):
```bash
pip install py_vapid cryptography
```

---

## Configuração

Crie um arquivo `.env` na pasta `trator/`:

```env
# Endpoint principal de ingestão (visitas e tours)
VANA_API_URL=https://beta.vanamadhuryamdaily.com/wp-json/vana/v1/ingest-visit

# Endpoint de ingestão de kathas
VANA_API_KATHA_URL=https://beta.vanamadhuryamdaily.com/wp-json/vana/v1/ingest-katha

# Segredo HMAC (deve corresponder ao VANA_INGEST_SECRET no wp-config.php)
VANA_SECRET=sua_chave_hmac_aqui

# Chave Gemini (necessária apenas para complete_katha.py)
GEMINI_API_KEY=sua_chave_gemini_aqui
```

---

## Uso

### Ingestão universal (main.py)

```bash
# Modo interativo — lista os JSONs em payloads/ e aguarda escolha
python main.py

# Modo direto — passa o nome do arquivo
python main.py 1_tour_india_2026.json

# Caminho absoluto
python main.py C:\caminho\completo\meu_payload.json
```

O payload deve seguir o envelope padrão:
```json
{
  "kind": "tour|visit|submission",
  "origin_key": "tour:india-2026",
  "data": { ... }
}
```

### Ingerir uma visita (ingest_visit.py)

```bash
python ingest_visit.py payloads/2_visit_vrindavan_dia1.json \
  --origin visit:india_2026:vrindavan_01 \
  --parent tour:india_2026 \
  --title "Dia 1 - A Chegada a Vṛndāvana"
```

### Ingerir uma katha (ingest_katha.py)

```bash
python ingest_katha.py payloads/minha_katha.json
```

O JSON deve conter `schema_version: "3.2"` e `context.katha_ref`.

### Completar traduções com Gemini (complete_katha.py)

```bash
# Completa todos os campos _en ausentes
python complete_katha.py payloads/minha_katha.json

# Força reescrita dos campos _en já preenchidos
python complete_katha.py payloads/minha_katha.json --force

# Simula sem salvar (mostra diff)
python complete_katha.py payloads/minha_katha.json --dry-run

# Processa apenas uma seção
python complete_katha.py payloads/minha_katha.json --only passages

# Salva em arquivo diferente
python complete_katha.py payloads/minha_katha.json -o payloads/minha_katha_complete.json
```

Seções disponíveis para `--only`: `lecture`, `verses`, `glossary`, `passages`.

### Visualizar traduções (preview_katha.py)

```bash
# Preview completo
python preview_katha.py payloads/minha_katha_complete.json

# Apenas uma seção
python preview_katha.py payloads/minha_katha_complete.json --section passages
```

### Smoke test do endpoint

```bash
python smoke_test.py
```

Executa 9 cenários: sucesso (create/update), falha de assinatura, timestamp expirado, JSON inválido, envelope incompleto, kind inválido, schema errado, payload grande demais e lock concorrente.

---

## Schemas de Payload

### Visita (schema 3.1)

Campos obrigatórios:

| Campo | Nível | Descrição |
|---|---|---|
| `visit_id` | raiz | Usado na URL e nos IDs HTML |
| `timezone` | raiz | Ex: `Asia/Kolkata`, `America/Sao_Paulo` |
| `days[].date_local` | dia | Formato `YYYY-MM-DD` |
| `days[].hero` | dia | Pelo menos uma fonte de mídia (`youtube_url`, `facebook_url`, `instagram_url` ou `drive_url`) |
| `days[].schedule[].status` | item | `done` / `live` / `upcoming` / `break` / `optional` |

### Katha (schema 3.2)

Estrutura principal:

| Seção | Descrição |
|---|---|
| `context` | Referências cruzadas (`katha_ref`, `visit_ref`, `day_key`) |
| `lecture` | Título, excerpt, summary e taxonomias |
| `passages[]` | Trechos numerados com `hook`, `key_quote`, `content` e timestamps |
| `verses_cited[]` | Versos śāstricos com transliteração e tradução |
| `glossary[]` | Termos sânscritos com definições curtas e completas |

Campos `_en` (ex: `title_en`, `content_en`) são gerados automaticamente pelo `complete_katha.py`.

---

## Autenticação HMAC

O `VanaClient` (`client.py`) implementa HMAC-SHA256 espelhando o `class-vana-hmac.php`:

```
mensagem = f"{timestamp}\n{nonce}\n{raw_body}"
assinatura = HMAC-SHA256(mensagem, VANA_SECRET)
```

Os parâmetros `vana_timestamp`, `vana_nonce` e `vana_signature` são enviados como query string. A janela de validade é de ±5 minutos e cada nonce é queimado após uso (anti-replay).

Erros comuns:

| HTTP | Causa |
|---|---|
| `401` | Assinatura inválida, timestamp expirado ou nonce reutilizado |
| `400` | JSON malformado |
| `413` | Payload maior que 3 MB |
| `422` | Envelope inválido (kind errado, `parent_origin_key` ausente, schema incorreto) |

---

## Segurança

- Nunca commite o arquivo `.env` — adicione-o ao `.gitignore`.
- O `VANA_SECRET` é o mesmo valor definido como `VANA_INGEST_SECRET` no `wp-config.php` do WordPress.
- Redirects HTTP são bloqueados pelo cliente para evitar mascarar quebra de autenticação.
