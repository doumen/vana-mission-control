# Revisão da Fase B + Autorização para Fase C

Marcel, o agente executou a Fase B corretamente. Vou revisar o relatório antes de liberar a próxima fase.

---

## Revisão da Fase B

### ✅ O que foi feito corretamente

```text
1. prev/next escopado por tour_id
   → condicional com escopo por tour implementada
   → fallback cronológico global preservado
   → flag prev_next_scoped exposto

2. Payload window.vanaDrawer completado
   → 4 campos adicionados: tourId, tourTitle, tourUrl, currentVisit
   → campos existentes preservados
   → fonte única mantida (visit-scripts.php)

3. PHP lint OK em ambos os arquivos
   → zero erros de sintaxe
```

### ✅ Observação do agente — homologada

```text
O agente reportou:
  "window.vanaDrawer está em visit-scripts.php,
   não em _bootstrap.php"

DECISÃO: correto e aceito.
  A regra é "fonte única".
  Onde estiver, enriquecer lá.
  O agente fez exatamente isso.
  Nenhuma correção necessária.
```

### ⚠️ Gate de Regressão

```text
O agente executou PHP lint (sintaxe).
Antes de avançar para Fase C, Marcel precisa validar
em browser/ambiente real:

[ ] Página de uma visita COM tour → prev/next aparece
[ ] Página de uma visita SEM tour → prev/next fallback global
[ ] DevTools → Console → window.vanaDrawer
    confirmar: tourId, tourTitle, tourUrl, currentVisit presentes
[ ] Drawer de tour abre e lista tours (sem regressão)
[ ] Stage carrega um vídeo (sem regressão)
[ ] Zero erros de console JS

Se algum item falhar → reportar antes de avançar.
Se todos passarem → confirmar para liberar Fase C.
```

---

## Autorização para Fase C — Condicional

```text
STATUS: AUTORIZADA com pré-requisito

PRÉ-REQUISITO:
  Marcel confirmar gate de regressão acima.

SE GATE PASSAR:
  Agente pode iniciar Fase C imediatamente.

ESCOPO EXATO DA FASE C:

  PASSO 1 — Mapear rotas existentes em /vana/v1/
    Ler class-vana-query-api.php completo
    Ler class-vana-rest-stage.php completo
    Montar lista: método HTTP + path de cada rota registrada

  PASSO 2 — Verificar conflito com paths da Fase 1
    /vana/v1/kathas    → conflita? sim/não
    /vana/v1/media     → conflita? sim/não
    /vana/v1/sangha    → conflita? sim/não
    /vana/v1/revista   → conflita? sim/não

  PASSO 3 — Reportar resultado antes de criar qualquer arquivo
    SE conflito → propor paths alternativos para aprovação
    SE livre    → criar includes/class-vana-rest-api.php

  PASSO 4 — Criar a classe REST
    Apenas endpoints GET:
      GET /vana/v1/kathas
      GET /vana/v1/media
      GET /vana/v1/sangha
      GET /vana/v1/revista
    POST /react → stub comentado, não implementar
    Registrar em vana-mission-control.php

  PASSO 5 — Validar no Postman ou browser
    GET /wp-json/vana/v1/kathas → HTTP 200
    GET /wp-json/vana/v1/media  → HTTP 200
    Sem conflito com rotas existentes

GATE DE REGRESSÃO DA FASE C:
  [ ] PHP lint OK na classe nova
  [ ] 4 endpoints retornam HTTP 200 (mesmo que array vazio)
  [ ] Rotas existentes não foram afetadas
  [ ] Zero PHP notices no log
```

---

Marcel, assim que você confirmar o gate de regressão da Fase B em browser, o agente já pode ir direto para o Passo 1 da Fase C. 🙏