# Revisão da Fase C + Decisão sobre /kathas + Autorização para Fase D

Marcel, o agente executou a Fase C com precisão. Vou revisar cada decisão antes de liberar a Fase D.

---

## Revisão da Fase C

### ✅ O que foi feito corretamente

```text
1. Mapeamento de conflitos antes de criar qualquer arquivo
   → exatamente o que foi pedido no Passo 1-3

2. /kathas excluída por conflito
   → adendo diz "não duplicar" → correto

3. 3 endpoints GET criados como stubs
   → /media, /sangha, /revista → correto

4. POST /react como stub comentado
   → Gap 1 respeitado → correto

5. PHP lint OK em ambos os arquivos
   → zero erros de sintaxe
```

### ✅ Decisão sobre /kathas — Homologada

```text
SITUAÇÃO:
  /vana/v1/kathas já existe em Vana_Hari_Katha_API
  com params visit_id + day

DIVERGÊNCIA COM SPEC:
  Spec v1 define param event_key (não visit_id + day)

DECISÃO:
  Não duplicar a rota — correto.
  A harmonização de event_key entra na Fase 2.
  Registrar como débito técnico documentado.

DÉBITO TÉCNICO DT-001:
  Classe:   api/class-vana-hari-katha-api.php
  Rota:     GET /vana/v1/kathas
  Situação: params atuais = visit_id + day
  Spec v1:  param esperado = event_key
  Ação:     Fase 2 → adicionar event_key como alias
             ou criar parâmetro adicional compatível
  Impacto:  baixo (não bloqueia Fase 1)
```

### ⚠️ Gate de Regressão

```text
Marcel, antes de avançar para Fase D validar em browser/Postman:

[ ] GET /wp-json/vana/v1/media   → HTTP 200, {"items":[],"total":0}
[ ] GET /wp-json/vana/v1/sangha  → HTTP 200, {"items":[],"total":0}
[ ] GET /wp-json/vana/v1/revista → HTTP 200, {"items":[],"total":0}
[ ] Rotas anteriores sem regressão:
    GET /wp-json/vana/v1/kathas  → ainda retorna HTTP 200
    GET /wp-json/vana/v1/visits  → ainda retorna HTTP 200
[ ] Zero PHP notices no log do WordPress
```

---

## Autorização para Fase D — Condicional

```text
STATUS: AUTORIZADA com pré-requisito

PRÉ-REQUISITO:
  Marcel confirmar gate de regressão da Fase C acima.
```

---

## Escopo Exato da Fase D

A Fase D tem **5 sub-tarefas** na ordem obrigatória:

### D1 — Extrair Tour Drawer do hero

```text
ARQUIVO ALVO:   templates/visit/parts/hero-header.php
CRIAR:          templates/visit/parts/tour-drawer.php

PASSO 1 — Ler hero-header.php completo
PASSO 2 — Identificar o bloco HTML do tour drawer
           (âncoras, lista de tours, overlay, etc.)
PASSO 3 — Recortar esse bloco exato do hero-header.php
PASSO 4 — Criar tour-drawer.php com o bloco recortado
           Adicionar os dois cenários conforme spec v1 seção 8:
             Cenário A: $tour_id existe → lista da tour
             Cenário B: $tour_id null   → lista cronológica global
PASSO 5 — No hero-header.php (ou visit-template.php):
           Substituir o bloco recortado por include do novo arquivo
PASSO 6 — Verificar que seletores CSS/ID do JS existente
           ainda encontram os elementos após extração

REGRA: JS do drawer permanece em visit-scripts.php
       Migra para VanaVisitController.js na Fase E
```

### D2 — Ajustar hero-header.php

```text
ARQUIVO: templates/visit/parts/hero-header.php

APÓS D1 (extração do drawer), adicionar:
  → tour-counter: "Visita X de Y da tour [nome]"
     Dados disponíveis em window.vanaDrawer após Fase B
  → day-selector: tabs ou botões para trocar de dia
     Usar dados já disponíveis no bootstrap

REGRA: não remover nada já existente
       apenas complementar
```

### D3 — Ajustar stage.php

```text
ARQUIVO: templates/visit/parts/stage.php

ADICIONAR (sem remover existente):
  → modo neutro: estado inicial sem evento selecionado
  → tela de transição: loading entre eventos
  → botão share: compartilhar evento atual
  → botão open-hk: abrir gaveta de hari-katha

REFERÊNCIA: spec v1 seção 9
```

### D4 — Criar agenda-drawer.php

```text
CRIAR: templates/visit/parts/agenda-drawer.php

CONTEÚDO conforme spec v1 seção 10:
  → estrutura de gaveta lateral
  → lista de eventos por dia
  → controle de idioma (PT/EN)
  → estado vazio e estado carregando
```

### D5 — Criar sections.php

```text
CRIAR: templates/visit/parts/sections.php

CONTEÚDO conforme spec v1 seção 12:
  → wrapper unificado para:
     Hari-Katha | Galeria | Sangha | Revista
  → cada seção como painel com ID único
  → estado ativo controlado por chip-bar

ANTES DE CRIAR:
  Verificar se alguma estrutura parcial já existe
  em visit-template.php ou outros parts/
  Não duplicar o que já existir
```

### D6 — Ajustar chip-bar (anchor-chips.php)

```text
ARQUIVO: templates/visit/parts/anchor-chips.php

SITUAÇÃO: existe como âncoras, spec pede section-tabs

AÇÃO:
  Ler anchor-chips.php completo
  Avaliar gap contra spec v1 seção 11
  Ajustar para:
    → chips como tabs (não âncoras de scroll)
    → estado .is-active por painel visível
    → IDs alinhados com sections.php (D5)

REGRA: não reescrever — ajustar cirurgicamente
```

---

## Ordem obrigatória dentro da Fase D

```text
D1 → D2 → D3 → D4 → D5 → D6

GATE ENTRE D1 e D2:
  Após extrair o drawer, confirmar que a página
  carrega sem erro antes de continuar.

GATE FINAL DA FASE D:
  [ ] Página carrega sem PHP notice
  [ ] Tour drawer abre (markup no novo arquivo)
  [ ] Stage exibe modo neutro quando sem evento
  [ ] Agenda drawer renderiza (mesmo vazia)
  [ ] Sections renderiza os 4 painéis
  [ ] Chip bar alterna estado .is-active
  [ ] Seletores JS do drawer ainda funcionam
  [ ] Zero erros de console JS
```

---

Marcel, assim que o gate da Fase C passar em browser, o agente pode ir direto para D1. 🙏