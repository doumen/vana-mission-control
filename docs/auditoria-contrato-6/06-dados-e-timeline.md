```markdown
# docs/auditoria-contrato-6/06-dados-e-timeline.md

## 1. Objetivo

Auditar **apenas a ESTRUTURA DE DADOS da página de visita** do plugin `vana-mission-control`, comparando o código atual com o **Contrato 6.0**, sem aplicar patch e sem alterar código.

Escopo validado:

- `_vana_tour_id`
- `_vana_start_date`
- `_vana_location`
- `_visit_timeline_json`
- estrutura `Visit -> Day -> Event`
- uso de `days`
- uso de `events`
- presença de `day_key`, `label`, `event_key`, `title`, `time`, `status`, `media_ref`, `segments`, `katha_refs`, `photo_refs`, `sangha_refs`
- compatibilidade com visit sem tour
- coerência entre CPT/meta e renderização

---

## 2. Arquivos inspecionados

Foram inspecionados somente os arquivos solicitados:

1. `includes/class-vana-visit-cpt.php`
2. `includes/class-vana-tour-cpt.php`
3. `includes/class-visit-event-resolver.php`
4. `includes/class-visit-stage-resolver.php`
5. `templates/visit/_bootstrap.php`
6. `templates/visit/visit-template.php`

---

## 3. Estrutura esperada pelo contrato

Com base no escopo fornecido pelo usuário, o contrato esperado para a página de visita inclui, no mínimo:

### 3.1 Metas esperadas
- `_vana_tour_id`
- `_vana_start_date`
- `_vana_location`
- `_visit_timeline_json`

### 3.2 Estrutura hierárquica esperada
- `Visit`
  - `Day`
    - `Event`

### 3.3 Chaves/coleções esperadas
- `days`
- `events`

### 3.4 Campos esperados no nível de Day/Event
- `day_key`
- `label`
- `event_key`
- `title`
- `time`
- `status`
- `media_ref`
- `segments`
- `katha_refs`
- `photo_refs`
- `sangha_refs`

### 3.5 Requisitos transversais
- compatibilidade com visit sem tour
- coerência entre CPT/meta persistida e renderização SSR

---

## 4. Estrutura encontrada no código

### 4.1 Meta registrada no CPT `vana_visit`

Em `includes/class-vana-visit-cpt.php`, a função `register_meta()` registra explicitamente:

```php
$small_string_meta = [
    '_vana_origin_key',
    '_vana_parent_tour_origin_key',
    '_vana_timeline_schema_version',
    '_vana_timeline_updated_at',
    '_vana_timeline_hash',
    '_vana_start_date',
    '_vana_tz',
    '_vana_youtube_id',
    '_vana_hero_fb_url',
];
```

Também registra:

```php
register_post_meta('vana_visit', '_vana_visit_timeline_json', [
    'type'              => 'string',
    'single'            => true,
    'show_in_rest'      => true,
    'auth_callback'     => $auth_cb,
    'sanitize_callback' => null,
]);
```

E ainda:

```php
register_post_meta('vana_visit', '_vana_gallery_ids', [
```

```php
register_post_meta('vana_visit', '_vana_gallery_type', [
```

### 4.2 Meta **não** registrada no CPT `vana_visit`
Nos arquivos inspecionados, **não foi encontrada** a chave registrada:
- `_vana_tour_id`
- `_vana_location`
- `_visit_timeline_json`

### 4.3 Tour é usado na renderização, mas não registrado no CPT `vana_visit`
Apesar de não aparecer em `register_meta()`, `_vana_tour_id` é lido em `_bootstrap.php`:

```php
$tour_id = (int) wp_get_post_parent_id( $visit_id );
if ( ! $tour_id ) {
    $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
}
```

### 4.4 Timeline usada no resolver
Em `includes/class-visit-stage-resolver.php`:
```php
$timeline = self::read_json_meta( $visit_id, '_vana_visit_timeline_json' );
```

Ou seja, a meta efetivamente usada pelo resolver é:
- `_vana_visit_timeline_json`

e **não** `_visit_timeline_json`.

### 4.5 Estrutura esperada no SSR
Em `templates/visit/_bootstrap.php`:
```php
$data = $timeline;
$days = is_array( $data['days'] ?? null ) ? $data['days'] : [];
```

A renderização depende explicitamente de:
- `$timeline`
- `$data['days']`

Em `templates/visit/visit-template.php`:
```php
if ( count( (array) $timeline['days'] ) > 1 ) {
```

### 4.6 Estrutura Day -> Event no resolver
Em `includes/class-visit-event-resolver.php`, a função `dayEvents()` aceita:
```php
if (!empty($day['events']) && is_array($day['events'])) {
    return $day['events'];
}

if (!empty($day['active_events']) && is_array($day['active_events'])) {
    return $day['active_events'];
}
```

Ou seja, no nível de Day, o código trabalha com:
- `events`
- fallback para `active_events`

### 4.7 Campos efetivamente usados no nível Day
Em `VisitEventResolver::resolve()` são usados:
- `visit_ref`
- `days`
- `date_local`
- `events` / `active_events`

Trechos:
```php
$visit_ref = $timeline['visit_ref'] ?? '';
```

```php
if (($day['date_local'] ?? '') === $requested_day) {
```

```php
$active_day = $active_day ?: ($timeline['days'][0] ?? []);
```

```php
'active_day_date' => $active_day['date_local'] ?? '',
```

### 4.8 Campos efetivamente usados no nível Event
Ainda em `VisitEventResolver`:
- `event_key`
- `status`
- `media.vods`

Trechos:
```php
if (($event['event_key'] ?? '') === $requested_event_key) {
```

```php
if (($event['status'] ?? '') === 'live') {
```

```php
if (!empty($event['media']['vods'] ?? [])) {
```

Em `VisitStageResolver::resolve_stage_mode()`:
- `kind`
- `status`
- `type`

Trechos:
```php
$kind   = (string) ( $hero['kind']   ?? '' );
$status = (string) ( $hero['status'] ?? '' );
```

### 4.9 Compatibilidade com visita sem tour
Em `_bootstrap.php`:
```php
$tour_id = (int) wp_get_post_parent_id( $visit_id );
if ( ! $tour_id ) {
    $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
}
$tour_url   = $tour_id ? (string) get_permalink( $tour_id )  : '';
$tour_title = $tour_id ? (string) get_the_title( $tour_id )  : '';
```

Isso indica compatibilidade com:
- visit com parent tour
- visit com `_vana_tour_id`
- visit sem tour

### 4.10 Coerência SSR
Em `_bootstrap.php`, o SSR inteiro nasce de:
```php
$vana_vm = VisitStageResolver::resolve( $visit_id );
extract( $vana_vm->to_template_vars() );
```

E o template principal `visit-template.php` exige:
```php
if ( ! isset( $visit_id, $timeline, $active_day, $active_events ) ) {
    return;
}
```

Ou seja, há coerência entre:
- CPT/meta -> resolver -> bootstrap -> template

mas apenas para os campos efetivamente usados.

---

## 5. Divergências

### 5.1 Divergência de nome da timeline meta
**Contrato esperado:** `_visit_timeline_json`  
**Código encontrado:** `_vana_visit_timeline_json`

Evidência:

`class-vana-visit-cpt.php`
```php
register_post_meta('vana_visit', '_vana_visit_timeline_json', [
```

`class-visit-stage-resolver.php`
```php
$timeline = self::read_json_meta( $visit_id, '_vana_visit_timeline_json' );
```

### 5.2 `_vana_tour_id` é usado, mas não aparece registrado no CPT
**Contrato esperado:** `_vana_tour_id`  
**Código encontrado:** usado em runtime, mas não registrado em `register_meta()` do `vana_visit`

Evidência:

`_bootstrap.php`
```php
$tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
```

`class-vana-visit-cpt.php`
```php
$small_string_meta = [
    '_vana_origin_key',
    '_vana_parent_tour_origin_key',
    '_vana_timeline_schema_version',
    '_vana_timeline_updated_at',
    '_vana_timeline_hash',
    '_vana_start_date',
    '_vana_tz',
    '_vana_youtube_id',
    '_vana_hero_fb_url',
];
```

### 5.3 `_vana_location` não foi encontrado
Nos arquivos inspecionados, não há registro nem leitura de:
- `_vana_location`

O código usa `location_meta` **dentro do timeline JSON**, não meta isolada.

Evidência em `_bootstrap.php`:
```php
$location_meta  = is_array( $data['location_meta'] ?? null ) ? $data['location_meta'] : [];
$visit_city_ref = (string) ( $location_meta['city_ref'] ?? '' );
$visit_tz_str   = (string) ( $location_meta['tz'] ?? $visit_timezone ?? 'UTC' );
```

### 5.4 `day_key` não foi encontrado
Nos arquivos lidos, o nível Day usa:
- `date_local`
- eventualmente `date`

Não foi encontrada a chave:
- `day_key`

### 5.5 `label` existe apenas em forma localizada, não como campo canônico único
O código usa:
- `label_pt`
- `label_en`

E não um campo canônico `label`.

### 5.6 `title` idem: uso localizado, não canônico
No geral, o ecossistema usa:
- `title_pt`
- `title_en`

Não foi demonstrada, nesses arquivos, a chave única `title` no contrato da timeline.

### 5.7 `time` não aparece neste recorte de resolver/bootstrap/template
Nos arquivos auditados aqui, não há uso explícito de:
- `time`
como campo estrutural do resolver de visita.  
O campo aparece em outras áreas do sistema, mas **não foi evidenciado neste recorte**.

### 5.8 `media_ref` não foi encontrado
O código trabalha com:
- `media`
- `media.vods`

Não foi encontrada a chave contratual:
- `media_ref`

### 5.9 `segments`, `katha_refs`, `photo_refs`, `sangha_refs` não aparecem como contrato de timeline neste recorte
Nos arquivos lidos:
- `segments` não é usado no resolver/bootstrap/template
- `katha_refs` não aparece
- `photo_refs` não aparece
- `sangha_refs` não aparece

### 5.10 Estrutura real aceita `events` **ou** `active_events`
O contrato pedido cita uso de `events`, mas o código aceita duas variantes:
```php
events
active_events
```
Isso é compatibilidade útil, mas também indica que o contrato estrutural não está rigidamente unificado.

---

## 6. Classificação por campo e por estrutura

### 6.1 Classificação por meta/campo

| Campo / Meta | Situação encontrada | Classificação |
|---|---|---|
| `_vana_tour_id` | Usado em `_bootstrap.php`, não registrado em `register_meta()` do `vana_visit` | **PARCIAL** |
| `_vana_start_date` | Registrado em `class-vana-visit-cpt.php` e usado em `_bootstrap.php` | **IMPLEMENTADO** |
| `_vana_location` | Não encontrado | **AUSENTE** |
| `_visit_timeline_json` | Não encontrado; existe `_vana_visit_timeline_json` | **DIVERGENTE** |
| `_vana_visit_timeline_json` | Registrado e usado no resolver | **IMPLEMENTADO** |
| `days` | Usado em resolver, bootstrap e template | **IMPLEMENTADO** |
| `events` | Usado em `VisitEventResolver`, com fallback para `active_events` | **IMPLEMENTADO** |
| `active_events` | Suportado como compatibilidade | **PARCIAL** |
| `day_key` | Não encontrado | **AUSENTE** |
| `label` | Não encontrado como campo canônico; há `label_pt`/`label_en` | **PARCIAL** |
| `event_key` | Usado explicitamente em resolveres | **IMPLEMENTADO** |
| `title` | Não encontrado como canônico; no ecossistema prevalece `title_pt`/`title_en` | **PARCIAL** |
| `time` | Não evidenciado neste recorte | **AUSENTE** |
| `status` | Usado em `VisitEventResolver` e `VisitStageResolver` | **IMPLEMENTADO** |
| `media_ref` | Não encontrado | **AUSENTE** |
| `segments` | Não evidenciado neste recorte estrutural | **AUSENTE** |
| `katha_refs` | Não encontrado | **AUSENTE** |
| `photo_refs` | Não encontrado | **AUSENTE** |
| `sangha_refs` | Não encontrado | **AUSENTE** |

### 6.2 Classificação por estrutura

| Estrutura | Evidência | Classificação |
|---|---|---|
| `Visit -> Day -> Event` | Resolveres navegam por `$timeline['days']` e pelos eventos do dia | **IMPLEMENTADO** |
| Uso de `days` | Presente em resolver, bootstrap e template | **IMPLEMENTADO** |
| Uso de `events` | Presente via `events` e fallback `active_events` | **IMPLEMENTADO** |
| Compatibilidade com visit sem tour | Presente em `_bootstrap.php` | **IMPLEMENTADO** |
| Coerência CPT/meta -> renderização | Parcialmente coerente, com divergências de nomenclatura/registro | **PARCIAL** |

---

## 7. Gaps

### Gap 1 — Nome da meta de timeline diverge do contrato
O contrato cita `_visit_timeline_json`, mas o código usa:
```php
_vana_visit_timeline_json
```

### Gap 2 — `_vana_tour_id` não está formalmente registrado no CPT `vana_visit`
Ele é usado em runtime, mas não aparece em `register_meta()`.

### Gap 3 — `_vana_location` não existe como meta registrada/consumida
A localização está embutida em:
```php
$data['location_meta']
```
e não numa meta `_vana_location`.

### Gap 4 — Contrato estrutural dos campos do Day/Event não está completo
Campos contratuais ausentes ou não comprovados neste recorte:
- `day_key`
- `label` canônico
- `title` canônico
- `time`
- `media_ref`
- `segments`
- `katha_refs`
- `photo_refs`
- `sangha_refs`

### Gap 5 — Estrutura aceita `events` e `active_events`
Isso melhora compatibilidade, mas também revela falta de forma canônica única.

### Gap 6 — Coerência entre persistência e renderização é incompleta
Há coerência operacional para:
- `timeline`
- `days`
- `events`
- `event_key`
- `status`

Mas não há coerência contratual total para vários campos estruturais esperados.

---

## 8. Próximo passo recomendado

1. **Congelar uma nomenclatura canônica de metas**
   - decidir entre:
     - `_visit_timeline_json`
     - `_vana_visit_timeline_json`
   - hoje há divergência entre contrato e implementação

2. **Registrar formalmente `_vana_tour_id` no CPT `vana_visit`**
   - já é usado em runtime
   - falta formalização na camada de meta registrada

3. **Decidir se localização será meta própria ou parte exclusiva da timeline**
   - hoje o sistema usa `location_meta` dentro do JSON
   - o contrato cita `_vana_location`

4. **Congelar contrato canônico do Day**
   - `day_key`
   - `label`
   - `date_local`
   - e definir se `label_pt/label_en` continuam ou se haverá alias oficial

5. **Congelar contrato canônico do Event**
   - `event_key`
   - `title`
   - `time`
   - `status`
   - `media_ref`
   - `segments`
   - `katha_refs`
   - `photo_refs`
   - `sangha_refs`

6. **Eliminar dualidade `events` vs `active_events`**
   - ou definir uma canônica
   - ou manter ambas, mas documentar claramente qual é a oficial e qual é compatibilidade

7. **Alinhar contrato e renderização SSR**
   - o SSR hoje funciona com um subconjunto da estrutura
   - para aderência ao Contrato 6.0, os campos exigidos precisam existir de forma explícita e consistente
```