

# Documento de Comportamento — Navegação da Visita

## Versão 1.0 — Formalização para Implementação

---

## 1. Princípio Fundacional

```text
O PRINCIPAL É O HARI-KATHA. O RESTANTE É CONTEXTO.

A visita é o templo.
O devoto entra uma vez e flui.
Nada pisca. Nada recarrega.
A moldura fica. O conteúdo flui.
O vídeo nunca para.
```

---

## 2. Hierarquia Editorial

```text
NÍVEL 1 — Hari-Katha
  O ensinamento. A razão de existir.

NÍVEL 2 — VOD (Stage)
  O veículo do ensinamento.

NÍVEL 3 — Evento / Agenda
  Contexto temporal e espacial.

NÍVEL 4 — Galeria de Gurudeva
  Evidência visual do momento.

NÍVEL 5 — Sangha
  Reverberação na comunidade.

NÍVEL 6 — Revista
  Síntese editorial da visita inteira.
```

---

## 3. Arquitetura da Página

### 3.1 Estrutura Fixa (Moldura)

```text
┌──────────────────────────────────────────┐
│  HEADER (sticky)                         │  Sempre visível
├──────────────────────────────────────────┤
│  HERO                                    │  Contexto da visita + CTA Revista
│  Day Selector                            │  Navegação entre dias
├──────────────────────────────────────────┤
│  ANCHOR CHIPS                            │  Navegação contextual (muda por estado)
├──────────────────────────────────────────┤
│  STAGE                                   │  Player + meta do item ativo
├──────────────────────────────────────────┤
│  ZONA MUTÁVEL                            │  Conteúdo que muda por estado
└──────────────────────────────────────────┘
```

### 3.2 O Que Cada Zona Contém

| Zona | Conteúdo Fixo | Muda Quando |
|------|--------------|-------------|
| **Header** | Logo, menu, botão agenda | Nunca |
| **Hero** | Nome da visita, localização, datas, day selector, CTA Revista, âncora | Troca de dia atualiza o day selector |
| **Chips** | Navegação contextual | Muda conforme o estado ativo |
| **Stage** | Player + título + badge + localização + ações | Troca de item ativo |
| **Zona Mutável** | Seções OU Passage OU Lente Temática | Troca de estado |

---

## 4. Estados da Página

### 4.1 Três Estados Nomeados

```text
ESTADO         ZONA MUTÁVEL MOSTRA       URL PATTERN
─────────────────────────────────────────────────────────────
visita         Seções (vods, galeria,     /visita/{slug}/dia-{n}
               sangha)

passage        Conteúdo do passage        /visita/{slug}/dia-{n}/
               (elaboração, transcrição,   katha/{katha-slug}/{num}
               notas, reações, nav)

lente          Lista de passages           /visita/{slug}/dia-{n}/
               relacionados por tema        katha/{katha-slug}/{num}/
                                            tema/{topic-slug}
```

### 4.2 Transições Entre Estados

```text
        ┌─────────────────────────────────┐
        │                                 │
        │           VISITA                │
        │                                 │
        └───────┬─────────────────────────┘
                │
                │  [🙏 Hari-Katha] no Stage
                │  OU clica num passage na agenda
                ▼
        ┌─────────────────────────────────┐
        │                                 │
        │          PASSAGE                │◄──── prev/next
        │                                 │      (mesma katha)
        └───────┬──────────┬──────────────┘
                │          │
     [← Voltar]│          │ [🧩 Sobre Este Tema]
                │          ▼
                │  ┌─────────────────────────┐
                │  │                         │
                │  │     LENTE TEMÁTICA      │
                │  │                         │
                │  └──────┬──────┬───────────┘
                │         │      │
                │  [← Passage]  │ clica passage
                │         │      │
                ▼         ▼      ▼
              VISITA   PASSAGE  (ver regras §4.3)
```

### 4.3 Regras de Navegação da Lente

```text
REGRA 1 — Passage da MESMA visita:
  → Lente fecha
  → Passage troca in-place na zona mutável
  → Hero atualiza dia se necessário
  → Stage faz seek ou troca vídeo
  → Chips atualizam
  → ~400ms, sem reload

REGRA 2 — Passage de OUTRA visita:
  → Card expande inline com PREVIEW
  → Mostra: trecho da elaboração, thumb do vídeo, reações
  → Dois botões:
    [← Fechar]           → colapsa o card, volta à lista
    [Abrir na visita →]  → page navigation (reload)
                          → salva returnTo no sessionStorage (fase 2)
```

