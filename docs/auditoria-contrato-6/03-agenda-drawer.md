```markdown
# docs/auditoria-contrato-6/03-agenda-drawer.md

## 1. Objetivo

Auditar **apenas a GAVETA DIREITA — AGENDA** da página de visita do plugin `vana-mission-control`, comparando o código atual com o **Contrato 6.0**, sem aplicar patch e sem alterar código.

Escopo validado:

- estado padrão fechada
- formas de acionamento
- exceções de abertura automática
- anatomia interna da agenda
- estados de evento: passado, ativo, futuro, sem mídia
- botões condicionais `[▶]`, `[📖 HK]`, `[🔔]`
- idioma PT/EN afeta HK e não áudio
- agenda troca mídia do stage
- stage terminado avança para próximo evento
- integração Agenda -> Stage
- integração Agenda -> HK

---

## 2. Arquivos inspecionados

Foram inspecionados somente os arquivos solicitados:

1. `templates/visit/parts/agenda-drawer.php`
2. `templates/visit/assets/visit-scripts.php`
3. `assets/js/VanaAgendaController.js`
4. `assets/js/VanaVisitController.js`
5. `includes/class-visit-stage-resolver.php`
6. `includes/class-visit-event-resolver.php`

---

## 3. Itens auditados

1. Estado padrão fechada  
2. Formas de acionamento  
3. Exceções de abertura automática  
4. Anatomia interna da agenda  
5. Estados de evento: passado, ativo, futuro, sem mídia  
6. Botões condicionais `[▶]`, `[📖 HK]`, `[🔔]`  
7. Idioma PT/EN afeta HK e não áudio  
8. Agenda troca mídia do stage  
9. Stage terminado avança para próximo evento  
10. Integração Agenda -> Stage  
11. Integração Agenda -> HK  

---

## 4. Evidências por item

### Item 1 — Estado padrão fechada

**Evidências:**

Em `templates/visit/parts/agenda-drawer.php`, a gaveta nasce com `hidden`:
```php
<div
    id="vana-agenda-drawer"
    class="vana-drawer vana-drawer--agenda"
    data-vana-agenda-drawer
    role="dialog"
    aria-modal="true"
    aria-label="<?php echo esc_attr('Agenda de eventos'); ?>"
    hidden
>
```

O overlay também nasce fechado:
```php
<div class="vana-drawer__overlay" id="vana-agenda-overlay" data-vana-agenda-overlay hidden></div>
```

Em `assets/js/VanaAgendaController.js`, o estado interno inicia fechado:
```js
let isOpen = false;
```

A função `openDrawer()` só abre explicitamente quando chamada:
```js
setVisibility( drawer, true );
setVisibility( overlay, true );
drawer.classList.add( 'is-open' );
```

**Leitura objetiva:**  
A gaveta da agenda está implementada com estado inicial fechado tanto no HTML quanto no controlador JS.

**Classificação:** **IMPLEMENTADO**

---

### Item 2 — Formas de acionamento

**Evidências:**

#### 2.1 Botão de abertura no header
Em `templates/visit/parts/hero-header.php` já auditado anteriormente, existe:
```php
<button
    type="button"
    class="vana-header__notify-btn vana-header__agenda-btn"
    id="vana-agenda-open-btn"
    data-vana-agenda-open
    aria-expanded="false"
    aria-controls="vana-agenda-drawer"
