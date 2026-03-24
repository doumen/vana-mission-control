# CONTRATO_PAGINA_6_0.md

```markdown
# Contrato da Página — Schema 6.0
# single-vana_visit.php
# Versão: 6.0 | Data: 22/03/2026 | Autor: Marcel + Vana Alquimista

---

## 1. UNIDADE MÍNIMA PUBLICÁVEL

```text
Visit
  └── Day(s)
        └── Event(s)

Tour → opcional, nunca pré-requisito
Day  → componente some se visit tem apenas 1 dia
```

---

## 2. HIERARQUIA TEMPORAL COMPLETA

```text
NÍVEL   OBJETO    OBRIGATÓRIO   EXEMPLO
──────  ────────  ────────────  ──────────────────────
1       Visit     ✅            São Paulo
2       Day       ✅ *          Dia 10, Dia 11, Dia 12
3       Event     ✅            Programa das 18h
4       Tour      ❌            América do Sul 2026

* Day some da UI se visit.days.length === 1
```

---

## 3. ESTRUTURA DE DADOS

```json
vana_visit {
  "_vana_tour_id"        : "opcional | nullable",
  "_vana_start_date"     : "obrigatório | YYYY-MM-DD",
  "_vana_location"       : "obrigatório | string",
  "_visit_timeline_json" : {
    "days": [
      {
        "day_key"  : "2026-03-10",
        "label"    : "10 mar",
        "events"   : [
          {
            "event_key"   : "20260310-1800-programa",
            "title"       : "Programa das 18h",
            "time"        : "18:00",
            "status"      : "past | active | future",
            "media_ref"   : "youtube_id | null",
            "segments"    : [],
            "katha_refs"  : [],
            "photo_refs"  : [],
            "sangha_refs" : []
          }
        ]
      }
    ]
  }
}
```

---

## 4. ZONAS DA PÁGINA

```text
ZONA 0  Breadcrumb
ZONA 1  Hero
ZONA 2  Seletor de Dia        (some se days === 1)
ZONA 3  Stage
ZONA 4  Chip Bar              (sticky)
ZONA 5  HK | Galeria | Sangha | Revista
```

---

## 5. ZONA 1 — HERO

```text
CONTEÚDO:
  - Nome da visita (_vana_location)
  - Período (start_date → end_date)
  - Contador de visita na tour (SE tour existe: "Visita 3 de 12")
  - Prev/next entre visitas

PREV/NEXT:
  DT-004 (decisão de produto): Prev/next permanece cronológica global.
  Tour é contexto visual/editorial e NÃO escopa a navegação.
  SE _vana_tour_id é null  → cronológico global (fallback)

GAVETA TOUR (botão no hero):
  SE tour existe  → exibe tour_title + lista de visitas da tour
  SE sem tour     → exibe "Visitas" + lista cronológica global

IDENTIDADE VISUAL:
  - Imagem de capa da visita
  - Gradiente devocional
  - Fundo neutro se sem capa
```

---

## 6. ZONA 2 — SELETOR DE DIA

```text
REGRA DE EXIBIÇÃO:
  days === 1   → some completamente
  days 2–8     → cabeçalho de mês + pílulas numéricas
  vira mês     → dois grupos com label próprio

ANATOMIA:
  março 2026
  [10] [11] [●12] [13] [14]
   ○    ○     ●    ○    ○

MOBILE:
  → scroll horizontal
  → nunca quebra layout

COMPORTAMENTO:
  → trocar dia recarrega Agenda
  → trocar dia NÃO troca Stage automaticamente
  → Stage só muda quando devoto toca [▶] num evento
```

---

## 7. ZONA 3 — STAGE

```text
MODOS:
  Vídeo      → YouTube embed protegido
  Áudio      → capa do evento + player
  Neutro     → logo Vana Madhuryam
  Aguardando → logo + horário de início

PARÂMETROS EMBED YOUTUBE:
  rel=0 | modestbranding | controls=0
  enablejsapi=1 | autoplay=0

AUTOPLAY CURADO — hierarquia:
  1ª  Próximo evento do dia (Agenda)
  2ª  Playlist curada da tour
  3ª  Tela neutra + logo
  ❌  Nunca recomendados externos

TELA DE TRANSIÇÃO (entre eventos):
  logo + "A seguir: [título]"
  [▶ Iniciar agora] [Pausar]
  contador regressivo de 5s

REGRA DE OURO:
  O Stage é um santuário.
  Nenhum elemento externo entra.
  Nenhuma plataforma assume o controle.

CONTROLES:
  ⏮ evento anterior | ▶/⏸ | ⏭ próximo evento | 🔊
  Nunca: "ir para YouTube" | Nunca: fullscreen externo

SEGMENTS:
  → exibidos abaixo dos controles
  → segment ativo destacado conforme currentTime
  → clique no segment → seek no player
  → segment ativo → HK rola para trecho correspondente