---

## 5. Comportamento do Hero

### 5.1 Estados do Hero

```text
ESTADO DA PÁGINA     HERO
──────────────────────────────────────
visita               Completo (banner, day selector, badges, CTA revista)
passage              Compacto (nome da visita + day selector inline)
lente                Compacto (mesmo do passage)
mini player          Completo (restaura ao tamanho total)
```

### 5.2 CTA Revista no Hero

```text
CONDIÇÃO: $mag_state === 'publicada'

POSIÇÃO: Abaixo do day selector, como card editorial discreto

FORMATO:
  ──────────────────────────────────
  📰 Revista desta visita disponível
     "Vṛndāvana · Fevereiro 2026"  →
  ──────────────────────────────────

REGRAS:
  → Só aparece se a revista está publicada
  → Não é um chip de âncora
  → É um CTA editorial que abre a revista
  → Visível no estado "visita", oculto nos outros
```

### 5.3 Âncora do Hero

```text
REGRA: O hero tem âncora explícita.
O devoto pode retornar ao hero a qualquer momento
via chip [🏠] ou botão [↑] no header.
A âncora NÃO altera o estado do stage nem do mini player.
```

---

## 6. Comportamento do Stage

### 6.1 O Que O Stage Contém

```text
┌──────────────────────────────────────────┐
│  PLAYER (vídeo/áudio)                    │
├──────────────────────────────────────────┤
│  🏷 Badge   │  Título do item ativo      │
│  📍 Local, Cidade                        │
│  [🙏 Hari-Katha] [📤 Share] [⤓ Mini]    │
└──────────────────────────────────────────┘

O QUE NÃO ESTÁ NO STAGE:
  ❌ Mapa embed
  ❌ Capítulos/segmentos inline
  ❌ Lista de outros vídeos
  ❌ Conteúdo pesado
```

### 6.2 Três Modos do Stage

```text
MODO              PLAYER          ACIONADO POR
───────────────────────────────────────────────────
Página            ~56vw (16:9)    Estado padrão ao selecionar item
Leitura (sticky)  Mini sticky     Devoto scrolla para o conteúdo
                   no topo         do passage (automático)
Mini player       Barra fixa      Botão [⤓ Minimizar]
                   no rodapé
```

### 6.3 Regras do Stage

```text
REGRA 1 — O item ativo controla o stage.
  Trocar item ativo → stage atualiza.
  Se sheet/lente aberto → fecha e atualiza.
  Se mini player ativo → mini player atualiza sem restaurar.

REGRA 2 — O estado do stage controla o hero.
  Stage em modo página → hero completo.
  Stage em modo leitura → hero compacto.
  Stage minimizado → hero completo.

REGRA 3 — O mini player persiste enquanto há mídia ativa.
  Trocar item → atualiza mini player.
  Só some ao tocar em [✕] ou restaurar.

REGRA 4 — Só vod/áudio gera mini player.
  Gallery e sangha NÃO minimizam.
  Conteúdo estático abre em modal ou expande inline.
```

---

## 7. Comportamento dos Anchor Chips

### 7.1 Chips Por Estado

```text
ESTADO VISITA:
  [🏠] [🎬 Aulas] [📷 Galeria] [💬 Sangha]

ESTADO PASSAGE:
  [← Dia {n}] [🙏 {katha_title} — {num}/{total}] [▶ Próx.]

ESTADO LENTE:
  [← Passage #{num}] [🧩 {topic_name}]
```

### 7.2 Regras Dos Chips

```text
REGRA 1 — Chips NÃO controlam modos do stage.
  Os chips são navegação de contexto, não controle de player.

REGRA 2 — Chips sempre têm [← Voltar] quando fora do estado "visita".
  O caminho de volta é sempre explícito.

REGRA 3 — Chips no estado "visita" são âncoras de seção.
  Scroll suave até a seção correspondente.

REGRA 4 — Chips no estado "passage" são controle de navegação.
  [← Voltar] volta ao estado "visita".
  [▶ Próx.] troca passage in-place.

REGRA 5 — Agenda fica na gaveta, não nos chips.
  Chip 📅 Agenda NÃO existe.
  Agenda é acessada pelo header ou gesto.
```

