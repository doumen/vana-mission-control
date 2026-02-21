## ENTREGA 14 — `README.md`

```markdown
# Vana Madhuryam — Visit Page System
### Template WordPress para Páginas de Visita de Missão
**Versão:** 2.6 | **Autor:** Vana Madhuryam Official | **Licença:** Proprietária

---

## 📋 Índice

1. [Visão Geral](#1-visão-geral)
2. [Estrutura de Arquivos](#2-estrutura-de-arquivos)
3. [Requisitos](#3-requisitos)
4. [Instalação](#4-instalação)
5. [Configuração do JSON](#5-configuração-do-json)
6. [Guia de Campos](#6-guia-de-campos)
7. [Provedores de Mídia](#7-provedores-de-mídia)
8. [Internacionalização](#8-internacionalização)
9. [Timezones](#9-timezones)
10. [Personalização Visual](#10-personalização-visual)
11. [Acessibilidade](#11-acessibilidade)
12. [Automações de Missão](#12-automações-de-missão)
13. [FAQ](#13-faq)
14. [Changelog](#14-changelog)

---

## 1. Visão Geral

O **Visit Page System** é um template modular para WordPress que
transforma um arquivo JSON em uma página de visita completa para
a missão de **Srila Bhaktivedanta Vana Goswami Maharaj**, com:

- 🎬 **Stage** — player principal (YouTube / Facebook / Instagram / Drive)
- 📅 **Abas por dia** — navegação entre dias da visita
- 🗓️ **Programação** — com status ao vivo, dual-timezone
- 📚 **Grade de aulas (VODs)** — com capítulos e seek direto no player
- 🖼️ **Galeria** — grid masonry com lightbox nativo
- 🙏 **Momentos da Sangha** — depoimentos, citações e realizações
- 🔗 **Canais da Missão** — YouTube, Facebook, Instagram, WhatsApp, Telegram
- 🌐 **Bilíngue** — PT-BR / EN com fallback automático

### Canais Oficiais

| Canal | URL |
|---|---|
| YouTube | https://www.youtube.com/@vanamadhuryamofficial |
| Facebook | https://www.facebook.com/vanamadhuryamofficial |
| Instagram | https://www.instagram.com/vanamadhuryamofficial/ |

---

## 2. Estrutura de Arquivos

```
wp-content/
└── themes/
    └── seu-tema/
        └── templates/
            └── visit/
                ├── _bootstrap.php          # Inicialização, variáveis globais
                ├── vana-utils.php          # Helpers PHP (i18n, resolve mídia)
                ├── visit-template.php      # Orquestrador principal (inclui partials)
                ├── visit-styles.php        # CSS — design tokens + componentes
                ├── visit-scripts.php       # JS — lightbox, dual-tz, segmentos
                ├── visit-sample.json       # JSON de exemplo documentado
                ├── README.md               # Este arquivo
                └── parts/
                    ├── day-tabs.php        # Abas de navegação entre dias
                    ├── stage.php           # Player principal + segmentos
                    ├── vod-list.php        # Grade de aulas do dia
                    ├── schedule.php        # Programação com status e horários
                    ├── community-links.php # Canais da missão
                    ├── gallery.php         # Galeria de fotos + lightbox
                    └── sangha-moments.php  # Depoimentos da comunidade
```

---

## 3. Requisitos

| Item | Versão Mínima |
|---|---|
| WordPress | 6.3+ |
| PHP | 8.1+ |
| Extensão `intl` | Qualquer |
| Extensão `json` | Qualquer |
| Tema base | Qualquer (sem dependência de tema pai) |

### Dependências de Frontend (CDN — já inclusas em `visit-styles.php`)

| Recurso | Finalidade |
|---|---|
| Google Fonts — Syne | Títulos e labels |
| Google Fonts — Questrial | Corpo e meta |
| Dashicons (WordPress core) | Ícones |

> **Nota:** Nenhuma biblioteca JavaScript externa é necessária.
> O sistema usa Vanilla JS puro.

---

## 4. Instalação

### 4.1 Copiar os arquivos

```bash
# A partir da raiz do tema ativo
cp -r visit/ wp-content/themes/seu-tema/templates/visit/
```

### 4.2 Registrar o template no WordPress

Adicione ao `functions.php` do tema:

```php
// functions.php

