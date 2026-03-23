# Aprovação da Matriz + Decisões sobre os 3 Gaps

Marcel, a Fase A está excelente. A matriz está precisa e o agente identificou 3 gaps de consistência reais entre os documentos. Vou resolver cada um agora antes de liberar a Fase B.

---

## Resolução dos 3 Gaps de Consistência

### Gap 1 — POST /react na Fase 1

```text
DECISÃO: REMOVER da Fase 1 completamente

RAZÃO:
  O adendo v2.1 já havia movido para Fase 2.
  O agente propõe "manter como exceção explícita".
  Mas uma exceção cria ambiguidade para futuras Fases.

REGRA FINAL:
  Fase 1 = zero endpoints POST.
  POST /react entra APENAS na Fase 2.
  A classe REST da Fase 1 não implementa o método post_react.
  Deixar o método como stub comentado é aceitável:

  // POST /vana/v1/react → Fase 2
  // public static function post_react() {}
```

### Gap 2 — Enqueue do VanaVisitController.js

```text
DECISÃO: wp_enqueue_script independente, no footer, sem handle-dep

RAZÃO:
  visit-scripts.php é include PHP → não tem handle WP.
  Não é possível declarar como dep formal.

REGRA FINAL:
  wp_enqueue_script(
    'vana-visit-controller',
    plugin_dir_url(__FILE__) . 'assets/js/VanaVisitController.js',
    [],      ← sem dependência declarada
    '1.0.0',
    true     ← footer = true
  );

  Garantir no template que o include de visit-scripts.php
  acontece ANTES do wp_footer().
  
  Ordem esperada no HTML renderizado:
    1. <script> visit-scripts.php (include PHP)
    2. <script src="VanaVisitController.js"> (wp_enqueue footer)

  Validar no DevTools antes de avançar para Fase E.
```

### Gap 3 — tour-drawer.php (criar, não ajustar)

```text
DECISÃO: STATUS CORRIGIDO para 🆕 CRIAR

RAZÃO:
  O agente confirmou que o arquivo não existe.
  O adendo v2.1 classificou como ⚙️ AJUSTAR por engano.
  O markup está embutido no hero-header.php hoje.

REGRA FINAL:
  🆕 CRIAR templates/visit/parts/tour-drawer.php
  ⚙️ AJUSTAR templates/visit/parts/hero-header.php
    (remover bloco do drawer que será extraído)

  O JS do drawer permanece em visit-scripts.php por ora.
  Migra para VanaVisitController.js na Fase E.
  Na Fase D: apenas garantir que os seletores CSS/ID
  do JS existente ainda encontram os elementos
  após a extração para o novo arquivo.
```

---

## Resolução do Gap REST — namespace conflito

```text
AGENTE IDENTIFICOU:
  namespace vana/v1 já tem rotas em:
    class-vana-query-api.php
    class-vana-rest-stage.php

DECISÃO:
  Antes de criar class-vana-rest-api.php,
  o agente deve:

  PASSO 1 — Listar todas as rotas registradas em /vana/v1/:
    grep register_rest_route em ambas as classes existentes
    Montar lista: método + path

  PASSO 2 — Confirmar que nenhum dos paths da Fase 1 conflita:
    /vana/v1/kathas     → checar
    /vana/v1/media      → checar
    /vana/v1/sangha     → checar
    /vana/v1/revista    → checar

  PASSO 3 — Se conflitar:
    Sufixar com versão: /vana/v1/visit/kathas (por exemplo)
    Reportar antes de criar.

  PASSO 4 — Se não conflitar:
    Criar class-vana-rest-api.php conforme spec v1 seção 16
    SEM o método post_react (Gap 1 resolvido acima)
```

---

## Matriz de Gap Final — Homologada

````markdown
# MATRIZ DE GAP — FASE 1 — HOMOLOGADA
# 22/03/2026

ITEM                                        STATUS      AÇÃO AUTORIZADA
──────────────────────────────────────────  ──────────  ──────────────────────────────────────
1.  CPTs (visit/tour/katha)                 ✅ OK        não tocar
2.  Handlers AJAX de Tour Drawer            ✅ OK        não tocar
3.  Bootstrap prev/next escopado            ⚙️ AJUSTAR   condicional tour_id + fallback global
4.  Payload vanaDrawer inline               ⚙️ AJUSTAR   adicionar 4 campos, não recriar
5.  Template single-vana_visit.php          ✅ OK        não tocar
6.  Hero header (tour-counter + day-sel.)   ⚙️ AJUSTAR   complementar campos e UX
7.  Tour Drawer arquivo dedicado            🆕 CRIAR     extrair do hero; JS permanece onde está
8.  Stage modos neutro/transição/erro       ⚙️ AJUSTAR   complementar estados e ações
9.  Agenda Drawer                           🆕 CRIAR     arquivo novo em parts/
10. Chip Bar                                ⚙️ AJUSTAR   convergir para section-tab do spec
11. Sections unificadas                     🆕 CRIAR     arquivo novo em parts/
12. VanaVisitController.js                  🆕 CRIAR     enqueue footer sem handle-dep
13. visit-scripts.php legado                ⚙️ AJUSTAR   remover bloco duplicado; migrar na Fase E
14. CSS tokens e estados                    ⚙️ AJUSTAR   adicionar sem remover existentes
15. REST API (GET only, sem /react)         🆕 CRIAR     checar namespace antes de criar
16. class-vana-cpts.php agregador           ❌ IGNORAR   CPTs já existem
17. functions.php do tema                   ❌ IGNORAR   plugin usa vana-mission-control.php

GAPS RESOLVIDOS:
  Gap 1 → POST /react removido da Fase 1 definitivamente
  Gap 2 → enqueue independente no footer, ordem validada no DevTools
  Gap 3 → tour-drawer.php reclassificado como 🆕 CRIAR
  Gap REST → checar namespace antes de criar a classe REST
````

---

## Autorização para iniciar Fase B

```text
✅ FASE A — APROVADA
✅ GAPS — RESOLVIDOS
✅ MATRIZ — HOMOLOGADA

FASE B — AUTORIZADA

AGENTE: pode iniciar agora.

ESCOPO EXATO DA FASE B:
  ARQUIVO: templates/visit/_bootstrap.php

  MUDANÇA 1 — prev/next escopado por tour_id
    Localizar o bloco atual de prev/next
    Aplicar condicional conforme spec v1 seção 5
    Preservar fallback cronológico global

  MUDANÇA 2 — payload vanaDrawer inline
    Localizar o bloco window.vanaDrawer existente
    Adicionar os 4 campos faltantes:
      tourId, tourTitle, tourUrl, currentVisit
    NÃO criar novo wp_localize_script
    NÃO remover campos existentes

GATE DE REGRESSÃO ANTES DE AVANÇAR:
  [ ] Página da visita carrega sem PHP notice
  [ ] Drawer de tour abre e lista tours
  [ ] Stage carrega um vídeo
  [ ] window.vanaDrawer tem os 4 novos campos no DevTools
  [ ] Sem erro de console JS
```

Pode passar para o agente, Marcel. 🙏