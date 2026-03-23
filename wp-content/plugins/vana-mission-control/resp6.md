# Formalização — Passo 0 da Fase E

Marcel, confirmado. Registrando oficialmente.

---

## DT-004 — Princípio Arquitetural de Navegação

```text
DECISÃO: 22/03/2026
AUTOR: Marcel (confirmado)

PRINCÍPIO:
  "Navegação prev/next é sempre cronológica global.
   Tour é contexto, nunca fronteira de navegação."

ENTIDADE PRIMÁRIA:  Visita  (eixo temporal)
ENTIDADE CONTEXTO:  Tour    (agrupamento temático)

IMPLICAÇÕES:
  1. prev/next → sempre global cronológico
  2. tour_id   → apenas para exibir contexto no hero
  3. tour drawer → ferramenta opcional de estudo focado
  4. Nenhuma lógica futura deve usar tour_id como
     fronteira de navegação
```

---

## Passo 0 da Fase E — Instrução para o Agente

```text
ANTES de qualquer bloco da Fase E, executar:

TAREFA: Corrigir resolver prev/next em _bootstrap.php

  PASSO 1:
    Ler _bootstrap.php completo
    Localizar a função/bloco que resolve prev/next

  PASSO 2:
    Identificar a bifurcação:
      if ($tour_id) → prev/next dentro da tour
      else          → prev/next global

  PASSO 3:
    Remover o branch de tour_id
    Manter APENAS a lógica cronológica global

  PASSO 4:
    Garantir que $tour_id ainda é passado
    para o template (hero precisa exibir o contexto)
    Apenas o resolver de navegação muda —
    não remover $tour_id das variáveis do template

  PASSO 5:
    PHP lint _bootstrap.php → zero erros
    Confirmar que as variáveis $prev_visit
    e $next_visit ainda existem no contexto do template

  PASSO 6:
    Documentar no resp5.md:
      "Passo 0 concluído — DT-004 implementado"
      + as linhas removidas (diff resumido)

GATE DO PASSO 0:
  [ ] PHP lint _bootstrap.php sem erros
  [ ] $prev_visit e $next_visit sempre globais
  [ ] $tour_id ainda disponível para o template
  [ ] Branch de tour_id no resolver removido
  [ ] DT-004 documentado no resp5.md
```

---

## Sequência Completa da Fase E

```text
PASSO 0  → Corrigir resolver prev/next (DT-004)
BLOCO A  → VanaVisitController.js — stage swap (GAP-001)
BLOCO B  → Migrar tour drawer JS
BLOCO C  → Chip bar + sections JS
BLOCO D  → Agenda drawer JS

Gate entre cada bloco obrigatório.
Não avançar sem confirmar o anterior.
```

---

Pode passar para o agente, Marcel. O Passo 0 é pequeno e cirúrgico — apenas remove uma bifurcação. 🙏