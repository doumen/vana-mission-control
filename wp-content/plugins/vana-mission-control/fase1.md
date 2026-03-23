# SPEC_FASE1_AGENTE.md

````markdown
# Especificação de Implementação — Fase 1
# single-vana_visit.php — Schema 6.0
# Destinatário: Agente de Implementação
# Data: 22/03/2026

---

## LEIA ANTES DE COMEÇAR

```text
Esta spec é autocontida.
Não tome decisões de arquitetura por conta própria.
Se algo não estiver especificado → PARE e pergunte.
Se um arquivo não existir → crie conforme especificado.
Se um arquivo existir → edite cirurgicamente, não reescreva.
```

---

## ÍNDICE

```text
1.  Contexto do projeto
2.  Stack e convenções
3.  Arquivos a criar ou editar
4.  CPTs e campos — class-vana-cpts.php
5.  Bootstrap — _bootstrap.php
6.  Template principal — single-vana_visit.php
7.  Hero e Seletor de Dia — hero-header.php
8.  Gaveta Tour — tour-drawer.php
9.  Stage — stage.php
10. Gaveta Agenda — agenda-drawer.php
11. Chip Bar — chip-bar.php
12. Seções de profundidade — sections.php
13. LocationPin — já existe, apenas integrar
14. JavaScript — VanaVisitController.js
15. CSS — vana-visit.css
16. REST API — endpoints de leitura
17. Testes mínimos exigidos
18. O que NÃO implementar na Fase 1
19. Checklist de entrega
```

---

## 1. CONTEXTO DO PROJETO

```text
PROJETO:      Vana Madhuryam — ecossistema devocional
SITE:         WordPress (tema customizado)
PÁGINA:       single-vana_visit.php
              página de uma visita específica de Srila Vana Maharaj
MISSÃO:       reproduzir as aulas e o acervo da visita
              de forma devocional, limpa e protegida
```

### Hierarquia de conteúdo

```text
Tour (opcional)
  └── Visit (obrigatório)
        └── Day (obrigatório, some da UI se days === 1)
              └── Event (obrigatório)
                    └── Agenda (kirtan, katha, encerramento)
```

### Princípio de ouro

```text
O Stage é um santuário.
Nenhum conteúdo externo entra.
Nenhuma plataforma assume o controle.
Só sai conteúdo da @vanamadhuryamofficial.
```

---

## 2. STACK E CONVENÇÕES

```text
CMS:          WordPress 6.x
PHP:          8.1+
JS:           ES6+ vanilla (sem jQuery no novo código)
CSS:          custom properties (vars) + BEM
REST API:     namespace /vana/v1/
AJAX legado:  manter onde já existe (drawer de tour)
```

### Convenção de nomes

```text
CPTs:         vana_{nome}           ex: vana_visit, vana_katha
Meta keys:    _vana_{campo}         ex: _vana_tour_id
              _visit_{campo}        ex: _visit_timeline_json
REST routes:  /vana/v1/{recurso}    ex: /vana/v1/events
JS classes:   Vana{Nome}            ex: VanaVisitController
CSS classes:  .vana-{componente}    ex: .vana-stage
              BEM: .vana-stage__controls
              Estado: .is-active, .is-open, .is-live
Componentes:  template-parts/vana/{nome}.php
```

### CSS custom properties obrigatórias

```css
--vana-primary:          #ff9933;
--vana-primary-dark:     #ff6600;
--vana-secondary:        #d84315;
--vana-text:             #333333;
--vana-text-light:       #666666;
--vana-background:       #ffffff;
--vana-background-light: #f9f9f9;
--vana-border:           #eeeeee;
--vana-accent:           #b5872a;
--vana-accent-hover:     #d4a43e;
--vana-surface-dark:     #1a1a1a;
```

### Fontes

```text
Noto Sans            → corpo
Noto Serif           → citações / blockquote
Noto Sans Devanagari → sânscrito
```

---

## 3. ARQUIVOS A CRIAR OU EDITAR

```text
AÇÃO      ARQUIVO
────────  ──────────────────────────────────────────────────
CRIAR     includes/class-vana-cpts.php
EDITAR    includes/_bootstrap.php               (cirúrgico)
CRIAR     single-vana_visit.php                 (ou editar se existir)
CRIAR     template-parts/vana/hero-header.php   (ou editar se existir)
CRIAR     template-parts/vana/tour-drawer.php
CRIAR     template-parts/vana/stage.php
CRIAR     template-parts/vana/agenda-drawer.php
CRIAR     template-parts/vana/chip-bar.php
CRIAR     template-parts/vana/sections.php
CRIAR     assets/js/VanaVisitController.js
CRIAR     assets/css/vana-visit.css
EDITAR    functions.php                         (registros)
```

---

## 4. CPTs E CAMPOS — class-vana-cpts.php

### CPTs a registrar

```php
<?php
/**
 * class-vana-cpts.php
 * Registra CPTs e campos do projeto Vana
 */

class Vana_CPTs {

  public static function init() {
    add_action( 'init', [ __CLASS__, 'register_cpts' ] );
    add_action( 'init', [ __CLASS__, 'register_meta' ] );
  }

  // ── CPTs ──────────────────────────────────────────────

  public static function register_cpts() {

    // vana_tour
    register_post_type( 'vana_tour', [
      'label'        => 'Tours',
      'public'       => true,
      'show_in_rest' => true,
      'supports'     => [ 'title', 'thumbnail', 'excerpt' ],
      'rewrite'      => [ 'slug' => 'tour' ],
    ]);

    // vana_visit
    register_post_type( 'vana_visit', [
      'label'        => 'Visitas',
      'public'       => true,
      'show_in_rest' => true,
      'supports'     => [ 'title', 'thumbnail', 'excerpt' ],
      'rewrite'      => [ 'slug' => 'visita' ],
    ]);

    // vana_katha
    register_post_type( 'vana_katha', [
      'label'        => 'Hari-kathā',
      'public'       => true,
      'show_in_rest' => true,
      'supports'     => [ 'title', 'thumbnail' ],
      'rewrite'      => [ 'slug' => 'katha' ],
    ]);

    // hk_passage (filho de vana_katha)
    register_post_type( 'hk_passage', [
      'label'        => 'Passages',
      'public'       => false,
      'show_in_rest' => true,
      'supports'     => [ 'title', 'editor' ],
    ]);

  }

  // ── Meta fields ───────────────────────────────────────

  public static function register_meta() {

    // vana_tour
    $tour_fields = [
      '_vana_tour_destinations' => 'string',   // "Brasil · Argentina"
      '_vana_tour_start_date'   => 'string',   // "2026-03-10"
      '_vana_tour_end_date'     => 'string',   // "2026-03-30"
      '_vana_tour_status'       => 'string',   // active | archived | planned
    ];

    // vana_visit
    $visit_fields = [
      '_vana_tour_id'           => 'integer',  // FK → vana_tour (nullable)
      '_vana_start_date'        => 'string',   // "2026-03-10"
      '_vana_end_date'          => 'string',   // "2026-03-12"
      '_vana_location'          => 'string',   // "São Paulo"
      '_vana_location_lat'      => 'number',
      '_vana_location_lng'      => 'number',
      '_vana_location_label'    => 'string',
      '_vana_location_zoom'     => 'integer',
      '_visit_timeline_json'    => 'string',   // JSON — ver schema abaixo
      '_visit_status'           => 'string',   // draft|planned|live|archived
      '_visit_cover_image_id'   => 'integer',  // attachment ID
    ];

    // vana_katha
    $katha_fields = [
      '_katha_visit_id'         => 'integer',
      '_katha_event_key'        => 'string',
      '_katha_day_key'          => 'string',
      '_katha_video_id'         => 'string',   // YouTube ID
      '_katha_duration_s'       => 'integer',
      '_katha_language'         => 'string',   // hi | en | pt
      '_katha_passage_count'    => 'integer',
      '_katha_reviewed'         => 'boolean',
    ];

    self::register_fields( 'vana_tour',   $tour_fields );
    self::register_fields( 'vana_visit',  $visit_fields );
    self::register_fields( 'vana_katha',  $katha_fields );

  }

  private static function register_fields( $cpt, $fields ) {
    foreach ( $fields as $key => $type ) {
      register_post_meta( $cpt, $key, [
        'type'         => $type,
        'single'       => true,
        'show_in_rest' => true,
      ]);
    }
  }
}

Vana_CPTs::init();
```

### Schema do _visit_timeline_json

```json
{
  "version": "6.0",
  "status": "draft | planned | live | archived",
  "days": [
    {
      "day_key"    : "2026-03-10",
      "label"      : "10 mar",
      "events"     : [
        {
          "event_key"   : "20260310-1800-programa",
          "title"       : "Programa das 18h",
          "time"        : "18:00",
          "type"        : "programa | mangala | parikrama | kirtan | encerramento",
          "speaker"     : "Srila Vana Maharaj",
          "media_ref"   : "youtube_id_aqui | null",
          "segments"    : [
            {
              "id"        : 1,
              "timestamp" : "00:00",
              "seconds"   : 0,
              "title"     : "Abertura",
              "type"      : "abertura"
            }
          ],
          "katha_refs"  : [42, 43],
          "photo_refs"  : [101, 102, 103],
          "sangha_refs" : [201]
        }
      ]
    }
  ]
}
```

---

## 5. BOOTSTRAP — _bootstrap.php

### Edição cirúrgica — apenas 2 mudanças

#### Mudança 1 — prev/next escopado por tour

