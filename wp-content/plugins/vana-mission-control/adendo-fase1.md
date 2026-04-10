# ADENDO — SPEC_FASE1_AGENTE v2.1
# Compatibilidade com Arquitetura Real do Plugin
# Data: 22/03/2026 | Supersede: v2.0

---

## CHANGELOG v2.1

```text
C1 — REST: POST /react movido para Fase 2, seção REST corrigida
C2 — Enqueue: estratégia de include PHP vs wp_enqueue documentada
C3 — Tour Drawer: arquivo-alvo único definido (fim do acoplamento)
C4 — vanaDrawer: fonte de verdade única definida (fim da dupla injeção)
```

---

## REGRA GERAL (inalterada)

```text
Onde a spec v1 diz         → o plugin real usa
─────────────────────────────────────────────────
template-parts/vana/       → templates/visit/parts/
functions.php              → vana-mission-control.php
includes/class-vana-*      → includes/class-vana-*-cpt.php (já existe)
assets/js/Vana*.js         → assets/js/ (estrutura já existe)
```

---

## FASE A — MATRIZ DE GAP (executar PRIMEIRO)

```text
STATUS      SIGNIFICADO
─────────   ─────────────────────────────────────────
✅ OK        já existe e atende
⚙️ AJUSTAR   existe mas precisa de modificação cirúrgica
🆕 CRIAR     não existe, criar do zero
❌ IGNORAR   fora de escopo ou substituído pela arquitetura atual
```

### Itens de gap identificados (atualizado v2.1)

```text
ITEM                          STATUS      AÇÃO
────────────────────────────  ──────────  ──────────────────────────────────────
CPTs (visit, tour, katha)     ✅ OK        não tocar
Handlers AJAX tour            ✅ OK        não tocar
Bootstrap _bootstrap.php      ⚙️ AJUSTAR   2 mudanças cirúrgicas — ver Fase B
Hero header                   ⚙️ AJUSTAR   adicionar: tour-counter, day-selector
Stage                         ⚙️ AJUSTAR   adicionar: modo neutro, transição, share
Tour Drawer                   ⚙️ AJUSTAR   ver Fase D — arquivo-alvo único definido
Agenda Drawer                 🆕 CRIAR     não existe como gaveta separada
Chip Bar                      ⚙️ AJUSTAR   validar se existe; criar se não
Seções (HK, Galeria, etc.)    ⚙️ AJUSTAR   validar estrutura atual
VanaVisitController.js        🆕 CRIAR     migrar responsabilidades — ver Fase E
REST API /vana/v1/* (GET)     🆕 CRIAR     endpoints GET; POST /react → Fase 2
CSS tokens e estados          ⚙️ AJUSTAR   consolidar vars e estados visuais
class-vana-cpts.php           ❌ IGNORAR   CPTs já existem como classes separadas
window.vanaDrawer             ⚙️ AJUSTAR   fonte única — ver Fase B / C4
```

---

## FASE B — BOOTSTRAP (cirúrgico)

```text
ARQUIVO: templates/visit/_bootstrap.php
```

### Mudança 1 — prev/next escopado por tour_id

```text
LOCALIZAR:
  bloco que calcula prev_visit e next_visit

SUBSTITUIR POR:
  condicional tour_id — código exato na spec v1 seção 5

PRESERVAR:
  fallback cronológico global quando _vana_tour_id é null
```

### Mudança 2 — fonte única do vanaDrawer [C4]