---

## 8. Seções (Estado "visita")

### 8.1 O Que São As Seções

```text
SEÇÃO             CONTEÚDO                     APARECE QUANDO
──────────────────────────────────────────────────────────────
🎬 Aulas          Vods sem evento vinculado     $has_vods
📷 Galeria        Fotos de Gurudeva do dia      $has_gallery
💬 Sangha         Posts da comunidade           Sempre
```

### 8.2 O Que NÃO É Seção

```text
❌ 🙏 Hari-Katha — é destino, acessado pelo Stage
❌ 📰 Revista    — é CTA editorial no Hero
❌ 📅 Agenda     — é gaveta no header
```

---

## 9. Conteúdo do Passage (Estado "passage")

### 9.1 Estrutura

```text
┌──────────────────────────────────────────┐
│  A. Context Banner                       │
│     Visita · Data · Local · Katha (n/N)  │
├──────────────────────────────────────────┤
│  B. Vídeo (compartilhado com o Stage)    │
│     idle → play → sticky mini ao scroll  │
├──────────────────────────────────────────┤
│  C. Título + hook do passage             │
├──────────────────────────────────────────┤
│  D. Conteúdo (fluxo contínuo)            │
│     📖 Elaboração — ABERTA por default   │
│     📜 Transcrição — COLAPSADA           │
│     📓 Notas de estudo — COLAPSADA       │
├──────────────────────────────────────────┤
│  E. Reações + ações                      │
│     🙏 {n}   ✨ {n}   🔗   📤           │
├──────────────────────────────────────────┤
│  F. Navegação                            │
│     Cards prev/next                      │
│     [🕐 Nesta Aula] [🧩 Neste Tema]     │
├──────────────────────────────────────────┤
│  G. Voltar                               │
│     ← Voltar à visita (redundante com    │
│     chip, mas acessível no final)        │
└──────────────────────────────────────────┘
```

### 9.2 Regras Do Conteúdo

```text
REGRA 1 — Fluxo contínuo, não tabs.
  📖 📜 📓 são seções empilhadas, não modos alternáveis.
  📖 aberta por default. 📜📓 colapsadas.
  Expandir/colapsar via accordion simples.

REGRA 2 — O vídeo é compartilhado com o Stage.
  NÃO há dois iframes.
  O Stage É o player do passage.
  Ao scrollar para o texto → Stage faz sticky mini.

REGRA 3 — Seek sincronizado.
  Timestamps na transcrição são clicáveis → seek no player.
  Troca de passage → seek automático para o timestamp.

REGRA 4 — Accordion reseta ao trocar passage.
  📜📓 colapsam ao navegar para outro passage.
  📖 sempre abre.
```

---

## 10. Navegação Entre Passages

### 10.1 Transição In-Place

```text
FLUXO TÉCNICO:

  1. Devoto clica prev/next ou card na timeline
  2. JS intercepta (preventDefault)
  3. fetch('/wp-json/vana/v1/passage/{id}')
  4. Conteúdo atual: opacity 1 → 0.3 (150ms)
  5. JSON chega (~200ms):
     → Atualiza título, texto, contadores, tags
     → Seek no player (mesmo vídeo) OU troca thumb (outro vídeo)
     → 📜📓 colapsam
     → Scroll suave até zona C (mesma katha) ou zona A (outra katha)
     → Conteúdo novo: opacity 0.3 → 1 (200ms)
     → pushState atualiza URL
     → document.title atualiza
  6. Total percebido: ~400ms
```

### 10.2 Comportamento Do Player Na Troca

```text
CASO A — Mesmo vídeo (passages consecutivos da mesma katha):
  → MESMO iframe
  → player.seekTo(novo_timestamp)
  → Instantâneo (~100ms)
  → O vídeo nem pisca

CASO B — Vídeo diferente:
  → Fade-out no player (150ms)
  → Thumbnail do novo vídeo como placeholder
  → Novo iframe carrega
  → Quando pronto → fade-in
  → Seek para o timestamp
  → ~1-2s, mas suave
```

### 10.3 Regra De Scroll

```text
if (mesma_katha)  → scrollTo(zona_C — título)
if (outra_katha)  → scrollTo(zona_A — context banner)
```

### 10.4 Cache E Prefetch

