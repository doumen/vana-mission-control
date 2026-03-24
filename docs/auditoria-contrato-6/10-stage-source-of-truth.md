# docs/auditoria-contrato-6/10-stage-source-of-truth.md

## 1. Resumo executivo curto

- Fonte de verdade SSR do Stage: `templates/visit/parts/stage.php` (template SSR incluido pelo visit template e também usado pelos endpoints REST/fragment via include).
- Fonte de verdade do Stage dinâmico/REST: handlers em `includes/rest/class-vana-rest-stage.php` e `includes/rest/class-vana-rest-stage-fragment.php` que re-renderizam `stage.php` ou `stage-fragment.php` a partir do `_vana_visit_timeline_json` ou `_vana_visit_data`.
- ViewModel / Resolver: `includes/class-visit-stage-resolver.php` produz `VisitStageViewModel` (contrato SSR-first) em `includes/class-visit-stage-view-model.php`.
- Situação: existe um contrato declarado (ViewModel → to_template_vars()) mas há divergências de nomes de meta keys e payloads entre SSR resolver e REST handlers (ex.: `_vana_tz` vs `_vana_visit_timezone`, `_vana_visit_timeline_json` vs `_vana_visit_data`).

Classificação recomendada do estado atual: **PARCIAL** — Stage funciona, mas o contrato não é 100% unificado entre SSR resolver e REST fragment handlers.

---

## 2. Fonte de verdade atual (respostas diretas)

1) Qual arquivo é a fonte de verdade do Stage SSR?
   - `templates/visit/parts/stage.php` (included pelo `visit-template.php` e pelo resolver/renderers).

2) Qual arquivo é a fonte de verdade do Stage dinâmico/REST?
   - `includes/rest/class-vana-rest-stage.php` para rota semântica `/vana/v1/stage/{event_key}` e
     `includes/rest/class-vana-rest-stage-fragment.php` para `/vana/v1/stage-fragment`. Ambos re-renderizam `stage.php` ou `stage-fragment.php` após normalizar dados.

3) JS dinâmico que faz swap/update do stage:
   - A maior parte da troca dinâmica é feita via re-render server-side pelos endpoints acima; o único controller cliente relacionado é `visit-scripts.php` (inline) que contém handlers para segmento/seek e outros comportamentos, e `VanaVisitController.js` não altera o Stage content — apenas prev/next navigation.

---

## 3. Mapa de responsabilidades por arquivo (evidências)

- `templates/visit/parts/stage.php`
  - SSR template principal. Exige (do _bootstrap ou extract): `$lang, $visit_id, $visit_tz, $visit_city_ref, $active_day, $active_day_date, $active_event, $visit_status` (header requisição). Trecho:
    ```php
    /* Requer (do _bootstrap.php):
       $lang, $visit_id, $visit_tz, $visit_city_ref
       $active_day, $active_day_date, $active_event, $visit_status */
    ```

- `includes/class-visit-stage-resolver.php`
  - Resolve `VisitStageViewModel` SSR-first a partir de meta `_vana_visit_timeline_json`, overrides e query string. Retorna ViewModel com `active_event`, `active_day`, `visit_timezone` e `timeline`.
  - Evidência: reads `_vana_visit_timeline_json` and `_vana_tz` and returns `visit_timezone` key in view model.

- `includes/class-visit-stage-view-model.php`
  - Contrato de saída: `to_template_vars()` lista variáveis expostas ao template (e.g., `$active_event`, `$active_day`, `$visit_timezone`).

- `includes/rest/class-vana-rest-stage.php`
  - Endpoint `/vana/v1/stage/{event_key}`: carrega `_vana_visit_timeline_json`, encontra evento, normaliza (`vana_normalize_event`) e invoca `vana_get_stage_content()`; monta vars e chama `render_stage()` que inclui `stage.php`.
  - Evidência: `render_stage(compact(...))` and `extract($vars)` before include.

- `includes/rest/class-vana-rest-stage-fragment.php`
  - Endpoint `/vana/v1/stage-fragment`: supports item types (vod/gallery/sangha/event/restore), may include `stage-fragment.php` which builds its own `$stage_item` from post meta or `_media_items`.
  - Observação: fragment path is a separate template (`stage-fragment.php`) with its own normalization and `vana_get_stage_content()` call.

- `templates/visit/parts/stage-fragment.php`
  - Fragment template intended for HTMX/REST; resolves item by `item_id` and `item_type`, normalizes, and renders a smaller variant of stage.

- `templates/visit/assets/visit-scripts.php` + `assets/js/VanaVisitController.js`
  - `visit-scripts.php` contains Stage helper code (segments seek, lightbox, map lazy) and defines `window.vanaDrawer` etc.; `VanaVisitController.js` does navigation/prefetch only.

---

## 4. Divergências concretas encontradas (evidência objetiva)

1) Meta key timezone divergência:
   - Resolver reads `'_vana_tz'`:
     ```php
     $visit_timezone = self::read_string_meta( $visit_id, '_vana_tz', 'UTC' );
     ```
   - REST Stage reads `'_vana_visit_timezone'`:
     ```php
     $visit_tz = (string) (get_post_meta($visit_id, '_vana_visit_timezone', true) ?: 'UTC');
     ```
   - Consequência: SSR resolver and REST may use different meta keys; if site instances populate one but not the other, timezone used in stage mode calculation may differ.

