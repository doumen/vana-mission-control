# 🪷 Vana Mission Control
**Plugin WordPress — v4.2.4**

Sistema de gestão automatizada de Tours, Visits e Hari-katha para a missão de Śrīla Bhaktivedanta Vana Goswami Maharaj.

---

## 📋 Sumário

- [Visão Geral](#visão-geral)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração de Segurança](#configuração-de-segurança)
- [Arquitetura do Plugin](#arquitetura-do-plugin)
- [Custom Post Types (CPTs)](#custom-post-types-cpts)
- [API REST](#api-rest)
- [Identidade Visual](#identidade-visual)
- [Desinstalação](#desinstalação)
- [Contribuindo](#contribuindo)

---

## 🌐 Visão Geral

O **Vana Mission Control** é o núcleo técnico do ecossistema digital da missão [@vanamadhuryamofficial](https://www.youtube.com/@vanamadhuryamofficial). Ele integra:

- **Tours:** Estrutura hierárquica principal de peregrinações e viagens de missão.
- **Visits:** Diários de missão com GPS, aulas e linha do tempo (*timeline*) por dia.
- **Submissions (Oferendas):** Sistema de recebimento de mensagens, fotos e vídeos dos devotos, com moderação antes da publicação.
- **Ingest API:** Endpoint autenticado via HMAC para ingestão automatizada de dados a partir de scripts externos (Python/Trator).
- **Check-in API:** Endpoint público (com proteção anti-spam e rate limiting) para envio de oferendas pelos devotos.

---

## ✅ Requisitos

| Dependência  | Versão Mínima |
|--------------|---------------|
| PHP          | 8.0+          |
| WordPress    | 6.0+          |
| MySQL/MariaDB | Compatível com WP 6.0 |

> O plugin verifica os requisitos automaticamente na ativação e exibe um aviso no painel Admin caso não sejam atendidos.

---

## 🚀 Instalação

1. Faça o upload da pasta `vana-mission-control` para `/wp-content/plugins/`.
2. Ative o plugin em **WordPress Admin → Plugins**.
3. Configure a chave secreta no `wp-config.php` (ver seção abaixo).
4. O plugin criará automaticamente a tabela `wp_vana_origin_index` no banco de dados.

---

## 🔐 Configuração de Segurança

Adicione a seguinte constante ao seu `wp-config.php`, **antes** da linha `/* That's all, stop editing! */`:

```php
define('VANA_INGEST_SECRET', 'sua-chave-secreta-forte-aqui');
```

> ⚠️ **Esta chave é obrigatória.** Sem ela, a Ingest API recusará todas as requisições com erro `401`.  
> Use uma string aleatória de no mínimo 32 caracteres.

### Como a Segurança Funciona (HMAC)

A API de Ingestão usa autenticação criptográfica de 3 camadas:

| Camada | Mecanismo | Proteção |
|--------|-----------|----------|
| 1 | `HMAC-SHA256` | Garante que o payload não foi alterado |
| 2 | `vana_timestamp` (±5 min) | Previne **Replay Attacks** |
| 3 | `vana_nonce` | Unicidade de cada requisição |

---

## 🏗️ Arquitetura do Plugin

```
vana-mission-control/
│
├── vana-mission-control.php       # Bootstrap principal (constantes, hooks, ativação)
├── uninstall.php                  # Limpeza na desinstalação
│
├── includes/
│   ├── class-vana-utils.php       # Utilitários globais (log, sanitização, respostas)
│   ├── class-vana-index.php       # Tabela de índice de origin_keys
│   ├── class-vana-hmac.php        # Validação criptográfica HMAC
│   ├── class-vana-contract.php    # Contratos de schema
│   ├── class-vana-store.php       # Camada de persistência
│   ├── class-vana-tour-cpt.php    # CPT: Tour
│   ├── class-vana-visit-cpt.php   # CPT: Visit
│   ├── class-vana-submission-cpt.php  # CPT: Oferendas
│   ├── class-vana-visit-materializer.php # Derivação automática de metadados
│   ├── cli/
│   │   └── class-vana-cli-backfill.php   # Comando WP-CLI para reprocessamento
│   └── rest/
│       └── class-vana-rest-backfill.php  # Endpoint REST de backfill
│
├── api/
│   ├── class-vana-ingest-api.php         # Roteador principal da Ingest API
│   ├── class-vana-ingest-visit-api.php   # Endpoint legado /ingest-visit
│   ├── class-vana-checkin-api.php        # Endpoint público de oferendas
│   └── handlers/
│       └── class-vana-ingest-visit.php   # Handler de upsert de Visits
│
├── templates/
│   ├── single-vana_tour.php       # Template de Tour individual
│   ├── archive-vana_tour.php      # Template de listagem de Tours
│   ├── single-vana_visit.php      # Template de Visit individual
│   └── archive-vana_visit.php     # Template de listagem de Visits
│
└── assets/
    └── css/
        ├── vana-ui.tokens.css         # Design tokens (cores, espaçamentos, fontes)
        ├── vana-ui.components.css     # Componentes reutilizáveis
        ├── vana-ui.hierarchy.css      # Layout de Tours e arquivos
        ├── vana-ui.visit-hub.css      # Layout do hub de Visits
        └── vana-ui.astra-bridge.css   # Compatibilidade com tema Astra
```

---

## 📦 Custom Post Types (CPTs)

### `vana_tour`
Representa uma viagem ou ciclo de missão.

| Meta Key | Descrição |
|----------|-----------|
| `_vana_origin_key` | Chave única de origem (`tour:slug`) |
| `_tour_is_current` | Se é a tour ativa no momento |
| `_vana_last_visit_id` | ID da Visita mais recente |
| `_vana_current_visit_id` | ID da Visita atual (se tour ativa) |

### `vana_visit`
Diário de missão com linha do tempo diária.

| Meta Key | Descrição |
|----------|-----------|
| `_vana_origin_key` | Chave única de origem (`visit:slug`) |
| `_vana_parent_tour_origin_key` | Tour pai (`tour:slug`) |
| `_vana_visit_timeline_json` | JSON completo da timeline (schema `3.1`) |
| `_vana_timeline_hash` | SHA-256 do JSON (controle de mudanças) |
| `_vana_timeline_updated_at` | Timestamp da última atualização |

### `vana_submission` (Oferendas)
Mensagens, fotos e vídeos enviados pelos devotos.

| Meta Key | Descrição |
|----------|-----------|
| `_visit_id` | ID da Visit associada |
| `_sender_display_name` | Nome do devoto |
| `_message` | Mensagem de texto |
| `_image_url` | URL da imagem enviada |
| `_external_url` | Link de vídeo (YouTube, Drive, Facebook) |
| `_submitted_at` | Unix timestamp do envio |
| `_consent_publish` | Consentimento de publicação (`1`) |

> As oferendas entram com status `pending` e devem ser aprovadas manualmente pelo administrador.

---

## 🔌 API REST

### `POST /wp-json/vana/v1/ingest`
**Autenticação:** HMAC obrigatória via query params.

Parâmetros de autenticação (na URL):

```
?vana_signature=<hmac-sha256>
&vana_timestamp=<unix-timestamp>
&vana_nonce=<string-aleatória>
```

**Corpo (JSON) — Kind `visit`:**

```json
{
  "kind": "visit",
  "origin_key": "visit:minha-visita-slug",
  "parent_origin_key": "tour:minha-tour-slug",
  "title": "Título da Visita",
  "slug_suggestion": "minha-visita-slug",
  "data": {
    "schema_version": "3.1",
    "updated_at": "2026-02-21T12:00:00Z",
    "days": [ ... ]
  }
}
```

**Respostas:**

| Status | Significado |
|--------|-------------|
| `201` | Visita criada com sucesso |
| `200` | Visita atualizada (ou `noop` se sem mudanças) |
| `401` | Assinatura HMAC inválida |
| `409` | Requisição concorrente (lock ativo) |
| `422` | Payload inválido (schema, prefixos, etc.) |

---

### `POST /wp-json/vana/v1/checkin`
**Autenticação:** Pública (protegida com Rate Limiting + Honeypot anti-spam).

Aceita `multipart/form-data`:

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `visit_id` | `int` | ✅ | ID do post `vana_visit` |
| `consent_publish` | `int` (=1) | ✅ | Consentimento de publicação |
| `sender_name` | `string` | ❌ | Nome do devoto |
| `message` | `string` | ❌* | Mensagem |
| `image` | `file` | ❌* | Imagem (JPG/PNG/WEBP, máx. 5MB) |
| `external_url` | `string` | ❌* | Link YouTube, Drive ou Facebook |
| `website` | `string` | 🍯 | **Honeypot** — deve ficar vazio |

> *Pelo menos um dos campos `message`, `image` ou `external_url` é obrigatório.

**Rate Limiting:** Máximo de **6 envios por IP** a cada 30 minutos por Visita.

---

## 🎨 Identidade Visual

O plugin usa um sistema de Design Tokens CSS próprio, com as seguintes cores da missão:

| Token | Valor | Uso |
|-------|-------|-----|
| `--vana-gold` | `#FDD80D` | Cor principal (botões, badges, destaques) |
| `--vana-gold-deep` | `#D4AF37` | Bordas e hovers dourados |
| `--vana-blue` | `#4AA3FF` | Títulos de cards |
| `--vana-text` | `#1A202C` | Texto principal |
| `--vana-muted` | `#4A5568` | Texto secundário |

**Tipografia:**
- Títulos: `Syne` (700) — via Google Fonts
- Corpo: `Questrial` — via Google Fonts

---

## 🗑️ Desinstalação

Ao desinstalar o plugin via WordPress Admin, as seguintes ações ocorrem **automaticamente**:

- ✅ Tabela `wp_vana_origin_index` removida
- ✅ Options `vana_auto_publish`, `vana_rate_limit` e `vana_mc_db_version` removidas
- ⚠️ **Os posts** de Tours, Visits e Oferendas **são preservados** por padrão

> Para remover também os posts, edite `uninstall.php` e descomente o bloco indicado. **Ação irreversível.**

---

## 🚜 Trator (Ingest Client Python)

O **Trator** é o cliente Python responsável por serializar, assinar e enviar
payloads de Tours e Visits para a API REST do WordPress.

### 📁 Estrutura

```
trator/
├── client.py          # Cliente HTTP com HMAC, retries e serialização
├── main.py            # CLI universal (modo interativo ou automático)
├── ingest_visit.py    # CLI dedicado para ingestão de Visits
├── smoke_test.py      # Suite de testes de contrato da API
├── test_geo_visit.py  # Teste de ingestão com geolocalização
└── payloads/          # Pasta de JSONs prontos para envio
    └── *.json
```

### ⚙️ Instalação

```bash
# 1. Crie e ative o ambiente virtual
python -m venv .venv
source .venv/bin/activate      # Linux/macOS
.venv\Scripts\activate         # Windows

# 2. Instale as dependências
pip install requests python-dotenv

# 3. Configure o .env
cp .env.example .env
```

### 🔑 Variáveis de Ambiente (`.env`)

```env
# URL do endpoint de ingestão (sem trailing slash)
VANA_API_URL=https://seu-site.com/wp-json/vana/v1/ingest

# Mesma chave definida no wp-config.php como VANA_INGEST_SECRET
VANA_SECRET=sua-chave-secreta-forte-aqui
```

> As variáveis legadas `WP_API_URL` e `VANA_INGEST_SECRET` também são aceitas
> como fallback em `main.py`.

---

### 🖥️ Modos de Uso

#### Modo Interativo (`main.py`)
Lista os JSONs da pasta `payloads/` e solicita escolha:

```bash
python main.py
```

```
📋 Payloads disponíveis para envio:
   1. india_2026_vrindavan_01.json
   2. tour_india_2026.json

Escolha um ficheiro (número): 1
```

#### Modo Automático (`main.py`)
Passa o arquivo diretamente como argumento:

```bash
python main.py payloads/india_2026_vrindavan_01.json
```

#### CLI Dedicado de Visits (`ingest_visit.py`)
Ideal para scripts e pipelines automatizados:

```bash
python ingest_visit.py caminho/para/visit_data.json \
  --origin "visit:india_2026:vrindavan_01" \
  --parent "tour:india_2026" \
  --title  "Dia 1 — Vrindavan"
```

| Argumento  | Obrigatório | Exemplo                            |
|------------|-------------|------------------------------------|
| `json_file`| ✅          | `payloads/vrindavan.json`          |
| `--origin` | ✅          | `visit:india_2026:vrindavan_01`    |
| `--parent` | ✅          | `tour:india_2026`                  |
| `--title`  | ✅          | `"Dia 1 — Vrindavan"`              |

---

### 📦 Estrutura do Payload JSON

Todo payload deve seguir o **Envelope de Ingestão**:

```json
{
  "kind": "visit",
  "origin_key": "visit:india_2026:vrindavan_01",
  "parent_origin_key": "tour:india_2026",
  "title": "Dia 1 — Vrindavan",
  "slug_suggestion": "dia-1-vrindavan",
  "data": {
    "schema_version": "3.1",
    "updated_at": "2026-02-21T12:00:00Z",
    "location_meta": {
      "city_ref": "Śrī Vṛndāvana Dhāma, IN",
      "lat": 27.5706,
      "lng": 77.6911
    },
    "days": [
      {
        "date_local": "2026-02-21",
        "hero": {
          "title_pt": "Aula Principal",
          "title_en": "Main Class",
          "provider": "youtube",
          "video_id": "VIDEO_ID_AQUI",
          "location": {
            "name": "Templo Radha Damodara",
            "lat": 27.5815,
            "lng": 77.6997
          }
        },
        "vod": [
          {
            "title_pt": "Parikrama",
            "provider": "drive",
            "url": "https://drive.google.com/file/d/ID/preview",
            "location": {
              "name": "Mānasi-gaṅgā, Govardhana",
              "lat": 27.4988,
              "lng": 77.4649
            }
          }
        ]
      }
    ]
  }
}
```

> ⚠️ `schema_version` **deve** ser `"3.1"`. Qualquer outro valor retorna `422`.

---

### 🔐 Como o HMAC Funciona no Trator

O `VanaClient` assina cada requisição automaticamente em `_sign()`:

```
mensagem = f"{timestamp}\n{nonce}\n" + payload_bytes
assinatura = HMAC-SHA256(secret, mensagem)
```

Os parâmetros `vana_timestamp`, `vana_nonce` e `vana_signature` são enviados
como **query params** na URL. Redirects são bloqueados para evitar quebra da
assinatura.

**Política de Retry automático:**

| Código HTTP | Comportamento        |
|-------------|----------------------|
| `409`       | Retry (até 3x)       |
| `500–504`   | Retry (até 3x)       |
| `401`, `422`| Sem retry (falha imediata) |

---

### 🧪 Testes

#### Smoke Test (Contrato da API)
Valida 9 cenários contra o servidor real:

```bash
python smoke_test.py
```

| Teste | Cenário                     | HTTP Esperado |
|-------|-----------------------------|---------------|
| 1     | Criação/Atualização OK      | `201` / `200` |
| 2     | Assinatura inválida         | `401`         |
| 3     | Timestamp expirado          | `401`         |
| 4     | JSON truncado               | `400`         |
| 5     | `parent_origin_key` ausente | `422`         |
| 6     | `kind` inválido             | `422`         |
| 7     | `schema_version` errada     | `422`         |
| 8     | Payload > 3MB               | `413`         |
| 9     | Lock concorrente (2 threads)| `409` (provável) |

#### Teste de Geolocalização
Envia uma Visit completa com GPS em hero e VOD:

```bash
python test_geo_visit.py
```

---

## 🤖 Vana Bot (Telegram)

O **Vana Bot** é o painel de controle em tempo real da missão via Telegram.
Permite que devotos autorizados controlem o estado da transmissão ao vivo
diretamente pelo celular, sem acessar o painel WordPress.

### 📁 Estrutura

```
vana-bot/
├── vana_bot.py           # Bot principal (Telegram)
├── smoke_live_update.py  # Teste de smoke do endpoint /schedule-live-update
├── context.json          # Contexto persistido (gerado automaticamente)
└── .env                  # Variáveis de ambiente
```

### ⚙️ Instalação

```bash
pip install python-telegram-bot requests python-dotenv
```

### 🔑 Variáveis de Ambiente (`.env`)

```env
# Token do Bot (obtido via @BotFather no Telegram)
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...

# URL base do WordPress (sem trailing slash)
WP_BASE=https://seu-site.com

# Chave HMAC para o endpoint /schedule-live-update
VANA_HMAC_SECRET=sua-chave-hmac-aqui

# IDs Telegram dos usuários autorizados (separados por vírgula)
# Deixe vazio para permitir TODOS (não recomendado em produção)
AUTHORIZED_USERS=123456789,987654321

# Contexto padrão (sobrescrito por /setcontext)
DEFAULT_VISIT_ID=0
DEFAULT_DATE_LOCAL=2026-02-21
DEFAULT_EVENT_ID=hero

# Arquivo de persistência do contexto
CONTEXT_FILE=context.json
```

### 🚀 Iniciar o Bot

```bash
python vana_bot.py
```

```
✅ Contexto carregado: visit_id=1234 date_local=2026-02-21 event_id=hero
🚀 Bot Vana Mission Control iniciado (Vrindavan 1.0).
```

---

### 📟 Comandos Disponíveis

| Comando | Descrição |
|---------|-----------|
| `/ops` | Abre o **Painel de Controle** com botões de ação |
| `/context` | Exibe o contexto ativo (Visit ID, data, evento) |
| `/setcontext VISIT_ID DATA [EVENT_ID]` | Define o destino das ações |

#### Exemplo de `/setcontext`
```
/setcontext 1234 2026-02-21 hero
/setcontext 1234 2026-02-21 stage_main
```

---

### 🎛️ Painel `/ops`

O comando `/ops` exibe um teclado inline com ações imediatas:

```
┌──────────────────┬──────────────────┐
│  🔴 Ao vivo      │  ⏳ Atrasar       │
├──────────────────┼──────────────────┤
│  ✅ Encerrar     │  🚫 Cancelar      │
├──────────────────┼──────────────────┤
│  🟢 Agendado     │  🧹 Limpar Alerta │
└──────────────────┴──────────────────┘
```

Cada botão dispara um `set_status` na Visit/Evento do contexto ativo.

---

### 🔗 Detecção Automática de Links (Grupo)

Quando o bot é **mencionado** (`@vana_bot`) ou **respondido** em um grupo,
ele detecta automaticamente o tipo de mensagem:

#### 📺 Link de Vídeo (YouTube ou Facebook)
```
@vana_bot https://youtu.be/M7lc1UVf-VE
```
→ Exibe botão: **"📺 Colocar YouTube na Home"**
→ Aplica `set_stream` na Visit ativa ao confirmar.

#### 🔔 Texto de Alerta
```
@vana_bot Mangala Arati em 10 minutos!
```
→ Exibe 3 botões para escolher o tipo:

```
[ 🔵 Info ]  [ ⚠️ Warning ]  [ 🔴 Error ]
```

---

### 🔐 Segurança do Bot

O bot usa um esquema HMAC diferente do Trator (via **HTTP Headers**):

```
mensagem = timestamp_bytes + b"." + body_bytes
assinatura = HMAC-SHA256(VANA_HMAC_SECRET, mensagem)
```

Os headers enviados ao WordPress são:

```http
X-Vana-Timestamp: 1740145462
X-Vana-Signature: a3f9b2c1...
Content-Type: application/json
```

**Cache de tokens (segurança anti-flood):**

| Parâmetro | Padrão | Descrição |
|-----------|--------|-----------|
| `SAFE_CACHE_TTL_SEC` | `600` | TTL dos tokens em segundos |
| `SAFE_CACHE_MAX` | `2000` | Máximo de tokens em memória |

Links e textos de alerta são armazenados como tokens temporários para evitar
que dados sensíveis fiquem expostos nos `callback_data` do Telegram.

---

### 🧪 Smoke Test do Bot

Testa o endpoint `/schedule-live-update` sem precisar do Telegram:

```bash
# set_status
python smoke_live_update.py \
  --wp-base https://seu-site.com \
  --secret sua-chave-hmac \
  --visit-id 1234 \
  --date-local 2026-02-21 \
  --action set_status \
  --status live

# set_stream (YouTube)
python smoke_live_update.py \
  --action set_stream \
  --youtube-id M7lc1UVf-VE \
  --visit-id 1234 --date-local 2026-02-21

# set_alert
python smoke_live_update.py \
  --action set_alert \
  --alert-type warning \
  --alert-message "Mangala Arati em 10 min." \
  --visit-id 1234 --date-local 2026-02-21
```

---

## 🔄 Fluxo Completo de Integração

```
┌─────────────────────────────────────────────────────────┐
│                    PRODUÇÃO DE CONTEÚDO                 │
│                                                         │
│  📹 YouTube  →  Gravação/Live  →  JSON de Timeline      │
└─────────────────────────┬───────────────────────────────┘
                          │ (arquivo .json)
                          ▼
┌─────────────────────────────────────────────────────────┐
│                   🚜 TRATOR (Python)                    │
│                                                         │
│  main.py / ingest_visit.py                              │
│  1. Lê o JSON da pasta payloads/                        │
│  2. Serializa de forma determinística                   │
│  3. Assina com HMAC-SHA256 (timestamp + nonce)          │
│  4. Envia para POST /vana/v1/ingest                     │
└─────────────────────────┬───────────────────────────────┘
                          │ (HTTPS + HMAC)
                          ▼
┌─────────────────────────────────────────────────────────┐
│              🔌 WORDPRESS (Plugin)                      │
│                                                         │
│  Ingest API → Valida HMAC → Upsert vana_visit           │
│  → Materializer → Atualiza Tour pai                     │
│  → Publica permalink                                    │
└─────────────────────────┬───────────────────────────────┘
                          │ (em tempo real)
                          ▼
┌─────────────────────────────────────────────────────────┐
│              🤖 VANA BOT (Telegram)                     │
│                                                         │
│  /setcontext 1234 2026-02-21 hero                       │
│  /ops → [🔴 Ao vivo]  →  POST /schedule-live-update    │
│                                                         │
│  @bot https://youtu.be/ID  →  [📺 Colocar na Home]     │
│  @bot "Mangala Arati em 10min" → [⚠️ Warning]          │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
               👥 Devotos no Site
          (Visit Hub com live, mapa GPS,
           playlist de aulas e oferendas)
```

---

## 📋 Checklist de Deploy Completo

### WordPress (Plugin)
- [ ] PHP 8.0+ e WordPress 6.0+ confirmados
- [ ] Plugin ativado e tabela `wp_vana_origin_index` criada
- [ ] `VANA_INGEST_SECRET` definido no `wp-config.php`
- [ ] Endpoint `POST /wp-json/vana/v1/ingest` respondendo

### Trator (Python)
- [ ] `.env` configurado com `VANA_API_URL` e `VANA_SECRET`
- [ ] `python smoke_test.py` — todos os 8 testes passando ✅
- [ ] Pasta `payloads/` criada com JSONs de teste

### Vana Bot (Telegram)
- [ ] Bot criado via `@BotFather` e token obtido
- [ ] `.env` configurado com `TELEGRAM_BOT_TOKEN`, `WP_BASE` e `VANA_HMAC_SECRET`
- [ ] `AUTHORIZED_USERS` configurado com IDs dos devotos administradores
- [ ] `python smoke_live_update.py` respondendo `HTTP 200` ✅
- [ ] `/setcontext` executado com Visit ID e data corretos
- [ ] `/ops` exibindo o painel de controle no grupo ✅

---
## 🤝 Contribuindo

Este plugin é parte da infraestrutura da missão **Vana Madhuryam**. Para contribuir:

1. Reporte bugs e sugestões via os canais oficiais da missão.
2. Siga o padrão de código PSR-12 e os guardrails de segurança já estabelecidos.
3. Toda nova rota REST deve usar `Vana_HMAC` para autenticação ou justificar explicitamente o acesso público.
4. Mantenha o `schema_version` atualizado em `class-vana-ingest-visit.php` ao modificar o schema de Visits.

---

## 🔗 Links Oficiais

| Canal | URL |
|-------|-----|
| YouTube | [@vanamadhuryamofficial](https://www.youtube.com/@vanamadhuryamofficial) |
| Facebook | [vanamadhuryamofficial](https://www.facebook.com/vanamadhuryamofficial) |
| Instagram | [@vanamadhuryamofficial](https://www.instagram.com/vanamadhuryamofficial/) |
| Site | [vanamadhuryam.org](https://vanamadhuryam.org) |