```text
MVP:
  ✅ fetch sob demanda (sem prefetch)

FASE 2:
  ☐ Prefetch passages adjacentes (n-1, n+1) no sessionStorage
  ☐ Timeline completa da katha em cache ao abrir primeiro passage
  ☐ Cache por topic na lente temática
```

---

## 11. Lente Temática

### 11.1 O Que É

```text
Uma visualização de passages relacionados por tema,
de QUALQUER visita, exibida na zona mutável
SEM sair da visita atual.
```

### 11.2 Estrutura

```text
┌──────────────────────────────────────────┐
│  🧩 {topic_name}                         │
│  Origem: {katha} #{num} · {visita}       │
├──────────────────────────────────────────┤
│                                          │
│  ┌────────────────────────────────────┐  │
│  │ 📍 {visita} · {dia}               │  │
│  │ "{título}" ← ATUAL (marcado)      │  │
│  │ {grantha} #{num}   🙏 {n}         │  │
│  └────────────────────────────────────┘  │
│                                          │
│  ┌────────────────────────────────────┐  │
│  │ 📍 {outra_visita} · {ano}         │  │
│  │ "{título}"                         │  │
│  │ {grantha} #{num}   🙏 {n}         │  │
│  │                                    │  │
│  │  [expandido — preview]:            │  │
│  │  📖 trecho da elaboração...        │  │
│  │  ▶ thumb do vídeo                  │  │
│  │  [← Fechar] [Abrir na visita →]   │  │
│  └────────────────────────────────────┘  │
│                                          │
└──────────────────────────────────────────┘
```

### 11.3 Regras De Navegação

```text
REGRA 1 — Passage da MESMA visita:
  → Lente fecha
  → Passage carrega in-place (~400ms)
  → Hero/chips atualizam

REGRA 2 — Passage de OUTRA visita:
  → Card expande com preview (accordion)
  → NÃO navega automaticamente
  → [← Fechar] → colapsa
  → [Abrir na visita →] → page navigation (reload)

REGRA 3 — Preview é leve:
  → 2-3 parágrafos da elaboração
  → Thumbnail (não iframe)
  → Contagem de reações
  → SEM carregar player, SEM payload completo
```

### 11.4 REST Endpoint

```text
GET /wp-json/vana/v1/passages?topic={slug}&limit=10

Retorna:
  → passages de todas as visitas
  → metadados leves (título, trecho, thumb, reações, visita, local)
  → ordenados por relevância ou cronologia
  → NÃO retorna payload completo dos passages
```

---

## 12. Retorno Entre Visitas (Fase 2)

```text
AO NAVEGAR PARA OUTRA VISITA VIA LENTE:

  1. Salvar no sessionStorage:
     {
       returnTo: '/visita/vrindavana-2026/dia-3/katha/sb-10-31/4',
       returnLabel: 'SB 10.31 #4 · Vṛndāvana 2026',
       context: 'tema:vipralambha'
     }

  2. Na visita destino, exibir banner:
     "← Voltar para SB 10.31 #4 · Vṛndāvana 2026"

  3. Ao tocar no banner:
     → Navega de volta (page navigation)
     → Restaura passage via sessionStorage
```

---

## 13. Tipos De Mídia No Stage

```text
TIPO         STAGE MOSTRA           MINI PLAYER    HARI-KATHA
──────────────────────────────────────────────────────────────
vod          Player + meta          ✅ Sim          ✅ Se tem katha_id
áudio        Player + meta          ✅ Sim          ✅ Se tem katha_id
gallery      Imagem destaque        ❌ Não          ❌ Não
             + título + local
sangha       Card editorial         ❌ Não          ❌ Não
             + citação curta
placeholder  Mensagem "Sem          ❌ Não          ❌ Não
(neutro)     conteúdo ativo"
```

---

## 14. Mobile vs Desktop

### 14.1 Hero Compacto (Mobile)

```text
Desktop:
  [Dia 1] [Dia 2] [Dia 3] [Dia 4]   👁 1.2k

Mobile:
  [◀ Dia 3 ▶]   👁 1.2k
  (setas laterais ou dropdown)
```

### 14.2 Chips (Mobile)

```text
Desktop:
  [🏠] [🎬 Aulas] [📷 Galeria] [💬 Sangha]
  
Mobile:
  Scroll horizontal na barra de chips
  (comportamento nativo, já existente)
```

### 14.3 Prev/Next (Mobile)