2) Timeline payload naming:
   - Resolver uses `_vana_visit_timeline_json` (read_json_meta) and returns `'timeline' => $timeline` in view model.
   - Some REST code (render_restore in fragment) reads `_vana_visit_data` as fallback (legacy):
     ```php
     $visit_meta = get_post_meta($visit_id, '_vana_visit_data', true);
     $visit_data = is_array($visit_meta) ? $visit_meta : [];
     ```
   - Consequence: two canonical meta structures in play (`_vana_visit_timeline_json` vs `_vana_visit_data`) → possible data shape mismatches.

3) Fragment vs SSR normalization:
   - `stage-fragment.php` builds `$stage_item` from different meta sources (`_vana_katha_data`, `_media_items`, post fields), then calls `vana_stage_resolve_media()` and includes fragment markup. This is a parallel normalization path separate from VisitStageResolver → VisitStageViewModel → vana_get_stage_content.
   - Evidence: fragment uses its own fallbacks and field names; SSR expects `active_event` array shape.

4) REST `Vana_REST_Stage` sometimes calls `vana_get_stage_content($event)` with event normalized by `vana_normalize_event()`; the resolver pipeline may normalize differently (VisitEventResolver). Small differences in normalization rules risk inconsistent `$stage['type']` and `$stage['data']` shapes.

---

## 5. Riscos de manutenção e regressão

1. Divergências de meta keys (`_vana_tz` vs `_vana_visit_timezone`) causam comportamento diferente entre SSR and REST responses (dates/time comparisons, stage_mode).
2. Multiple payload shapes (`_vana_visit_timeline_json` vs `_vana_visit_data`) risk event lookup failures or different fallbacks across endpoints.
3. Parallel normalization paths (resolver vs fragment) increase chance of inconsistent `stage['type']` (vod/gallery/sangha) leading to different render branches and UX flakes.
4. Lack of a single documented contract means future changes may fix one path and forget the other, producing subtle regressions.

---

## 6. Escolha recomendada entre estratégias

- A) Alinhar contrato e manter estrutura atual — recomendado como passo inicial (baixo risco):
  - Rationale: a pequena unificação de meta keys and a canonical serialization/reader for timeline/timezone reconciles SSR and REST quickly without moving rendering code.
  - Etapas: document canonical meta keys (`_vana_visit_timeline_json`, `_vana_visit_timezone`), add adapter readers in REST and Resolver to read both keys, and centralize `vana_normalize_event()` usage.

- B) Refatorar para fonte única mais forte (longer-term):
  - Move: have VisitStageResolver be the single source for REST as well (REST endpoints call Resolver and `to_json_response()`), deprecate fragment-specific normalization code.
  - Pros: single path, easier testing. Cons: larger change and requires regression tests.

Recommendation: start with **A (align contract)** immediately; plan **B** as a follow-up with tests.

---

## 7. Patch mínimo recomendado (não aplicado)

1. Canonicalize timezone meta read:
   - Change readers to accept both keys: `get_post_meta($id, '_vana_visit_timezone', true) ?: get_post_meta($id, '_vana_tz', true) ?: 'UTC'` and document `_vana_visit_timezone` as canonical.

2. Canonicalize timeline meta read:
   - Ensure both REST and Resolver read `_vana_visit_timeline_json` first, then fallback to `_vana_visit_data` for legacy imports.

3. Add small adapter function `vana_visit_stage_bootstrap($visit_id): array` that returns the minimal vars required by `stage.php` (`lang, visit_id, visit_tz, visit_city_ref, active_day, active_day_date, active_vod/active_event, vod_list, visit_status`) and use it in REST and Resolver render paths.

4. Update `stage-fragment.php` to prefer the Resolver/normalized event when `item_type === 'event'` to avoid double normalization; keep its other item-type behavior for vod/gallery/sangha.

Impact: these changes are minimal, low-risk, and keep `stage.php` untouched; they harmonize inputs to the template.

---

## 8. Classificação final do núcleo arquitetural do Stage

- Estado atual: **PARCIAL** — funcional but with measurable contract mismatches.
- To reach **RESOLVED**: apply the canonicalization patch above (7.1–7.3) and make REST call Resolver (or use adapter) so SSR and REST share the same canonical input.

---

## 9. Evidência (trechos relevantes)

- Resolver reads `_vana_visit_timeline_json` and `_vana_tz`:
  ```php
  $timeline       = self::read_json_meta( $visit_id, '_vana_visit_timeline_json' );
  $visit_timezone = self::read_string_meta( $visit_id, '_vana_tz', 'UTC' );
  ```

- REST Stage reads timeline and `_vana_visit_timezone`:
  ```php
  $raw = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
  $timeline = $raw ? json_decode((string) $raw, true) : null;
  $visit_tz = (string) (get_post_meta($visit_id, '_vana_visit_timezone', true) ?: 'UTC');
  ```

- Fragment render_restore reads `_vana_visit_data` fallback:
  ```php
  $visit_meta = get_post_meta($visit_id, '_vana_visit_data', true);
  $visit_data = is_array($visit_meta) ? $visit_meta : [];
  ```

- `stage-fragment.php` builds `$stage_item` from `_vana_katha_data`, `_media_items` or post fields and then calls `vana_get_stage_content($stage_item)` — a normalization path separate from VisitStageResolver.

---

Auditado por: equipe técnica / automação (leitura em código, 23/03/2026)
