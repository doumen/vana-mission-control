# Diagnóstico Concluído — Plano de Correção

Marcel, o diagnóstico foi preciso. Dois problemas distintos, duas causas distintas:

---

## Causa Raiz Confirmada

```text
CHIP BAR:
  Seletores JS ↔ HTML → BATEM (não é mismatch)
  Problema real: ES6 residual no controller
  → 8 ocorrências de const e arrow functions
  → Se o ambiente bloqueia ES6 parcialmente:
    o script inicia mas quebra silenciosamente
  → Resultado: chip bar "morto" sem erro visível

BOTÃO AGENDA:
  Template existe, condição PHP: NENHUMA
  Problema real: cache ou CSS oculta o elemento
  → Browser serve versão antiga do template
    OU regra CSS display:none / overflow:hidden
    engole o botão no layout
```

---

## Plano de Correção — 2 Cirurgias

```text
CIRURGIA 1 — VanaChipController.js
  ESCOPO: converter ES6 residual para ES5 puro
  ARQUIVOS: apenas VanaChipController.js
  CRITÉRIO: zero ocorrências de const/let/arrow após patch

CIRURGIA 2 — Diagnóstico CSS do botão agenda
  ESCOPO: verificar se existe regra CSS que oculta
          o botão .vana-header__agenda-btn
  ARQUIVOS: visit-style.php + qualquer CSS do tema
  CRITÉRIO: confirmar que o seletor não tem
            display:none, visibility:hidden,
            opacity:0 ou overflow:hidden que sumate
```

---

## Instrução para o Agente

```text
PASSO 1 — Converter VanaChipController.js para ES5

  LER o arquivo atual completo
  CONVERTER:
    const → var
    let   → var
    arrow functions (=>) → function() {}
    template literals (``) → concatenação com +
    (se houver) spread/destructuring → forma explícita

  VALIDAR:
    node --check VanaChipController.js
    grep "const \|let \|=>" VanaChipController.js → ZERO matches

  NÃO ALTERAR: lógica, seletores, eventos, nomes de função

─────────────────────────────────────────────────────────

PASSO 2 — Auditar CSS do botão agenda

  BUSCAR em visit-style.php:
    .vana-header__agenda-btn
    .vana-header__actions

  REPORTAR:
    → Todas as regras CSS que afetam esses seletores
    → Se há display:none, visibility:hidden,
      opacity:0, overflow:hidden, width:0, max-width:0
    → Se o botão está dentro de um container
      com regra que o esconde

  NÃO CORRIGIR AINDA — só reportar as regras

─────────────────────────────────────────────────────────

PASSO 3 — Reportar ao Marcel:
  [CIRURGIA 1] Arquivo convertido + grep confirma ZERO ES6
  [CIRURGIA 2] Regras CSS encontradas ou "NENHUMA REGRA"

NÃO TOCAR em outros arquivos
```

---

```text
AGUARDANDO: resultado do agente com as duas cirurgias
ENTÃO: você decide se aplica direto ou revisa primeiro
```

Passa para o agente, Marcel. 🙏