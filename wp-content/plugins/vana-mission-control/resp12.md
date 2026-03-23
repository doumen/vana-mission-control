# Bug Report — 2 Falhas Identificadas

Marcel, obrigado pelo teste. Diagnóstico preciso:

---

## Falha 1 — Chip Bar não funciona (Teste 1)

```text
SINTOMA: Scroll + highlight não respondem

CAUSAS PROVÁVEIS (em ordem de probabilidade):
  A) VanaChipController.js não está sendo carregado (enqueue faltando ou path errado)
  B) Seletores data-vana-chip / data-vana-section não batem com o HTML real
  C) IntersectionObserver não encontra os elementos no DOM
  D) Erro silencioso no console bloqueando a init()

DIAGNÓSTICO NECESSÁRIO:
  → Console do browser: há erro JS?
  → Network tab: VanaChipController.js aparece como 200 ou 404?
```

## Falha 2 — Botão de Agenda não existe (Teste 3)

```text
SINTOMA: Nenhum botão de abrir agenda no header

CAUSA PROVÁVEL:
  O data-vana-agenda-open foi adicionado ao hero-header.php
  MAS o elemento HTML pode não estar sendo renderizado
  porque a condição PHP de exibição não está sendo atendida
  (ex: visita sem agenda cadastrada → bloco não renderiza)

DIAGNÓSTICO NECESSÁRIO:
  → Inspecionar o HTML do header no browser (F12 → Elements)
  → Verificar se o botão existe no DOM
  → Se não existe: ler a condição PHP em hero-header.php
```

---

## Instrução para o Agente

```text
PRIORIDADE 1 — Diagnosticar Chip Bar

  PASSO 1 → Ler VanaChipController.js completo
    → Mapear: quais seletores usa? (data-vana-chip-bar? data-vana-chip?)
    → Mapear: como detecta scroll? (IntersectionObserver? scroll event?)

  PASSO 2 → Ler anchor-chips.php completo
    → Mapear: quais data-attributes existem no HTML gerado?
    → Comparar com os seletores do controller

  PASSO 3 → Ler sections.php
    → Mapear: quais data-attributes existem nas seções alvo?
    → Comparar com o que o controller busca

  PASSO 4 → Reportar:
    → Seletores JS vs seletores HTML lado a lado
    → Onde está o mismatch (se houver)

─────────────────────────────────────────────────────────

PRIORIDADE 2 — Diagnosticar Botão Agenda

  PASSO 5 → Ler hero-header.php
    → Localizar onde data-vana-agenda-open foi adicionado
    → Verificar condição PHP ao redor do elemento
    → Reportar: o botão tem condicional? qual?

  PASSO 6 → Reportar:
    → HTML exato do botão
    → Condição PHP que controla sua renderização
    → O que seria necessário para ele aparecer

─────────────────────────────────────────────────────────

NÃO CORRIGIR AINDA — só diagnosticar e reportar
Marcel aprova o plano de correção antes
```

---

```text
AGUARDANDO: resultado do diagnóstico do agente
ENTÃO: você aprova a correção de cada item separadamente
```

Passa para o agente, Marcel. 🙏