CONEXÃO COM AGENDA:
  → Stage emite evento ativo via postMessage
  → Agenda destaca o evento correspondente
  → Stage pausa → toggle Acompanhar desativa
```

---

## 8. ZONA 4 — CHIP BAR

```text
POSIÇÃO:  sticky abaixo do Stage
ITENS:    [📖 HK] [🖼️ Galeria] [🙏 Sangha] [📰 Revista]

NOTA: Agenda não está no Chip Bar
      Agenda é gaveta direita acionada por botão/pill
```

---

## 9. GAVETA DIREITA — AGENDA

```text
ESTADO PADRÃO: fechada

ACIONAMENTO:
  Mobile  → pill flutuante ou botão no Stage
  Desktop → botão discreto no Hero (lado direito)

ABERTURA AUTOMÁTICA — exceções:
  Visit sem mídia vinculada → abre automaticamente
  Live ativa               → abre mostrando evento ao vivo
                             fecha sozinha após 3s sem interação

ANATOMIA INTERNA:
  ┌─────────────────────────────────┐
  │ AGENDA  ·  12 mar            X  │
  ├─────────────────────────────────┤
  │ ○ 06:00  Mangala Ārati          │  passado
  │   [▶ Ouvir]                     │
  │                                 │
  │ ● 09:00  Parikramā              │  ativo
  │   Srila Vana Maharaj            │
  │   [▶ Ouvir] [📖 HK] [🔔]       │
  │                                 │
  │ ○ 18:00  Programa da Tarde      │  futuro
  │   [▶ Ouvir] [🔔]               │
  ├─────────────────────────────────┤
  │ [🌐 PT] [EN]                    │
  └─────────────────────────────────┘

ESTADOS DO EVENTO:
  passado  → texto esmaecido | [▶] disponível | [🔔] some
  ativo    → destaque + borda | [▶] pulsando
  futuro   → normal | [🔔] disponível
  sem mídia → [▶] some | só título

BOTÕES CONDICIONAIS:
  [▶ Ouvir]   → só se media_ref existe
  [📖 HK]     → só se katha_ref existe
  [🔔]        → só em eventos futuros

IDIOMA:
  [🌐 PT] [EN] → troca conteúdo HK, não troca o áudio
  Detecção automática por navigator.language
  Fallback: PT
  Preferência salva em localStorage

REAÇÃO AO STAGE:
  → evento ativo na Agenda = evento tocando no Stage
  → trocar evento na Agenda → Stage carrega nova mídia
  → Stage termina → próximo evento da Agenda (autoplay curado)
```

---

## 10. ZONA 5 — SEÇÕES DE PROFUNDIDADE

```text
ORDEM:
  1. HK — Hari-katha
  2. Galeria
  3. Sangha
  4. Revista

HK:
  → listagem de kathas por event_id
  → passages com timestamp clicável → seek Stage
  → permalink compartilhável
  → reactions 🙏 ✨
  → filtro por taxonomia (passage_topic, passage_rasa)

GALERIA:
  → fotos do evento selecionado
  → UX temporal pura

SANGHA:
  → relatos de devotos
  → UX temporal pura

REVISTA:
  → curadoria manual dos passages mais relevantes
  → 3 estados: coleta | edição | publicada
  → ponte para Biblioteca (Fase 3)
```

---

## 11. GAVETA ESQUERDA — TOUR

```text
ACIONAMENTO: botão "Tours" no header (já existe)

CENÁRIO A — Visit com tour:
  ┌─────────────────────────────────────────┐
  │  América do Sul 2026                    │
  │  Brasil · Argentina · Chile             │
  │  Visita 3 de 12                         │
  ├─────────────────────────────────────────┤
  │  ○ Rio de Janeiro    12–14 mar    ✅    │
  │  ● São Paulo         16–18 mar    ▶     │
  │  ○ Buenos Aires      20–22 mar    📅    │
  ├─────────────────────────────────────────┤
  │  Outras tours (colapsadas)              │
  └─────────────────────────────────────────┘

CENÁRIO B — Visit sem tour / legado:
  ┌─────────────────────────────────────────┐
  │  Visitas                                │
  ├─────────────────────────────────────────┤
  │  ○ Vrindavan         fev 2019           │
  │  ● São Paulo         mar 2019    ▶      │
  │  ○ Mayapur           abr 2019           │
  └─────────────────────────────────────────┘

STATUS DE CONTEÚDO POR VISITA:
  ✅  conteúdo publicado
  ▶   você está aqui
  📅  programado (futuro)
  ⚙️  em preparação

TOUR É OPCIONAL:
  Visit sem tour_id → gaveta funciona normalmente
  Visit legado      → nunca quebra
  Campos de tour    → renderização condicional
```

---

## 12. DEPENDÊNCIAS ENTRE ELEMENTOS

```text
Seletor de Dia
  └──► Agenda     (recarrega eventos do dia)
  └──► Stage      (NÃO muda automaticamente)

Agenda
  └──► Stage      (evento selecionado → carrega mídia)
  └──► HK         (katha_ref → abre seção HK)

