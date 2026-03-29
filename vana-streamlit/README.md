# 🪷 Vana Mission Control — Painel Streamlit

Painel de gestão interno da missão **Vana Madhuryam Daily**, construído com [Streamlit](https://streamlit.io/).  
Permite criar e editar tours, ingerir visitas, moderar oferendas dos devotos e conduzir o fluxo editorial da Revista.

---

## Funcionalidades

| Página | Descrição |
|---|---|
| 🗺️ **Tours** | Listar, criar e mover tours para lixeira |
| 📅 **Visits** | Criar, editar e ingerir visitas (schema 3.1) com validação completa |
| 🙏 **Oferendas** | Moderar submissions dos devotos por status (pendente / aprovada / rascunho / lixeira) |
| 📰 **Revista** | Fluxo editorial completo: edição de blocos, geração de PDF e publicação no WordPress |

O acesso é protegido por senha configurada nos secrets do Streamlit.

---

## Estrutura do Projeto

```
vana-streamlit/
├── app.py                    # Entry point + login guard
├── requirements.txt
├── ingest_tour.py            # CLI: cria/atualiza tour via REST
├── ingest_visit.py           # CLI: ingere visita via HMAC (schema 3.1)
│
├── api/
│   ├── hmac_client.py        # Requisições autenticadas por HMAC (/vana/v1/)
│   ├── wp_client.py          # WordPress REST API (Application Passwords)
│   └── github_client.py      # GitHub REST API v3 (visit.json / editorial.json)
│
├── components/
│   ├── days_editor.py        # Editor visual de dias de visita
│   └── block_editor.py       # Editor de blocos editoriais da Revista
│
├── pages/
│   ├── 1_Tours.py
│   ├── 2_Visits.py
│   ├── 3_Submissions.py
│   └── 4_Revista.py
│
├── services/
│   ├── wp_service.py         # Notificações de estado ao WordPress
│   ├── r2_service.py         # Upload de PDFs e capas para Cloudflare R2
│   └── pdf_service.py        # Geração de PDF com WeasyPrint + Jinja2
│
└── templates/
    └── revista/              # Templates HTML/CSS para geração da Revista em PDF
```

---

## Pré-requisitos

- Python 3.11+
- Acesso ao WordPress com Application Password
- Secret HMAC do endpoint `/vana/v1/`
- Token GitHub com permissão de leitura/escrita no repositório de conteúdo
- Bucket Cloudflare R2 (necessário apenas para a página Revista)

---

## Instalação

```bash
# Clone o repositório e entre na pasta
cd vana-streamlit

# Instale as dependências
pip install -r requirements.txt
```

Para a geração de PDFs (página Revista), instale também o WeasyPrint e suas dependências de sistema conforme a [documentação oficial](https://doc.courtbouillon.org/weasyprint/stable/first_steps.html).

---

## Configuração de Secrets

Crie o arquivo `.streamlit/secrets.toml` (não comitado via `.gitignore`):

```toml
[vana]
app_password   = "senha-do-painel"
api_base       = "https://seu-site.com/wp-json"
ingest_secret  = "seu-hmac-secret"

[wp]
user         = "usuario-wordpress"
app_password = "xxxx xxxx xxxx xxxx xxxx xxxx"

[github]
token  = "ghp_..."
repo   = "org/vana-mission-control"
branch = "main"

[r2]
endpoint    = "https://<account>.r2.cloudflarestorage.com"
access_key  = "..."
secret_key  = "..."
bucket      = "vana-assets"
public_base = "https://assets.vanamadhuryamdaily.com"
```

---

## Execução

```bash
streamlit run app.py
```

O painel ficará disponível em `http://localhost:8501`.

---

## Scripts CLI

### Ingerir uma visita

```bash
python ingest_visit.py payloads/visit.json tour:india-2026
python ingest_visit.py payloads/visit.json tour:india-2026 --dry-run
python ingest_visit.py payloads/visit.json tour:india-2026 --skip-validation
```

### Ingerir um tour

```bash
python ingest_tour.py tour-sample.json
python ingest_tour.py tour-sample.json --dry-run
```

As variáveis de ambiente `WP_URL`, `WP_USER`, `WP_APP_PASS` e `VANA_INGEST_SECRET` podem substituir os valores padrão nos scripts CLI.

---

## Autenticação HMAC

Todos os endpoints `/vana/v1/` usam autenticação HMAC-SHA256 espelhando o `class-vana-hmac.php` do plugin WordPress.  
A assinatura é composta por `timestamp + nonce + body` e enviada como query params (`vana_timestamp`, `vana_nonce`, `vana_signature`).

---

## Schema de Visita (3.1)

Campos obrigatórios no payload de visita:

| Campo | Descrição |
|---|---|
| `visit_id` | Identificador único (ex: `vrindavan-dia1`) |
| `title_pt` | Título em português |
| `timezone` | Timezone local (ex: `Asia/Kolkata`) |
| `days` | Array de dias (máx. 400), cada um com `date_local`, `label_pt` e `hero` |

Cada item de `days.hero` exige pelo menos uma fonte de mídia: `youtube_url`, `instagram_url`, `facebook_url` ou `drive_url`.

---

## Segurança

- O arquivo `secrets.toml` está listado no `.gitignore` e nunca deve ser commitado.
- O acesso ao painel exige senha em todas as páginas (login guard no `app.py`).
- Requisições ao WordPress são assinadas com HMAC-SHA256 com nonce e timestamp para prevenir replay attacks.