```text
PROBLEMA IDENTIFICADO:
  Existe injeção inline de window.vanaDrawer no código atual.
  A spec v1 pedia wp_localize_script como fonte.
  Duas fontes = bug silencioso (uma sobrescreve a outra).

DECISÃO:
  A injeção inline em _bootstrap.php é a fonte de verdade.
  wp_localize_script NÃO será usado para vanaDrawer.

AÇÃO:
  Localizar o bloco inline atual de window.vanaDrawer.
  ADICIONAR os campos faltantes neste mesmo bloco.
  NÃO criar um wp_localize_script paralelo.

CAMPOS A ADICIONAR (se não existirem):
  tourId       → (int|null)   _vana_tour_id do post atual
  tourTitle    → (string|null) get_the_title( $tour_id )
  tourUrl      → (string|null) get_permalink( $tour_id )
  currentVisit → {
    id    : (int)    get_the_ID()
    title : (string) get_the_title()
    url   : (string) get_permalink()
  }

VALIDAÇÃO:
  Após ajuste: window.vanaDrawer deve existir uma única vez
  no HTML renderizado. Verificar via DevTools → Sources.
```

### O que NÃO tocar no bootstrap

```text
→ resolução de tour_id por post_parent ou _vana_tour_id
→ cálculo existente de $tour_title e $tour_url
→ qualquer outra lógica já funcional
```

---

## FASE C — REST API [C1]

```text
ARQUIVO NOVO: includes/class-vana-rest-api.php
```

### Escopo Fase 1 — GET apenas

```text
FASE 1 (este documento):
  GET  /vana/v1/kathas      → lista kathas por event_key
  GET  /vana/v1/media       → fotos/vídeos por event_key
  GET  /vana/v1/sangha      → relatos por event_key
  GET  /vana/v1/revista     → curadoria por visit_id

FASE 2 (fora deste documento):
  POST /vana/v1/react       → reactions persistentes
  POST /vana/v1/notify      → preferências de notificação
  POST /vana/v1/sangha      → envio de relato

REGRA:
  Fase 1 é somente leitura.
  Nenhum endpoint POST nesta fase.
```

### Validação de namespace

```text
ANTES DE CRIAR:
  Grep em todo o plugin:
    register_rest_route

  Confirmar que nenhuma rota /vana/v1/* já existe.
  Se existir → integrar na classe, não duplicar.
```

### Registro da classe

```text
EM: vana-mission-control.php

ADICIONAR:
  require_once plugin_dir_path(__FILE__) . 'includes/class-vana-rest-api.php';
  Vana_REST_API::init();

LOCAL: junto aos outros requires de includes/
```

---

## FASE D — COMPONENTES UI [C3]

```text
DIRETÓRIO: templates/visit/parts/
```

### Arquivos a criar

```text
ARQUIVO                     SPEC REF    OBSERVAÇÃO
──────────────────────────  ──────────  ─────────────────────────────────
agenda-drawer.php           seção 10    não existe hoje
sections.php                seção 12    validar se existe estrutura parcial
```

### Arquivos a ajustar (cirúrgico)

```text
ARQUIVO              SPEC REF    O QUE ADICIONAR
─────────────────────  ────────   ──────────────────────────────────────────
hero-header.php        seção 7    tour-counter + day-selector (Zona 2)
stage.php              seção 9    modo neutro, tela de transição,
                                  botão share, botão open-hk
chip-bar.php           seção 11   validar se existe; criar se não
```

### Tour Drawer — arquivo-alvo único [C3]

```text
PROBLEMA IDENTIFICADO:
  O drawer de tour está hoje acoplado em dois lugares:
    A. hero-header.php   → markup HTML do drawer
    B. visit-scripts.php → lógica JS do drawer

  Esse acoplamento causa dispersão e dificulta manutenção.

DECISÃO DE ARQUITETURA:
  O drawer de tour será extraído para arquivo próprio.

AÇÃO:
  PASSO 1 — Ler hero-header.php atual
    Identificar o bloco HTML do tour drawer.
    Recortar esse bloco.

  PASSO 2 — Criar tour-drawer.php
    Colar o bloco recortado.
    Adicionar os dois cenários conforme spec v1, seção 8:
      Cenário A: visita com tour (lista da tour)
      Cenário B: visita sem tour (lista cronológica)
    Manter estrutura de markup compatível com JS atual.

  PASSO 3 — Incluir no template
    Em visit-template.php (ou equivalente):
    Adicionar get_template_part para tour-drawer.php
    Remover o bloco de hero-header.php

  PASSO 4 — JS permanece em visit-scripts.php por ora
    Migrar para VanaVisitController.js na Fase E.
    Na Fase D: apenas garantir que os seletores CSS
    do JS atual ainda encontram os elementos no novo arquivo.

VALIDAÇÃO:
  Após Fase D: drawer abre e lista tours normalmente.
  Nenhuma regressão de comportamento.
```

