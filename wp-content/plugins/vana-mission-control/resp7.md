# Revisão Crítica — Passo 0 Não Foi Executado Corretamente

Marcel, preciso alertar sobre um problema sério no que o agente reportou.

---

## O Problema

```text
O agente reportou no sumário final:

  "Simplified the logic to use $tour_id directly
   for determining navigation scope"

  "dual navigation capabilities
   (tour-scoped and global)"

ISSO CONTRADIZ DIRETAMENTE O DT-004.

DT-004 determina:
  "Tour é contexto, NUNCA fronteira de navegação."
  "prev/next → SEMPRE cronológico global."
  "Remover COMPLETAMENTE o branch de tour_id
   no resolver de navegação."

O agente removeu apenas uma variável ($prev_next_scoped)
mas MANTEVE a lógica dual (escopado + global).
O objetivo era eliminar o modo escopado, não renomeá-lo.
```

---

## O Que Precisa Ser Verificado

```text
Abrir _bootstrap.php e verificar manualmente:

PROCURAR por qualquer uma destas condições:
  if ($tour_id) {
  if ($tour_id && ...)
  $query_args['meta_query'] com tour_id
  WP_Query filtrando por tour

SE ENCONTRAR:
  O Passo 0 não foi concluído.
  A bifurcação ainda existe — só foi reformatada.

SE NÃO ENCONTRAR:
  O resolver é genuinamente global.
  Passo 0 concluído corretamente.
```

---

## Instrução para o Agente

```text
TAREFA: Auditar e corrigir o resolver prev/next

  PASSO 1:
    Ler _bootstrap.php completo
    Mostrar o bloco atual que resolve $prev_visit
    e $next_visit (diff completo, sem omissões)

  PASSO 2:
    Verificar se existe QUALQUER filtro por tour_id
    nos WP_Query de prev/next

  PASSO 3 — SE houver filtro por tour_id:
    Remover completamente
    O resolver deve usar APENAS:
      post_type   = vana_visit
      orderby     = date (ou meta_value de data real)
      date filter = antes/depois da visita atual
      sem meta_query de tour

  PASSO 4:
    $tour_id deve continuar existindo no contexto
    para o template (hero exibe "Tour: Vrindavan 2023")
    MAS não deve aparecer em nenhum WP_Query
    de prev/next

  PASSO 5:
    PHP lint _bootstrap.php → zero erros

  PASSO 6:
    Reportar o bloco final do resolver
    (as linhas exatas que resolvem prev e next)
    para confirmação antes de avançar para Bloco A
```

---

## Gate do Passo 0 — Revisado

```text
BLOQUEANTE para Fase E:

  [ ] Bloco resolver prev/next mostrado ao Marcel
  [ ] Nenhum filtro por tour_id no resolver
  [ ] $prev_visit → visita anterior no tempo (global)
  [ ] $next_visit → visita seguinte no tempo (global)
  [ ] $tour_id ainda disponível para o template
  [ ] PHP lint zero erros
  [ ] Marcel aprova o bloco antes de Bloco A
```

---

Marcel, o agente simplificou mas não eliminou a lógica dual. Precisa de uma nova rodada cirúrgica antes de avançar. Confirma que quer corrigir agora? 🙏