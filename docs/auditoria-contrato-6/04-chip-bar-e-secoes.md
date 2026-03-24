```markdown
# docs/auditoria-contrato-6/04-chip-bar-e-secoes.md

## 1. Objetivo

Auditar **apenas a ZONA 4 e ZONA 5** da página de visita do plugin `vana-mission-control`, comparando o código atual com o **Contrato 6.0**, sem aplicar patch e sem alterar código.

Escopo validado:

- chip bar sticky
- chips HK / Galeria / Sangha / Revista
- agenda não pertence ao chip bar
- HK com listagem por `event_id`
- passages com timestamp clicável
- reactions
- filtros por taxonomia
- galeria temporal por evento
- sangha temporal por evento
- revista com estados coleta / edição / publicada

---

## 2. Arquivos inspecionados

Foram inspecionados somente os arquivos solicitados:

1. `templates/visit/parts/anchor-chips.php`
2. `templates/visit/parts/hari-katha.php`
3. `templates/visit/parts/gallery.php`
4. `templates/visit/parts/sangha-moments.php`
5. `templates/visit/parts/revista-card.php`
6. `templates/visit/parts/sections.php`
7. `assets/js/VanaChipController.js`
8. `assets/js/VanaVisitController.js`

---

## 3. Itens auditados

1. Chip bar sticky  
2. Chips HK / Galeria / Sangha / Revista  
3. Agenda não pertence ao chip bar  
4. HK com listagem por `event_id`  
5. Passages com timestamp clicável  
6. Reactions  
7. Filtros por taxonomia  
8. Galeria temporal por evento  
9. Sangha temporal por evento  
10. Revista com estados coleta / edição / publicada  

---

## 4. Evidências por item

### Item 1 — Chip bar sticky

**Evidências:**

Em `templates/visit/parts/anchor-chips.php`, a navegação de chips é renderizada com estilo inline sticky:
```php
<nav
  id="vana-anchor-chips"
  class="vana-anchor-chips"
  data-vana-chip-bar
  aria-label="<?php echo esc_attr(vana_t('anchor.nav_aria', $lang)); ?>"
  style="
    position:        sticky;
    top:             56px; /* altura do vana-header */
    z-index:         900;
    background:      rgba(255,255,255,0.96);
    backdrop-filter: blur(10px);
    border-bottom:   1px solid var(--vana-line);
    padding:         0 16px;
    overflow-x:      auto;
    overflow-y:      hidden;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  "
>
```

Em `assets/js/VanaChipController.js`, o cálculo de scroll considera offset do header e chip bar:
```js
var offset = 56 + 45 + 8;
```

**Leitura objetiva:**  
O chip bar está explicitamente implementado como sticky e o JS foi escrito assumindo essa anatomia fixa abaixo do header.

**Classificação:** **IMPLEMENTADO**

---

### Item 2 — Chips HK / Galeria / Sangha / Revista

**Evidências:**

Em `templates/visit/parts/anchor-chips.php`, os chips são montados condicionalmente via `$chips[]`.

#### 2.1 HK
```php
if ($has_hari_katha):
    $chips[] = [
        'id'    => 'vana-section-hk',
        'icon'  => '🙏',
        'label' => vana_t('anchor.hari_katha', $lang),
    ];
endif;
```

#### 2.2 Galeria
```php
if ($has_gallery):
    $chips[] = [
        'id'    => 'vana-section-gallery',
        'icon'  => '📷',
        'label' => vana_t('anchor.photos', $lang),
    ];
endif;
```

#### 2.3 Revista
```php
if ($has_revista):
    $chips[] = [
        'id'    => 'vana-section-revista',
        'icon'  => '📰',
        'label' => vana_t('anchor.magazine', $lang),
    ];
endif;
```

#### 2.4 Sangha
```php
if ($has_moments):
    $chips[] = [
        'id'    => 'vana-section-sangha',
        'icon'  => '💬',
        'label' => vana_t('anchor.sangha', $lang),
    ];
endif;
```

Os chips renderizados usam:
```php
<a
  href="#<?php echo esc_attr($chip['id']); ?>"
  class="vana-anchor-chip"
  data-vana-chip="<?php echo esc_attr($chip['id']); ?>"
  data-vana-section="<?php echo esc_attr($chip['id']); ?>"
  data-target="<?php echo esc_attr($chip['id']); ?>"
>
```

Em `assets/js/VanaChipController.js`, esses seletores são reconhecidos:
```js
var BAR_SEL = '[data-vana-chip-bar], #vana-anchor-chips';
var CHIP_SEL = '[data-vana-chip], .vana-anchor-chip';
var SECTION_SEL = '[data-vana-section]';
```

**Leitura objetiva:**  
Os chips de HK, Galeria, Sangha e Revista existem e são renderizados condicionalmente conforme disponibilidade dos dados.

**Classificação:** **IMPLEMENTADO**

---

### Item 3 — Agenda não pertence ao chip bar

**Evidências:**

Em `templates/visit/parts/anchor-chips.php`, existe um comentário afirmando:
```php
 *   📅 Agenda     → sempre (se há schedule)