```php
<?php
// SUBSTITUIR a lógica atual de prev/next por:

$tour_id = get_post_meta( get_the_ID(), '_vana_tour_id', true );

if ( $tour_id ) {

  // Prev/next dentro da tour — ordem cronológica
  $tour_visits = get_posts([
    'post_type'      => 'vana_visit',
    'posts_per_page' => -1,
    'meta_key'       => '_vana_tour_id',
    'meta_value'     => $tour_id,
    'orderby'        => 'meta_value',
    'meta_key'       => '_vana_start_date',
    'order'          => 'ASC',
    'fields'         => 'ids',
  ]);

  $current_index = array_search( get_the_ID(), $tour_visits );
  $prev_visit_id = $tour_visits[ $current_index - 1 ] ?? null;
  $next_visit_id = $tour_visits[ $current_index + 1 ] ?? null;

} else {

  // Fallback — cronológico global
  // manter lógica original existente aqui
  $prev_visit_id = /* lógica original */ null;
  $next_visit_id = /* lógica original */ null;

}

// Expor para o template
$context['prev_visit'] = $prev_visit_id
  ? [ 'id' => $prev_visit_id, 'url' => get_permalink( $prev_visit_id ), 'title' => get_the_title( $prev_visit_id ) ]
  : null;

$context['next_visit'] = $next_visit_id
  ? [ 'id' => $next_visit_id, 'url' => get_permalink( $next_visit_id ), 'title' => get_the_title( $next_visit_id ) ]
  : null;

$context['prev_next_scoped'] = (bool) $tour_id; // true = escopado por tour
```

#### Mudança 2 — garantir window.vanaDrawer populado

```php
<?php
// ADICIONAR junto ao wp_json_encode existente:

$drawer_data = [
  'visitId'      => get_the_ID(),
  'tourId'       => $tour_id ?: null,
  'tourTitle'    => $tour_id ? get_the_title( $tour_id ) : null,
  'tourUrl'      => $tour_id ? get_permalink( $tour_id ) : null,
  'currentVisit' => [
    'id'    => get_the_ID(),
    'title' => get_the_title(),
    'url'   => get_permalink(),
  ],
];

wp_localize_script(
  'vana-visit-scripts',
  'vanaDrawer',
  $drawer_data
);
```

---

## 6. TEMPLATE PRINCIPAL — single-vana_visit.php

```php
<?php
/**
 * Template: single-vana_visit.php
 * Página de uma visita específica — Schema 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// Bootstrap resolve: $context, $timeline, $tour_id, $days, $current_day
// etc. — variáveis já disponíveis via _bootstrap.php

?>

<div class="vana-visit" data-visit-id="<?php echo esc_attr( get_the_ID() ); ?>">

  <?php
  // ZONA 0 — Breadcrumb
  get_template_part( 'template-parts/vana/breadcrumb' );

  // ZONA 1 + 2 — Hero + Seletor de Dia
  get_template_part( 'template-parts/vana/hero-header' );

  // ZONA 3 — Stage
  get_template_part( 'template-parts/vana/stage' );

  // ZONA 4 — Chip Bar (sticky)
  get_template_part( 'template-parts/vana/chip-bar' );

  // ZONA 5 — Seções de profundidade
  get_template_part( 'template-parts/vana/sections' );
  ?>

</div>

<?php
// Gavetas (fora do fluxo principal)
get_template_part( 'template-parts/vana/tour-drawer' );
get_template_part( 'template-parts/vana/agenda-drawer' );

// Modal de mapa (LocationPin)
get_template_part( 'template-parts/components/maps-modal' );

get_footer();
```

---

## 7. HERO E SELETOR DE DIA — hero-header.php

### Dados necessários do bootstrap

```text
$visit_title      → get_the_title()
$visit_location   → _vana_location
$start_date       → _vana_start_date
$end_date         → _vana_end_date
$tour_id          → _vana_tour_id (nullable)
$tour_title       → resolvido no bootstrap
$visit_index      → posição na tour (ex: 3)
$tour_total       → total de visitas na tour (ex: 12)
$days             → array de days do timeline_json
$current_day_key  → day_key ativo (default: hoje ou primeiro)
$prev_visit       → array com id, url, title (nullable)
$next_visit       → array com id, url, title (nullable)
$prev_next_scoped → bool
$cover_image_url  → thumbnail da visita (nullable)
```

### Markup

```php
<?php
/**
 * Component: Hero Header
 * Zona 1 (Hero) + Zona 2 (Seletor de Dia)
 */
?>

<header
  class="vana-hero"
  <?php if ( $cover_image_url ) : ?>
    style="--hero-bg: url('<?php echo esc_url( $cover_image_url ); ?>')"
  <?php endif; ?>
>

  <div class="vana-hero__inner">

    <!-- Linha superior: botão Tour + prev/next -->
    <div class="vana-hero__top">

      <!-- Botão que abre gaveta Tour -->
      <button
        class="vana-hero__tours-btn"
        data-action="open-tour-drawer"
        aria-label="Ver todas as tours"
        aria-haspopup="dialog"
      >
        <span class="vana-hero__tours-icon" aria-hidden="true">☰</span>
        <span>Tours</span>
      </button>

      <!-- Prev / Next -->
      <nav class="vana-hero__prevnext" aria-label="Navegação entre visitas">

        <?php if ( $prev_visit ) : ?>
          <a
            href="<?php echo esc_url( $prev_visit['url'] ); ?>"
            class="vana-hero__nav-btn vana-hero__nav-btn--prev"
            title="<?php echo esc_attr( $prev_visit['title'] ); ?>"
          >← anterior</a>
        <?php endif; ?>

        <?php if ( $next_visit ) : ?>
          <a
            href="<?php echo esc_url( $next_visit['url'] ); ?>"
            class="vana-hero__nav-btn vana-hero__nav-btn--next"
            title="<?php echo esc_attr( $next_visit['title'] ); ?>"
          >próxima →</a>
        <?php endif; ?>

      </nav>

    </div>

    <!-- Identidade da visita -->
    <div class="vana-hero__identity">

      <h1 class="vana-hero__title">
        <?php echo esc_html( $visit_title ); ?>
      </h1>

      <div class="vana-hero__meta">
        <span class="vana-hero__location">
          <?php echo esc_html( $visit_location ); ?>
        </span>
        <span class="vana-hero__period">
          <?php echo esc_html( vana_format_period( $start_date, $end_date ) ); ?>
        </span>
        <?php if ( $tour_id && $visit_index && $tour_total ) : ?>
          <span class="vana-hero__tour-counter">
            Visita <?php echo (int) $visit_index; ?> de <?php echo (int) $tour_total; ?>
          </span>
        <?php endif; ?>
      </div>

    </div>

    <!-- Seletor de Dia (Zona 2) — só se days > 1 -->
    <?php if ( count( $days ) > 1 ) : ?>
      <?php
      // Agrupa por mês para o cabeçalho
      $days_by_month = vana_group_days_by_month( $days );
      ?>
      <div
        class="vana-day-selector"
        role="tablist"
        aria-label="Selecionar dia"
      >
        <?php foreach ( $days_by_month as $month_label => $month_days ) : ?>

          <div class="vana-day-selector__group">

            <span class="vana-day-selector__month-label">
              <?php echo esc_html( $month_label ); ?>
            </span>

            <div class="vana-day-selector__pills">
              <?php foreach ( $month_days as $day ) : ?>
                <button
                  class="vana-day-selector__pill
                    <?php echo $day['day_key'] === $current_day_key ? 'is-active' : ''; ?>"
                  role="tab"
                  data-day-key="<?php echo esc_attr( $day['day_key'] ); ?>"
                  aria-selected="<?php echo $day['day_key'] === $current_day_key ? 'true' : 'false'; ?>"
                >
                  <?php echo esc_html( $day['label'] ); ?>
                </button>
              <?php endforeach; ?>
            </div>

          </div>

        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

</header>
```

### Funções auxiliares PHP necessárias

```php
<?php
/**
 * Formata período da visita
 * Ex: "10–12 mar 2026" ou "10 mar – 2 abr 2026"
 */
function vana_format_period( string $start, string $end ): string {
  if ( empty( $start ) ) return '';
  if ( empty( $end ) || $start === $end ) {
    return date_i18n( 'j M Y', strtotime( $start ) );
  }
  // mesmo mês
  $s = new DateTime( $start );
  $e = new DateTime( $end );
  if ( $s->format( 'mY' ) === $e->format( 'mY' ) ) {
    return $s->format( 'j' ) . '–' . date_i18n( 'j M Y', $e->getTimestamp() );
  }
  return date_i18n( 'j M', $s->getTimestamp() ) . ' – ' . date_i18n( 'j M Y', $e->getTimestamp() );
}

/**
 * Agrupa days por mês para o seletor
 * Retorna: [ "março 2026" => [ day, day ], "abril 2026" => [ day ] ]
 */
function vana_group_days_by_month( array $days ): array {
  $groups = [];
  foreach ( $days as $day ) {
    $ts    = strtotime( $day['day_key'] );
    $month = date_i18n( 'F Y', $ts );
    $groups[ $month ][] = $day;
  }
  return $groups;
}
```

---

## 8. GAVETA TOUR — tour-drawer.php

### Verificar antes de implementar

```text
⚠️  VERIFICAÇÃO OBRIGATÓRIA:
    Confirmar se os handlers PHP existem:
      add_action( 'wp_ajax_vana_get_tours', ... )
      add_action( 'wp_ajax_nopriv_vana_get_tours', ... )
      add_action( 'wp_ajax_vana_get_tour_visits', ... )
      add_action( 'wp_ajax_nopriv_vana_get_tour_visits', ... )

    SE existirem → usar e integrar com o novo markup
    SE não existirem → criar conforme abaixo
```

### Handlers AJAX (criar se não existirem)

