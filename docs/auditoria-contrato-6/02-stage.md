```markdown
# docs/auditoria-contrato-6/02-stage.md

## 1. Objetivo

Auditar **apenas a ZONA 3 — STAGE da página de visita** do plugin `vana-mission-control`, comparando o código atual com o **Contrato 6.0**, sem aplicar patch e sem alterar código.

Escopo validado:

- modos vídeo / áudio / neutro / aguardando
- embed YouTube protegido
- autoplay curado
- ausência de recomendados externos
- controles esperados
- segments abaixo dos controles
- clique em segment faz seek
- segment ativo acompanha `currentTime`
- integração Stage -> Agenda
- integração Stage -> HK
- existência e coerência entre stage fragment / resolver / view model

---

## 2. Arquivos inspecionados

Foram inspecionados somente os arquivos indicados:

1. `templates/visit/parts/stage.php`
2. `templates/visit/parts/stage-fragment.php`
3. `templates/visit/assets/visit-scripts.php`
4. `includes/class-visit-stage-resolver.php`
5. `includes/class-visit-stage-view-model.php`
6. `includes/rest/class-vana-rest-stage.php`
7. `includes/rest/class-vana-rest-stage-fragment.php`
8. `assets/js/VanaVisitController.js`

---

## 3. Itens auditados

1. Modos vídeo / áudio / neutro / aguardando  
2. Embed YouTube protegido  
3. Autoplay curado  
4. Ausência de recomendados externos  
5. Controles esperados  
6. Segments abaixo dos controles  
7. Clique em segment faz seek  
8. Segment ativo acompanha `currentTime`  
9. Integração Stage -> Agenda  
10. Integração Stage -> HK  
11. Coerência entre Stage Fragment / Resolver / ViewModel  

---

## 4. Evidências por item

### Item 1 — Modos vídeo / áudio / neutro / aguardando

**Evidências:**

#### 1.1 Estrutura de modo neutro em `stage.php`
Em `templates/visit/parts/stage.php`:
```php
$is_neutral_mode = empty( $current_event['event_key'] ) || empty( $stage['type'] );
```

E a section marca isso no DOM:
```php
<section
    class="vana-stage <?php echo $is_neutral_mode ? 'vana-stage--neutral' : ''; ?>"
    id="vana-stage"
    data-event-key="<?php echo esc_attr( $current_event['event_key'] ); ?>"
    aria-label="<?php echo esc_attr( vana_t( 'stage.aria', $lang ) ); ?>"
    data-is-neutral="<?php echo $is_neutral_mode ? '1' : '0'; ?>"
    data-transitioning="<?php echo $is_transitioning ? '1' : '0'; ?>"
>
```

#### 1.2 Placeholder de aguardando / sem mídia
Ainda em `stage.php`, quando não há tipo de mídia renderizável:
```php
<?php else : ?>
  <div class="vana-stage-placeholder"
       role="status" aria-live="polite"
       style="color:var(--vana-muted);font-size:1.2rem;text-align:center;padding:40px;">
    <?php if ( $has_live ) : ?>
      ...
      <?php echo esc_html( vana_t( 'stage.live_soon', $lang ) ); ?>
    <?php else : ?>
      ...
      <?php echo esc_html( vana_t( 'stage.empty', $lang ) ); ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