>
```

#### 2.2 Controller da agenda escuta botão de abertura
Em `assets/js/VanaAgendaController.js`:
```js
const OPEN_BTN_SEL = '[data-vana-agenda-open]';
```

Em `bindOpenClose()`:
```js
if ( openBtn ) {
    openBtn.addEventListener( 'click', function ( e ) {
        e.preventDefault();
        openDrawer();
    } );
}
```

#### 2.3 Fechamento por botão interno
Em `agenda-drawer.php`:
```php
<button class="vana-drawer__close" data-vana-agenda-close aria-label="Fechar">
```

Em `VanaAgendaController.js`:
```js
const CLOSE_BTN_SEL = '[data-vana-agenda-close]';
```
e:
```js
if ( closeBtn ) {
    closeBtn.addEventListener( 'click', function ( e ) {
        e.preventDefault();
        closeDrawer();
    } );
}
```

#### 2.4 Fechamento por overlay
Em `VanaAgendaController.js`:
```js
if ( overlay ) {
    overlay.addEventListener( 'click', closeDrawer );
}
```

#### 2.5 Fechamento por `Escape`
Em `VanaAgendaController.js`:
```js
document.addEventListener( 'keydown', function ( e ) {
    if ( e.key === 'Escape' && isOpen ) {
        closeDrawer();
    }
} );
```

#### 2.6 Trap de foco
Em `VanaAgendaController.js`:
```js
trapFocus( drawer );
```
com controle de `Tab` dentro da gaveta.

**Leitura objetiva:**  
Há abertura por botão dedicado e fechamento por botão interno, overlay e tecla `Escape`, com trap de foco implementado.

**Classificação:** **IMPLEMENTADO**

---

### Item 3 — Exceções de abertura automática

**Evidências:**

Nos arquivos inspecionados, **não foi encontrado**:

- chamada automática a `openDrawer()` baseada em estado da visita
- abertura automática por query string
- abertura automática por evento `live`
- abertura automática após transição de stage
- abertura automática por falta de mídia
- abertura automática por dia ativo

Em `assets/js/VanaAgendaController.js`, `openDrawer()` é chamado apenas dentro de:
```js
openBtn.addEventListener( 'click', function ( e ) {
    e.preventDefault();
    openDrawer();
} );
```

Não há outro `openDrawer()` nos trechos fornecidos.

**Leitura objetiva:**  
Não há evidência de exceções de abertura automática da agenda neste recorte.

**Classificação:** **AUSENTE**

---

### Item 4 — Anatomia interna da agenda

**Evidências:**

Em `templates/visit/parts/agenda-drawer.php`, a gaveta contém:

#### 4.1 Header
```php
<div class="vana-drawer__header">
    <span class="vana-drawer__header-title">
        📅 <?php echo esc_html( vana_t( 'agenda.title', $lang ) ?: 'Agenda' ); ?>
    </span>
    <button class="vana-drawer__close" data-vana-agenda-close aria-label="Fechar">
```

#### 4.2 Body
```php
<div class="vana-drawer__body">
```

#### 4.3 Day selector interno
```php
<div class="vana-agenda-day-selector" id="vana-agenda-day-selector" role="tablist">
```

Com tabs:
```php
<button
    id="vana-agenda-day-tab-<?php echo esc_attr($idx); ?>"
    role="tab"
    class="vana-agenda-day-tab <?php echo $is_first ? 'vana-agenda-day-tab--active' : ''; ?>"
    data-day-date="<?php echo esc_attr($day_date); ?>"
    data-vana-day-tab="<?php echo esc_attr($day_date); ?>"
    aria-selected="<?php echo $is_first ? 'true' : 'false'; ?>"
    aria-controls="vana-agenda-day-<?php echo esc_attr($idx); ?>"
>
```

#### 4.4 Painéis por dia
```php
<div
    id="vana-agenda-day-<?php echo esc_attr($idx); ?>"
    role="tabpanel"
    class="vana-agenda-events <?php echo $is_first ? '' : 'hidden'; ?>"
    aria-labelledby="vana-agenda-day-tab-<?php echo esc_attr($idx); ?>"
>
```

#### 4.5 Lista de eventos
```php
<ul class="vana-agenda-event-list">
```

#### 4.6 Botão por evento
```php
<button
    type="button"
    class="vana-agenda-event-btn"
    data-event-key="<?php echo esc_attr($event_key); ?>"
    data-vana-event="<?php echo esc_attr($event_key); ?>"
    aria-label="<?php echo esc_attr($event_time . ' — ' . $event_title); ?>"
>
```

#### 4.7 Estados vazios
Sem eventos no dia:
```php
<div class="vana-agenda-empty" role="status">
    <p><?php echo esc_html( vana_t( 'agenda.empty', $lang ) ?: 'Sem eventos para este dia' ); ?></p>
</div>
```

Sem dias disponíveis:
```php
<div class="vana-agenda-empty" role="status">
    <p><?php echo esc_html( vana_t( 'agenda.no_days', $lang ) ?: 'Nenhum dia disponível' ); ?></p>
</div>
```

#### 4.8 Comportamento dos tabs
Em `VanaAgendaController.js`:
```js
function activateDay( dayId ) { ... }
```
atualiza:
- `aria-selected`
- classe `vana-agenda-day-tab--active`
- visibilidade dos painéis `.vana-agenda-events`

**Leitura objetiva:**  
A anatomia da gaveta está bem definida: header, close, overlay, tabs de dia, painéis por dia, lista de eventos e estados vazios.

**Classificação:** **IMPLEMENTADO**

---

### Item 5 — Estados de evento: passado, ativo, futuro, sem mídia

**Evidências:**

Em `templates/visit/parts/agenda-drawer.php`, por evento são lidos:
```php
$event_key = $event['event_key'] ?? '';
$event_title = $event['title_' . $lang] ?? $event['title_pt'] ?? '';
$event_time = $event['time_local'] ?? $event['time'] ?? '';
$event_status = $event['status'] ?? '';
```

A única diferenciação visual encontrada é para `live`:
```php
<?php if ($event_status === 'live'): ?>
    <span class="vana-agenda-event-badge" aria-label="ao vivo">🔴</span>
