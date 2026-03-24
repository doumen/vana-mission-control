```markdown
# docs/auditoria-contrato-6/07-riscos-r1-r4.md

## 1. Objetivo

Consolidar os **riscos conhecidos do Contrato 6.0** para o plugin `vana-mission-control`, com base:

- nos arquivos solicitados nesta rodada
- e nas auditorias já produzidas em `docs/auditoria-contrato-6/`

Riscos avaliados:

- **R1** — handlers AJAX do drawer não confirmados no PHP
- **R2** — prev/next precisa de condicional por `tour_id`
- **R3** — duplicação de implementação do drawer em `visit-scripts.php`
- **R4** — `window.vanaDrawer` não garantidamente populado

---

## 2. Evidências por risco

### R1 — handlers AJAX do drawer não confirmados no PHP

#### Evidências atuais

Em `vana-mission-control.php`, os handlers AJAX estão registrados explicitamente:

```php
add_action('wp_ajax_vana_get_tours',        [$this, 'ajax_get_tours']);
add_action('wp_ajax_nopriv_vana_get_tours', [$this, 'ajax_get_tours']);
add_action('wp_ajax_vana_get_tour_visits',        [$this, 'ajax_get_tour_visits']);
add_action('wp_ajax_nopriv_vana_get_tour_visits', [$this, 'ajax_get_tour_visits']);
```

Os métodos também existem na classe principal:

```php
public function ajax_get_tours(): void {
```

```php
public function ajax_get_tour_visits(): void {
```

Em `templates/visit/assets/visit-scripts.php`, o frontend realmente consome esses endpoints:

```php
$drawer_data = [
  'visitId' => (int) $visit_id,
  'lang'    => $lang,
  'ajaxUrl' => admin_url( 'admin-ajax.php' ),
  'nonce'   => wp_create_nonce( 'vana_visit_drawer' ),
```

E no JS inline:

```js
body: new URLSearchParams({
  action: 'vana_get_tours',
  visit_id: visitId,
  _wpnonce: nonce
})
```

```js
body: new URLSearchParams({
  action: 'vana_get_tour_visits',
  tour_id: tourId,
  visit_id: visitId,
  lang: window.vanaDrawer ? window.vanaDrawer.lang : 'pt',
  _wpnonce: nonce
})
```

Além disso, `ajax_get_tour_visits()` valida nonce:

```php
$check_result = check_ajax_referer('vana_visit_drawer', '_wpnonce', false);
```

E `ajax_get_tours()` também:

```php
check_ajax_referer('vana_visit_drawer', '_wpnonce');
```

#### Referência às auditorias anteriores

Em `docs/auditoria-contrato-6/05-tour-drawer-prev-next.md`, item **Drawer funcional**, já havia sido documentado:

- estrutura da gaveta existe
- frontend AJAX existe
- handlers backend existem

#### Leitura objetiva

O risco original “handlers AJAX do drawer não confirmados no PHP” **não se sustenta mais** com base nos arquivos atuais. Os handlers estão registrados e implementados.

#### Classificação do risco

**RESOLVIDO**

#### Impacto técnico

Baixo no estado atual.  
O backend mínimo necessário para a gaveta esquerda já existe e está conectado ao frontend.

---

### R2 — prev/next precisa de condicional por `tour_id`

#### Evidências atuais

Em `templates/visit/_bootstrap.php`, a função é explícita:

```php
function vana_visit_prev_next_ids( int $current_id, int $tour_id = 0 ): array {
    // DT-004: Navegação é sempre cronológica global.
    // Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
    // Tour é contexto para o hero, nunca fronteira de navegação.
```

As queries de fallback não usam `tour_id`:

```php
$prev_q = new WP_Query( array_merge( $base, [
    'orderby'    => 'meta_value',
    'order'      => 'DESC',
    'meta_query' => [ [ 'key' => '_vana_start_date', 'value' => $start, 'compare' => '<', 'type' => 'DATE' ] ],
] ) );
```

```php
$next_q = new WP_Query( array_merge( $base, [
    'orderby'    => 'meta_value',
    'order'      => 'ASC',
    'meta_query' => [ [ 'key' => '_vana_start_date', 'value' => $start, 'compare' => '>', 'type' => 'DATE' ] ],
] ) );
```

Em `templates/visit/assets/visit-scripts.php`, `window.vanaDrawer` carrega `tourId`, mas isso serve ao drawer, não ao SSR do prev/next:

```php
'tourId'  => $tour_id ?: null,
```

As auditorias anteriores já registraram esse ponto:

- `docs/auditoria-contrato-6/01-hero-e-day-selector.md`
  - item “Prev/next escopado por `_vana_tour_id` quando houver tour” = **DIVERGENTE**
- `docs/auditoria-contrato-6/05-tour-drawer-prev-next.md`
  - item correspondente também = **DIVERGENTE**
  - risco R2 marcado como **PENDENTE**

#### Leitura objetiva

O comportamento contratual esperado seria:

- se houver `_vana_tour_id` → prev/next dentro da tour
- se não houver tour → fallback cronológico global

O comportamento real é:

- prev/next global sempre
- `tour_id` é ignorado no resolver de navegação

#### Classificação do risco

**PENDENTE**

#### Impacto técnico

Alto.

Esse risco afeta diretamente:

- coerência de navegação entre visitas
- semântica da tour como agrupamento navegável
- aderência contratual do HERO e da gaveta de tour

É um risco estrutural, não cosmético.

---

### R3 — duplicação de implementação do drawer em `visit-scripts.php`

#### Evidências atuais

Em `templates/visit/assets/visit-scripts.php`, existe um módulo inline inteiro responsável pela gaveta esquerda:

```js
(function () {
  'use strict';

  function initDrawer() {
    var drawer = document.getElementById('vana-tour-drawer');
    var overlay = document.getElementById('vana-drawer-overlay');
    var btn = document.querySelector('[data-drawer="vana-tour-drawer"]');
    ...
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDrawer);
  } else {
    initDrawer();
  }
}());
```

Esse módulo inline implementa:
- abrir/fechar drawer
- buscar tours
- buscar visits da tour
- renderizar listas
- expor `window.__vanaDrawerSelectTour`
- expor `window.__vanaDrawerBackToTours`

Ao mesmo tempo, o comentário de `templates/visit/parts/tour-drawer.php` afirma:

```php
 * JS Controller: VanaVisitController.js (Fase E)
```

Mas o arquivo `assets/js/VanaVisitController.js` recebido nesta conversa trata apenas de:

- prefetch
- fade in/out
- prev/next
- passthrough de `tour`

Trechos:

```js
const prevBtn   = document.querySelector( '[data-vana-prev-visit]' );
const nextBtn   = document.querySelector( '[data-vana-next-visit]' );
```

```js
bindNavButton( prevBtn, prevUrl );
bindNavButton( nextBtn, nextUrl );
```

Não há no `VanaVisitController.js` fornecido:
- `#vana-tour-drawer`
- `#vana-drawer-tour-list`
- `vana_get_tours`
- `vana_get_tour_visits`

#### Referência às auditorias anteriores

Em `docs/auditoria-contrato-6/05-tour-drawer-prev-next.md`, a gaveta foi auditada como funcional, mas não havia seção específica sobre **duplicação de implementação**.  
Nesta consolidação, a divergência fica mais clara: o comentário estrutural de `tour-drawer.php` não bate com o controlador real encontrado nos arquivos.

#### Leitura objetiva

Há um risco real de **fonte de verdade difusa**:

- o template diz que o controller é `VanaVisitController.js`
- a implementação concreta do drawer está embutida em `visit-scripts.php`

Hoje isso não prova necessariamente quebra funcional, mas indica:
- acoplamento implícito
- documentação divergente
- maior chance de regressão ao refatorar

#### Classificação do risco

**PARCIAL**

#### Impacto técnico

Médio.

Não é o risco mais grave para aderência funcional imediata, mas:
- aumenta custo de manutenção
- aumenta chance de dupla implementação futura
- dificulta auditoria e refatoração segura

---

### R4 — `window.vanaDrawer` não garantidamente populado

#### Evidências atuais

Em `templates/visit/assets/visit-scripts.php`, o objeto global é inicializado **antes** do módulo que usa a gaveta:

```php
<script>
window.vanaDrawer = <?php echo wp_json_encode( $drawer_data ); ?>;
</script>
```

Depois vem o script inline com `initDrawer()`.

O payload inclui:

```php
$drawer_data = [
  'visitId' => (int) $visit_id,
  'lang'    => $lang,
  'ajaxUrl' => admin_url( 'admin-ajax.php' ),
  'nonce'   => wp_create_nonce( 'vana_visit_drawer' ),
  'tourId'  => $tour_id ?: null,
  'tourTitle' => $tour_id ? $tour_title : null,
  'tourUrl'   => $tour_id ? $tour_url : null,
  'currentVisit' => [
    'id'    => (int) $visit_id,
    'title' => get_the_title( $visit_id ),
    'url'   => get_permalink( $visit_id ),
  ],
];
```

O consumidor JS faz fallback defensivo:

```js
var nonce = window.vanaDrawer ? window.vanaDrawer.nonce : '';
var visitId = window.vanaDrawer ? window.vanaDrawer.visitId : 0;
var ajaxUrl = window.vanaDrawer ? window.vanaDrawer.ajaxUrl : '/wp-admin/admin-ajax.php';
```

E também:

```js
lang: window.vanaDrawer ? window.vanaDrawer.lang : 'pt',
```

Ou seja:
- o objeto é populado explicitamente no HTML
- o JS ainda trata ausência com fallback

#### Referência às auditorias anteriores

Em `docs/auditoria-contrato-6/05-tour-drawer-prev-next.md`, o funcionamento do drawer já havia sido validado com base nesse payload.

#### Leitura objetiva

O risco “`window.vanaDrawer` não garantidamente populado” está atualmente mitigado por dois fatos:
1. o objeto é de fato impresso antes do uso
2. o consumidor JS tem fallback defensivo

O risco só reaparece se:
- `visit-scripts.php` deixar de ser incluído
- ou a ordem dos scripts mudar em refatoração futura

Com os arquivos atuais, não há evidência de falha iminente.

#### Classificação do risco

**RESOLVIDO**

#### Impacto técnico

Baixo no estado atual.  
Existe alguma fragilidade arquitetural por dependência global, mas não uma ausência factual do objeto.

---

## 3. Classificação de cada risco: resolvido / parcial / pendente

| Risco | Situação | Classificação |
|---|---|---|
| **R1** — handlers AJAX do drawer não confirmados no PHP | Handlers registrados e implementados em `vana-mission-control.php` | **RESOLVIDO** |
| **R2** — prev/next precisa de condicional por `tour_id` | Navegação continua global; `tour_id` é ignorado no resolver | **PENDENTE** |
| **R3** — duplicação de implementação do drawer em `visit-scripts.php` | Implementação concreta está inline, enquanto comentário do template aponta outro controller | **PARCIAL** |
| **R4** — `window.vanaDrawer` não garantidamente populado | Objeto é impresso antes do uso e o JS possui fallback defensivo | **RESOLVIDO** |

---

## 4. Impacto técnico

### R1 — baixo
Como os handlers já existem, o impacto residual é baixo.  
O risco foi essencialmente encerrado.

### R2 — alto
É o risco de maior impacto funcional e contratual porque altera:
- experiência de navegação
- coerência da tour
- previsibilidade do prev/next
- aderência explícita ao Contrato 6.0

### R3 — médio
Impacta principalmente:
- manutenção
- clareza arquitetural
- segurança de refatoração

Não é o primeiro bloqueador funcional, mas pode gerar regressões secundárias.

### R4 — baixo
O objeto global está presente e o consumo é defensivo.  
O impacto atual é baixo, embora a dependência global mereça observação futura.

---

## 5. Ordem recomendada de correção

### 1º — R2
**Motivo:** é a principal divergência funcional aberta contra o Contrato 6.0.

A regra esperada precisa ser explicitada no SSR:
- com `tour_id` → prev/next por tour
- sem `tour_id` → fallback global

### 2º — R3
**Motivo:** após corrigir a lógica de navegação, é importante consolidar a fonte de verdade do drawer.

Hoje há desvio entre:
- comentário/contrato estrutural do template
- implementação real inline em `visit-scripts.php`

### 3º — R1
**Motivo:** apenas revalidação documental, não correção funcional.  
Na prática, o risco já está resolvido.

### 4º — R4
**Motivo:** somente monitoramento arquitetural.  
No estado atual, não demanda correção imediata.

---

## 6. Risco principal antes de iniciar nova implementação

O **risco principal** a tratar antes de iniciar nova implementação é:

# **R2 — prev/next precisa de condicional por `tour_id`**

### Justificativa objetiva

1. É o único risco desta lista classificado como **PENDENTE** com impacto alto.
2. Já foi apontado como divergência nas auditorias:
   - `01-hero-e-day-selector.md`
   - `05-tour-drawer-prev-next.md`
3. O próprio código atual declara o comportamento contrário ao contrato:

```php
// DT-004: Navegação é sempre cronológica global.
// Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
```

### O que falta para aderir ao contrato

Para aderir ao Contrato 6.0, ainda falta:

- introduzir condição real por `tour_id` na resolução de prev/next
- usar o vínculo de tour na navegação SSR
- preservar fallback cronológico global apenas para visita sem tour

---

## 7. Gaps

### Gap 1 — A documentação do drawer não bate com a implementação real
`tour-drawer.php` afirma:

```php
 * JS Controller: VanaVisitController.js (Fase E)
```

Mas a implementação do drawer está no script inline de `visit-scripts.php`.

### Gap 2 — A navegação por tour ainda não existe
O contrato pede comportamento condicional por `tour_id`, mas `_bootstrap.php` implementa apenas navegação global.

### Gap 3 — O objeto global `window.vanaDrawer` está funcional, mas permanece como dependência arquitetural implícita
Embora o risco imediato esteja mitigado, a solução ainda depende de estado global injetado em `<script>` inline.

---

## 8. Próximo passo recomendado

1. **Corrigir primeiro R2**
   - tornar `vana_visit_prev_next_ids()` condicional a `tour_id`
   - manter fallback global apenas quando a visita não pertencer a tour

2. **Consolidar depois R3**
   - escolher uma única fonte de verdade para o drawer:
     - ou `VanaVisitController.js`
     - ou o módulo inline de `visit-scripts.php`
   - alinhar comentário, contrato e código real

3. **Registrar formalmente o fechamento de R1 e R4**
   - R1: handlers AJAX existem
   - R4: `window.vanaDrawer` está sendo populado antes do uso e tem fallback defensivo

4. **Só depois iniciar nova implementação funcional**
   - especialmente qualquer evolução em gavetas, navegação ou integração entre módulos
   - para evitar construir novas features sobre uma base de navegação ainda divergente
```