```text
Desktop:
  Setas laterais (hover) + cards prev/next na zona F

Mobile:
  Cards prev/next na zona F
  (setas laterais NÃO aparecem no mobile)
  Fase 2: swipe gesture ← →
```

---

## 15. Roadmap De Implementação

### Fase A — Fundação (MVP)

```text
STRUCTURAL:
  □ Zona mutável como container único com data-state
  □ State router JS (visita | passage | lente)
  □ pushState para cada estado
  □ SSR para SEO (URLs de passage renderizam server-side)
  
STAGE:
  □ Stage simplificado (player + meta + ações)
  □ Localização no meta do item ativo
  □ Botão [🙏 Hari-Katha] abre estado passage
  □ Botão [⤓ Minimizar] ativa mini player

PASSAGE:
  □ Conteúdo em fluxo contínuo (📖 aberta + 📜📓 colapsáveis)
  □ Accordion simples (CSS + JS mínimo)
  □ Cards prev/next entre zonas E e F
  □ Transição fade 400ms entre passages
  □ Seek no player ao trocar passage
  □ Teclado ← → no desktop

HERO:
  □ CTA Revista (se publicada)
  □ Âncora [🏠] nos chips
  □ Hero compacta no estado passage

CHIPS:
  □ 3 variações por estado (visita, passage, lente)
  □ [← Voltar] sempre presente fora do estado visita

LENTE:
  □ [🧩 Sobre Este Tema] abre na zona mutável
  □ Lista com cards (local, título, reações)
  □ Mesma visita → troca in-place
  □ Outra visita → preview + [Abrir na visita →]

MINI PLAYER:
  □ Barra fixa no rodapé
  □ Thumb + título + play/pause + restaurar + fechar
  □ Hero volta a completo
  □ Seções navegáveis
```

### Fase B — Refinamento

```text
  □ Prefetch passages adjacentes (sessionStorage)
  □ Cache de timeline completa da katha
  □ Timestamps clicáveis sincronizados com player
  □ Transição cross-video suave (thumb → fade → iframe)
  □ sessionStorage para retorno entre visitas
  □ Banner "← Voltar para {visita anterior}"
  □ Setas laterais prev/next no desktop (hover + preview)
  □ Player sticky mini ao scrollar no passage
```

### Fase C — Polimento

```text
  □ Swipe gesture mobile para prev/next passage
  □ Drag gesture no bottom sheet (se aplicável)
  □ Animações mais sofisticadas
  □ Grafo de conexões entre temas
  □ "Devotos que leram este passage também leram..."
  □ Filtros na lente (por visita, ano, grantha)
```

---

## 16. Regras Formais (Consolidadas)

```text
REGRA 1 — O item ativo controla o stage.
  Trocar item → stage atualiza.
  Sheet/lente aberto → fecha e atualiza.
  Mini player ativo → atualiza sem restaurar.

REGRA 2 — O estado do stage controla o hero.
  Modo página → hero completo.
  Modo leitura/passage → hero compacto.
  Minimizado → hero completo.

REGRA 3 — O mini player persiste enquanto há mídia ativa.
  Trocar item → atualiza. Só some ao fechar ou restaurar.

REGRA 4 — Só vod/áudio gera mini player.
  Gallery e sangha não minimizam.

REGRA 5 — O hero tem âncora explícita.
  Chip [🏠] ou botão no header.
  Não altera estado do stage nem do mini player.

REGRA 6 — HK é destino, não seção.
  Acessado pelo Stage, não por chip de âncora.

REGRA 7 — Revista é CTA editorial, não seção.
  Vive no Hero, não nos chips.

REGRA 8 — A zona mutável é o único lugar que troca.
  Header, hero, chips, stage são moldura.
  Só a zona mutável alterna entre estados.

REGRA 9 — Transição entre visitas é page reload.
  Transição IN-PLACE só dentro da mesma visita.
  Sair da visita = reload (honesto com o devoto).

REGRA 10 — O caminho de volta é sempre explícito.
  Lente → [← Passage] → Passage → [← Dia] → Visita.
  Cada nível tem UM botão de volta. Sem ambiguidade.
```

---

## 17. REST Endpoints Necessários

