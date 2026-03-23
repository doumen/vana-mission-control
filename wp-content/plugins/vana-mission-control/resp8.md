# Fase E — Bloco A: VanaVisitController.js

Marcel, confirmado. Passando a instrução completa para o agente.

---

## Instrução para o Agente — Bloco A

```text
OBJETIVO: Implementar stage swap (GAP-001)
ARQUIVO ALVO: VanaVisitController.js
```

---

### PASSO A.0 — Leitura e Diagnóstico

```text
TAREFA:
  1. Ler VanaVisitController.js completo
  2. Localizar o mecanismo atual de troca de stage/panel
  3. Identificar:
     a. Como o stage atual é determinado
     b. Como a troca é disparada (evento, clique, etc.)
     c. Se existe lógica de história (history.pushState)
     d. Se existe integração com tour drawer ou chip bar
  4. Reportar o bloco atual SEM fazer alterações
  5. Aguardar aprovação antes de qualquer edição
```

---

### PASSO A.1 — Gate de Diagnóstico

```text
Antes de qualquer edição, o agente deve reportar:

  [ ] Path completo do arquivo
  [ ] Tamanho atual (linhas)
  [ ] Bloco atual de stage swap (linhas exatas)
  [ ] Lista de eventos que disparam troca de stage
  [ ] Dependências externas (outros JS chamados)
  [ ] Conflitos potenciais com tour drawer ou chip bar
```

---

### PASSO A.2 — Implementação (só após aprovação de A.1)

```text
REGRAS DT-004 aplicadas ao JS:

  1. Stage swap nunca filtra por tour_id
  2. O stage ativo é determinado pela URL ou
     por atributo data- no elemento, não por tour
  3. history.pushState deve preservar tour_id
     apenas como parâmetro de contexto (?tour=X)
     — nunca como controlador de navegação

PADRÃO DE IMPLEMENTAÇÃO:
  - Usar data-stage="[nome]" nos elementos
  - Função central: swapStage(name)
  - Disparar CustomEvent 'vana:stage:change'
    para que outros módulos possam reagir
  - Sem acoplamento direto com tour drawer
```

---

### PASSO A.3 — Gate de Conclusão do Bloco A

```text
  [ ] Diagnóstico mostrado ao Marcel (A.1)
  [ ] Marcel aprovou a abordagem
  [ ] swapStage() implementada e isolada
  [ ] CustomEvent 'vana:stage:change' disparado
  [ ] Nenhum filtro por tour_id no swap
  [ ] JS lint sem erros (eslint ou console sem erros)
  [ ] Reportar bloco final para confirmação
  [ ] Marcel aprova antes de avançar para Bloco B
```

---

```text
SEQUÊNCIA FASE E — STATUS:

  [x] PASSO 0  → DT-004 implementado ✅
  [ ] BLOCO A  → VanaVisitController.js (ATUAL)
  [ ] BLOCO B  → Tour Drawer JS
  [ ] BLOCO C  → Chip Bar + Sections JS
  [ ] BLOCO D  → Agenda Drawer JS
```

---

Passa para o agente, Marcel. Ele deve **reportar o diagnóstico primeiro** e aguardar sua aprovação antes de editar qualquer linha. 🙏