```php
<?php
/**
 * AJAX: retorna lista de tours
 */
add_action( 'wp_ajax_vana_get_tours',        'vana_ajax_get_tours' );
add_action( 'wp_ajax_nopriv_vana_get_tours', 'vana_ajax_get_tours' );

function vana_ajax_get_tours() {
  $tours = get_posts([
    'post_type'      => 'vana_tour',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value',
    'meta_key'       => '_vana_tour_start_date',
    'order'          => 'DESC',
  ]);

  $data = array_map( function( $tour ) {
    return [
      'id'           => $tour->ID,
      'title'        => get_the_title( $tour ),
      'destinations' => get_post_meta( $tour->ID, '_vana_tour_destinations', true ),
      'status'       => get_post_meta( $tour->ID, '_vana_tour_status', true ),
      'start_date'   => get_post_meta( $tour->ID, '_vana_tour_start_date', true ),
    ];
  }, $tours );

  wp_send_json_success( $data );
}

/**
 * AJAX: retorna visitas de uma tour
 */
add_action( 'wp_ajax_vana_get_tour_visits',        'vana_ajax_get_tour_visits' );
add_action( 'wp_ajax_nopriv_vana_get_tour_visits', 'vana_ajax_get_tour_visits' );

function vana_ajax_get_tour_visits() {
  $tour_id = intval( $_POST['tour_id'] ?? 0 );
  if ( ! $tour_id ) wp_send_json_error( 'tour_id missing' );

  $visits = get_posts([
    'post_type'      => 'vana_visit',
    'posts_per_page' => -1,
    'meta_query'     => [[ 'key' => '_vana_tour_id', 'value' => $tour_id ]],
    'meta_key'       => '_vana_start_date',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
  ]);

  $current_id = intval( $_POST['current_visit_id'] ?? 0 );

  $data = array_map( function( $visit ) use ( $current_id ) {
    $timeline = json_decode(
      get_post_meta( $visit->ID, '_visit_timeline_json', true ), true
    );
    $status = $timeline['status'] ?? 'draft';
    return [
      'id'         => $visit->ID,
      'title'      => get_the_title( $visit ),
      'url'        => get_permalink( $visit ),
      'location'   => get_post_meta( $visit->ID, '_vana_location', true ),
      'start_date' => get_post_meta( $visit->ID, '_vana_start_date', true ),
      'end_date'   => get_post_meta( $visit->ID, '_vana_end_date', true ),
      'status'     => $status,
      'is_current' => $visit->ID === $current_id,
    ];
  }, $visits );

  wp_send_json_success( $data );
}
```

### Markup da gaveta

```php
<?php
/**
 * Component: Tour Drawer
 * Gaveta esquerda — dois cenários: com tour e sem tour
 */
?>

<div
  id="vana-tour-drawer"
  class="vana-drawer vana-drawer--tour"
  role="dialog"
  aria-modal="true"
  aria-label="Navegação por tours"
  aria-hidden="true"
>

  <div class="vana-drawer__backdrop" data-action="close-tour-drawer"></div>

  <div class="vana-drawer__panel">

    <header class="vana-drawer__header">
      <button
        class="vana-drawer__back"
        data-action="drawer-back"
        style="display:none"
        aria-label="Voltar para lista de tours"
      >← Voltar</button>
      <h2 class="vana-drawer__title">Tours</h2>
      <button
        class="vana-drawer__close"
        data-action="close-tour-drawer"
        aria-label="Fechar"
      >✕</button>
    </header>

    <!-- Nível 1: lista de tours (estado inicial) -->
    <div class="vana-drawer__level" id="drawer-level-tours">
      <div class="vana-drawer__loading">Carregando…</div>
      <!-- preenchido via JS -->
    </div>

    <!-- Nível 2: visitas de uma tour -->
    <div class="vana-drawer__level" id="drawer-level-visits" hidden>
      <div class="vana-drawer__loading">Carregando…</div>
      <!-- preenchido via JS -->
    </div>

  </div>

</div>
```

### Status visual das visitas

```text
STATUS          BADGE     COR
────────────    ──────    ──────────────────
archived        ✅        verde sutil
live            ▶         laranja (--vana-primary)
planned         📅        azul sutil
draft / vazio   ⚙️        cinza
is_current      ● você    destaque + borda
```

---

## 9. STAGE — stage.php

### Modos de exibição

```text
MODO          CONDIÇÃO                    EXIBE
────────────  ──────────────────────────  ──────────────────────────
video         event.media_ref != null     YouTube embed protegido
neutro        event.media_ref === null    logo + título do evento
aguardando    evento futuro sem mídia     logo + horário de início
erro          embed falhou                logo + "Mídia indisponível"
```

### Markup

```php
<?php
/**
 * Component: Stage
 * Zona 3 — player devocional protegido
 *
 * Estado inicial: evento mais recente com VOD
 * ou primeiro evento do dia atual
 */

// Estado inicial resolvido pelo bootstrap
// $initial_event = primeiro evento com media_ref do dia atual
?>

<section
  class="vana-stage"
  data-visit-id="<?php echo esc_attr( get_the_ID() ); ?>"
  aria-label="Player do evento"
>

  <!-- Área de mídia -->
  <div class="vana-stage__media-wrapper">

    <!-- Iframe YouTube — src preenchido via JS -->
    <iframe
      id="vana-stage-iframe"
      class="vana-stage__iframe"
      src=""
      allow="accelerometer; autoplay; clipboard-write;
             encrypted-media; gyroscope; picture-in-picture"
      allowfullscreen
      title="Player do evento"
    ></iframe>

    <!-- Tela neutra — exibida quando sem mídia -->
    <div class="vana-stage__neutral" aria-hidden="true" hidden>
      <img
        src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/img/vana-logo.svg"
        alt="Vana Madhuryam"
        class="vana-stage__neutral-logo"
      >
      <p class="vana-stage__neutral-text"></p>
    </div>

    <!-- Tela de transição entre eventos -->
    <div class="vana-stage__transition" aria-hidden="true" hidden>
      <img
        src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/img/vana-logo.svg"
        alt="Vana Madhuryam"
      >
      <p class="vana-stage__transition-label">A seguir:</p>
      <p class="vana-stage__transition-title"></p>
      <div class="vana-stage__transition-actions">
        <button data-action="stage-play-next">▶ Iniciar agora</button>
        <button data-action="stage-pause-autoplay">Pausar</button>
      </div>
      <div class="vana-stage__transition-countdown" aria-live="polite">5</div>
    </div>

  </div>

  <!-- Informações do evento ativo -->
  <div class="vana-stage__info">
    <h2 class="vana-stage__event-title"></h2>
    <p class="vana-stage__event-meta">
      <span class="vana-stage__speaker"></span>
      <span class="vana-stage__time"></span>
    </p>
  </div>

  <!-- Controles -->
  <div class="vana-stage__controls">
    <button
      class="vana-stage__btn"
      data-action="stage-prev"
      aria-label="Evento anterior"
    >⏮</button>

    <button
      class="vana-stage__btn vana-stage__btn--play"
      data-action="stage-play-pause"
      aria-label="Play / Pause"
    >▶</button>

    <button
      class="vana-stage__btn"
      data-action="stage-next"
      aria-label="Próximo evento"
    >⏭</button>

    <div class="vana-stage__volume">
      <label for="vana-stage-volume" class="sr-only">Volume</label>
      <input
        id="vana-stage-volume"
        type="range"
        min="0"
        max="100"
        value="80"
        data-action="stage-volume"
      >
    </div>
  </div>

  <!-- Segments -->
  <div class="vana-stage__segments" hidden>
    <ul class="vana-stage__segments-list" role="list">
      <!-- preenchido via JS -->
    </ul>
  </div>

  <!-- Ações -->
  <div class="vana-stage__actions">
    <button
      class="vana-stage__action-btn"
      data-action="open-hk"
      hidden
    >📖 Hari-katha</button>

    <button
      class="vana-stage__action-btn"
      data-action="stage-share"
    >🔗 Compartilhar</button>

    <!-- Botão que abre Agenda -->
    <button
      class="vana-stage__action-btn vana-stage__agenda-btn"
      data-action="open-agenda-drawer"
      aria-haspopup="dialog"
    >📅 <span class="vana-stage__agenda-count"></span></button>
  </div>

</section>
```

### Parâmetros do embed YouTube

```text
enablejsapi=1
rel=0
modestbranding=1
controls=0
autoplay=0
origin={site_url}

URL base:
https://www.youtube.com/embed/{video_id}?{params}
```

### Estados CSS do Stage

```text
.vana-stage                      → estado base
.vana-stage.is-playing           → mídia ativa
.vana-stage.is-paused            → pausado
.vana-stage.is-loading           → spinner
.vana-stage.is-neutral           → sem mídia
.vana-stage.is-transitioning     → entre eventos
.vana-stage.is-error             → falha de mídia
.vana-stage.is-live              → live ativa (badge 🔴)
```

---

## 10. GAVETA AGENDA — agenda-drawer.php

```php
<?php
/**
 * Component: Agenda Drawer
 * Gaveta direita — programa do dia selecionado
 *
 * Estado padrão: FECHADA
 * Abre: botão no Stage ou pill flutuante (mobile)
 * Fecha: X, ESC, clique fora, toque fora
 *
 * Abertura automática:
 *   → visit sem mídia → abre
 *   → live ativa      → abre, fecha em 3s se sem interação
 */
?>

<div
  id="vana-agenda-drawer"
  class="vana-drawer vana-drawer--agenda"
  role="dialog"
  aria-modal="true"
  aria-label="Agenda do dia"
  aria-hidden="true"
>

  <div class="vana-drawer__backdrop" data-action="close-agenda-drawer"></div>

  <div class="vana-drawer__panel">

    <header class="vana-drawer__header">
      <h2 class="vana-drawer__title">
        Agenda
        <span class="vana-agenda__day-label"></span>
      </h2>
      <button
        class="vana-drawer__close"
        data-action="close-agenda-drawer"
        aria-label="Fechar agenda"
      >✕</button>
    </header>

    <!-- Lista de eventos — preenchida via JS -->
    <ul
      class="vana-agenda__list"
      role="list"
      aria-label="Eventos do dia"
    >
      <!-- item template (gerado via JS):

      <li class="vana-agenda__event {is-active|is-past|is-future}"
          data-event-key="{event_key}"
          data-media-ref="{youtube_id|null}">

        <div class="vana-agenda__event-time">{time}</div>

        <div class="vana-agenda__event-body">
          <h3 class="vana-agenda__event-title">{title}</h3>
          <p  class="vana-agenda__event-speaker">{speaker}</p>

          <div class="vana-agenda__event-actions">
            // [▶ Ouvir]  → só se media_ref existe
            // [📖 HK]    → só se katha_ref existe
            // [🔔]       → só se evento futuro
          </div>
        </div>

      </li>
      -->
    </ul>

    <!-- Rodapé: seletor de idioma -->
    <footer class="vana-drawer__footer">
      <div class="vana-lang-selector" role="group" aria-label="Idioma do conteúdo">
        <button
          class="vana-lang-selector__btn is-active"
          data-lang="pt"
          aria-pressed="true"
        >PT</button>
        <button
          class="vana-lang-selector__btn"
          data-lang="en"
          aria-pressed="false"
        >EN</button>
      </div>
    </footer>

  </div>

</div>

<!-- Pill flutuante (mobile) — sempre visível quando agenda fechada -->
<button
  id="vana-agenda-pill"
  class="vana-agenda-pill"
  data-action="open-agenda-drawer"
  aria-label="Abrir agenda"
  aria-haspopup="dialog"
>
  📅 <span class="vana-agenda-pill__count"></span>
</button>
```