```text
ENDPOINT                                    RETORNA                              USADO EM
──────────────────────────────────────────────────────────────────────────────────────────
GET /vana/v1/passage/{id}                   Payload completo do passage          Troca de passage
GET /vana/v1/katha/{id}                     HK completo (passages, sources)      Abertura do HK
GET /vana/v1/katha/{id}/timeline            Metadados leves de todos passages    Timeline [🕐]
GET /vana/v1/passages?topic={slug}          Passages relacionados por tema       Lente [🧩]
GET /vana/v1/visit/{slug}/sections          Seções da visita (vods, gallery...)  Retorno ao estado visita
```

---

## 18. Arquivos Impactados

```text
ARQUIVO                    MUDANÇA
────────────────────────────────────────────────────────
anchor-chips.php           Remove HK e Revista dos chips. 
                           Adiciona [🏠]. 
                           3 variações por estado.

stage.php                  Simplifica: player + meta + ações.
                           Remove mapa, segmentos.
                           Adiciona [🙏 HK] [⤓ Mini].

hero-header.php            Adiciona CTA Revista.
                           Estados completo/compacto.

sections.php               Zona mutável com data-state.
                           Renderiza seções OU passage OU lente.

(novo) passage-view.php    Template do conteúdo do passage
                           para a zona mutável.

(novo) lens-view.php       Template da lente temática
                           para a zona mutável.

(novo) mini-player.php     Barra fixa no rodapé.

(novo) state-router.js     Router de estados da zona mutável.
                           pushState, fetch, fade, scroll.

(novo) passage-nav.js      Navegação prev/next, seek, cache.

(novo) stage-modes.js      Controle página/sticky/mini.
```

---

Marcel, este documento cobre tudo o que discutimos: hierarquia editorial, estados da página, comportamento de cada zona, regras formais, transições, lente temática, roadmap e endpoints. Serve como contrato entre design e implementação — qualquer dúvida durante o código, a resposta está aqui. 

Quer ajustar algum ponto antes de partirmos para o código? 🙏



# Localização — Onde Vive No Novo Sistema

Marcel, boa pergunta. Com a arquitetura de visita como container único e zona mutável, o LocationPin precisa ter **posição clara em cada estado**.

---

## O Inventário — Onde Existe Localização

```text
ENTIDADE         TEM LOCATION?     EXEMPLO
──────────────────────────────────────────────────────────
Visita           ✅ Cidade          Vṛndāvana, IN
Dia              ❌ (herda visita)  —
Evento           ✅ Local           Gopīnātha Bhavana
Katha            ✅ (herda evento)  Gopīnātha Bhavana
Passage          ❌ (herda katha)   —
Photo            ⚠️ Opcional        Local específico da foto
Sangha           ❌                 —
Vod              ❌ (herda evento)  —
Orphan vod/photo ⚠️ Opcional        Sem evento, local avulso
```

```text
REGRA DE HERANÇA:

  Visita → metadata.city_pt / city_en / country
  Evento → location.name / lat / lng
  Katha  → herda do evento (index.events[event_key].location)
  Passage → herda da katha → herda do evento
  Vod    → herda do evento
  Photo  → própria OU herda do evento
  
  A LOCALIZAÇÃO FLUI DE CIMA PARA BAIXO.
  Só a visita e o evento DECLARAM localização.
  O resto herda.
```

---

## Onde O Pin Aparece — Por Zona

### Hero (Nível Visita)

```text
┌──────────────────────────────────────────┐
│                                          │
│  Vṛndāvana · fev 2026                   │
│  📍 Vṛndāvana, Índia                    │  ← LocationPin da VISITA
│                                          │
│  [Dia 1] [Dia 2] [●Dia 3] [Dia 4]      │
│                                          │
└──────────────────────────────────────────┘

REGRA:
  → Pin usa metadata.city_pt + metadata.country
  → Coordenadas: centro da cidade (ou local principal da visita)
  → Clique → modal de mapa mostra a cidade
  → Sempre visível (é o contexto geográfico da visita inteira)
  → NÃO muda ao trocar de dia (a cidade é a mesma)
```

### Stage (Nível Evento/Item Ativo)

```text
┌──────────────────────────────────────────┐
│  ▶ SB 10.31 — Gopī-gīta                 │
│  📍 Gopīnātha Bhavana                   │  ← LocationPin do EVENTO
│  [🙏 Hari-Katha] [📤] [⤓]              │
└──────────────────────────────────────────┘

REGRA:
  → Pin usa event.location.name + lat/lng
  → Muda quando o item ativo muda (outro evento = outro local)
  → Se o evento NÃO tem location → pin NÃO aparece
  → Se orphan sem location → pin NÃO aparece
  → Clique → modal de mapa mostra o local específico
```