/**
 * Registra o template de visita e carrega as classes necessárias.
 */
add_action('after_setup_theme', function () {

    // Autoload de utilitários do sistema de visita
    require_once get_template_directory() . '/templates/visit/vana-utils.php';

});

/**
 * Redireciona requisições de ?visit_id=xxx para o template.
 */
add_action('template_redirect', function () {

    if (!isset($_GET['visit_id'])) return;

    $visit_id = sanitize_text_field($_GET['visit_id']);
    if (!$visit_id) return;

    $tpl = get_template_directory() . '/templates/visit/visit-template.php';
    if (file_exists($tpl)) {
        include $tpl;
        exit;
    }
});
```

### 4.3 Criar e publicar o JSON

Salve o JSON da visita em qualquer URL pública acessível ao PHP,
por exemplo via **WordPress Media** ou **ACF** (campo texto/URL).

```php
// Exemplo de leitura via ACF em uma página WordPress
$json_url = get_field('visit_json_url'); // URL do arquivo JSON
```

### 4.4 Configurar a fonte do JSON no bootstrap

Abra `_bootstrap.php` e ajuste a função `vana_load_visit_data()`:

```php
// _bootstrap.php — trecho a customizar

function vana_load_visit_data(string $visit_id): array {

    // Opção A: JSON via URL pública (padrão)
    $url = 'https://seu-site.com/visitas/' . $visit_id . '.json';

    // Opção B: JSON via ACF (campo na página)
    // $url = get_field('visit_json_url', get_the_ID());

    // Opção C: JSON local no servidor
    // $file = get_template_directory() . '/visitas/' . $visit_id . '.json';
    // return json_decode(file_get_contents($file), true) ?: [];

    $response = wp_remote_get($url, ['timeout' => 10]);
    if (is_wp_error($response)) return [];

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true) ?: [];
}
```

### 4.5 Acessar a página

```
https://seu-site.com/?visit_id=vrindavan-2026-02
https://seu-site.com/?visit_id=vrindavan-2026-02&day=2026-02-22
https://seu-site.com/?visit_id=vrindavan-2026-02&day=2026-02-22&vod=1&lang=en
```

---

## 5. Configuração do JSON

### 5.1 Criando um novo arquivo de visita

Copie `visit-sample.json` e renomeie:

```bash
cp visit-sample.json vrindavan-2026-02.json
```

Edite os campos conforme o guia abaixo e publique na URL configurada.

### 5.2 Estrutura raiz

```json
{
  "visit_id":       "vrindavan-2026-02",
  "title_pt":       "Visita a Vrindavan — Fevereiro 2026",
  "title_en":       "Vrindavan Visit — February 2026",
  "description_pt": "...",
  "description_en": "...",
  "timezone":       "Asia/Kolkata",
  "cover_url":      "https://...",
  "days":           [ ... ]
}
```

### 5.3 Estrutura de um dia

```json
{
  "date_local":          "2026-02-21",
  "label_pt":            "Sáb, 21 Fev",
  "label_en":            "Sat, 21 Feb",
  "hero":                { ... },
  "schedule":            [ ... ],
  "vods":                [ ... ],
  "photos":              [ ... ],
  "sangha_moments":      [ ... ],
  "links":               [ ... ],
  "photos_submit_url":   "https://...",
  "moments_submit_url":  "https://..."
}
```

---

## 6. Guia de Campos

### 6.1 Hero (player principal)

| Campo | Tipo | Obrig. | Descrição |
|---|---|---|---|
| `title_pt` / `title_en` | string | ✅ | Título exibido no Stage |
| `description_pt/en` | string | ⭕ | Subtítulo do Stage |
| `youtube_url` | string | ⭕ | URL do YouTube (padrão) |
| `facebook_url` | string | ⭕ | URL do Facebook Video |
| `instagram_url` | string | ⭕ | URL do post Instagram |
| `drive_url` | string | ⭕ | URL do Google Drive |
| `thumb_url` | string | ⭕ | Thumbnail custom (auto se YouTube) |
| `duration` | string | ⭕ | Ex: `"42:18"` |
| `location.name` | string | ⭕ | Nome do local |
| `location.lat` / `lng` | string | ⭕ | Coordenadas para mini-mapa |
| `segments[]` | array | ⭕ | Capítulos (ver 6.2) |

### 6.2 Segmentos (capítulos)

```json
{
  "t":        "8:32",
  "title_pt": "A glória do dhama",
  "title_en": "The glory of the dhama"
}
```

| Campo | Tipo | Descrição |
|---|---|---|
| `t` | string | Timecode `"MM:SS"` ou `"H:MM:SS"` |
| `title_pt/en` | string | Label do capítulo |

### 6.3 Schedule (programação)

| Campo | Tipo | Obrig. | Descrição |
|---|---|---|---|
| `time_local` | string | ✅ | Horário local `"HH:MM"` |
| `title_pt/en` | string | ✅ | Título do item |
| `description_pt/en` | string | ⭕ | Descrição breve |
| `status` | string | ✅ | `done` / `live` / `upcoming` / `break` / `optional` |
| `speaker` | string | ⭕ | Nome do palestrante |
| `location_pt/en` | string | ⭕ | Local do item |

### 6.4 VODs (aulas)

| Campo | Tipo | Obrig. | Descrição |
|---|---|---|---|
| `title_pt/en` | string | ✅ | Título da aula |
| `description_pt/en` | string | ⭕ | Descrição breve |
| `youtube_url` | string | ⭕ | URL YouTube |
| `facebook_url` | string | ⭕ | URL Facebook Video |
| `drive_url` | string | ⭕ | URL Google Drive |
| `thumb_url` | string | ⭕ | Thumbnail custom |
| `duration` | string | ⭕ | Ex: `"58:44"` |
| `segments[]` | array | ⭕ | Capítulos (ver 6.2) |

### 6.5 Photos (galeria)

| Campo | Tipo | Obrig. | Descrição |
|---|---|---|---|
| `thumb_url` | string | ✅ | URL da miniatura |
| `full_url` | string | ⭕ | URL HD para lightbox |
| `caption_pt/en` | string | ⭕ | Legenda da foto |
| `credit` | string | ⭕ | Crédito do fotógrafo |
| `featured` | bool | ⭕ | `true` → destaque full-width |

### 6.6 Sangha Moments (depoimentos)

| Campo | Tipo | Obrig. | Descrição |
|---|---|---|---|
| `type` | string | ⭕ | `quote` / `moment` / `service` / `realization` |
| `author` | string | ⭕ | Nome do autor |
| `role_pt/en` | string | ⭕ | Função/título |
| `avatar_url` | string | ⭕ | Foto do autor (auto-inicial se ausente) |
| `text_pt/en` | string | ✅ | Texto do depoimento |
| `city` | string | ⭕ | Localização |
| `featured` | bool | ⭕ | `true` → badge dourado |

### 6.7 Links (canais do dia)

| Campo | Tipo | Obrig. | Descrição |
|---|---|---|---|
| `type` | string | ✅ | `youtube` / `facebook` / `instagram` / `whatsapp` / `telegram` / `site` / `custom` |
| `url` | string | ✅ | URL de destino |
| `label_pt/en` | string | ✅ | Label do card |
| `desc_pt/en` | string | ⭕ | Descrição breve |
| `icon` | string | ⭕ | Classe dashicon custom |

---

## 7. Provedores de Mídia

| Provedor | Campo JSON | Extração automática |
|---|---|---|
| YouTube | `youtube_url` | ID via regex, thumbnail auto, `enablejsapi` |
| Facebook | `facebook_url` | URL completa no iframe embed |
| Instagram | `instagram_url` | oEmbed oficial |
| Google Drive | `drive_url` | ID extraído da URL `/file/d/{ID}/view` |

### Prioridade de resolução (hero e VODs)

```
youtube_url → facebook_url → instagram_url → drive_url
```

O primeiro campo não-vazio encontrado é utilizado.

---

## 8. Internacionalização

### Seleção de idioma via URL

```
?lang=pt   → Português (padrão)
?lang=en   → English
```

### Fallback automático

Todos os campos `_pt` / `_en` possuem fallback:

```
campo_pt → campo_en  (quando lang=pt e campo_pt vazio)
campo_en → campo_pt  (quando lang=en e campo_en vazio)
```

### Adicionar novo idioma

1. Adicione campos `_XX` no JSON (ex: `title_es`)
2. Atualize `Vana_Utils::pick_i18n_key()` em `vana-utils.php`
3. Adicione o lang na validação do `_bootstrap.php`

---

## 9. Timezones

O sistema utiliza **dual-timezone**:

- **Fuso do evento** → definido em `timezone` no JSON
- **Fuso do visitante** → detectado via `Intl.DateTimeFormat` no browser

### Comportamento

| Situação | Exibição |
|---|---|
| Fusos iguais | Só horário do evento |
| Fusos diferentes | Horário do evento + "Seu horário: HH:MM TZ" |
| Browser sem `Intl` | Só horário do evento (graceful degradation) |

### Fusos comuns da missão

```
Asia/Kolkata          → Vrindavan, Mayapur (IST +5:30)
America/Sao_Paulo     → Brasil (BRT -3:00)
Europe/London         → UK (GMT/BST)
America/New_York      → EUA Leste (ET)
Australia/Sydney      → Austrália Leste (AEST)
```

---

## 10. Personalização Visual

Todos os tokens de design estão em `visit-styles.php`:

```css
:root {
  --vana-gold:          #FFD906;
  --vana-orange:        #F97316;
  --vana-pink:          #EC4899;
  --vana-blue:          #3B82F6;
  --vana-text:          #1e293b;
  --vana-muted:         #64748b;
  --vana-bg:            #ffffff;
  --vana-bg-soft:       #f8fafc;
  --vana-line:          #e2e8f0;
  --vana-hero-gradient: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
}
```

### Dark mode

Adicione ao `visit-styles.php`:

```css
@media (prefers-color-scheme: dark) {
  :root {
    --vana-text:     #f1f5f9;
    --vana-muted:    #94a3b8;
    --vana-bg:       #0f172a;
    --vana-bg-soft:  #1e293b;
    --vana-line:     #334155;
  }
}
```

---

## 11. Acessibilidade

O sistema segue as diretrizes **WCAG 2.1 AA**:

| Recurso | Implementação |
|---|---|
| Semântica | `<section>`, `<nav>`, `<blockquote>`, `<figure>` |
| ARIA | `role`, `aria-label`, `aria-current`, `aria-live`, `aria-modal` |
| Teclado | Tabs (Arrow/Home/End), Lightbox (Esc/Arrows), Segmentos (Enter) |
| Foco | Outline dourado visível, foco devolvido ao fechar lightbox |
| Imagens | `alt` descritivo em todas as imagens |
| Links externos | `rel="noopener noreferrer"` + hint "abre em nova aba" |
| Live regions | `aria-live="polite"` quando há itens com `status: live` |

---

## 12. Automações de Missão

### Fluxo recomendado (COMANDO ORQUESTRAR)

```
1. Download do vídeo YouTube (yt-dlp)
        ↓