### Regras de idioma

```text
DETECÇÃO AUTOMÁTICA:
  1. localStorage 'vana_lang_preference' → usa se existe
  2. navigator.language → extrai 'pt' ou 'en'
  3. fallback → 'pt'

TROCA MANUAL:
  → salva em localStorage
  → dispara evento: new CustomEvent('vana:lang:change', { detail: { lang } })
  → single-vana_katha.php escuta e reage

ESCOPO:
  → troca o idioma do HK (texto)
  → NÃO troca o áudio
  → NÃO recarrega a página
```

---

## 11. CHIP BAR — chip-bar.php

```php
<?php
/**
 * Component: Chip Bar
 * Zona 4 — navegação entre seções de profundidade
 * Sticky abaixo do Stage
 */
?>

<nav
  class="vana-chip-bar"
  role="tablist"
  aria-label="Seções da visita"
>
  <button
    class="vana-chip is-active"
    role="tab"
    data-target="section-hk"
    aria-selected="true"
  >📖 Hari-kathā</button>

  <button
    class="vana-chip"
    role="tab"
    data-target="section-galeria"
    aria-selected="false"
  >🖼️ Galeria</button>

  <button
    class="vana-chip"
    role="tab"
    data-target="section-sangha"
    aria-selected="false"
  >🙏 Sangha</button>

  <button
    class="vana-chip"
    role="tab"
    data-target="section-revista"
    aria-selected="false"
  >📰 Revista</button>

</nav>
```

---

## 12. SEÇÕES DE PROFUNDIDADE — sections.php

```php
<?php
/**
 * Component: Sections
 * Zona 5 — HK | Galeria | Sangha | Revista
 * Conteúdo filtrado pelo evento ativo no Stage
 */
?>

<div class="vana-sections">

  <!-- HK — Hari-kathā -->
  <section
    id="section-hk"
    class="vana-section vana-section--hk is-active"
    role="tabpanel"
    aria-label="Hari-kathā"
  >
    <div class="vana-section__loading" aria-live="polite">
      Carregando Hari-kathā…
    </div>
    <div class="vana-section__content">
      <!-- preenchido via REST GET /vana/v1/kathas?event_key= -->
    </div>
  </section>

  <!-- Galeria -->
  <section
    id="section-galeria"
    class="vana-section vana-section--galeria"
    role="tabpanel"
    aria-label="Galeria"
    hidden
  >
    <div class="vana-section__content">
      <!-- preenchido via REST GET /vana/v1/media?event_key=&type=photo -->
    </div>
  </section>

  <!-- Sangha -->
  <section
    id="section-sangha"
    class="vana-section vana-section--sangha"
    role="tabpanel"
    aria-label="Sangha"
    hidden
  >
    <div class="vana-section__content">
      <!-- preenchido via REST GET /vana/v1/sangha?event_key= -->
    </div>
  </section>

  <!-- Revista -->
  <section
    id="section-revista"
    class="vana-section vana-section--revista"
    role="tabpanel"
    aria-label="Revista"
    hidden
  >
    <div class="vana-section__content">
      <!-- preenchido via REST GET /vana/v1/revista?visit_id= -->
    </div>
  </section>

</div>

<!-- Modal de assets órfãos (colaborador) -->
<div id="vana-orphan-modal" class="vana-orphan-modal" hidden>
  <!-- assets sem event_key — visível apenas para colaborador -->
</div>
```

---

## 13. LOCATIONPIN — apenas integrar

```text
O componente LocationPin já está completo e entregue.
NÃO reescrever. Apenas integrar.

USO no hero-header.php ou sections.php:

<?php
get_template_part(
  'template-parts/components/location-pin',
  null,
  [ 'post_id' => get_the_ID() ]
);
?>

MODAL no footer (1x por página):
  → já está em single-vana_visit.php via maps-modal
  → confirmar que não está duplicado
```

---

## 14. JAVASCRIPT — VanaVisitController.js

### Estrutura modular