```

E o código efetivamente adiciona Agenda ao chip bar:
```php
if ($has_schedule):
    $chips[] = [
        'id'    => 'vana-section-schedule',
        'icon'  => '📅',
        'label' => vana_t('anchor.agenda', $lang),
    ];
endif;
```

Isso contradiz o requisito contratual informado pelo usuário:
- **“agenda não pertence ao chip bar”**

Além disso, o chip renderizado aponta para:
```php
id => 'vana-section-schedule'
```

**Leitura objetiva:**  
O código atual inclui **Agenda dentro do chip bar**, o que diverge explicitamente do escopo contratual desta auditoria.

**Classificação:** **DIVERGENTE**

---

### Item 4 — HK com listagem por `event_id`

**Evidências:**

#### 4.1 Estrutura SSR da seção HK
Em `templates/visit/parts/hari-katha.php`:
```php
<section
  id="vana-section-hari-katha"
  class="vana-section vana-section--hari-katha"
  data-visit-id="<?php echo (int) $visit_id; ?>"
  data-day="<?php echo esc_attr($active_day_date); ?>"
  data-lang="<?php echo esc_attr($lang); ?>"
>
```

A seção fornece:
- `data-visit-id`
- `data-day`
- `data-lang`

#### 4.2 Não há `event_id`
No markup de `hari-katha.php`, não foi encontrado:
- `data-event-id`
- `data-event-key`
- `event_id`

#### 4.3 `sections.php` repete o mesmo padrão por dia
Em `templates/visit/parts/sections.php`:
```php
<section
    id="vana-section-hk"
    class="vana-section-panel"
    data-vana-section="vana-section-hk"
    data-section-id="section-hk"
    role="tabpanel"
    aria-labelledby="vana-chip-hk"
    data-visit-id="<?php echo (int) $visit_id; ?>"
    data-day="<?php echo esc_attr($active_day_date); ?>"
    data-lang="<?php echo esc_attr($lang); ?>"
>
```

#### 4.4 Nos arquivos lidos não há JS de loader HK
O arquivo `visit-scripts.php`, onde estava o loader HK por `visit_id` e `day`, **não faz parte** deste escopo solicitado.  
Portanto, dentro dos arquivos realmente autorizados aqui, não há evidência de listagem por `event_id`.

**Leitura objetiva:**  
Nos arquivos auditados nesta rodada, a HK está parametrizada por **dia** e **visita**, não por `event_id`.

**Classificação:** **AUSENTE**

---

### Item 5 — Passages com timestamp clicável

**Evidências:**

Nos arquivos desta auditoria:

- `hari-katha.php` apenas define o contêiner:
  ```php
  <div class="vana-hk__list"     data-role="katha-list"></div>
  <div class="vana-hk__passages" data-role="passage-list" hidden></div>
  ```
- `sections.php` também só define o contêiner:
  ```php
  <div data-role="katha-list" class="vana-hk__list" hidden></div>
  <div data-role="passage-list" class="vana-hk__passages" hidden></div>
  ```

Não há, nos arquivos permitidos deste bloco:
- render de `t_start`
- elemento clicável de timestamp
- handler JS de seek
- ligação com `vanaStageIframe`

**Leitura objetiva:**  
Dentro do recorte de arquivos desta auditoria, não há evidência implementada de passages com timestamp clicável.

**Classificação:** **AUSENTE**

---

### Item 6 — Reactions

**Evidências:**

Nos arquivos inspecionados não foram encontrados:
- botões de reação
- contadores de reação
- taxonomias de reação
- endpoint/recurso de “like”, “heart”, “clap” etc.
- campos como `reactions`, `likes`, `emoji`

Em `gallery.php` e `sangha-moments.php`, há apenas cards/modal, badges de tipo e CTA de envio, mas não reactions.

**Leitura objetiva:**  
Não há evidência de reactions nas zonas auditadas.

**Classificação:** **AUSENTE**

---

### Item 7 — Filtros por taxonomia

**Evidências:**

#### 7.1 Galeria usa meta query, não taxonomia
Em `templates/visit/parts/gallery.php`:
```php
$gurudeva_submissions = new WP_Query([
    'post_type'      => 'vana_submission',
    'post_status'    => 'publish',
    'posts_per_page' => 96,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => '_visit_id',
            'value'   => $visit_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => '_subtype',
            'value'   => 'gurudeva_gallery',
            'compare' => '=',
        ],
    ],
]);
```

#### 7.2 Sangha usa meta query, não taxonomia
Em `templates/visit/parts/sangha-moments.php`:
```php
$submissions = new WP_Query([
    'post_type'      => 'vana_submission',
    'post_status'    => 'publish',
    'posts_per_page' => 48,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => '_visit_id',
            'value'   => $visit_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => '_subtype',
            'value'   => 'gurudeva_gallery',
            'compare' => '!=',
        ],
    ],
]);
```

#### 7.3 Não há `tax_query`
Nos arquivos inspecionados não foi encontrado:
- `tax_query`
- taxonomia registrada/consultada
- UI de filtro por categoria/tag/termo

**Leitura objetiva:**  
Os filtros existentes são por `meta_query`, não por taxonomia. O requisito contratual de filtros por taxonomia não está implementado no recorte auditado.

**Classificação:** **AUSENTE**

---

### Item 8 — Galeria temporal por evento

**Evidências:**

#### 8.1 A galeria é filtrada por `visit_id`, não por evento
Em `templates/visit/parts/gallery.php`:
```php
'meta_query' => [
    [
        'key'     => '_visit_id',
        'value'   => $visit_id,
        'compare' => '=',
        'type'    => 'NUMERIC',
    ],
    [
        'key'     => '_subtype',
        'value'   => 'gurudeva_gallery',
        'compare' => '=',
    ],
],
```

#### 8.2 Não usa `active_day_date`
Embora o arquivo declare nas variáveis esperadas:
```php
 *   $lang, $visit_id, $active_day_date