```

#### 1.3 Resolução de `stage_mode` no resolver
Em `includes/class-visit-stage-resolver.php`, a função:
```php
private static function resolve_stage_mode(...)
```
retorna estados como:
```php
return 'katha_live';
return 'darshan_live';
return 'parikrama_live';
return 'replay_ready';
return 'visit_closed';
return 'pre_arrival';
return 'day_closed';
return 'post_visit';
return 'day_live';
```

#### 1.4 Tipos efetivamente renderizados em `stage.php`
Os tipos reconhecidos visualmente são:
```php
if ( $stage['type'] === 'vod' )
elseif ( $stage['type'] === 'gallery' )
elseif ( $stage['type'] === 'sangha' )
else ...
```

#### 1.5 Ausência de modo explícito de áudio
Nos arquivos inspecionados, não foi encontrado ramo específico para:
- `audio`
- player de áudio
- `<audio>`
- provider de áudio dedicado

**Leitura objetiva:**  
Há implementação clara de **modo neutro** e de **modo aguardando/placeholder**. Há também **modo de vídeo** via `vod`. Não há evidência de **modo áudio** dedicado nos arquivos lidos. O resolver possui `stage_mode` semântico, mas `stage.php` não expõe uma matriz completa e explícita de modos visuais conforme o contrato.

**Classificação:** **PARCIAL**

---

### Item 2 — Embed YouTube protegido

**Evidências:**

#### 2.1 Uso de domínio privacy-enhanced
Em `templates/visit/assets/visit-scripts.php`:
```js
iframe.contentWindow.postMessage(JSON.stringify(msg), 'https://www.youtube-nocookie.com');
```

Em `templates/visit/parts/stage-fragment.php`:
```php
src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($stage_video_id); ?>?rel=0&amp;autoplay=1&amp;enablejsapi=1&amp;origin=<?php echo esc_attr( home_url() ); ?>"
```

Em `visit-scripts.php`, no swap dinâmico:
```js
var src = 'https://www.youtube-nocookie.com/embed/' + videoId
  + '?rel=0&modestbranding=1&enablejsapi=1&autoplay=1&origin=' + encodeURIComponent(window.location.origin);
```

#### 2.2 Endurecimento do iframe via JS
Em `initSegments()`:
```js
if (src.indexOf('enablejsapi') === -1) {
  src += (src.indexOf('?') > -1 ? '&' : '?') + 'enablejsapi=1';
}
if (src.indexOf('origin=') === -1) {
  src += (src.indexOf('?') > -1 ? '&' : '?') + 'origin=' + encodeURIComponent(window.location.origin);
}
iframe.setAttribute('src', src);
```

**Leitura objetiva:**  
Há uso consistente de `youtube-nocookie.com` e reforço de `enablejsapi=1` + `origin=...`, o que caracteriza embed YouTube mais protegido.

**Classificação:** **IMPLEMENTADO**

---

### Item 3 — Autoplay curado

**Evidências:**

#### 3.1 Autoplay no fragmento de stage
Em `templates/visit/parts/stage-fragment.php`:
```php
src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($stage_video_id); ?>?rel=0&amp;autoplay=1&amp;enablejsapi=1&amp;origin=<?php echo esc_attr( home_url() ); ?>"
```

#### 3.2 Autoplay no swap Stage <- Agenda
Em `templates/visit/assets/visit-scripts.php`:
```js
var src = 'https://www.youtube-nocookie.com/embed/' + videoId
  + '?rel=0&modestbranding=1&enablejsapi=1&autoplay=1&origin=' + encodeURIComponent(window.location.origin);