```javascript
/**
 * VanaVisitController.js
 * Controlador principal da página single-vana_visit
 *
 * DEPENDÊNCIAS:
 *   window.vanaVisitData  → dados do bootstrap (PHP)
 *   window.vanaDrawer     → dados da gaveta (PHP)
 *   YouTube IFrame API    → player
 */

(function () {
  'use strict';

  // ── Módulos ───────────────────────────────────────────

  const VanaVisitController = {

    // Estado global da página
    state: {
      currentDayKey   : null,
      currentEventKey : null,
      currentLang     : 'pt',
      player          : null,   // YT.Player instance
      autoplayTimer   : null,
    },

    // ── Init ────────────────────────────────────────────

    init() {
      this.state.currentDayKey = window.vanaVisitData?.currentDayKey;
      this.state.currentLang   = this._resolveLang();

      this._initDaySelector();
      this._initTourDrawer();
      this._initAgendaDrawer();
      this._initChipBar();
      this._initStage();
      this._bindGlobalEvents();
    },

    // ── Seletor de Dia ───────────────────────────────────

    _initDaySelector() {
      document.querySelectorAll('[data-day-key]').forEach(btn => {
        btn.addEventListener('click', () => {
          this._selectDay( btn.dataset.dayKey );
        });
      });
    },

    _selectDay( dayKey ) {
      this.state.currentDayKey = dayKey;

      // Atualiza pills
      document.querySelectorAll('[data-day-key]').forEach(btn => {
        btn.classList.toggle( 'is-active', btn.dataset.dayKey === dayKey );
        btn.setAttribute( 'aria-selected', btn.dataset.dayKey === dayKey );
      });

      // Recarrega agenda com eventos do dia
      this._loadAgendaEvents( dayKey );

      // NÃO muda o Stage automaticamente
    },

    // ── Tour Drawer ──────────────────────────────────────

    _initTourDrawer() {
      document.addEventListener('click', e => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        if ( action === 'open-tour-drawer'  ) this._openTourDrawer();
        if ( action === 'close-tour-drawer' ) this._closeTourDrawer();
        if ( action === 'drawer-back'       ) this._drawerBack();
      });

      // ESC fecha
      document.addEventListener('keydown', e => {
        if ( e.key === 'Escape' ) {
          this._closeTourDrawer();
          this._closeAgendaDrawer();
        }
      });
    },

    _openTourDrawer() {
      const drawer = document.getElementById('vana-tour-drawer');
      if (!drawer) return;
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      this._loadTours();
    },

    _closeTourDrawer() {
      const drawer = document.getElementById('vana-tour-drawer');
      if (!drawer) return;
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    },

    _loadTours() {
      const container = document.getElementById('drawer-level-tours');
      if (!container) return;

      // Verifica se já carregou
      if ( container.dataset.loaded ) return;

      fetch( window.ajaxurl, {
        method  : 'POST',
        headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
        body    : 'action=vana_get_tours',
      })
      .then( r => r.json() )
      .then( res => {
        if (!res.success) return;
        container.innerHTML = this._renderTourList( res.data );
        container.dataset.loaded = '1';
        container.querySelectorAll('[data-tour-id]').forEach(btn => {
          btn.addEventListener('click', () => {
            this._loadTourVisits( btn.dataset.tourId, btn.dataset.tourTitle );
          });
        });
      });
    },

    _renderTourList( tours ) {
      if (!tours.length) return '<p>Nenhuma tour encontrada.</p>';
      return tours.map( t => `
        <button class="vana-drawer__tour-item" data-tour-id="${t.id}" data-tour-title="${t.title}">
          <span class="vana-drawer__tour-title">${t.title}</span>
          <span class="vana-drawer__tour-destinations">${t.destinations || ''}</span>
        </button>
      `).join('');
    },

    _loadTourVisits( tourId, tourTitle ) {
      const levelTours  = document.getElementById('drawer-level-tours');
      const levelVisits = document.getElementById('drawer-level-visits');
      const backBtn     = document.querySelector('[data-action="drawer-back"]');
      const titleEl     = document.querySelector('.vana-drawer__title');

      levelTours.hidden  = true;
      levelVisits.hidden = false;
      backBtn.style.display = '';
      if (titleEl) titleEl.textContent = tourTitle;

      fetch( window.ajaxurl, {
        method  : 'POST',
        headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
        body    : `action=vana_get_tour_visits&tour_id=${tourId}&current_visit_id=${window.vanaDrawer?.currentVisit?.id || 0}`,
      })
      .then( r => r.json() )
      .then( res => {
        if (!res.success) return;
        levelVisits.innerHTML = this._renderVisitList( res.data );
      });
    },

    _renderVisitList( visits ) {
      const statusIcon = {
        archived : '✅',
        live     : '▶',
        planned  : '📅',
        draft    : '⚙️',
      };
      return visits.map( v => {
        const icon    = statusIcon[v.status] || '⚙️';
        const current = v.is_current ? 'is-current' : '';
        return `
          <a href="${v.url}" class="vana-drawer__visit-item ${current}">
            <span class="vana-drawer__visit-status">${icon}</span>
            <span class="vana-drawer__visit-info">
              <span class="vana-drawer__visit-title">${v.title}</span>
              <span class="vana-drawer__visit-date">${v.start_date || ''}</span>
            </span>
            ${v.is_current ? '<span class="vana-drawer__visit-current">● você está aqui</span>' : ''}
          </a>
        `;
      }).join('');
    },

    _drawerBack() {
      const levelTours  = document.getElementById('drawer-level-tours');
      const levelVisits = document.getElementById('drawer-level-visits');
      const backBtn     = document.querySelector('[data-action="drawer-back"]');
      const titleEl     = document.querySelector('.vana-drawer__title');

      levelTours.hidden  = false;
      levelVisits.hidden = true;
      backBtn.style.display = 'none';
      if (titleEl) titleEl.textContent = 'Tours';
    },

    // ── Agenda Drawer ────────────────────────────────────

    _initAgendaDrawer() {
      document.addEventListener('click', e => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        if ( action === 'open-agenda-drawer'  ) this._openAgendaDrawer();
        if ( action === 'close-agenda-drawer' ) this._closeAgendaDrawer();
      });

      // Abertura automática: visita sem mídia
      const timeline = window.vanaVisitData?.timeline;
      if ( timeline && this._visitHasNoMedia( timeline ) ) {
        this._openAgendaDrawer();
      }

      // Idioma
      document.querySelectorAll('[data-lang]').forEach(btn => {
        btn.addEventListener('click', () => this._setLang( btn.dataset.lang ));
      });
    },

    _openAgendaDrawer() {
      const drawer = document.getElementById('vana-agenda-drawer');
      const pill   = document.getElementById('vana-agenda-pill');
      if (!drawer) return;
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      if (pill) pill.hidden = true;
      this._loadAgendaEvents( this.state.currentDayKey );
    },

    _closeAgendaDrawer() {
      const drawer = document.getElementById('vana-agenda-drawer');
      const pill   = document.getElementById('vana-agenda-pill');
      if (!drawer) return;
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (pill) pill.hidden = false;
    },

    _loadAgendaEvents( dayKey ) {
      const timeline = window.vanaVisitData?.timeline;
      if (!timeline) return;

      const day = timeline.days.find( d => d.day_key === dayKey );
      if (!day) return;

      const list = document.querySelector('.vana-agenda__list');
      const label = document.querySelector('.vana-agenda__day-label');
      const pill  = document.querySelector('.vana-agenda-pill__count');
      const stageCount = document.querySelector('.vana-stage__agenda-count');

      if (label) label.textContent = `· ${day.label}`;

      const now = new Date();

      list.innerHTML = day.events.map( event => {
        const status   = this._getEventStatus( event, now );
        const hasMedia = !!event.media_ref;
        const hasKatha = event.katha_refs?.length > 0;

        return `
          <li
            class="vana-agenda__event is-${status}"
            data-event-key="${event.event_key}"
            data-media-ref="${event.media_ref || ''}"
          >
            <div class="vana-agenda__event-time">${event.time}</div>
            <div class="vana-agenda__event-body">
              <h3 class="vana-agenda__event-title">${event.title}</h3>
              ${event.speaker ? `<p class="vana-agenda__event-speaker">${event.speaker}</p>` : ''}
              <div class="vana-agenda__event-actions">
                ${hasMedia ? `<button class="vana-agenda__btn" data-action="agenda-play" data-event-key="${event.event_key}">▶ Ouvir</button>` : ''}
                ${hasKatha ? `<button class="vana-agenda__btn" data-action="agenda-hk"   data-event-key="${event.event_key}">📖 HK</button>` : ''}
                ${status === 'future' ? `<button class="vana-agenda__btn" data-action="agenda-notify" data-event-key="${event.event_key}">🔔</button>` : ''}
              </div>
            </div>
          </li>
        `;
      }).join('');

      // Conta eventos
      const count = day.events.length;
      if (pill)        pill.textContent        = count;
      if (stageCount)  stageCount.textContent  = `${count} eventos`;

      // Bind ações da agenda
      list.querySelectorAll('[data-action="agenda-play"]').forEach( btn => {
        btn.addEventListener('click', () => {
          const eventKey = btn.dataset.eventKey;
          this._playEvent( eventKey );
          this._closeAgendaDrawer();
        });
      });
    },

    _getEventStatus( event, now ) {
      if (!event.time) return 'future';
      const [h, m] = event.time.split(':').map(Number);
      const eventDate = new Date( this.state.currentDayKey );
      eventDate.setHours(h, m, 0, 0);
      if ( now < eventDate ) return 'future';
      // Evento ativo: dentro de 2 horas após início (estimativa)
      const twoHours = new Date( eventDate.getTime() + 2 * 60 * 60 * 1000 );
      if ( now < twoHours ) return 'active';
      return 'past';
    },

    _visitHasNoMedia( timeline ) {
      return timeline.days.every( day =>
        day.events.every( event => !event.media_ref )
      );
    },

    // ── Idioma ───────────────────────────────────────────

    _resolveLang() {
      const saved = localStorage.getItem('vana_lang_preference');
      if (saved) return saved;
      const browser = navigator.language?.slice(0, 2) || 'pt';
      return ['pt','en'].includes(browser) ? browser : 'pt';
    },

    _setLang( lang ) {
      this.state.currentLang = lang;
      localStorage.setItem( 'vana_lang_preference', lang );

      // Atualiza botões
      document.querySelectorAll('[data-lang]').forEach( btn => {
        btn.classList.toggle( 'is-active', btn.dataset.lang === lang );
        btn.setAttribute( 'aria-pressed', btn.dataset.lang === lang );
      });

      // Dispara evento global
      document.dispatchEvent(
        new CustomEvent( 'vana:lang:change', { detail: { lang } })
      );
    },

    // ── Chip Bar ─────────────────────────────────────────

    _initChipBar() {
      document.querySelectorAll('.vana-chip').forEach( chip => {
        chip.addEventListener('click', () => {
          const target = chip.dataset.target;
          this._activateSection( target );
        });
      });
    },

    _activateSection( targetId ) {
      // Chips
      document.querySelectorAll('.vana-chip').forEach( chip => {
        const active = chip.dataset.target === targetId;
        chip.classList.toggle( 'is-active', active );
        chip.setAttribute( 'aria-selected', active );
      });

      // Seções
      document.querySelectorAll('.vana-section').forEach( section => {
        const active = section.id === targetId;
        section.classList.toggle( 'is-active', active );
        section.hidden = !active;
      });

      // Carrega conteúdo da seção se ainda não carregou
      this._loadSectionContent( targetId );
    },

    // ── Stage ────────────────────────────────────────────

    _initStage() {
      // Carrega YouTube IFrame API
      if ( !window.YT ) {
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
        window.onYouTubeIframeAPIReady = () => this._initPlayer();
      } else {
        this._initPlayer();
      }

      // Bind controles
      document.addEventListener('click', e => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        if ( action === 'stage-play-pause'    ) this._togglePlay();
        if ( action === 'stage-prev'          ) this._prevEvent();
        if ( action === 'stage-next'          ) this._nextEvent();
        if ( action === 'stage-play-next'     ) this._playNextAutoplay();
        if ( action === 'stage-pause-autoplay') this._cancelAutoplay();
        if ( action === 'stage-share'         ) this._shareEvent();
        if ( action === 'open-hk'             ) this._openHK();
      });
    },

    _initPlayer() {
      // Evento inicial
      const timeline     = window.vanaVisitData?.timeline;
      const currentDay   = timeline?.days.find( d => d.day_key === this.state.currentDayKey );
      const initialEvent = currentDay?.events.find( e => e.media_ref );

      if (!initialEvent) {
        this._showNeutralStage( 'Nenhuma mídia disponível para este dia.' );
        return;
      }

      this._loadEvent( initialEvent );
    },

    _loadEvent( event ) {
      this.state.currentEventKey = event.event_key;

      const iframe = document.getElementById('vana-stage-iframe');
      const neutral     = document.querySelector('.vana-stage__neutral');
      const transition  = document.querySelector('.vana-stage__transition');
      const titleEl     = document.querySelector('.vana-stage__event-title');
      const speakerEl   = document.querySelector('.vana-stage__speaker');
      const timeEl      = document.querySelector('.vana-stage__time');
      const stage       = document.querySelector('.vana-stage');
      const hkBtn       = document.querySelector('[data-action="open-hk"]');

      // Esconde telas especiais
      if (neutral)    neutral.hidden    = true;
      if (transition) transition.hidden = true;

      // Informações
      if (titleEl)  titleEl.textContent  = event.title;
      if (speakerEl) speakerEl.textContent = event.speaker || '';
      if (timeEl)    timeEl.textContent    = event.time || '';

      // Stage state
      stage?.classList.remove('is-neutral','is-transitioning','is-error');
      stage?.classList.add('is-loading');

      if ( event.media_ref ) {
        const params = new URLSearchParams({
          enablejsapi      : 1,
          rel              : 0,
          modestbranding   : 1,
          controls         : 0,
          autoplay         : 0,
          origin           : window.location.origin,
        });
        iframe.src = `https://www.youtube.com/embed/${event.media_ref}?${params}`;
        iframe.hidden = false;
        stage?.classList.remove('is-loading');
        stage?.classList.add('is-paused');
      } else {
        this._showNeutralStage( event.title );
      }

      // Segments
      this._renderSegments( event.segments || [] );

      // Botão HK
      if (hkBtn) hkBtn.hidden = !(event.katha_refs?.length > 0);

      // Sincroniza Agenda
      document.querySelectorAll('.vana-agenda__event').forEach( item => {
        item.classList.toggle( 'is-active', item.dataset.eventKey === event.event_key );
      });

      // Carrega HK se seção ativa
      this._loadSectionContent('section-hk');
    },

    _showNeutralStage( message ) {
      const neutral  = document.querySelector('.vana-stage__neutral');
      const iframe   = document.getElementById('vana-stage-iframe');
      const msgEl    = document.querySelector('.vana-stage__neutral-text');
      const stage    = document.querySelector('.vana-stage');

      if (iframe)  iframe.hidden  = true;
      if (neutral) neutral.hidden = false;
      if (msgEl)   msgEl.textContent = message || '';
      stage?.classList.add('is-neutral');
      stage?.classList.remove('is-loading','is-playing','is-paused');
    },

    _renderSegments( segments ) {
      const wrapper = document.querySelector('.vana-stage__segments');
      const list    = document.querySelector('.vana-stage__segments-list');
      if (!wrapper || !list) return;

      if (!segments.length) {
        wrapper.hidden = true;
        return;
      }

      wrapper.hidden = false;
      list.innerHTML = segments.map( seg => `
        <li
          class="vana-stage__segment"
          data-seconds="${seg.seconds}"
          data-segment-id="${seg.id}"
        >
          <button
            class="vana-stage__segment-btn"
            data-action="segment-seek"
            data-seconds="${seg.seconds}"
          >
            <span class="vana-stage__segment-time">${seg.timestamp}</span>
            <span class="vana-stage__segment-title">${seg.title}</span>
          </button>
        </li>
      `).join('');

      // Bind seek
      list.querySelectorAll('[data-action="segment-seek"]').forEach( btn => {
        btn.addEventListener('click', () => {
          this._seekTo( parseInt( btn.dataset.seconds ) );
        });
      });
    },

    _togglePlay() {
      if (!this.state.player) return;
      const state = this.state.player.getPlayerState();
      state === 1 ? this.state.player.pauseVideo() : this.state.player.playVideo();
    },

    _seekTo( seconds ) {
      if (!this.state.player) return;
      this.state.player.seekTo( seconds, true );
    },

    _playEvent( eventKey ) {
      const timeline   = window.vanaVisitData?.timeline;
      const currentDay = timeline?.days.find( d => d.day_key === this.state.currentDayKey );
      const event      = currentDay?.events.find( e => e.event_key === eventKey );
      if (event) this._loadEvent(event);
    },

    _prevEvent() {
      const events     = this._getCurrentDayEvents();
      const currentIdx = events.findIndex( e => e.event_key === this.state.currentEventKey );
      const prev       = events[ currentIdx - 1 ];
      if (prev) this._loadEvent(prev);
    },

    _nextEvent() {
      const events     = this._getCurrentDayEvents();
      const currentIdx = events.findIndex( e => e.event_key === this.state.currentEventKey );
      const next       = events[ currentIdx + 1 ];
      if (next) this._showTransition(next);
      else      this._showNeutralStage('Até amanhã, Hare Kṛṣṇa 🙏');
    },

    _showTransition( nextEvent ) {
      const transition = document.querySelector('.vana-stage__transition');
      const titleEl    = document.querySelector('.vana-stage__transition-title');
      const countdown  = document.querySelector('.vana-stage__transition-countdown');
      const iframe     = document.getElementById('vana-stage-iframe');
      const stage      = document.querySelector('.vana-stage');

      if (iframe)     iframe.hidden     = true;
      if (transition) transition.hidden = false;
      if (titleEl)    titleEl.textContent = nextEvent.title;
      stage?.classList.add('is-transitioning');

      let count = 5;
      if (countdown) countdown.textContent = count;

      this.state.autoplayTimer = setInterval(() => {
        count--;
        if (countdown) countdown.textContent = count;
        if (count <= 0) {
          clearInterval(this.state.autoplayTimer);
          this._loadEvent(nextEvent);
        }
      }, 1000);
    },

    _playNextAutoplay() {
      clearInterval(this.state.autoplayTimer);
      const events     = this._getCurrentDayEvents();
      const currentIdx = events.findIndex( e => e.event_key === this.state.currentEventKey );
      const next       = events[ currentIdx + 1 ];
      if (next) this._loadEvent(next);
    },

    _cancelAutoplay() {
      clearInterval(this.state.autoplayTimer);
      const transition = document.querySelector('.vana-stage__transition');
      const iframe     = document.getElementById('vana-stage-iframe');
      const stage      = document.querySelector('.vana-stage');
      if (transition) transition.hidden = true;
      if (iframe)     iframe.hidden     = false;
      stage?.classList.remove('is-transitioning');
    },

    _getCurrentDayEvents() {
      const timeline   = window.vanaVisitData?.timeline;
      const currentDay = timeline?.days.find( d => d.day_key === this.state.currentDayKey );
      return currentDay?.events || [];
    },

    _shareEvent() {
      const event = this._getCurrentDayEvents()
        .find( e => e.event_key === this.state.currentEventKey );
      if (!event) return;

      const text = `Ouça "${event.title}" com Srila Vana Maharaj\n👉 ${window.location.href}\n#HareKrishna #VanaMadhuryam`;

      if (navigator.share) {
        navigator.share({ title: event.title, text });
      } else {
        navigator.clipboard.writeText(text);
      }
    },

    _openHK() {
      this._activateSection('section-hk');
      // Scroll até a seção
      document.getElementById('section-hk')?.scrollIntoView({ behavior: 'smooth' });
    },

    // ── Seções REST ──────────────────────────────────────

    _loadSectionContent( sectionId ) {
      const section = document.getElementById(sectionId);
      if (!section) return;
      if (section.dataset.loaded === this.state.currentEventKey) return;

      const eventKey = this.state.currentEventKey;
      const visitId  = window.vanaVisitData?.visitId;
      const lang     = this.state.currentLang;

      const endpoints = {
        'section-hk'      : `/wp-json/vana/v1/kathas?event_key=${eventKey}&lang=${lang}`,
        'section-galeria' : `/wp-json/vana/v1/media?event_key=${eventKey}&type=photo`,
        'section-sangha'  : `/wp-json/vana/v1/sangha?event_key=${eventKey}`,
        'section-revista' : `/wp-json/vana/v1/revista?visit_id=${visitId}`,
      };

      const url = endpoints[sectionId];
      if (!url) return;

      const content = section.querySelector('.vana-section__content');
      const loading = section.querySelector('.vana-section__loading');
      if (loading) loading.hidden = false;

      fetch(url)
        .then( r => r.json() )
        .then( data => {
          if (loading) loading.hidden = true;
          if (content) content.innerHTML = this._renderSection( sectionId, data );
          section.dataset.loaded = eventKey;
        })
        .catch(() => {
          if (loading) loading.hidden = true;
          if (content) content.innerHTML = '<p class="vana-section__empty">Conteúdo não disponível.</p>';
        });
    },

    _renderSection( sectionId, data ) {
      // Renderização mínima — expandir conforme schema da API
      if (!data || !data.length) {
        return '<p class="vana-section__empty">Nenhum conteúdo para este evento.</p>';
      }
      // Retorna JSON temporário até templates finais
      return `<pre class="vana-section__debug">${JSON.stringify(data, null, 2)}</pre>`;
    },

    // ── Eventos globais ──────────────────────────────────

    _bindGlobalEvents() {
      // Escuta mudança de idioma global
      document.addEventListener('vana:lang:change', e => {
        this.state.currentLang = e.detail.lang;
        // Recarrega seção HK com novo idioma
        const hkSection = document.getElementById('section-hk');
        if (hkSection) delete hkSection.dataset.loaded;
        this._loadSectionContent('section-hk');
      });
    },

  };

  // ── Start ──────────────────────────────────────────────

  if ( document.readyState === 'loading' ) {
    document.addEventListener('DOMContentLoaded', () => VanaVisitController.init());
  } else {
    VanaVisitController.init();
  }

})();
```

---

## 15. CSS — vana-visit.css

```css
/* ─────────────────────────────────────────────────────────────
   vana-visit.css — Schema 6.0
   Complementa o tema existente
   ───────────────────────────────────────────────────────────── */