<?php endif; ?>
```

Não foram encontrados, neste arquivo ou no controlador:
- classe para passado
- classe para futuro
- classe para ativo/selecionado do evento atual
- estado “sem mídia”
- botões condicionados à existência de mídia
- comparação temporal com `now`

Em `includes/class-visit-event-resolver.php`, existe resolução de evento ativo:
```php
private static function resolveActiveEvent(array $events): ?array {
    foreach ($events as $event) {
        if (($event['status'] ?? '') === 'live') {
            return $event;
        }
    }
    foreach ($events as $event) {
        if (!empty($event['media']['vods'] ?? [])) {
            return $event;
        }
    }
    return $events[0] ?? null;
}
```

Mas esse estado não é refletido na agenda-drawer com classes/ícones específicos além do badge live.

**Leitura objetiva:**  
A agenda reconhece `status === 'live'`, mas não implementa os estados contratuais completos de passado, ativo, futuro e sem mídia na UI observada.

**Classificação:** **PARCIAL**

---

### Item 6 — Botões condicionais `[▶]`, `[📖 HK]`, `[🔔]`

**Evidências:**

No `agenda-drawer.php`, cada item de evento contém apenas um botão único:
```php
<button
    type="button"
    class="vana-agenda-event-btn"
    data-event-key="<?php echo esc_attr($event_key); ?>"
    data-vana-event="<?php echo esc_attr($event_key); ?>"
>
```

Dentro dele, só há:
```php
<div class="vana-agenda-event-time">
    <strong><?php echo esc_html($event_time); ?></strong>
</div>
<div class="vana-agenda-event-title">
    <?php echo esc_html($event_title); ?>
    <?php if ($event_status === 'live'): ?>
        <span class="vana-agenda-event-badge" aria-label="ao vivo">🔴</span>
    <?php endif; ?>
