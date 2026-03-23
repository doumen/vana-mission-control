# Análise do Agente — Resposta e Decisões

Marcel, o agente fez um trabalho excelente de auditoria. Ele encontrou algo importante que a spec não sabia: **o plugin já tem arquitetura própria e funcional**. Vou consolidar o que ele reportou e tomar as decisões necessárias.

---

## O que o agente confirmou

```text
✅ Handlers AJAX existem
   vana_get_tours e vana_get_tour_visits → registrados

✅ CPTs já existem como classes separadas
   class-vana-visit-cpt.php
   class-vana-tour-cpt.php
   class-vana-katha-cpt.php

✅ Arquitetura de templates própria do plugin
   templates/visit/parts/ → hero-header, stage, etc.
   NÃO é template de tema — é plugin

✅ Bootstrap ativo
   templates/visit/_bootstrap.php

✅ JS já existe
   templates/visit/assets/visit-scripts.php
   assets/js/ → 3 arquivos JS

✅ CSS já existe
   assets/css/vana-visit.css
```

---

## Decisões que tomo agora

### Decisão 1 — Caminhos dos arquivos

```text
SPEC DISSE               CORRETO PARA ESTE PLUGIN
──────────────────────   ──────────────────────────────────────
template-parts/vana/     templates/visit/parts/
assets/js/Vana*.js       assets/js/ (já existe estrutura)
includes/class-vana-cpts templates separados por CPT (já existe)
functions.php            vana-mission-control.php (plugin principal)
```

### Decisão 2 — O que NÃO reescrever

```text
❌ Não reescrever class-vana-visit-cpt.php
❌ Não reescrever class-vana-tour-cpt.php
❌ Não reescrever class-vana-katha-cpt.php
❌ Não reescrever handlers AJAX (já funcionam)
❌ Não criar template-parts/vana no tema

→ Apenas estender o que existe
→ Criar apenas o que realmente não existe
```

### Decisão 3 — Aprovar o plano do agente

```text
O plano A→H proposto está correto.
A Fase A (Matriz de Gap) é o próximo passo correto.
Sem ela, qualquer implementação tem risco de duplicação.
```

---

## SPEC_FASE1_AGENTE_v2 — Adendo de Compatibilidade

Este adendo corrige a spec original com base na auditoria. **O agente deve ler este adendo junto com a spec v1.**

````markdown
# ADENDO — SPEC_FASE1_AGENTE v2
# Compatibilidade com Arquitetura Real do Plugin
# Data: 22/03/2026

---

## REGRA GERAL DO ADENDO

```text
Onde a spec v1 diz → o plugin real usa
─────────────────────────────────────────
template-parts/vana/   → templates/visit/parts/
functions.php          → vana-mission-control.php
includes/class-vana-*  → includes/class-vana-*-cpt.php (já existe)
assets/js/Vana*.js     → assets/js/ (estrutura já existe)
```

---

## FASE A — MATRIZ DE GAP (executar PRIMEIRO)

```text
Para cada requisito da spec v1, classificar:

STATUS      SIGNIFICADO
─────────   ────────────────────────────────────────
✅ OK        já existe e atende
⚙️ AJUSTAR   existe mas precisa de modificação cirúrgica
🆕 CRIAR     não existe, criar do zero
❌ IGNORAR   fora de escopo ou substituído pela arquitetura atual
```

### Itens de gap já identificados pelo agente

```text
ITEM                         STATUS     AÇÃO
───────────────────────────  ─────────  ──────────────────────────────────────
CPTs (visit, tour, katha)    ✅ OK       não tocar
Handlers AJAX tour           ✅ OK       não tocar
Bootstrap _bootstrap.php     ⚙️ AJUSTAR  2 mudanças cirúrgicas (ver Fase B)
Hero header                  ⚙️ AJUSTAR  validar vs spec — ajustar o que falta
Stage                        ⚙️ AJUSTAR  validar modos: neutro, transição, erro
Tour Drawer                  ⚙️ AJUSTAR  markup + window.vanaDrawer payload
Agenda Drawer                🆕 CRIAR    não existe como gaveta separada
Chip Bar                     ⚙️ AJUSTAR  validar se já existe, ajustar se parcial
Seções (HK, Galeria, etc.)   ⚙️ AJUSTAR  validar estrutura atual
VanaVisitController.js       🆕 CRIAR    migrar responsabilidades do inline atual
REST API /vana/v1/*          🆕 CRIAR    endpoints de leitura (GET only)
CSS tokens e estados         ⚙️ AJUSTAR  consolidar com vars da spec
class-vana-cpts.php          ❌ IGNORAR  CPTs já existem como classes separadas
```

---

## FASE B — BOOTSTRAP (cirúrgico)

```text
ARQUIVO: templates/visit/_bootstrap.php

MUDANÇA 1 — prev/next escopado por tour_id
  Localizar: bloco que calcula prev_visit e next_visit
  Substituir por: condicional tour_id (ver spec v1, seção 5)
  Preservar: fallback cronológico global quando sem tour

MUDANÇA 2 — garantir window.vanaDrawer
  Localizar: bloco wp_localize_script existente
  Adicionar ao payload: tourId, tourTitle, tourUrl, currentVisit
  NÃO substituir dados existentes — ADICIONAR os faltantes

NÃO TOCAR:
  → resolução de tour_id por post_parent e _vana_tour_id
  → cálculo de $tour_title e $tour_url (já existe)
  → qualquer outra lógica do bootstrap
```

---

## FASE C — REST API