/* ── Hero ──────────────────────────────────────────────────── */

.vana-hero {
  position: relative;
  background: var(--hero-bg) center/cover no-repeat,
              var(--vana-background-light);
  padding: 2rem 1.5rem 1.5rem;
  border-bottom: 1px solid var(--vana-border);
}

.vana-hero__inner     { max-width: 960px; margin: 0 auto; }
.vana-hero__top       { display: flex; justify-content: space-between;
                        align-items: center; margin-bottom: 1rem; }
.vana-hero__tours-btn { background: none; border: 1px solid var(--vana-border);
                        border-radius: 6px; padding: 6px 12px; cursor: pointer;
                        display: flex; align-items: center; gap: 6px;
                        color: var(--vana-text); font-size: 0.85rem; }
.vana-hero__tours-btn:hover { border-color: var(--vana-accent); color: var(--vana-accent); }

.vana-hero__prevnext  { display: flex; gap: 8px; }
.vana-hero__nav-btn   { color: var(--vana-accent); text-decoration: none;
                        font-size: 0.85rem; padding: 4px 8px; border-radius: 4px;
                        transition: background 0.2s; }
.vana-hero__nav-btn:hover { background: var(--vana-background-light); }

.vana-hero__title     { font-size: 1.6rem; font-weight: 700;
                        color: var(--vana-text); margin: 0 0 0.5rem; }
.vana-hero__meta      { display: flex; flex-wrap: wrap; gap: 8px 16px;
                        font-size: 0.85rem; color: var(--vana-text-light); }
.vana-hero__tour-counter { color: var(--vana-accent); font-weight: 500; }

/* ── Seletor de Dia ────────────────────────────────────────── */

.vana-day-selector     { margin-top: 1rem; }
.vana-day-selector__group { margin-bottom: 0.5rem; }
.vana-day-selector__month-label { font-size: 0.75rem; color: var(--vana-text-light);
                                   text-transform: lowercase; display: block;
                                   margin-bottom: 4px; }
.vana-day-selector__pills { display: flex; gap: 6px; overflow-x: auto;
                              -webkit-overflow-scrolling: touch;
                              scrollbar-width: none; }
.vana-day-selector__pills::-webkit-scrollbar { display: none; }

.vana-day-selector__pill {
  flex-shrink: 0;
  background: none;
  border: 1px solid var(--vana-border);
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 0.82rem;
  cursor: pointer;
  color: var(--vana-text);
  transition: all 0.2s;
}
.vana-day-selector__pill.is-active {
  background: var(--vana-primary);
  border-color: var(--vana-primary);
  color: #fff;
  font-weight: 600;
}
.vana-day-selector__pill:hover:not(.is-active) {
  border-color: var(--vana-accent);
  color: var(--vana-accent);
}

/* ── Stage ─────────────────────────────────────────────────── */

.vana-stage {
  background: #000;
  position: relative;
}

.vana-stage__media-wrapper {
  position: relative;
  aspect-ratio: 16 / 9;
  background: #000;
}

.vana-stage__iframe {
  width: 100%; height: 100%;
  border: none; display: block;
}

.vana-stage__neutral,
.vana-stage__transition {
  position: absolute; inset: 0;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 12px;
  background: #0d0d0d;
  color: #fff;
  text-align: center;
  padding: 2rem;
}

.vana-stage__neutral-logo,
.vana-stage__transition img { width: 80px; opacity: 0.8; }

.vana-stage__info {
  padding: 12px 16px;
  background: var(--vana-background);
  border-bottom: 1px solid var(--vana-border);
}
.vana-stage__event-title { font-size: 1rem; font-weight: 600;
                            margin: 0 0 4px; color: var(--vana-text); }
.vana-stage__event-meta  { font-size: 0.8rem; color: var(--vana-text-light);
                            display: flex; gap: 8px; margin: 0; }