```
a query efetiva não filtra por:
- dia
- horário
- `event_id`
- `event_key`

#### 8.3 Modal também é agregado
O modal em `gallery.php` agrupa itens a partir de:
```php
data-vana-gallery-item="1"
```
mas sem qualquer referência ao evento temporal.

**Leitura objetiva:**  
A galeria atual é **por visita**, não por evento temporal.

**Classificação:** **AUSENTE**

---

### Item 9 — Sangha temporal por evento

**Evidências:**

#### 9.1 Sangha é filtrada por `visit_id`, não por evento
Em `templates/visit/parts/sangha-moments.php`:
```php
'meta_query' => [
    'relation' => 'AND',
    [
        'key'     => '_visit_id',
        'value'   => $visit_id,
        'compare' => '=',
        'type'    => 'NUMERIC',
    ],
    [
        'key'     => '_subtype',
        'value'   => 'gurudeva_gallery',
        'compare' => '!=',
    ],
],
```

#### 9.2 Não usa `active_day_date` no filtro
Embora o arquivo declare:
```php
 *   $lang, $visit_id, $active_day_date
```
não há filtro por:
- dia
- evento
- `event_key`
- período

#### 9.3 `sections.php` possui `sangha_items` vindos de `$active_day`
Em `templates/visit/parts/sections.php`:
```php
$sangha_items  = $active_day['sangha_moments'] ?? [];
```
e a section correspondente renderiza:
```php
<?php if (!empty($sangha_items)): ?>
    <ul class="vana-section-list">
        <?php foreach ($sangha_items as $moment): ?>
```

Ou seja, há **uma representação de Sangha ligada ao active_day em `sections.php`**, mas o arquivo dedicado `sangha-moments.php` consulta submissions globais da visita, não por evento.

**Leitura objetiva:**  
Há inconsistência entre a versão “sections panel” baseada em `$active_day` e a versão dedicada `sangha-moments.php` baseada em `visit_id`. Em nenhum caso há evidência de recorte temporal **por evento**.

**Classificação:** **PARCIAL**

---

### Item 10 — Revista com estados coleta / edição / publicada

**Evidências:**

#### 10.1 Chip de revista depende apenas de “publicada”
Em `anchor-chips.php`:
```php
$mag_state  = (string) get_post_meta($visit_id, '_vana_mag_state', true);
$has_revista = $mag_state === 'publicada';
```

#### 10.2 `revista-card.php` só renderiza se houver URL
Em `templates/visit/parts/revista-card.php`:
```php
$revista_url   = $visit_data['revista_url']   ?? '';
$revista_title = $visit_data['revista_title'] ?? __( 'Revista da Visita', 'vana-mc' );
$revista_cover = $visit_data['revista_cover'] ?? '';

if ( empty( $revista_url ) ) {
    return; // Silencioso — sem dados, sem output
}
```

#### 10.3 Não há estados “coleta” ou “edição”
Nos arquivos inspecionados não foram encontrados:
- render específico para `coleta`
- render específico para `edição`
- mensagens de progresso editorial
- CTA condicional por estado editorial

#### 10.4 `sections.php` traz placeholder de loading
Em `templates/visit/parts/sections.php`:
```php
<div class="vana-section-body" id="vana-revista-content">
    <p class="vana-section-empty"><?php echo esc_html( vana_t( 'sections.revista_loading', $lang ) ?: 'Carregando revista...' ); ?></p>