2. Transcrição automática (Whisper / YouTube CC)
        ↓
3. Geração do JSON da visita (GPT / Claude)
        ↓
4. Upload do JSON para URL pública
        ↓
5. Publicação do post no Blogger (hub de textos)
        ↓
6. Corte de Reels para Instagram (FFmpeg)
        ↓
7. Post no Facebook com link do Blogger
        ↓
8. Atualização da página WordPress (?visit_id=xxx)
```

### Campos geráveis por IA

Os seguintes campos podem ser gerados automaticamente a partir
da transcrição do vídeo:

- `title_pt/en`
- `description_pt/en`
- `segments[]` (timecodes + títulos)
- `sangha_moments[]` (citações extraídas)
- `schedule[].description_pt/en`

### Exemplo de prompt para geração do JSON

```
Você é o [Vana] Alquimista Criativo v2.6.
A partir da transcrição abaixo de uma palestra de
Srila Vana Maharaj, gere o JSON completo no schema
visit-sample.json para a data {DATA}, timezone {TZ},
em PT e EN. Extraia: título, descrição, segmentos com
timecodes, e até 3 momentos da sangha (tipo: quote).

TRANSCRIÇÃO:
{TRANSCRIÇÃO_AQUI}
```

---

## 13. FAQ

**P: Posso usar mais de 4 dias numa visita?**
R: Sim. O array `days[]` suporta qualquer quantidade.
   As abas rolam horizontalmente em mobile.

**P: O que acontece se um campo obrigatório estiver ausente?**
R: Cada partial verifica os campos antes de renderizar.
   Seções sem dados simplesmente não aparecem (graceful degradation).

**P: Como atualizar o status de um item de schedule para "live"?**
R: Edite o campo `status` no JSON e faça o upload.
   O template relê o JSON a cada carregamento de página.
   Para atualização em tempo real, considere adicionar
   um `meta refresh` ou chamada AJAX periódica.

**P: O lightbox funciona sem JavaScript?**
R: Sem JS, as fotos ainda são exibidas no grid.
   O lightbox (overlay fullscreen) requer JS habilitado.

**P: Como adicionar um mapa customizado?**
R: Preencha `hero.location.lat` e `hero.location.lng`.
   O mini-mapa usa OpenStreetMap via iframe (sem API key).

**P: Posso hospedar o JSON no Google Sheets?**
R: Sim. Publique a planilha como CSV, converta para JSON
   via um Apps Script e use a URL pública gerada.

**P: Como proteger uma visita com senha?**
R: Adicione no `_bootstrap.php` uma verificação de
   `session_start()` + senha antes de `vana_load_visit_data()`.

---

## 14. Changelog

### v2.6 (2026-02-21)
- Refactor completo: monolito → 12 partials modulares
- Dual-timezone com detecção automática de fuso do visitante
- Lightbox nativo (VanaGallery) sem dependências externas
- Suporte a Google Drive como provedor de mídia
- Sangha Moments: tipos `quote`, `moment`, `service`, `realization`
- Avatar automático por inicial quando `avatar_url` ausente
- Galeria masonry com `column-span` para fotos em destaque
- Fallback Facebook com painel e botão copy-link
- Navegação por teclado nas abas (ARIA tablist completo)
- JSON schema v2.6 com todos os campos documentados

### v2.0
- Suporte bilíngue PT/EN com fallback automático
- Player YouTube com capítulos e seek via postMessage
- Programação com status ao vivo (pulsante)
- Grade de VODs com thumbnail automático

### v1.0
- Template inicial (monolito)
- Suporte YouTube e Facebook
- Programação estática

---

*Hare Krishna — Jaya Srila Vana Maharaj* 🙏
```