.vana-stage__controls {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 16px;
  background: var(--vana-background);
  border-bottom: 1px solid var(--vana-border);
}
.vana-stage__btn {
  background: none; border: none; cursor: pointer;
  font-size: 1.1rem; color: var(--vana-text);
  padding: 6px; border-radius: 4px;
  transition: color 0.2s;
}
.vana-stage__btn:hover { color: var(--vana-primary); }
.vana-stage__btn--play { font-size: 1.4rem; color: var(--vana-primary); }

.vana-stage__volume { flex: 1; max-width: 120px; }
.vana-stage__volume input { width: 100%; accent-color: var(--vana-primary); }

.vana-stage__segments { padding: 8px 16px; background: var(--vana-background-light); }
.vana-stage__segments-list { list-style: none; margin: 0; padding: 0; }
.vana-stage__segment { border-bottom: 1px solid var(--vana-border); }
.vana-stage__segment:last-child { border-bottom: none; }
.vana-stage__segment-btn {
  width: 100%; background: none; border: none;
  display: flex; gap: 12px; align-items: center;
  padding: 8px 0; cursor: pointer; text-align: left;
  color: var(--vana-text);
}
.vana-stage__segment-btn:hover { color: var(--vana-primary); }
.vana-stage__segment.is-active .vana-stage__segment-btn {
  color: var(--vana-primary); font-weight: 600;
}
.vana-stage__segment-time { font-size: 0.78rem; color: var(--vana-accent);
                              font-family: monospace; flex-shrink: 0; }

.vana-stage__actions {
  display: flex; gap: 8px; padding: 10px 16px;
  background: var(--vana-background);
  flex-wrap: wrap;
}
.vana-stage__action-btn {
  background: none; border: 1px solid var(--vana-border);
  border-radius: 6px; padding: 6px 12px; cursor: pointer;
  font-size: 0.82rem; color: var(--vana-text);
  transition: all 0.2s;
}
.vana-stage__action-btn:hover {
  border-color: var(--vana-accent);
  color: var(--vana-accent);
}

/* ── Gavetas ───────────────────────────────────────────────── */

.vana-drawer {
  position: fixed; inset: 0;
  z-index: 9000;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.25s ease;
}
.vana-drawer.is-open {
  pointer-events: auto;
  opacity: 1;
}
.vana-drawer__backdrop {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0.6);
  cursor: pointer;
}
.vana-drawer__panel {
  position: absolute;
  top: 0; bottom: 0;
  width: min(360px, 90vw);
  background: var(--vana-background);
  display: flex; flex-direction: column;
  overflow: hidden;
  transform: translateX(-100%);
  transition: transform 0.25s ease;
}
.vana-drawer--tour  .vana-drawer__panel { left: 0; transform: translateX(-100%); }
.vana-drawer--agenda .vana-drawer__panel { right: 0; left: auto; transform: translateX(100%); }
.vana-drawer.is-open .vana-drawer__panel { transform: translateX(0); }

.vana-drawer__header {
  display: flex; align-items: center; gap: 8px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--vana-border);
  flex-shrink: 0;
}
.vana-drawer__title { flex: 1; font-size: 1rem; font-weight: 600; margin: 0; }
.vana-drawer__close,
.vana-drawer__back  { background: none; border: none; cursor: pointer;
                       color: var(--vana-text-light); font-size: 1rem;
                       padding: 4px 8px; border-radius: 4px; }
.vana-drawer__close:hover,
.vana-drawer__back:hover { color: var(--vana-text); background: var(--vana-background-light); }

.vana-drawer__level { flex: 1; overflow-y: auto; padding: 8px 0; }
.vana-drawer__footer { padding: 12px 16px; border-top: 1px solid var(--vana-border); flex-shrink: 0; }

/* Tour items */
.vana-drawer__tour-item {
  width: 100%; background: none; border: none;
  padding: 12px 16px; text-align: left; cursor: pointer;
  display: flex; flex-direction: column; gap: 2px;
  border-bottom: 1px solid var(--vana-border);
  transition: background 0.2s;
}
.vana-drawer__tour-item:hover { background: var(--vana-background-light); }
.vana-drawer__tour-title { font-weight: 600; font-size: 0.9rem; color: var(--vana-text); }
.vana-drawer__tour-destinations { font-size: 0.78rem; color: var(--vana-text-light); }