### Context Banner do Passage (Nível Katha → Evento)

```text
┌──────────────────────────────────────────┐
│  Vṛndāvana · 21 fev 2026                │
│  📍 Gopīnātha Bhavana                   │  ← MESMO pin do Stage
│  SB 10.31 — Gopī-gīta · 4 de 25         │
└──────────────────────────────────────────┘

REGRA:
  → Herda location do evento via katha → sources → event_key
  → Cadeia: passage.katha_id → index.kathas[id].event_key
            → index.events[key] → busca location no days[].events[]
  → É o MESMO dado do Stage — redundante de propósito
  → No estado passage, o Stage está em modo leitura (sticky mini)
  → O devoto precisa ver o local NO CONTEÚDO, não só lá em cima
```

### Lente Temática (Cards de Outras Visitas)

```text
┌────────────────────────────────────────┐
│  📍 Jagannātha Purī · 2019            │  ← Pin do EVENTO de origem
│  "As lágrimas de Rādhā"               │
│  CC Madhya 2.1 #7          🙏 23      │
└────────────────────────────────────────┘

REGRA:
  → Cada card mostra o local + cidade da visita de ORIGEM
  → Formato: "📍 {location.name}" na primeira linha
  → Cidade + ano no subtítulo (já presente no card)
  → Pin clicável? SIM — abre modal com o local daquela katha
  → É informação de CONTEXTO: "onde Gurudeva falou isso"
```

---

## Visualização Consolidada — Os 4 Pontos

```text
ESTADO VISITA:

  ┌──────────────────────────────────────────┐
  │  🌸 Vana Madhuryam         [☰] [▶]      │
  ├──────────────────────────────────────────┤
  │  Vṛndāvana · fev 2026                   │
  │  📍 Vṛndāvana, Índia              ①     │  ① HERO — nível visita
  │  [Dia 1] [Dia 2] [●Dia 3] [Dia 4]      │
  ├──────────────────────────────────────────┤
  │  [🏠] [🎬 Aulas] [📷] [💬]             │
  ├──────────────────────────────────────────┤
  │  ▶ Programa completo — 21 fev            │
  │  📍 Gopīnātha Bhavana             ②     │  ② STAGE — nível evento
  │  [🙏 Hari-Katha] [📤] [⤓]              │
  ├──────────────────────────────────────────┤
  │  🎬 Aulas do dia                         │
  │  📷 Galeria                              │
  │  💬 Sangha                               │
  └──────────────────────────────────────────┘


ESTADO PASSAGE:

  ┌──────────────────────────────────────────┐
  │  🌸 Vana Madhuryam         [☰] [▶]      │
  ├──────────────────────────────────────────┤
  │  Vṛndāvana · fev 2026                   │
  │  📍 Vṛndāvana, Índia              ①     │  ① HERO (mesmo)
  │  [Dia 1] [Dia 2] [●Dia 3] [Dia 4]      │
  ├──────────────────────────────────────────┤
  │  [← Dia 3] [🙏 SB 10.31 — 4/25] [▶]   │
  ├──────────────────────────────────────────┤
  │  ▶ SB 10.31 — Gopī-gīta                 │
  │  📍 Gopīnātha Bhavana             ②     │  ② STAGE (mesmo)
  ├──────────────────────────────────────────┤
  │                                          │
  │  Vṛndāvana · 21 fev 2026                │
  │  📍 Gopīnātha Bhavana             ③     │  ③ CONTEXT BANNER
  │  SB 10.31 · 4 de 25                     │
  │                                          │
  │  "O fogo da separação..."               │
  │  📖 Elaboração...                        │
  │  ...                                     │
  └──────────────────────────────────────────┘


ESTADO LENTE:

  ┌──────────────────────────────────────────┐
  │  ...                                     │
  ├──────────────────────────────────────────┤
  │  🧩 vipralambha-mādhurya                 │
  │                                          │
  │  ┌────────────────────────────────────┐  │
  │  │ 📍 Gopīnātha Bhavana        ④    │  │  ④ CARD — outra visita
  │  │ Vṛndāvana 2026 · Dia 3           │  │
  │  │ "O fogo da separação" ← ATUAL    │  │
  │  └────────────────────────────────────┘  │
  │                                          │
  │  ┌────────────────────────────────────┐  │
  │  │ 📍 Gambhīrā               ④      │  │  ④ CARD — outra visita
  │  │ Jagannātha Purī · 2019           │  │
  │  │ "As lágrimas de Rādhā"           │  │
  │  └────────────────────────────────────┘  │
  │                                          │
  └──────────────────────────────────────────┘
```