### Regra universal para Fase D

```text
Ler o arquivo atual completo antes de editar.
Fazer diff mental contra a spec.
Adicionar apenas o que falta.
Não remover o que já funciona.
```

---

## FASE E — JAVASCRIPT [C2]

```text
ARQUIVO NOVO: assets/js/VanaVisitController.js
```

### Estratégia de enqueue [C2]

```text
PROBLEMA IDENTIFICADO:
  O script de visita é incluído por include PHP diretamente
  (templates/visit/assets/visit-scripts.php).
  Não é um handle wp_enqueue_script convencional.
  Isso impede usar dependency array e wp_localize_script.

DECISÃO:
  Manter o include PHP como mecanismo de carregamento
  para o script existente (visit-scripts.php).
  O VanaVisitController.js será registrado via
  wp_enqueue_script convencional.

IMPLEMENTAÇÃO:
  EM: vana-mission-control.php (ou onde os assets são registrados)

  add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'vana_visit' ) ) return;

    wp_enqueue_script(
      'vana-visit-controller',
      plugin_dir_url(__FILE__) . 'assets/js/VanaVisitController.js',
      [],           // sem dependência declarada — ver nota abaixo
      '1.0.0',
      true          // footer
    );
  });

NOTA SOBRE DEPENDÊNCIAS:
  visit-scripts.php é incluído via PHP, não tem handle WP.
  Portanto não pode ser declarado como dependência formal.
  Solução: VanaVisitController.js carrega no footer (true).
  O include PHP do visit-scripts.php deve ocorrer ANTES.
  Verificar a ordem no template para garantir isso.
  Ordem esperada no HTML final:
    1. visit-scripts.php (include PHP → inline ou src)
    2. VanaVisitController.js (wp_enqueue → footer)
```

### Estratégia de migração

```text
PASSO 1 — Criar VanaVisitController.js conforme spec v1, seção 14
PASSO 2 — Identificar responsabilidades em visit-scripts.php:
  A. drawer tour (abrir, fechar, carregar tours, carregar visitas)
  B. stage (carregar evento, controles, segments, transição)
  C. agenda (abrir, fechar, listar eventos, trocar idioma)
  D. seletor de dia (trocar dia)
  E. chip bar (trocar seção)

PASSO 3 — Migrar bloco por bloco na ordem A→E
  Após cada bloco: testar funcionalidade
  Só remover de visit-scripts.php quando controller cobrir 100%

DUPLICAÇÃO EXISTENTE EM visit-scripts.php:
  O agente identificou dois blocos de drawer no mesmo arquivo.
  ANTES de migrar: identificar qual bloco está ativo.
  Remover o bloco legado PRIMEIRO.
  Migrar apenas o bloco ativo para o controller.
```

### dados globais disponíveis para o controller

```text
window.vanaVisitData   → já existe, validar payload completo
window.vanaDrawer      → enriquecido na Fase B (fonte única)
window.ajaxurl         → já existe via WP
```

---

## FASE F — CSS

```text
ARQUIVO: assets/css/vana-visit.css
```

### Estratégia

```text
PASSO 1 — Ler CSS atual completo
PASSO 2 — Mapear custom properties existentes
PASSO 3 — Adicionar tokens da spec que não existem:
  --vana-primary, --vana-surface-dark, etc.
PASSO 4 — Adicionar classes de estado faltantes:
  .is-active, .is-playing, .is-neutral, .is-transitioning
PASSO 5 — Adicionar estilos de gavetas:
  .vana-drawer, .vana-drawer--tour, .vana-drawer--agenda
PASSO 6 — Adicionar estilos da agenda-drawer e sections (novos)
```