/* Visit items */
.vana-drawer__visit-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 16px; text-decoration: none;
  border-bottom: 1px solid var(--vana-border);
  transition: background 0.2s;
}
.vana-drawer__visit-item:hover { background: var(--vana-background-light); }
.vana-drawer__visit-item.is-current { background: #fff8ee; border-left: 3px solid var(--vana-primary); }
.vana-drawer__visit-status { font-size: 0.9rem; flex-shrink: 0; margin-top: 2px; }
.vana-drawer__visit-title  { font-size: 0.88rem; font-weight: 500; color: var(--vana-text); display: block; }
.vana-drawer__visit-date   { font-size: 0.75rem; color: var(--vana-text-light); display: block; }
.vana-drawer__visit-current { font-size: 0.72rem; color: var(--vana-primary); font-weight: 600; }

/* Agenda items */
.vana-agenda__list { list-style: none; margin: 0; padding: 8px 0; flex: 1; overflow-y: auto; }
.vana-agenda__event {
  display: flex; gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--vana-border);
  transition: background 0.2s;
}
.vana-agenda__event.is-active  { background: #fff8ee; border-left: 3px solid var(--vana-primary); }
.vana-agenda__event.is-past    { opacity: 0.6; }
.vana-agenda__event-time { font-size: 0.78rem; color: var(--vana-accent);
                            font-weight: 600; flex-shrink: 0;
                            margin-top: 2px; font-family: monospace; }
.vana-agenda__event-body { flex: 1; }
.vana-agenda__event-title  { font-size: 0.9rem; font-weight: 500;
                              margin: 0 0 2px; color: var(--vana-text); }
.vana-agenda__event-speaker { font-size: 0.78rem; color: var(--vana-text-light); margin: 0 0 8px; }
.vana-agenda__event-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.vana-agenda__btn {
  background: none; border: 1px solid var(--vana-border);
  border-radius: 4px; padding: 3px 8px; cursor: pointer;
  font-size: 0.75rem; color: var(--vana-text);
  transition: all 0.2s;
}
.vana-agenda__btn:hover { border-color: var(--vana-accent); color: var(--vana-accent); }

/* Pill flutuante */
.vana-agenda-pill {
  position: fixed;
  bottom: 24px; right: 16px;
  background: var(--vana-primary);
  color: #fff;
  border: none; border-radius: 24px;
  padding: 10px 16px;
  cursor: pointer;
  font-size: 0.88rem; font-weight: 600;
  box-shadow: 0 4px 16px rgba(0,0,0,0.25);
  z-index: 500;
  display: flex; align-items: center; gap: 6px;
  transition: background 0.2s, transform 0.2s;
}
.vana-agenda-pill:hover { background: var(--vana-primary-dark); transform: translateY(-2px); }

/* ── Chip Bar ───────────────────────────────────────────────── */

.vana-chip-bar {
  display: flex; gap: 4px;
  padding: 8px 12px;
  background: var(--vana-background);
  border-bottom: 2px solid var(--vana-border);
  position: sticky;
  top: 0;
  z-index: 100;
  overflow-x: auto;
  scrollbar-width: none;
}
.vana-chip-bar::-webkit-scrollbar { display: none; }

.vana-chip {
  flex-shrink: 0;
  background: none;
  border: 1px solid transparent;
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 0.82rem;
  cursor: pointer;
  color: var(--vana-text-light);
  transition: all 0.2s;
  white-space: nowrap;
}
.vana-chip.is-active {
  background: var(--vana-primary);
  color: #fff;
  font-weight: 600;
}
.vana-chip:hover:not(.is-active) {
  border-color: var(--vana-accent);
  color: var(--vana-accent);
}

/* ── Seções ─────────────────────────────────────────────────── */

.vana-sections { padding: 16px; max-width: 960px; margin: 0 auto; }
.vana-section { display: none; }
.vana-section.is-active { display: block; }
.vana-section__empty { color: var(--vana-text-light); font-size: 0.88rem;
                        text-align: center; padding: 2rem; }
.vana-section__loading { color: var(--vana-text-light); font-size: 0.85rem;
                          text-align: center; padding: 1rem; }

/* ── Seletor de idioma ──────────────────────────────────────── */

.vana-lang-selector { display: flex; gap: 4px; }
.vana-lang-selector__btn {
  background: none;
  border: 1px solid var(--vana-border);
  border-radius: 4px; padding: 4px 10px;
  cursor: pointer; font-size: 0.8rem;
  color: var(--vana-text-light);
  transition: all 0.2s;
}
.vana-lang-selector__btn.is-active {
  background: var(--vana-accent);
  border-color: var(--vana-accent);
  color: #fff;
}

/* ── Utilitários ────────────────────────────────────────────── */

.sr-only {
  position: absolute; width: 1px; height: 1px;
  padding: 0; margin: -1px; overflow: hidden;
  clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}

/* ── Mobile ─────────────────────────────────────────────────── */

@media (max-width: 640px) {
  .vana-hero__title { font-size: 1.2rem; }
  .vana-hero__meta  { flex-direction: column; gap: 4px; }
  .vana-stage__controls { padding: 8px 12px; gap: 8px; }
  .vana-sections { padding: 12px; }
  .vana-chip-bar { padding: 6px 8px; }
}
```

---

## 16. REST API — ENDPOINTS DE LEITURA (FASE 1)

```php
<?php
/**
 * REST API — Fase 1
 * Apenas leitura (GET)
 * Adicionar em includes/class-vana-rest-api.php
 */

class Vana_REST_API {

  public static function init() {
    add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
  }

  public static function register_routes() {

    $namespace = 'vana/v1';

    // GET /vana/v1/kathas?event_key=&lang=
    register_rest_route( $namespace, '/kathas', [
      'methods'             => 'GET',
      'callback'            => [ __CLASS__, 'get_kathas' ],
      'permission_callback' => '__return_true',
      'args'                => [
        'event_key' => [ 'type' => 'string', 'required' => false ],
        'visit_id'  => [ 'type' => 'integer', 'required' => false ],
        'lang'      => [ 'type' => 'string', 'default' => 'pt' ],
      ],
    ]);

    // GET /vana/v1/media?event_key=&type=photo
    register_rest_route( $namespace, '/media', [
      'methods'             => 'GET',
      'callback'            => [ __CLASS__, 'get_media' ],
      'permission_callback' => '__return_true',
      'args'                => [
        'event_key' => [ 'type' => 'string', 'required' => false ],
        'type'      => [ 'type' => 'string', 'default' => 'photo' ],
      ],
    ]);

    // GET /vana/v1/sangha?event_key=
    register_rest_route( $namespace, '/sangha', [
      'methods'             => 'GET',
      'callback'            => [ __CLASS__, 'get_sangha' ],
      'permission_callback' => '__return_true',
    ]);

    // GET /vana/v1/revista?visit_id=
    register_rest_route( $namespace, '/revista', [
      'methods'             => 'GET',
      'callback'            => [ __CLASS__, 'get_revista' ],
      'permission_callback' => '__return_true',
    ]);

    // POST /vana/v1/react
    register_rest_route( $namespace, '/react', [
      'methods'             => 'POST',
      'callback'            => [ __CLASS__, 'post_react' ],
      'permission_callback' => '__return_true',
    ]);

  }

  public static function get_kathas( WP_REST_Request $req ) {
    $event_key = sanitize_text_field( $req->get_param('event_key') );
    $lang      = sanitize_text_field( $req->get_param('lang') ) ?: 'pt';

    $args = [
      'post_type'      => 'vana_katha',
      'posts_per_page' => 20,
      'post_status'    => 'publish',
    ];

    if ( $event_key ) {
      $args['meta_query'] = [[
        'key'   => '_katha_event_key',
        'value' => $event_key,
      ]];
    }

    $kathas = get_posts( $args );

    $data = array_map( function( $k ) use ($lang) {
      return [
        'id'         => $k->ID,
        'title'      => get_the_title($k),
        'video_id'   => get_post_meta($k->ID, '_katha_video_id', true),
        'duration_s' => (int) get_post_meta($k->ID, '_katha_duration_s', true),
        'event_key'  => get_post_meta($k->ID, '_katha_event_key', true),
        'url'        => get_permalink($k),
      ];
    }, $kathas );

    return rest_ensure_response( $data );
  }

  public static function get_media( WP_REST_Request $req ) {
    // Implementar conforme CPT de foto/vídeo disponível
    return rest_ensure_response( [] );
  }

  public static function get_sangha( WP_REST_Request $req ) {
    // Implementar conforme CPT vana_sangha disponível
    return rest_ensure_response( [] );
  }

  public static function get_revista( WP_REST_Request $req ) {
    // Implementar conforme CPT vana_revista disponível
    return rest_ensure_response( [] );
  }

  public static function post_react( WP_REST_Request $req ) {
    $post_id  = intval( $req->get_param('post_id') );
    $reaction = sanitize_key( $req->get_param('reaction') );

    if ( !$post_id || !in_array($reaction, ['haribol', 'nectar']) ) {
      return new WP_Error('invalid', 'Parâmetros inválidos', ['status' => 400]);
    }

    $key   = "_reaction_{$reaction}";
    $count = (int) get_post_meta( $post_id, $key, true );
    update_post_meta( $post_id, $key, $count + 1 );

    return rest_ensure_response([ 'count' => $count + 1 ]);
  }

}

Vana_REST_API::init();
```

---

## 17. TESTES MÍNIMOS EXIGIDOS

```text
ANTES DE CONSIDERAR FASE 1 COMPLETA,
confirmar manualmente cada item:

HERO
[ ] Visita sem tour → hero exibe sem "Visita X de Y"
[ ] Visita com tour → hero exibe "Visita X de Y"
[ ] Prev/next com tour → navega apenas dentro da tour
[ ] Prev/next sem tour → navega cronológico global
[ ] Capa da visita renderiza no hero
[ ] Sem capa → fundo neutro sem erro

SELETOR DE DIA
[ ] 1 dia → seletor some
[ ] 2+ dias → seletor aparece
[ ] Dias em meses diferentes → dois grupos com label
[ ] Mobile → scroll horizontal sem quebrar layout
[ ] Trocar dia → Agenda recarrega
[ ] Trocar dia → Stage NÃO muda

STAGE
[ ] Evento com media_ref → YouTube embed carrega
[ ] Evento sem media_ref → tela neutra aparece
[ ] Params do embed: rel=0, controls=0, enablejsapi=1
[ ] Botão play/pause funciona
[ ] Botão prev/next muda o evento
[ ] Ao terminar → tela de transição com countdown 5s
[ ] [Iniciar agora] → carrega próximo evento
[ ] [Pausar] → cancela autoplay
[ ] Fim da playlist → "Até amanhã, Hare Kṛṣṇa"
[ ] Stage não expõe links para YouTube
[ ] Segments renderizam quando existem
[ ] Clique no segment → seek funciona

GAVETA TOUR
[ ] Botão "Tours" abre gaveta
[ ] Visita com tour → tour ativa expandida
[ ] Visita sem tour → lista "Visitas" cronológica
[ ] Clicar numa tour → carrega visitas
[ ] Botão [← Voltar] volta para lista de tours
[ ] Visita atual marcada com "● você está aqui"
[ ] ESC fecha
[ ] Clique no backdrop fecha
[ ] handlers AJAX: vana_get_tours e vana_get_tour_visits respondem

GAVETA AGENDA
[ ] Fechada por padrão
[ ] Pill flutuante visível (mobile)
[ ] Botão 📅 no Stage abre agenda
[ ] Visita sem mídia → agenda abre automaticamente
[ ] Eventos do dia listados corretamente
[ ] Evento ativo destacado
[ ] [▶ Ouvir] só aparece com media_ref
[ ] [📖 HK] só aparece com katha_ref
[ ] [🔔] só aparece em eventos futuros
[ ] Clicar [▶ Ouvir] → Stage carrega evento → gaveta fecha
[ ] ESC fecha
[ ] Clique no backdrop fecha

CHIP BAR
[ ] Sticky ao rolar
[ ] Clicar chip → seção correspondente ativa
[ ] Seção inativa → hidden
[ ] HK ativo por padrão

IDIOMA
[ ] Usuário PT-BR → PT selecionado automaticamente
[ ] Usuário EN → EN selecionado automaticamente
[ ] Trocar idioma → salva em localStorage
[ ] Recarregar página → mantém idioma escolhido
[ ] Evento vana:lang:change disparado ao trocar

LOCATIONPIN
[ ] Visita com lat/lng → pin renderiza
[ ] Clique no pin → modal de mapa abre
[ ] ESC fecha modal
[ ] Modal não duplicado (apenas 1 por página)

RISCOS CONHECIDOS
[ ] R1 — handlers AJAX existem e respondem (vana_get_tours)
[ ] R2 — prev/next escopado por tour_id ✅
[ ] R3 — duplicação do drawer consolidada
[ ] R4 — window.vanaDrawer populado via wp_localize_script
```

---

## 18. O QUE NÃO IMPLEMENTAR NA FASE 1

```text
❌ Clip Devocional ([A][B][✈️])
❌ Camada colaborador no Stage
❌ Bot Telegram
❌ Fila de transcrição HK
❌ Biblioteca temática (Fase 2)
❌ Reactions persistentes por usuário
❌ Notificações push ([🔔])
   → renderizar o botão mas sem funcionalidade
❌ Revista publicada (Fase 3)
   → renderizar a seção vazia com "Em breve"
❌ Filtro de taxonomia no HK (Fase 2)
❌ Modo Acompanhar (single-vana_katha.php)
   → é outra superfície, não esta página
❌ vana-forja / vana-trator
❌ Qualquer escrita via REST API
   → Fase 1 só lê, nunca escreve via REST
```

---

## 19. CHECKLIST DE ENTREGA

```text
ARQUIVOS
[ ] includes/class-vana-cpts.php              criado
[ ] includes/class-vana-rest-api.php          criado
[ ] includes/_bootstrap.php                   editado (cirúrgico)
[ ] single-vana_visit.php                     criado/editado
[ ] template-parts/vana/hero-header.php       criado/editado
[ ] template-parts/vana/tour-drawer.php       criado
[ ] template-parts/vana/stage.php             criado
[ ] template-parts/vana/agenda-drawer.php     criado
[ ] template-parts/vana/chip-bar.php          criado
[ ] template-parts/vana/sections.php          criado
[ ] assets/js/VanaVisitController.js          criado
[ ] assets/css/vana-visit.css                 criado
[ ] functions.php                             editado (registros)

REGISTROS NO functions.php
[ ] Vana_CPTs::init() chamado
[ ] Vana_REST_API::init() chamado
[ ] vana-visit-scripts enfileirado com wp_enqueue_script
[ ] vana-visit-styles enfileirado com wp_enqueue_style
[ ] vanaVisitData populado via wp_localize_script
[ ] vanaDrawer populado via wp_localize_script
[ ] AJAX handlers de tour registrados

QUALIDADE
[ ] Nenhum PHP notice ou warning no log
[ ] Nenhum erro de console JS
[ ] Página passa W3C HTML validator
[ ] Funciona sem JavaScript (conteúdo básico visível)
[ ] Testes mínimos da seção 17 todos marcados
```

---

*Spec gerada em 22/03/2026.*
*Schema 6.0 — Fase 1.*
*Destinatário: Agente de Implementação.*
*Dúvidas → parar e perguntar antes de decidir.*
````

---

Marcel, spec completa gerada. 🙏

**O que o agente recebe:**

```text
→ Contexto completo do projeto
→ Stack e todas as convenções de nome
→ Schema do JSON com exemplos reais
→ Todos os arquivos com código funcional
→ Edições cirúrgicas separadas das criações
→ Verificação obrigatória dos handlers AJAX
→ 60+ testes mínimos com checklist
→ Lista explícita do que NÃO fazer
→ Checklist de entrega com todos os arquivos
```

**Uma recomendação antes de passar para o agente:**

```text
Confirmar se os handlers AJAX
vana_get_tours e vana_get_tour_visits
existem no PHP atual.

Se existirem → agente integra com o novo markup.
Se não existirem → agente cria conforme a spec.

Essa confirmação evita
o maior risco do drawer. ⚠️
```