</div>
```

Não foram encontrados na agenda:
- botão `[▶]`
- botão `[📖 HK]`
- botão `[🔔]`

O sino existente está no header da página, não na agenda:
em `hero-header.php`, botão:
```php
id="vana-notify-btn"
```
mas não dentro da gaveta da agenda.

**Leitura objetiva:**  
Os botões condicionais previstos no contrato não aparecem na anatomia da agenda inspecionada.

**Classificação:** **AUSENTE**

---

### Item 7 — Idioma PT/EN afeta HK e não áudio

**Evidências:**

#### 7.1 Título do evento usa idioma
Em `agenda-drawer.php`:
```php
$event_title = $event['title_' . $lang] ?? $event['title_pt'] ?? '';
```

#### 7.2 Título da agenda e vazios usam `vana_t(...)`
```php
vana_t( 'agenda.title', $lang )
vana_t( 'agenda.empty', $lang )
vana_t( 'agenda.no_days', $lang )
```

#### 7.3 HK não aparece como botão dentro da agenda
Não há botão HK na agenda para verificar comportamento PT/EN específico.

#### 7.4 Áudio não aparece na agenda
Não foi encontrado item/branch específico de áudio na agenda-drawer.

**Leitura objetiva:**  
Existe i18n geral da agenda e do título dos eventos, mas o requisito específico “PT/EN afeta HK e não áudio” não pode ser validado integralmente porque **não há botão HK nem modo áudio dentro da agenda** nos arquivos fornecidos.

**Classificação:** **PARCIAL**

---

### Item 8 — Agenda troca mídia do stage

**Evidências:**

#### 8.1 Evento da agenda emite evento customizado
Em `assets/js/VanaAgendaController.js`:
```js
emit( 'vana:agenda:event:click', {
    eventKey: eventKey,
    dayId: activeDay,
} );
```

#### 8.2 Agenda tenta acionar seletor de evento fora da gaveta
Ainda em `handleEventClick()`:
```js
if ( eventKey ) {
    const selectorBtn = document.querySelector(
        '[data-vana-event-key="' + CSS.escape( eventKey ) + '"]'
    );
    if ( selectorBtn ) {
        selectorBtn.click();
    }
}
```

#### 8.3 Fechamento após clique
```js
closeDrawer();
```

#### 8.4 Em `visit-scripts.php`, a troca de mídia do stage acontece por `schedule`, não por agenda drawer
A troca dinâmica de stage está implementada para:
```js
'.vana-schedule-item[data-vod-case="single"]'
'.vana-schedule-item[data-vod-case="multi"]'
'.vana-vod-accordion__btn'
```
e não há listener para:
- `vana:agenda:event:click`
- `.vana-agenda-event-btn`
- `[data-vana-event]`

**Leitura objetiva:**  
A gaveta da agenda tenta integrar-se indiretamente com um seletor externo via `[data-vana-event-key="..."]`, mas os arquivos fornecidos não mostram o destino desse clique nem um vínculo direto com o swap de mídia do stage. Portanto a troca de mídia existe apenas como integração indireta/potencial.

**Classificação:** **PARCIAL**

---

### Item 9 — Stage terminado avança para próximo evento

**Evidências:**

Nos arquivos inspecionados, **não foi encontrado**:

- listener de `ended` do player
- polling de estado do YouTube
- detecção de término de stage
- chamada para avançar automaticamente evento/agenda
- uso de `currentTime` + duração para avançar
- evento customizado como `stage:end`

Em `visit-scripts.php`, `swapStageYouTube()` só altera `iframe.src`:
```js
iframe.src = src;
```

Em `VanaAgendaController.js`, não há integração com ciclo de vida do player.

**Leitura objetiva:**  
Não há evidência de avanço automático para o próximo evento quando o stage termina.

**Classificação:** **AUSENTE**

---

### Item 10 — Integração Agenda -> Stage

**Evidências:**

#### 10.1 Estrutura de clique por `event_key`
Em `agenda-drawer.php`:
```php
data-vana-event="<?php echo esc_attr($event_key); ?>"
```

#### 10.2 Controller da agenda emite evento e tenta acionar seletor externo
Em `VanaAgendaController.js`:
```js
emit( 'vana:agenda:event:click', {
    eventKey: eventKey,
    dayId: activeDay,
} );
```
e:
```js
const selectorBtn = document.querySelector(
    '[data-vana-event-key="' + CSS.escape( eventKey ) + '"]'
);
if ( selectorBtn ) {
    selectorBtn.click();
}
```

#### 10.3 Resolver da página conhece `event_key`
Em `includes/class-visit-stage-resolver.php`:
```php
$requested_event_key = self::read_query_text( 'event_key' );
```

E usa isso em:
```php
VisitEventResolver::resolve(
    $timeline,
    $overrides,
    $requested_event_key,
    $requested_day,
    $visit_timezone
);
```

#### 10.4 EventResolver seleciona o dia correto pelo `event_key`
Em `includes/class-visit-event-resolver.php`:
```php
if ($requested_event_key) {
    foreach ($timeline['days'] ?? [] as $day) {
        foreach (self::dayEvents(is_array($day) ? $day : []) as $event) {
            if (($event['event_key'] ?? '') === $requested_event_key) {
                $active_day = $day;
                break 2;
            }
        }
    }
}
```

E o evento ativo:
```php
if ($requested_event_key) {
    foreach ($active_events as $event) {
        if (($event['event_key'] ?? '') === $requested_event_key) {
            $active_event = $event;
            break;
        }
    }
}
```

#### 10.5 Falta a prova do seletor-alvo
Nos arquivos lidos, não foi fornecido o componente que renderiza:
```html
[data-vana-event-key="..."]
```
Logo não é possível comprovar o encadeamento completo até o stage.

**Leitura objetiva:**  
A arquitetura aponta claramente para integração Agenda -> Stage por `event_key`, mas a prova final depende do seletor externo `[data-vana-event-key]`, que não está nos arquivos inspecionados.

**Classificação:** **PARCIAL**

---

### Item 11 — Integração Agenda -> HK

**Evidências:**

#### 11.1 A agenda não renderiza botão HK por evento
Em `agenda-drawer.php`, cada item só possui um botão genérico `.vana-agenda-event-btn`. Não há botão dedicado de HK.

#### 11.2 O controlador da agenda só trata `eventKey`
Em `VanaAgendaController.js`:
```js
emit( 'vana:agenda:event:click', {
    eventKey: eventKey,
    dayId: activeDay,
} );
```
Não há:
- `kathaId`
- `openHK`
- `drawer hk`
- dispatch de evento para HK

#### 11.3 O loader de HK em `visit-scripts.php` é independente da agenda
O HK é carregado por `visit_id` + `day`:
```js
'/kathas?visit_id=' + encodeURIComponent(state.visitId)
+ '&day=' + encodeURIComponent(state.activeDay);
```
Sem ponte explícita com clique da agenda.

**Leitura objetiva:**  
Não há integração explícita Agenda -> HK nos arquivos fornecidos.

**Classificação:** **AUSENTE**

---

## 5. Classificação por item

| Item auditado | Classificação |
|---|---|
| Estado padrão fechada | **IMPLEMENTADO** |
| Formas de acionamento | **IMPLEMENTADO** |
| Exceções de abertura automática | **AUSENTE** |
| Anatomia interna da agenda | **IMPLEMENTADO** |
| Estados de evento: passado, ativo, futuro, sem mídia | **PARCIAL** |
| Botões condicionais `[▶]`, `[📖 HK]`, `[🔔]` | **AUSENTE** |
| Idioma PT/EN afeta HK e não áudio | **PARCIAL** |
| Agenda troca mídia do stage | **PARCIAL** |
| Stage terminado avança para próximo evento | **AUSENTE** |
| Integração Agenda -> Stage | **PARCIAL** |
| Integração Agenda -> HK | **AUSENTE** |

---

## 6. Gaps

### Gap 1 — Não há exceções de abertura automática
O controlador só abre a agenda por clique em `[data-vana-agenda-open]`.  
Não há evidência de regras contratuais de auto-open.

### Gap 2 — Estados de evento estão incompletos
A UI da agenda só diferencia `status === 'live'` com:
```php
<span class="vana-agenda-event-badge" aria-label="ao vivo">🔴</span>
```
Não há modelagem explícita de:
- passado
- ativo
- futuro
- sem mídia

### Gap 3 — Botões condicionais contratuais não existem
Não foram encontrados na agenda:
- `[▶]`
- `[📖 HK]`
- `[🔔]`

### Gap 4 — Requisito de idioma específico para HK/áudio não pode ser fechado
Há i18n geral da agenda, mas:
- HK não é um botão da agenda
- áudio não é um tipo tratado explicitamente
- portanto a regra específica do contrato não está demonstrada

### Gap 5 — Troca de mídia do stage depende de integração indireta
A agenda faz:
```js
document.querySelector('[data-vana-event-key="..."]').click()
```
Mas o arquivo que define esse alvo não foi fornecido neste escopo.  
Então a troca de mídia não está comprovada ponta a ponta.

### Gap 6 — Não existe avanço automático para próximo evento
Não há qualquer integração com lifecycle do player para detectar fim do stage e mover a agenda.

### Gap 7 — Integração Agenda -> HK está ausente
O fluxo de HK observado em `visit-scripts.php` é independente da agenda-drawer.

---

## 7. Observação sobre aderência da agenda ao contrato

A agenda atual está **bem encaminhada na camada estrutural e de acessibilidade básica**, com:

- gaveta fechada por padrão
- abertura/fechamento corretos
- trap de foco
- tabs de dias
- eventos por dia
- emissão de eventos customizados
- tentativa de integração por `event_key`

Porém, **a aderência ao Contrato 6.0 ainda é parcial**, porque os requisitos mais funcionais da agenda não estão completos neste recorte:

- estados ricos dos eventos
- botões condicionais por item
- integração robusta com Stage
- integração com HK
- auto-open por exceção
- avanço automático para próximo evento

Em termos objetivos: a agenda está **implementada como gaveta navegável SSR-first**, mas **não ainda como orquestradora contratual completa de mídia + HK + estados de sessão**.

---

## 8. Próximo passo recomendado

1. **Fechar o contrato de interação Agenda -> Stage**
   - garantir e documentar o alvo `[data-vana-event-key]`
   - comprovar troca de stage por `event_key`

2. **Modelar estados de evento na agenda**
   - passado
   - ativo
   - futuro
   - sem mídia

3. **Adicionar botões condicionais por evento**
   - `[▶]` quando houver mídia tocável
   - `[📖 HK]` quando houver Hari-Katha relacionado
   - `[🔔]` quando houver ação de lembrete/notificação

4. **Definir política de abertura automática**
   - se houver exceções contratuais, elas precisam aparecer explicitamente no controller

5. **Conectar agenda ao ciclo de vida do stage**
   - detectar fim de reprodução
   - decidir se deve avançar para próximo evento
   - manter agenda e stage sincronizados

6. **Fechar integração Agenda -> HK**
   - ou por botão dedicado dentro da agenda
   - ou por fluxo explícito baseado em `event_key` / `day`

7. **Formalizar o papel do idioma na agenda**
   - especificar o que muda em HK
   - especificar o que não muda em áudio
   - refletir isso nos seletores e labels da UI
```