</div>
```
Mas isso não constitui modelagem explícita dos estados contratuais `coleta / edição / publicada`.

**Leitura objetiva:**  
A Revista está tratada, neste recorte, essencialmente como binária:
- existe/publicada → exibe
- não existe → não exibe

O contrato de estados `coleta / edição / publicada` não está implementado de forma explícita.

**Classificação:** **PARCIAL**

---

## 5. Classificação por item

| Item auditado | Classificação |
|---|---|
| Chip bar sticky | **IMPLEMENTADO** |
| Chips HK / Galeria / Sangha / Revista | **IMPLEMENTADO** |
| Agenda não pertence ao chip bar | **DIVERGENTE** |
| HK com listagem por `event_id` | **AUSENTE** |
| Passages com timestamp clicável | **AUSENTE** |
| Reactions | **AUSENTE** |
| Filtros por taxonomia | **AUSENTE** |
| Galeria temporal por evento | **AUSENTE** |
| Sangha temporal por evento | **PARCIAL** |
| Revista com estados coleta / edição / publicada | **PARCIAL** |

---

## 6. O que já está aderente

### 6.1 Chip bar sticky
O chip bar está efetivamente sticky com:
```php
position: sticky;
top: 56px;
```

### 6.2 Chips centrais das seções existem
Há chips para:
- HK
- Galeria
- Sangha
- Revista

Todos com:
- `href="#section-id"`
- `data-vana-chip`
- `data-vana-section`
- labels i18n

### 6.3 Controle JS do chip bar está funcionalmente coerente
Em `assets/js/VanaChipController.js`, há:
- clique com scroll suave
- `IntersectionObserver`
- highlight do chip ativo
- evento customizado `vana:chip:activated`

### 6.4 Estrutura base das seções existe
Há seções dedicadas/IDs de referência para:
- `vana-section-hk`
- `vana-section-gallery`
- `vana-section-sangha`
- `vana-section-revista`

Isso dá base estrutural para aderência futura mais completa ao contrato.

---

## 7. O que ainda não atende ao contrato

### 7.1 Agenda ainda faz parte do chip bar
O arquivo `anchor-chips.php` inclui Agenda:
```php
'id' => 'vana-section-schedule'
```
Isso diverge do contrato desta zona.

### 7.2 HK não está modelada por `event_id`
Nos arquivos lidos, HK está amarrada a:
- `visit_id`
- `active_day_date`

Não há evidência de recorte por evento específico.

### 7.3 Passages clicáveis não podem ser comprovadas neste bloco
Os arquivos desta zona só mostram os contêineres da HK, não o render das passagens com timestamp e seek.

### 7.4 Não há reactions
Nenhuma das seções auditadas possui UI ou dados de reactions.

### 7.5 Não há filtros por taxonomia
Os filtros existentes são por `meta_query`, não por taxonomia.

### 7.6 Galeria não é temporal por evento
`gallery.php` busca tudo por `visit_id` e `_subtype = gurudeva_gallery`.

### 7.7 Sangha não está fechada temporalmente por evento
Há uma versão por `$active_day` em `sections.php`, mas o template dedicado `sangha-moments.php` agrega por visita.  
Não há evidência de segmentação por evento.

### 7.8 Revista não modela estados editoriais completos
Só há evidência de “publicada” / “com URL disponível”.  
Os estados `coleta` e `edição` não aparecem explicitamente.

---

## 8. Próximo passo recomendado

1. **Remover Agenda do chip bar no contrato visual desta zona**
   - o código atual a inclui explicitamente
   - isso precisa ser alinhado ao Contrato 6.0

2. **Formalizar HK por evento**
   - adicionar contexto por `event_id` ou `event_key`
   - garantir que listagem e passages sejam filtradas por evento, não só por dia

3. **Trazer para esta zona a camada real de render de passages**
   - para que timestamps clicáveis fiquem comprováveis no bloco HK

4. **Definir taxonomias e filtros reais**
   - se o contrato exige filtros por taxonomia, a modelagem atual por `meta_query` é insuficiente

5. **Recortar Galeria e Sangha por evento**
   - hoje a galeria dedicada é por visita
   - sangha dedicada também é por visita
   - o contrato pede temporalidade por evento

6. **Modelar Revista por workflow editorial**
   - exibir estados explícitos:
     - coleta
     - edição
     - publicada

7. **Evitar duplicidade/confusão entre `sections.php` e templates dedicados**
   - há duas representações concorrentes de HK/Galeria/Sangha/Revista:
     - panel unificado (`sections.php`)
     - templates dedicados (`gallery.php`, `sangha-moments.php`, `revista-card.php`, `hari-katha.php`)
   - isso dificulta comprovar aderência única ao contrato
```