```

#### 3.3 Ausência de política curatorial explícita
Nos arquivos inspecionados, não foi encontrada regra condicionando autoplay a:
- tipo de evento
- consentimento do usuário
- somente navegação via clique
- somente troca vinda da agenda
- estado `live` vs `replay`
- feature flag
- heurística de “curado”

#### 3.4 Stage SSR principal
Em `templates/visit/parts/stage.php`, a implementação do iframe YouTube não aparece diretamente neste arquivo; ela é delegada a:
```php
echo vana_render_vod_player( $stage['data'], $lang );
```
Mas o conteúdo desta função **não foi fornecido**, então não há como confirmar a política do autoplay no SSR principal.

**Leitura objetiva:**  
Existe autoplay em fragmento e em troca dinâmica do stage, mas não há evidência suficiente de que o autoplay seja “curado” nos termos do contrato. Além disso, o player SSR principal depende de `vana_render_vod_player()` não disponibilizada.

**Classificação:** **PARCIAL**

---

### Item 4 — Ausência de recomendados externos

**Evidências:**

#### 4.1 Parâmetros no embed YouTube
Em `stage-fragment.php`:
```php
?rel=0&amp;autoplay=1&amp;enablejsapi=1&amp;origin=...
```

Em `visit-scripts.php`:
```js
'?rel=0&modestbranding=1&enablejsapi=1&autoplay=1&origin='
```

#### 4.2 Ausência de flags adicionais
Nos trechos inspecionados, não foram encontrados parâmetros como:
- `iv_load_policy`
- `controls`
- `playsinline`
- `fs`
- `cc_load_policy`
- qualquer lógica específica para tela final / related cards além de `rel=0`

#### 4.3 Dependência de função externa no SSR
Em `stage.php`, o player de VOD é delegado a:
```php
echo vana_render_vod_player( $stage['data'], $lang );
```
Sem o corpo dessa função, não é possível validar todos os parâmetros do embed SSR principal.

**Leitura objetiva:**  
Há esforço explícito para reduzir recomendados externos via `rel=0`, mas não é possível afirmar aderência completa da ausência de recomendados no player principal, pois o render SSR depende de função não fornecida.

**Classificação:** **PARCIAL**

---

### Item 5 — Controles esperados

**Evidências:**

#### 5.1 Ações do Stage renderizadas abaixo do título
Em `templates/visit/parts/stage.php`:
```php
<div class="vana-stage-actions" style="display:flex;gap:10px;margin:12px 0;flex-wrap:wrap;">
```

Botão Share:
```php
<button
  type="button"
  class="vana-stage-action-btn vana-stage-action-btn--share"
  id="vana-stage-share-btn"
>
```

Botão Hari-Katha:
```php
<button
  type="button"
  class="vana-stage-action-btn vana-stage-action-btn--hk"
  id="vana-stage-hk-btn"
  data-drawer="vana-hk-drawer"
>
```

#### 5.2 Controles do player não auditáveis integralmente
O player principal em `stage.php` é:
```php
echo vana_render_vod_player( $stage['data'], $lang );
```
Sem definição da função, não é possível validar:
- controles nativos esperados
- esconder/exibir controles
- fullscreen
- scrubber
- mute
- captions
- etc.

#### 5.3 Controles do fragmento de YouTube
Em `stage-fragment.php`, o iframe inclui:
```php
allowfullscreen
allow="autoplay"
loading="lazy"
```
Mas não define explicitamente `controls=1` ou equivalente.

**Leitura objetiva:**  
Existem controles de ação do stage (share / HK), mas os controles do player multimídia não podem ser validados integralmente com os arquivos fornecidos.

**Classificação:** **PARCIAL**

---

### Item 6 — Segments abaixo dos controles

**Evidências:**

Em `templates/visit/parts/stage.php`, a ordem visual é:

1. bloco de vídeo
2. bloco de info
3. ações (`.vana-stage-actions`)
4. descrição / localização
5. segmentos

O trecho dos segmentos aparece **depois** de info/ações:
```php
<div class="vana-stage-info">
  ...
  <?php if ( ! $is_neutral_mode ): ?>
    <div class="vana-stage-actions" ...>
  <?php endif; ?>
  ...
</div>

<?php if ( ! empty( $stage_segments ) && $seg_provider === 'youtube' ) : ?>
  <div class="vana-stage-segments" ...>
```

No `stage-fragment.php`, a mesma estrutura se repete: segmentos vêm após o bloco de info:
```php
<div class="vana-stage-info">...</div>
<?php if (!empty($stage_segments) && $stage_provider === 'youtube'): ?>
  <div class="vana-stage-segments" ...>