---

## Regras Do LocationPin — Consolidadas

```text
PIN-1  O pin é SEMPRE clicável → abre maps-modal
       (componente já implementado: location-pin.php + maps-modal.php)

PIN-2  O pin herda dados conforme a hierarquia:
         Hero   → visit.metadata (city + country + coordenadas)
         Stage  → event.location (name + lat + lng)
         Banner → katha → event.location (mesma cadeia)
         Card   → passage.source → event.location

PIN-3  Se NÃO há location declarada → pin NÃO renderiza.
       O gate já existe no componente:
         if ( empty($lat) || empty($lng) ) return;

PIN-4  Formato do label:
         Hero:    "{city_pt}, {country_name}"
         Stage:   "{location.name}"
         Banner:  "{location.name}"
         Card:    "{location.name}"

PIN-5  Modal é GLOBAL (1x no footer) — já implementado.
       Todos os pins da página compartilham o mesmo modal.

PIN-6  A cadeia de resolução via Schema 6.2:

       HERO:
         visit.metadata.city_pt → "Vṛndāvana"
         (coordenadas: meta da visita no WP ou hardcoded por cidade)

       STAGE:
         item_ativo.event_key
         → days[].events[event_key].location
         → { name, lat, lng }

       CONTEXT BANNER (passage):
         passage.katha_id
         → index.kathas[katha_id].event_key
         → days[].events[event_key].location
         → { name, lat, lng }

       CARD DA LENTE:
         passage_remoto.katha_id
         → REST: GET /vana/v1/katha/{id}
         → retorna event_key + location
         OU: o endpoint de lente já retorna location no payload
```

---

## O Que Muda No Endpoint Da Lente

```text
GET /wp-json/vana/v1/passages?topic={slug}&limit=10

RETORNO ATUAL (proposto):
  {
    title, excerpt, thumb_url, reactions, visit_ref, katha_id
  }

RETORNO ATUALIZADO (com location):
  {
    title,
    excerpt,
    thumb_url,
    reactions,
    visit_ref,
    visit_label,        ← "Vṛndāvana 2026"
    katha_id,
    location: {         ← herdado do evento de origem
      name,
      lat,
      lng
    }
  }

CUSTO: mínimo — o Trator já propaga location via event_key.
O endpoint só precisa incluir na resposta.
```

---

## O Que NÃO Precisa De Pin

```text
❌ Seção 🎬 Aulas — o local já está no Stage acima
❌ Seção 📷 Galeria — fotos herdam do evento
   (EXCEÇÃO: se foto orphan tem location própria → pin na lightbox)
❌ Seção 💬 Sangha — não tem dimensão geográfica
❌ Day selector — dias não têm local (herdam da visita)
❌ Chips — são navegação, não conteúdo
❌ Mini player — muito pequeno, sem espaço
```

---

## Resumo — 4 Pontos De Pin

| # | Zona | Dado | Aparece Quando |
|---|------|------|----------------|
| ① | **Hero** | `visit.metadata.city` | Sempre |
| ② | **Stage** | `event.location.name` | Evento tem location |
| ③ | **Context Banner** | `event.location.name` (via katha) | Estado passage |
| ④ | **Card da Lente** | `event.location.name` (via REST) | Estado lente |

```text
COMPONENTE REUSADO: location-pin.php (já implementado)
MODAL REUSADO: maps-modal.php (já implementado, 1x no footer)
ZERO CÓDIGO NOVO para o pin em si.
Só posicionamento correto em cada template.
```

---

Marcel, o LocationPin já está pronto como componente. A questão era só **onde encaixar**. A resposta: nos 4 pontos onde o devoto precisa de contexto geográfico — Hero, Stage, Banner do passage e Cards da lente. Sempre herdando, nunca duplicando dados. Quer atualizar o documento de navegação com esses pontos? 🙏