# Revisão do Smoke Test + Diagnóstico + Próximos Passos

Marcel, o agente fez algo muito valioso aqui: **executou um smoke test antes de avançar para Fase D**, e isso revelou um gap de arquitetura importante. Vou analisar o resultado com precisão.

---

## Avaliação do Smoke Test

### ✅ O que está funcionando

```text
endpoint200       → REST /stage/{event_key} retorna HTTP 200
urlUpdated        → history.pushState funcionando após clique
stageV2Header     → header x-vana-endpoint: stage-v2 confirmado
backForwardWorks  → navegação browser back/forward OK
blankStage        → stage não fica em branco (SSR inicial OK)
requestLoop       → sem loop de requisições
consoleErrors     → zero erros de console JS
```

### ❌ O que está quebrado

```text
htmlInjected: false
Network traces: []

O VanaEventController.js atualiza a URL (pushState)
mas NÃO dispara fetch/HTMX para #vana-stage.
O conteúdo do stage não muda após clique.
```

### Diagnóstico confirmado

```text
CAUSA RAIZ:
  O ciclo click → fetch → swap HTMX não está implementado.

  O que existe hoje:
    click em event-selector → history.pushState (URL atualiza)

  O que falta:
    click em event-selector → fetch /vana/v1/stage/{key}
                            → swap HTML em #vana-stage

LOCALIZAÇÃO DO GAP:
  Fase E — VanaVisitController.js
  Bloco A da migração: event-selector + stage swap
```

---

## Decisão de Sequência

```text
SITUAÇÃO:
  Fase D (UI components) cria markup novo.
  Fase E (JS controller) conecta comportamento.
  O gap identificado está em Fase E.

PERGUNTA: executar D antes de E ou inverter?

DECISÃO: manter ordem D → E

RAZÃO:
  Fase E precisa dos seletores CSS/ID que Fase D vai criar.
  Inverter causaria retrabalho no controller.
  O gap do stage swap é documentado como ponto de entrada de Fase E.

EXCEÇÃO:
  Se durante Fase D o agente encontrar que
  VanaEventController.js já tem estrutura parcial do swap,
  reportar antes de continuar.
```

---

## Registro Formal do Gap

```text
GAP-001 — Stage Swap não implementado
  Sintoma:   click no event-selector não atualiza conteúdo do stage
  Causa:     fetch/HTMX para #vana-stage ausente no JS atual
  Smoke:     htmlInjected: false, Network traces: []
  Fase:      E (VanaVisitController.js, Bloco A)
  Prioridade: ALTA — é o comportamento central da página
  Bloqueador: não bloqueia Fase D (markup)
              bloqueia validação visual completa do stage
```

---

## Avaliação do Trabalho do Agente

```text
✅ Instalou Playwright do zero sem pedir ajuda
✅ Corrigiu stale ElementHandle autonomamente
✅ Corrigiu event= → event_key= autonomamente
✅ Interpretou Network traces: [] como ausência de fetch
✅ Diagnóstico de causa raiz correto
✅ Não avançou para código sem reportar o gap
```

---

## Autorização para Fase D

```text
STATUS: AUTORIZADA

O gap identificado é de Fase E, não bloqueia Fase D.
O agente pode avançar para os componentes de UI.

LEMBRETE PARA O AGENTE:
  Antes de cada sub-tarefa D1→D6,
  ler o arquivo atual completo.
  Só então aplicar ajuste cirúrgico.
  Confirmar gate entre D1 e D2 antes de continuar.
```

### Ordem das sub-tarefas

```text
D1 → extrair tour-drawer.php do hero-header.php
D2 → ajustar hero-header.php (tour-counter + day-selector)
D3 → ajustar stage.php (neutro + transição + share + open-hk)
D4 → criar agenda-drawer.php
D5 → criar sections.php
D6 → ajustar anchor-chips.php (section-tabs + is-active)

Gate entre D1 e D2:
  [ ] Página carrega sem erro
  [ ] Drawer abre (mesmo que via JS atual)

Gate final da Fase D:
  [ ] PHP lint OK em todos os arquivos tocados
  [ ] Página carrega sem PHP notice
  [ ] Tour drawer renderiza no novo arquivo
  [ ] Stage exibe modo neutro quando sem evento
  [ ] Agenda drawer renderiza (mesmo vazia)
  [ ] Sections renderiza os 4 painéis
  [ ] Chip bar alterna estado is-active
  [ ] Seletores JS do drawer ainda funcionam
  [ ] Zero erros de console JS
```

### Ponto de entrada para Fase E após D

```text
Bloco A de Fase E:
  Implementar: click → fetch /stage/{key} → swap #vana-stage
  Arquivo:     assets/js/VanaVisitController.js
  Validação:   smoke test htmlInjected: true
               Network traces: [{url: /stage/*, status: 200}]
```

Marcel, pode passar para o agente com autorização para D1. 🙏