```

**Leitura objetiva:**  
Os segments são renderizados **abaixo do bloco de informações/ações**, portanto materialmente abaixo dos controles do stage.

**Classificação:** **IMPLEMENTADO**

---

### Item 7 — Clique em segment faz seek

**Evidências:**

#### 7.1 Botões de segmento
Em `stage.php`:
```php
<button
  type="button"
  class="vana-seg-btn"
  data-vana-stage-seg="1"
  data-t="<?php echo esc_attr( $t ); ?>"
>
```

Em `stage-fragment.php`:
```php
<button type="button" class="vana-seg-btn"
        data-vana-stage-seg="1"
        data-t="<?php echo esc_attr($t); ?>"
>
```

#### 7.2 JS que escuta clique
Em `templates/visit/assets/visit-scripts.php`:
```js
function initSegments() {
  var btns = document.querySelectorAll('[data-vana-stage-seg="1"]');
  if (!btns.length) return;
```

No clique:
```js
btn.addEventListener('click', function () {
  var t   = btn.getAttribute('data-t') || '0:00';
  var sec = timeToSec(t);

  ytPostMessage({ event: 'command', func: 'seekTo', args: [sec, true] });
```

E faz scroll ao player:
```js
if (iframe) {
  iframe.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
```

**Leitura objetiva:**  
O clique no segment dispara `seekTo` via YouTube postMessage.

**Classificação:** **IMPLEMENTADO**

---

### Item 8 — Segment ativo acompanha `currentTime`

**Evidências:**

- Em `templates/visit/assets/visit-scripts.php`, `initSegments()` apenas:
  - adiciona listeners de clique
  - faz `seekTo`
  - muda temporariamente estilo do botão clicado
- Não foi encontrado:
  - polling de `currentTime`
  - API de player com `getCurrentTime`
  - listener de estado do player
  - classe como `.is-active` aplicada por progresso temporal
  - sincronização contínua com o tempo atual do vídeo

Trecho existente:
```js
var prev = btn.style.background;
btn.style.background = 'var(--vana-gold)';
btn.style.color      = '#111';
setTimeout(function () {
  btn.style.background = prev;
  btn.style.color      = '';
}, 900);
```

**Leitura objetiva:**  
Existe apenas feedback temporário no clique. Não existe acompanhamento reativo do tempo atual do vídeo para manter o segmento ativo sincronizado.

**Classificação:** **AUSENTE**

---

### Item 9 — Integração Stage -> Agenda

**Evidências:**

#### 9.1 Agenda aciona Stage
Em `visit-scripts.php`, há integração **Agenda -> Stage** por `initScheduleVod()`:
```js
var singles = document.querySelectorAll('.vana-schedule-item[data-vod-case="single"]');
```
e:
```js
loadAndPlay(vodId, segStart);
```

#### 9.2 Swap do player e título do stage
```js
swapStageYouTube(videoId, title, segStart);
```
com:
```js
iframe.src = src;
```
e:
```js
var titleEl = document.getElementById('vanaStageTitle')
              || document.querySelector('[data-vana-stage-title]');
if (titleEl && title) titleEl.textContent = title;
```

#### 9.3 Ausência do caminho inverso
Nos arquivos inspecionados, não foi encontrado:
- clique no stage destacando item correspondente na agenda
- `data-event-key` do stage sendo usado para sincronizar agenda
- chamada REST/DOM para atualizar agenda a partir do stage
- listener de evento em `#vana-stage` que repercuta na agenda

**Leitura objetiva:**  
Existe integração **Agenda -> Stage**, mas não há evidência de integração **Stage -> Agenda** no sentido inverso pedido pelo item contratual.

**Classificação:** **PARCIAL**

---

### Item 10 — Integração Stage -> HK

**Evidências:**

#### 10.1 Botão de HK no stage
Em `templates/visit/parts/stage.php`:
```php
<button
  type="button"
  class="vana-stage-action-btn vana-stage-action-btn--hk"
  id="vana-stage-hk-btn"
  data-drawer="vana-hk-drawer"
>
```

#### 10.2 Loader de Hari-Kathā independente do stage
Em `templates/visit/assets/visit-scripts.php`, o módulo HK:
```js
var API_BASE = ... '/vana/v1';
```
inicializa lendo do root da seção HK:
```js
state.visitId   = getAttrAny(root, ['data-visit-id', 'data-v-visit-id', 'data-visit']);
state.activeDay = getAttrAny(root, ['data-day', 'data-v-day', 'data-day-date']);
```
e busca:
```js
'/kathas?visit_id=' + encodeURIComponent(state.visitId)
+ '&day=' + encodeURIComponent(state.activeDay);
```

#### 10.3 HK faz seek no Stage
Dentro de passagens HK:
```js
iframe.contentWindow.postMessage(
  JSON.stringify({ event: 'command', func: 'seekTo', args: [sec, true] }),
  'https://www.youtube-nocookie.com'
);
```

#### 10.4 Ausência de bind do botão `#vana-stage-hk-btn`
Nos arquivos inspecionados, não foi encontrado listener JS para:
- `#vana-stage-hk-btn`
- `[data-drawer="vana-hk-drawer"]`

**Leitura objetiva:**  
Há acoplamento parcial: o HK consegue interagir com o Stage via seek, e o Stage renderiza um botão relacionado ao HK. Porém o fluxo Stage -> HK não está comprovado integralmente, porque o botão do stage não possui implementação demonstrada neste recorte.

**Classificação:** **PARCIAL**

---

### Item 11 — Coerência entre Stage Fragment / Resolver / ViewModel

**Evidências:**

#### 11.1 Resolver e ViewModel SSR
Em `includes/class-visit-stage-resolver.php`, `resolve()` monta:
```php
return new VisitStageViewModel( [
    'visit_id'  => $visit_id,
    'visit_ref' => ...,
    'timeline'  => $timeline,
    'overrides' => $overrides,
    'active_day'      => ...,
    'active_day_date' => ...,
    'active_events'   => ...,
    'active_event'    => ...,
    'hero'       => $hero,
    'stage_mode' => $stage_mode,
    'viewer_mode'      => $viewer_mode,
    'viewer_event_key' => $viewer_event_key,
    'viewer_item_id'   => $viewer_item_id,
    'visit_timezone' => $visit_timezone,
    'visit_status'   => $visit_status,
] );
```

Em `includes/class-visit-stage-view-model.php`, o contrato do view model expõe:
```php
public function to_template_vars(): array {
    return $this->data;
}
```

#### 11.2 `stage.php` depende de `active_event`
Em `templates/visit/parts/stage.php`:
```php
$_evt = is_array( $active_event ) ? $active_event : [];
```
e depois:
```php
$current_event = vana_normalize_event([
    'active_vod'   => $_vod_first,
    'vod_list'     => array_slice( $_vods, 1 ),
    'hero'         => $active_day['hero']  ?? [],
    ...
    'event_key'    => $_evt['event_key']   ?? '',
```

Ou seja, `stage.php` espera **`$active_event`**.

#### 11.3 REST `/stage/{event_key}` não entrega `active_event`
Em `includes/rest/class-vana-rest-stage.php`, `render_stage()` faz:
```php
extract($vars, EXTR_SKIP);
include $template;
```
Mas os vars passados são:
```php
compact(
    'lang',
    'visit_id',
    'visit_tz',
    'visit_city_ref',
    'active_day',
    'active_day_date',
    'active_vod',
    'vod_list',
    'visit_status'
)
```
Não inclui:
- `active_event`
- `hero`
- `stage_mode`

#### 11.4 REST `stage-fragment` também não entrega `active_event`
Em `includes/rest/class-vana-rest-stage-fragment.php`, `render_event_stage()` faz:
```php
extract(compact(
    'lang', 'visit_id', 'visit_tz',
    'visit_city_ref', 'visit_status',
    'active_day', 'active_day_date',
    'active_vod', 'vod_list'
));
include $stage_path;
```
Também não inclui `active_event`.

#### 11.5 `stage-fragment.php` é estruturalmente autônomo
O arquivo `templates/visit/parts/stage-fragment.php` não depende do ViewModel SSR. Ele reconstrói item por:
- `_vana_katha_data`
- `_media_items`
- metas de post

#### 11.6 Inconsistência de timezone meta key
Em `class-visit-stage-resolver.php`:
```php
$visit_timezone = self::read_string_meta( $visit_id, '_vana_tz', 'UTC' );
```
Mas em `class-vana-rest-stage.php`:
```php
$visit_tz = (string) (get_post_meta($visit_id, '_vana_visit_timezone', true) ?: 'UTC');
```
E em `class-vana-rest-stage-fragment.php`:
```php
$visit_tz = get_post_meta($visit_id, '_vana_visit_timezone', true) ?: 'UTC';
```

#### 11.7 `render_restore()` usa outra fonte de dados
Em `class-vana-rest-stage-fragment.php`:
```php
$visit_meta = get_post_meta($visit_id, '_vana_visit_data', true);
```
Enquanto o resolver principal usa:
```php
_vana_visit_timeline_json
_vana_overrides_json
```

**Leitura objetiva:**  
Existe uma arquitetura formal de **Resolver + ViewModel** para SSR, mas os endpoints REST de stage **não reaproveitam coerentemente o mesmo contrato de entrada** que `stage.php` parece esperar. Há divergência de variáveis fornecidas, de meta keys (`_vana_tz` vs `_vana_visit_timezone`) e até de fonte de dados (`_vana_visit_timeline_json` vs `_vana_visit_data`).

**Classificação:** **DIVERGENTE**

---

## 5. Classificação por item

| Item auditado | Classificação |
|---|---|
| Modos vídeo / áudio / neutro / aguardando | **PARCIAL** |
| Embed YouTube protegido | **IMPLEMENTADO** |
| Autoplay curado | **PARCIAL** |
| Ausência de recomendados externos | **PARCIAL** |
| Controles esperados | **PARCIAL** |
| Segments abaixo dos controles | **IMPLEMENTADO** |
| Clique em segment faz seek | **IMPLEMENTADO** |
| Segment ativo acompanha `currentTime` | **AUSENTE** |
| Integração Stage -> Agenda | **PARCIAL** |
| Integração Stage -> HK | **PARCIAL** |
| Stage fragment / resolver / view model coerentes | **DIVERGENTE** |

---

## 6. Gaps

### Gap 1 — Modo áudio não foi encontrado
Nos arquivos inspecionados não há:
- branch `audio`
- `<audio>`
- provider de áudio dedicado
- UI de áudio

### Gap 2 — `stage_mode` existe semanticamente, mas não governa plenamente o render
`VisitStageResolver::resolve_stage_mode()` gera estados como:
- `katha_live`
- `replay_ready`
- `pre_arrival`
- `day_closed`

Porém `templates/visit/parts/stage.php` renderiza principalmente por:
- `stage['type'] === 'vod'`
- `gallery`
- `sangha`
- placeholder

Ou seja, o contrato semântico do resolver não aparece totalmente amarrado ao render do Stage.

### Gap 3 — Política de autoplay não está explicitamente curada
Há `autoplay=1` em:
- `stage-fragment.php`
- `swapStageYouTube()` em `visit-scripts.php`

Mas não há política explícita do tipo:
- autoplay só após gesto do usuário
- autoplay apenas em troca via agenda
- autoplay desativado em SSR inicial
- autoplay condicionado ao modo

### Gap 4 — Controles do player principal não são auditáveis
O SSR principal delega para:
```php
vana_render_vod_player( $stage['data'], $lang )
```
Como o corpo da função não foi fornecido, não é possível validar:
- controles
- parâmetros finais de embed
- autoplay
- suppress de recomendados
- labels de acessibilidade do player

### Gap 5 — Segmento ativo por `currentTime` está ausente
Só há seek por clique e highlight temporário por 900 ms.  
Não há sincronização contínua com o tempo do vídeo.

### Gap 6 — Integração Stage -> Agenda não está fechada
Existe **Agenda -> Stage**, mas não há evidência do caminho inverso:
- stage mudar destaque na agenda
- stage restaurar item correspondente
- event_key do stage governar agenda

### Gap 7 — Integração Stage -> HK não está totalmente comprovada
O Stage renderiza botão HK, mas nos arquivos inspecionados:
- não há listener do botão `#vana-stage-hk-btn`
- não há abertura de drawer HK demonstrada

### Gap 8 — Divergência arquitetural forte entre SSR e REST
Os endpoints REST que renderizam Stage:
- não passam `active_event` para `stage.php`
- usam meta keys diferentes para timezone
- `render_restore()` usa `_vana_visit_data`, enquanto o resolver SSR usa `_vana_visit_timeline_json`

Isso compromete a coerência do contrato único do Stage.

### Gap 9 — `stage-fragment.php` enviado parece conter artefato de shell
O conteúdo recebido começa com:
```php
cat > templates/visit/parts/stage-fragment.php << 'PHPEOF'
```
e termina com:
```php
PHPEOF
```
Esse conteúdo indica artefato de comando shell no arquivo fornecido. A auditoria considerou o conteúdo como recebido, mas isso sugere inconsistência do próprio arquivo entregue.

---

## 7. Dependências pendentes para Stage aderir ao contrato

Para a ZONA 3 aderir ao Contrato 6.0, os seguintes pontos ainda dependem de fechamento técnico:

1. **Definição explícita dos modos visuais do Stage**
   - mapear contrato para render:
     - vídeo
     - áudio
     - neutro
     - aguardando
   - hoje o render está centrado em `vod/gallery/sangha/placeholder`

2. **Fonte única de verdade para render SSR e REST**
   - alinhar `stage.php`, `VisitStageResolver`, `VisitStageViewModel`,
     `Vana_REST_Stage` e `Vana_REST_Stage_Fragment`

3. **Contrato uniforme de variáveis do Stage**
   - `stage.php` usa `active_event`
   - os endpoints REST não fornecem `active_event`
   - isso precisa ser harmonizado

4. **Unificação de meta keys e bootstrap**
   - `_vana_tz` vs `_vana_visit_timezone`
   - `_vana_visit_timeline_json` vs `_vana_visit_data`

5. **Política explícita de autoplay**
   - definir quando autoplay é permitido e quando não é

6. **Validação do player SSR principal**
   - sem o código de `vana_render_vod_player()`, não é possível fechar aderência dos embeds

7. **Sincronização temporal dos segments**
   - implementar/garantir acompanhamento de `currentTime`

8. **Integrações inversas do Stage**
   - Stage -> Agenda
   - Stage -> HK

---

## 8. Próximo passo recomendado

1. **Fechar primeiro o contrato estrutural do Stage**
   - alinhar `stage.php` com o mesmo payload usado por SSR e REST
   - eliminar divergência entre resolver/view model e endpoints

2. **Auditar ou expor `vana_render_vod_player()`**
   - sem essa função, a aderência de player principal fica incompleta

3. **Definir matriz de modos do Stage**
   - `video`, `audio`, `neutral`, `waiting`
   - com critérios claros de entrada e saída

4. **Especificar política de autoplay**
   - inicial, por clique, por agenda, por fragment swap

5. **Fechar a camada de segments**
   - manter seek por clique
   - adicionar sincronização por `currentTime`
   - refletir ativo visualmente

6. **Amarrar Stage com Agenda e HK**
   - validar fluxo bidirecional
   - ligar botão HK do stage a comportamento comprovável

7. **Corrigir a divergência de bootstrap REST**
   - endpoints de stage devem usar o mesmo contrato do `VisitStageResolver` ou um contrato derivado formalmente compatível
```