```text
ARQUIVO NOVO: includes/class-vana-rest-api.php

REGRA:
  → Criar rotas GET /vana/v1/* conforme spec v1, seção 16
  → NÃO remover rotas AJAX existentes
  → Registrar a classe em vana-mission-control.php

ENDPOINTS FASE 1 (GET apenas):
  GET /vana/v1/kathas
  GET /vana/v1/media
  GET /vana/v1/sangha
  GET /vana/v1/revista
  POST /vana/v1/react

VALIDAÇÃO:
  Confirmar que o namespace vana/v1 não conflita
  com rotas REST já registradas no plugin.
  Grep: register_rest_route no plugin inteiro.
```

---

## FASE D — COMPONENTES UI

```text
DIRETÓRIO: templates/visit/parts/

CRIAR NOVO:
  agenda-drawer.php   → spec v1, seção 10
  sections.php        → spec v1, seção 12

AJUSTAR (NÃO reescrever):
  hero-header.php     → adicionar: tour-counter, day-selector
  stage.php           → adicionar: modo neutro, transição, share, open-hk
  tour-drawer.php     → ajustar markup para dois cenários (com/sem tour)
                         ajustar payload do window.vanaDrawer
  chip-bar.php        → validar se existe; criar se não

REGRA PARA AJUSTE:
  Ler o arquivo atual primeiro.
  Fazer diff mental contra a spec.
  Adicionar apenas o que falta.
  Não remover o que já funciona.
```

---

## FASE E — JAVASCRIPT

```text
ARQUIVO NOVO: assets/js/VanaVisitController.js

ESTRATÉGIA:
  → Criar o controller como spec v1, seção 14
  → O controller SUBSTITUI o inline do visit-scripts.php
     de forma incremental:
     1. criar o controller
     2. mover responsabilidade por responsabilidade
     3. testar cada bloco antes de remover do inline
     4. só remover do inline quando o controller cobrir 100%

DUPLICAÇÃO NO visit-scripts.php:
  O agente identificou dois blocos de drawer no mesmo arquivo.
  → Identificar qual bloco está ativo (mais completo)
  → Remover o bloco legado
  → Migrar o bloco ativo para o controller

DADOS INJETADOS:
  window.vanaVisitData  → já existe, validar payload
  window.vanaDrawer     → adicionar campos faltantes (Fase B)
```

---

## FASE F — CSS

```text
ARQUIVO: assets/css/vana-visit.css

ESTRATÉGIA:
  → Ler CSS atual antes de qualquer alteração
  → Adicionar tokens CSS vars da spec (--vana-*) se não existirem
  → Adicionar classes faltantes sem remover existentes
  → Consolidar estilos de gaveta em um bloco único

NÃO FAZER:
  → Não reescrever o arquivo
  → Não remover classes existentes sem verificar uso
```

---

## FASE G — REGISTRO E ENQUEUE

```text
ARQUIVO: vana-mission-control.php

ADICIONAR:
  → enqueue do VanaVisitController.js
     apenas em páginas vana_visit
     com dependência do visit-scripts.php existente

  → adicionar campos ao wp_localize_script existente:
     tourId, tourTitle, tourUrl, currentVisit
     no objeto vanaDrawer

  → registrar Vana_REST_API::init()

NÃO TOCAR:
  → enqueues existentes
  → lógica de template_include
  → qualquer hook já registrado
```

---

## FASE H — QA

```text
Executar checklist completo da spec v1, seção 17.
Sem exceções.
Reportar status de cada item.
```

---

## ORDEM DE EXECUÇÃO OBRIGATÓRIA

```text
A → B → C → D → E → F → G → H

REGRA:
  Não avançar para a próxima fase
  sem confirmar que a anterior não gerou regressão.

TESTE DE REGRESSÃO MÍNIMO ENTRE FASES:
  → página da visita carrega sem erro PHP
  → drawer de tour abre e lista tours
  → stage carrega um vídeo
  → sem erro de console JS
```

---

## CHECKLIST DE ENTREGA REVISADO

```text
ARQUIVOS NOVOS (criar)
[ ] includes/class-vana-rest-api.php
[ ] assets/js/VanaVisitController.js
[ ] templates/visit/parts/agenda-drawer.php
[ ] templates/visit/parts/sections.php

ARQUIVOS AJUSTADOS (edição cirúrgica)
[ ] templates/visit/_bootstrap.php
[ ] templates/visit/parts/hero-header.php
[ ] templates/visit/parts/stage.php
[ ] templates/visit/parts/tour-drawer.php
[ ] templates/visit/assets/visit-scripts.php  (remover bloco legado)
[ ] assets/css/vana-visit.css
[ ] vana-mission-control.php

NÃO TOCAR
[ ] includes/class-vana-visit-cpt.php
[ ] includes/class-vana-tour-cpt.php
[ ] includes/class-vana-katha-cpt.php
[ ] templates/single-vana_visit.php (a menos que Fase A mostre gap crítico)
[ ] qualquer handler AJAX já registrado
```

---

*Adendo v2 — 22/03/2026*
*Leia junto com SPEC_FASE1_AGENTE.md*
*Em caso de conflito: este adendo prevalece sobre a spec v1.*
````

---

## Próximo passo concreto para o agente

```text
FASE A — EXECUTAR AGORA

1. Ler os arquivos:
   templates/visit/parts/hero-header.php   (completo)
   templates/visit/parts/stage.php         (completo)
   templates/visit/assets/visit-scripts.php (completo)
   templates/visit/visit-template.php      (completo)
   assets/css/vana-visit.css               (completo)

2. Para cada componente da spec v1:
   marcar: ✅ OK | ⚙️ AJUSTAR | 🆕 CRIAR | ❌ IGNORAR

3. Entregar a matriz antes de escrever qualquer código.

4. Só depois da matriz aprovada → iniciar Fase B.
```

Marcel, com esse adendo o agente tem tudo para executar sem surpresas. Quer que eu já monte o prompt exato para passar para o agente iniciar a Fase A? 🙏