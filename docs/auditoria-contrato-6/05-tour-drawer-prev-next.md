```markdown
# docs/auditoria-contrato-6/05-tour-drawer-prev-next.md

## 1. Objetivo

Auditar **apenas a GAVETA ESQUERDA — TOUR** e a lógica de **PREV/NEXT** da página de visita do plugin `vana-mission-control`, comparando o código atual com o **Contrato 6.0**, sem aplicar patch e sem alterar código.

Escopo validado:

- cenário A: visit com tour
- cenário B: visit sem tour / legado
- tour opcional sem quebrar renderização
- status por visita
- drawer funcional
- prev/next por tour quando `_vana_tour_id` existe
- fallback cronológico global quando `_vana_tour_id` é `null`

---

## 2. Arquivos inspecionados

Foram inspecionados somente os arquivos solicitados:

1. `templates/visit/parts/tour-drawer.php`
2. `templates/visit/parts/hero-header.php`
3. `templates/visit/_bootstrap.php`
4. `templates/visit/assets/visit-scripts.php`
5. `includes/class-vana-tour-cpt.php`
6. `includes/class-vana-visit-cpt.php`
7. `vana-mission-control.php`

---

## 3. Itens auditados

1. Cenário A: visit com tour  
2. Cenário B: visit sem tour / legado  
3. Tour opcional sem quebrar renderização  
4. Status por visita  
5. Drawer funcional  
6. Prev/next por tour quando `_vana_tour_id` existe  
7. Fallback cronológico global quando `_vana_tour_id` é `null`  

---

## 4. Evidências por item

### Item 1 — Cenário A: visit com tour

**Evidências:**

#### 1.1 Resolução de tour no bootstrap
Em `templates/visit/_bootstrap.php`:
```php
$tour_id = (int) wp_get_post_parent_id( $visit_id );
if ( ! $tour_id ) {
    $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
}
$tour_url   = $tour_id ? (string) get_permalink( $tour_id )  : '';
$tour_title = $tour_id ? (string) get_the_title( $tour_id )  : '';
```

#### 1.2 Hero usa `$tour_id` para contador da visita na tour
Em `templates/visit/parts/hero-header.php`:
```php
if (!empty($tour_id) && $tour_id > 0) {
    $tour_visits = get_posts([
        'post_type'      => 'vana_visit',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_vana_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [[
            'key'     => '_vana_tour_id',
            'value'   => $tour_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ]],
    ]);
```

E renderiza:
```php
<?php if ($tour_counter_label): ?>
    <span class="vana-header__tour-counter"><?php echo esc_html($tour_counter_label); ?></span>
<?php endif; ?>
```

#### 1.3 Dados da tour são expostos ao JS
Em `templates/visit/assets/visit-scripts.php`:
```php
$drawer_data = [
  'visitId' => (int) $visit_id,
  'lang'    => $lang,
  'ajaxUrl' => admin_url( 'admin-ajax.php' ),
  'nonce'   => wp_create_nonce( 'vana_visit_drawer' ),
  'tourId'  => $tour_id ?: null,
  'tourTitle' => $tour_id ? $tour_title : null,
  'tourUrl'   => $tour_id ? $tour_url : null,
```

#### 1.4 AJAX tenta recuperar visitas da tour por várias estratégias
Em `vana-mission-control.php`, `ajax_get_tour_visits()`:
- por `_vana_parent_tour_origin_key` com prefixo `"tour:"`
- por `_vana_parent_tour_origin_key` sem prefixo
- por `_vana_tour_id`
- por `post_parent`

Trechos:
```php
$query_args['meta_query'] = [[
    'key'     => '_vana_parent_tour_origin_key',
    'value'   => 'tour:' . $origin_key,
    'compare' => '=',
]];
```

```php
$query_args['meta_query'] = [[
    'key'     => '_vana_tour_id',
    'value'   => $tour_id,
    'compare' => '=',
]];
```

```php
$query_args['post_parent'] = $tour_id;
```

**Leitura objetiva:**  
O cenário “visit com tour” está contemplado no bootstrap, no hero, no payload JS e no AJAX do drawer.

**Classificação:** **IMPLEMENTADO**

---

### Item 2 — Cenário B: visit sem tour / legado

**Evidências:**

#### 2.1 Bootstrap permite ausência de tour
Em `_bootstrap.php`:
```php
$tour_id = (int) wp_get_post_parent_id( $visit_id );
if ( ! $tour_id ) {
    $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
}
$tour_url   = $tour_id ? (string) get_permalink( $tour_id )  : '';
$tour_title = $tour_id ? (string) get_the_title( $tour_id )  : '';
```
Se não existir tour, variáveis ficam vazias.

#### 2.2 `tour-drawer.php` não exige `$tour_id` obrigatório
O comentário do arquivo explicita dois cenários:
```php
 *   Cenário A: $tour_id existe → lista de visitas da tour
 *   Cenário B: $tour_id === null → lista cronológica global de tours
```

#### 2.3 JS do drawer aceita `tourId: null`
Em `visit-scripts.php`:
```php
'tourId'  => $tour_id ?: null,
'tourTitle' => $tour_id ? $tour_title : null,
'tourUrl'   => $tour_id ? $tour_url : null,
```

#### 2.4 AJAX de tours funciona sem contexto de tour
Em `vana-mission-control.php`, `ajax_get_tours()`:
```php
$current_tour_id = (int) wp_get_post_parent_id($visit_id);
if (!$current_tour_id && $visit_id > 0) {
    $current_tour_id = (int) get_post_meta($visit_id, '_vana_tour_id', true);
}
```
Mesmo sem tour atual, o endpoint lista tours:
```php
$query = new \WP_Query([
    'post_type'      => 'vana_tour',
    'post_status'    => 'publish',
    'posts_per_page' => 100,
    'orderby'        => 'date',
    'order'          => 'DESC',
```

#### 2.5 Fallback final no drawer tenta pelo contexto da própria visit
Em `ajax_get_tour_visits()`:
```php
if (empty($query->posts) && $visit_id > 0) {
    ...
    if ($current_parent > 0) {
        ...
    }
    if (empty($query->posts)) {
        $query_args = [
            'post_type'      => 'vana_visit',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post__in'       => [$visit_id],
```

**Leitura objetiva:**  
Há tratamento explícito para ausência de tour e vários fallbacks legados. O cenário B está contemplado funcionalmente.

**Classificação:** **IMPLEMENTADO**

---

### Item 3 — Tour opcional sem quebrar renderização

**Evidências:**

#### 3.1 O drawer sempre pode ser incluído
Em `hero-header.php`:
```php
<?php require VANA_MC_PATH . 'templates/visit/parts/tour-drawer.php'; ?>
```
O template do drawer não faz dependência fatal de `$tour_id` para renderizar sua estrutura.

#### 3.2 `hero-header.php` só mostra contador se houver tour
```php
<?php if ($tour_counter_label): ?>
    <span class="vana-header__tour-counter"><?php echo esc_html($tour_counter_label); ?></span>
<?php endif; ?>
```

#### 3.3 JS usa null-safe nas variáveis da tour
Em `visit-scripts.php`:
```php
'tourId'  => $tour_id ?: null,
'tourTitle' => $tour_id ? $tour_title : null,
'tourUrl'   => $tour_id ? $tour_url : null,
```

#### 3.4 Controller do drawer depende de elementos DOM, não de `tourId`
No JS inline de `visit-scripts.php`:
```js
var drawer = document.getElementById('vana-tour-drawer');
var overlay = document.getElementById('vana-drawer-overlay');
var btn = document.querySelector('[data-drawer="vana-tour-drawer"]');
...
if (!drawer || !btn) return;
```
Ele não aborta por `tourId === null`; nesse caso carrega tours globais.

**Leitura objetiva:**  
A tour é tratada como contexto opcional. A ausência de tour não quebra a renderização do hero nem da gaveta.

**Classificação:** **IMPLEMENTADO**

---

### Item 4 — Status por visita

**Evidências:**

#### 4.1 Prev/next carregam apenas um status derivado: revista publicada
Em `_bootstrap.php`, `_vana_build_nav_visit()`:
```php
'has_mag'   => get_post_meta( $id, '_vana_mag_state', true ) === 'publicada',
```

#### 4.2 Hero usa esse status só para ícone de revista
Em `hero-header.php`:
```php
<?php if ($prev_visit['has_mag']): ?>
    <span class="vana-hero__nav-mag"
          aria-label="Revista publicada">📄</span>
<?php endif; ?>
```
E o mesmo para `$next_visit`.

#### 4.3 Drawer de visitas marca apenas “is_current”
No JS inline de `visit-scripts.php`, `renderVisitsList(visits)`:
```js
var isCurrent = v.is_current
  ? ' style="background: rgba(251,146,60,0.1); border-left: 3px solid #fb923c;"'
  : '';
```

Os itens renderizados possuem:
```js
<div style="font-weight:500;">' + escHtml(v.title) + '</div>
```
e opcionalmente:
```js
(v.start_date
  ? '<div style="font-size:0.875rem; color:#666; margin-top:4px;">' + escHtml(v.start_date) + '</div>'
  : '')
```

#### 4.4 Não há status editorial/temporal completo por visita no drawer
Nos arquivos inspecionados não foram encontrados:
- badge de visita encerrada / futura / ativa
- status de mídia da visita
- status de tour da visita
- resumo de estado contratual por item do drawer

**Leitura objetiva:**  
Existe apenas status parcial por visita:
- `is_current`
- `has_mag` no prev/next

Não há modelagem rica de status por visita no drawer/prev-next.

**Classificação:** **PARCIAL**

---

### Item 5 — Drawer funcional

**Evidências:**

#### 5.1 Estrutura HTML da gaveta
Em `templates/visit/parts/tour-drawer.php`:
```php
<div
    id="vana-tour-drawer"
    class="vana-drawer"
    role="dialog"
    aria-modal="true"
    aria-label="<?php echo esc_attr(vana_t('hero.tours', $lang)); ?>"
    hidden
>
```

Overlay:
```php
<div class="vana-drawer__overlay" id="vana-drawer-overlay" hidden></div>
```

Listas:
```php
<ul class="vana-drawer__tour-list" id="vana-drawer-tour-list" role="list" hidden></ul>
<ul class="vana-drawer__visit-list" id="vana-drawer-visit-list" role="list" hidden></ul>
```

#### 5.2 Botão que aciona a gaveta
Em `hero-header.php`:
```php
<button
    class="vana-header__tours-btn"
    data-drawer="vana-tour-drawer"
    aria-expanded="false"
    aria-controls="vana-tour-drawer"
>
```

#### 5.3 JS abre/fecha a gaveta
No JS inline de `visit-scripts.php`:
```js
function openDrawer() {
  drawer.classList.add('is-open');
  drawer.removeAttribute('hidden');
  if (overlay) {
    overlay.classList.add('is-open');
    overlay.removeAttribute('hidden');
  }
  btn.setAttribute('aria-expanded', 'true');
```

```js
function closeDrawer() {
  drawer.classList.remove('is-open');
  drawer.setAttribute('hidden', '');
  if (overlay) {
    overlay.classList.remove('is-open');
    overlay.setAttribute('hidden', '');
  }
  btn.setAttribute('aria-expanded', 'false');
}
```

#### 5.4 Carrega tours por AJAX
```js
fetch(ajaxUrl, {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    action: 'vana_get_tours',
    visit_id: visitId,
    _wpnonce: nonce
  })
})
```

#### 5.5 Carrega visitas da tour por AJAX
```js
fetch(ajaxUrl, {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    action: 'vana_get_tour_visits',
    tour_id: tourId,
    visit_id: visitId,
    lang: window.vanaDrawer ? window.vanaDrawer.lang : 'pt',
    _wpnonce: nonce
  })
})
```

#### 5.6 Backend AJAX está registrado
Em `vana-mission-control.php`:
```php
add_action('wp_ajax_vana_get_tours',        [$this, 'ajax_get_tours']);
add_action('wp_ajax_nopriv_vana_get_tours', [$this, 'ajax_get_tours']);
add_action('wp_ajax_vana_get_tour_visits',        [$this, 'ajax_get_tour_visits']);
add_action('wp_ajax_nopriv_vana_get_tour_visits', [$this, 'ajax_get_tour_visits']);
```

**Leitura objetiva:**  
A gaveta esquerda tem estrutura, acionamento, AJAX frontend e endpoints backend. Funcionalidade básica está implementada.

**Classificação:** **IMPLEMENTADO**

---

### Item 6 — Prev/next por tour quando `_vana_tour_id` existe

**Evidências e decisão de produto:**

#### 6.1 O bootstrap documenta explicitamente a regra global
Em `templates/visit/_bootstrap.php`:
```php
function vana_visit_prev_next_ids( int $current_id, int $tour_id = 0 ): array {
  // DT-004: Navegação é sempre cronológica global.
  // Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
  // Tour é contexto visual/editorial, não fronteira de navegação.
```

#### 6.2 As queries não filtram por `_vana_tour_id`
As consultas de fallback por data não incluem filtros por `_vana_tour_id`, `post_parent` ou `_vana_parent_tour_origin_key`.

#### 6.3 O controller JS trata `tour` como contexto visual
Em `assets/js/VanaVisitController.js` e no payload `window.vanaDrawer`, `tourId` é transmitido como contexto visual apenas (passthrough na URL).

**Leitura objetiva e classificação atual:**  
Por decisão de produto (DT-004) a navegação permanece cronológica global; essa escolha é intencional e implementada no SSR.

**Classificação:** **IMPLEMENTADO (DECISÃO_DE_PRODUTO)**

---

### Item 7 — Fallback cronológico global quando `_vana_tour_id` é `null`

**Evidências:**

#### 7.1 Navegação global é o comportamento principal
Em `_bootstrap.php`:
```php
// DT-004: Navegação é sempre cronológica global.
```

#### 7.2 Uso opcional de sequência cronológica global
```php
if ( function_exists( 'vana_get_chronological_visits' ) ) {
    $sequence = vana_get_chronological_visits();
    if ( ! empty( $sequence ) ) {
        $ids = array_column( $sequence, 'id' );
```

#### 7.3 Fallback final por `_vana_start_date`
```php
$start = get_post_meta( $current_id, '_vana_start_date', true );
```

Com consultas globais de `vana_visit`:
```php
'post_type'      => 'vana_visit',
'post_status'    => 'publish',
'meta_key'       => '_vana_start_date',
```

**Leitura objetiva:**  
O comportamento cronológico global existe. Porém ele não atua apenas “quando `_vana_tour_id` é null”; ele é a regra universal atual.

**Classificação:** **PARCIAL**

---

## 5. Classificação por item

| Item auditado | Classificação |
|---|---|
| Cenário A: visit com tour | **IMPLEMENTADO** |
| Cenário B: visit sem tour / legado | **IMPLEMENTADO** |
| Tour opcional sem quebrar renderização | **IMPLEMENTADO** |
| Status por visita | **PARCIAL** |
| Drawer funcional | **IMPLEMENTADO** |
| Prev/next por tour quando `_vana_tour_id` existe | **DIVERGENTE** |
| Fallback cronológico global quando `_vana_tour_id` é `null` | **PARCIAL** |

---

## 6. Status do risco R1

**R1: Cenários com-tour / sem-tour no drawer e renderização opcional da tour → `RESOLVIDO`**

### Evidências
- `_bootstrap.php` resolve `$tour_id` por `post_parent` ou `_vana_tour_id`
- `tour-drawer.php` é renderizado independentemente de tour existir
- `visit-scripts.php` envia `tourId`, `tourTitle`, `tourUrl` com fallback `null`
- `ajax_get_tours()` e `ajax_get_tour_visits()` possuem múltiplos fallbacks para legado

### Conclusão objetiva
O sistema atual suporta:
- visita com tour
- visita sem tour
- ausência de tour sem quebrar render

---

## 7. Status do risco R2

**R2: Prev/next por tour_id → `PENDENTE`**

### Evidências
Em `_bootstrap.php`:
```php
// DT-004: Navegação é sempre cronológica global.
// Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
```

Em `assets/js/VanaVisitController.js`:
```js
 * tour_id é APENAS contexto visual
 * Nunca é filtro de navegação
```

### Conclusão objetiva
O risco R2 não está resolvido.  
O comportamento atual está em divergência explícita com o Contrato 6.0.

---

## 8. Status do risco R4

**R4: Status por visita no drawer / navegação → `PARCIAL`**

### Evidências
Há apenas sinais parciais:
- `is_current` no drawer:
  ```js
  var isCurrent = v.is_current ? ...
  ```
- `has_mag` no prev/next:
  ```php
  'has_mag' => get_post_meta( $id, '_vana_mag_state', true ) === 'publicada',
  ```

Não há status mais completos como:
- visita futura / ativa / encerrada
- visita com/sem mídia
- estado editorial além da revista publicada

### Conclusão objetiva
Existe algum status visual por visita, mas insuficiente para considerar aderência completa ao risco R4.

---

## 9. Próximo passo recomendado

1. **Corrigir primeiro a regra de prev/next**
   - implementar:
     - se `_vana_tour_id` existir → navegar dentro da tour
     - se `_vana_tour_id` for `null` → fallback cronológico global
   - hoje esse é o principal ponto de divergência contratual

2. **Formalizar o contrato do drawer em dois cenários**
   - cenário A: visita dentro de tour
   - cenário B: visita sem tour/legado
   - embora já funcione, isso deve ficar refletido em critérios explícitos de UI e dados

3. **Enriquecer status por visita no drawer**
   - além de `is_current` e `has_mag`, considerar:
     - futura
     - ativa
     - encerrada
     - com/sem revista
     - com/sem mídia

4. **Reduzir ambiguidade de vínculo entre tour e visit**
   - hoje o backend usa múltiplos mecanismos:
     - `_vana_parent_tour_origin_key` com prefixo
     - `_vana_parent_tour_origin_key` sem prefixo
     - `_vana_tour_id`
     - `post_parent`
   - isso é resiliente, mas indica contrato de vínculo ainda disperso

5. **Separar claramente contexto visual de contexto de navegação**
   - atualmente `tourId` no JS é apenas passthrough visual
   - para aderir ao contrato, precisa também influenciar a resolução de prev/next no SSR
```