---

**ENTREGA 14 concluída.** ✅

---

### ✅ PROJETO 100% COMPLETO — Todas as entregas:

| # | Arquivo | Linhas aprox. |
|---|---|---|
| 1 | `_bootstrap.php` | ~180 |
| 2 | `vana-utils.php` | ~120 |
| 3 | `visit-template.php` | ~90 |
| 4 | `visit-styles.php` | ~380 |
| 5 | `parts/day-tabs.php` | ~140 |
| 6 | `parts/stage.php` | ~260 |
| 7 | `parts/vod-list.php` | ~220 |
| 8 | `parts/schedule.php` | ~240 |
| 9 | `parts/community-links.php` | ~200 |
| 10 | `parts/gallery.php` | ~210 |
| 11 | `parts/sangha-moments.php` | ~230 |
| 12 | `visit-scripts.php` | ~280 |
| 13 | `visit-sample.json` | ~260 |
| 14 | `README.md` | ~400 |
| **Total** | | **~3.210 linhas** |

---

O sistema **Vana Madhuryam Visit Page v2.6** está completo, Marcel. 🙏

Quer que eu prepare agora o **prompt de geração de JSON via IA** (para usar com Claude/GPT a partir de transcrições do YouTube) ou o **fluxo de automação no n8n** para orquestrar o pipeline completo?