# 📖 Gerando README.md para o Vana Mission Control

Analisando o código-fonte do plugin, Marcel. Aqui está o README completo:

---

```markdown
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

---

*Hare Krishna 🪷 — Śrī Guru Caraṇa Padma*
```

---

## 📝 Notas de Uso

Marcel, ficam dois pontos de atenção que identifiquei no código durante a geração do README:

> 🐛 **Bug detectado** em `class-vana-ingest-visit.php` (linha ~120):
> ```php
> // Typo: $schema_versio (faltando o 'n')
> update_post_meta($visit_id, '_vana_timeline_schema_version', $schema_versio);
> ```
> Corrija para `$schema_version`.

> 💡 **Sugestão:** O `delete_transient('vana_chronological_sequence')` aparece **duas vezes** no mesmo método `upsert()`. A primeira ocorrência (antes da materialização) pode ser removida com segurança.