Stage
  └──► Agenda     (emite evento ativo via postMessage)
  └──► HK         (segment ativo → scroll até passage)
  └──► Seletor    (badge 🔴 quando live)

HK
  └──► Stage      (timestamp → seek)
  └──► Biblioteca (Fase 2+)
```

---

## 13. FONTES DE EDIÇÃO — CONTRATO DE PRIORIDADE

```text
FONTE         PAPEL                        PRIORIDADE
────────────  ───────────────────────────  ──────────
Streamlit     curadoria humana intencional     1ª
Bot Telegram  registro ao vivo                 2ª
WP-Admin      campos escalares / emergência    3ª
Automação     rascunho — nunca sobrescreve     4ª

STATUS DO TIMELINE_JSON:
  draft     → criado por automação / editor
  planned   → revisado pelo Streamlit
  live      → visita em curso (Bot tem escrita)
  archived  → encerrada e curada
              automação e bot perdem escrita
              apenas Streamlit e WP-Admin editam

REGRA DE OURO:
  A automação NUNCA sobrescreve campo
  confirmado por humano.
```

---

## 14. MODAL DE ASSETS ÓRFÃOS

```text
TRIGGER: assets sem event_key
ACESSO:  colaborador autenticado
FUNÇÃO:  abrigo de conteúdo não associado
         enriquecível posteriormente
VISÍVEL: fora do Stage principal
```

---

## 15. AUTENTICAÇÃO DE COLABORADOR

```text
COLABORADOR FIXO (editor, transcritor):
  → /vincular no Bot → token Telegram (Opção B)
  → token de longa duração, renovado pelo Bot

COLABORADOR PONTUAL (fotógrafo de evento):
  → Admin gera link → URL com token (Opção C)
  → link específico por evento, expira por data

ADMIN / EMERGÊNCIA:
  → Login discreto (Opção A)
  → acesso sem Telegram

REGRA DO STAGE:
  O Stage nunca muda para o devoto comum.
  Ferramentas existem mas são invisíveis.
  O colaborador vê o mesmo Stage + profundidade.
```

---

## 16. RISCOS CONHECIDOS — A RESOLVER ANTES DO LANÇAMENTO

```text
⚠️  R1 — Handlers AJAX do drawer não confirmados no PHP
         vana_get_tours e vana_get_tour_visits
         precisam ser verificados / implementados

⚠️  R2 — Prev/next precisa de condicional por tour_id
         hoje é cronológico global
         corrigir em _bootstrap.php

⚠️  R3 — Duplicação de implementação do drawer
         dois blocos no mesmo visit-scripts.php
         consolidar antes do lançamento

⚠️  R4 — window.vanaDrawer não garantidamente populado
         verificar inicialização no wp_json_encode
```

---

## 17. FASES DE IMPLEMENTAÇÃO

```text
FASE 1 — MVP (este contrato)
  ✅ Hero + Seletor de Dia
  ✅ Stage (vídeo + neutro + aguardando)
  ✅ Agenda (gaveta direita)
  ✅ Chip Bar
  ✅ HK básico (listagem + passages)
  ✅ Galeria
  ✅ Sangha
  ✅ Gaveta Tour (dois cenários)
  ✅ LocationPin

FASE 2 — Profundidade
  → HK temático (Biblioteca)
  → Reactions persistentes
  → Filtros por taxonomia

FASE 3 — Curadoria
  → Revista publicada
  → Ponte Biblioteca

FASE 4 — Colaboração
  → Clip Devocional
  → Fila HK via Bot
  → Camada colaborador no Stage

FASE 5 — Automação
  → vana-forja / vana-trator
  → Ingestão automática de media/segments
```

---

## 18. ARQUIVOS A GERAR — SEMANA 1

```text
ARQUIVO                          FUNÇÃO
───────────────────────────────  ──────────────────────────────
EDITORIAL_MANUAL.md              Guia de operação das 4 fontes
class-vana-cpts.php              Registro dos CPTs e campos
single-vana_visit.php            Template principal da visita
_bootstrap.php (ajuste)          Condicional tour_id no prev/next
hero-header.php (ajuste)         Gaveta tour — dois cenários
visit-scripts.php (consolidar)   Eliminar duplicação do drawer
```

---

*Contrato fechado em 22/03/2026.*
*Próxima revisão: após implementação da Fase 1.*
```

---

Marcel, contrato gerado e fechado. ✅

**Próximo passo imediato:**

```text
OPÇÃO A → EDITORIAL_MANUAL.md
  Guia operacional das 4 fontes de edição
  Como cada devoto/colaborador opera o sistema

OPÇÃO B → class-vana-cpts.php
  Registro formal dos CPTs, campos e REST API
  Base técnica para tudo que vem depois

OPÇÃO C → _bootstrap.php (ajuste cirúrgico)
  Corrigir prev/next por tour_id
  Resolve o Risco R2 imediatamente
```

Qual dos três você quer primeiro? 🙏