### Regra

```text
NÃO reescrever o arquivo.
NÃO remover classes sem verificar uso no HTML.
Adicionar em blocos comentados por componente.
```

---

## FASE G — REGISTRO E ENQUEUE

```text
ARQUIVO: vana-mission-control.php
```

### O que adicionar

```text
1. require da REST API
   require_once plugin_dir_path(__FILE__) . 'includes/class-vana-rest-api.php';
   Vana_REST_API::init();

2. wp_enqueue_script do VanaVisitController.js
   conforme spec acima em Fase E

3. NÃO adicionar wp_localize_script para vanaDrawer
   (fonte de verdade é o inline do bootstrap — C4)
```

### O que NÃO tocar

```text
→ enqueues existentes
→ lógica de template_include
→ qualquer hook já registrado
→ includes já existentes
```

---

## FASE H — QA

```text
Executar checklist completo da spec v1, seção 17.
Sem exceções.
Reportar status de cada item.
```

### Gate de regressão entre fases

```text
Após cada fase, confirmar TODOS os 4 itens antes de avançar:

[ ] Página da visita carrega sem PHP notice ou warning
[ ] Drawer de tour abre e lista tours corretamente
[ ] Stage carrega um vídeo (evento com media_ref)
[ ] Sem erro de console JS
```

---

## ORDEM DE EXECUÇÃO OBRIGATÓRIA

```text
A → B → C → D → E → F → G → H

Não avançar para a próxima fase
sem passar o gate de regressão.
```

---

## CHECKLIST DE ENTREGA FINAL

```text
ARQUIVOS NOVOS (criar)
[ ] includes/class-vana-rest-api.php
[ ] assets/js/VanaVisitController.js
[ ] templates/visit/parts/tour-drawer.php     (extraído do hero)
[ ] templates/visit/parts/agenda-drawer.php
[ ] templates/visit/parts/sections.php

ARQUIVOS AJUSTADOS (cirúrgico)
[ ] templates/visit/_bootstrap.php            (prev/next + vanaDrawer inline)
[ ] templates/visit/parts/hero-header.php     (tour-counter + day-selector)
[ ] templates/visit/parts/stage.php           (neutro + transição + share)
[ ] templates/visit/parts/chip-bar.php        (validar ou criar)
[ ] templates/visit/assets/visit-scripts.php  (remover bloco legado)
[ ] assets/css/vana-visit.css                 (tokens + estados + gavetas)
[ ] vana-mission-control.php                  (REST + enqueue controller)

NÃO TOCAR
[ ] includes/class-vana-visit-cpt.php
[ ] includes/class-vana-tour-cpt.php
[ ] includes/class-vana-katha-cpt.php
[ ] templates/single-vana_visit.php
[ ] qualquer handler AJAX já registrado
[ ] qualquer wp_localize_script já existente (exceto enriquecer vanaDrawer inline)
```

---

## DECISÕES DE ARQUITETURA CONSOLIDADAS

```text
DECISÃO                        ESCOLHA
─────────────────────────────  ──────────────────────────────────────────
Caminhos de template           templates/visit/parts/ (padrão do plugin)
Fonte de verdade vanaDrawer    inline no bootstrap (não wp_localize_script)
Estratégia de enqueue JS       include PHP para existente + wp_enqueue novo
Arquivo do tour drawer         tour-drawer.php separado (extraído do hero)
REST POST /react               Fase 2 (não Fase 1)
CPTs                           classes existentes (não recriar)
Handlers AJAX tour             existentes (não recriar)
```

---

*v2.1 — 22/03/2026*
*Supersede: ajuste-fase1.md v2.0*
*Leia junto com SPEC_FASE1_AGENTE.md (spec v1)*
*Em caso de conflito: este documento prevalece.*
*Próximo passo: Fase A — Matriz de Gap (sem escrever código)*
