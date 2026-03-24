```markdown
# docs/auditoria-contrato-6/01-hero-e-day-selector.md

## 1. Objetivo da auditoria

Auditar **apenas o bloco HERO e SELETOR DE DIA da página de visita** do plugin `vana-mission-control`, comparando o código atual com o **Contrato 6.0**, sem aplicar patch e sem alterar código.

O escopo validado nesta auditoria é:

- Hero com nome da visita
- período `start_date -> end_date`
- contador da visita na tour quando houver tour
- prev/next entre visitas
- prev/next escopado por `_vana_tour_id` quando houver tour
- fallback cronológico global quando não houver tour
- gaveta com cenário com-tour e sem-tour
- seletor de dia
- seletor some quando `days === 1`
- trocar dia recarrega agenda
- trocar dia **não** troca stage automaticamente

---

## 2. Arquivos inspecionados

Foram inspecionados somente os arquivos solicitados:

1. `templates/single-vana_visit.php`
2. `templates/visit/visit-template.php`
3. `templates/visit/_bootstrap.php`
4. `templates/visit/parts/hero-header.php`
5. `templates/visit/parts/day-tabs.php`
6. `templates/visit/assets/visit-scripts.php`
7. `assets/js/VanaVisitController.js`

---

## 3. Itens auditados

1. Hero com nome da visita  
2. Hero com período `start_date -> end_date`  
3. Contador da visita na tour quando houver tour  
4. Prev/next entre visitas  
5. Prev/next escopado por `_vana_tour_id` quando houver tour  
6. Fallback cronológico global quando não houver tour  
7. Gaveta com cenário com-tour e sem-tour  
8. Seletor de dia presente  
9. Seletor some quando `days === 1`  
10. Trocar dia recarrega agenda  
11. Trocar dia não troca stage automaticamente  

---

## 4. Evidências por item

### Item 1 — Hero com nome da visita

**Evidências:**

- Em `templates/visit/parts/hero-header.php`:
  ```php
  $title = $data['title_' . $lang] ?? $data['title_pt'] ?? get_the_title();
  ```
- O título é renderizado no header contextual:
  ```php
  <span class="vana-header__title"><?php echo esc_html($title); ?></span>
  ```
- O mesmo título é renderizado no hero principal:
  ```php
  <h1 class="vana-hero__title"><?php echo esc_html($title); ?></h1>
  ```

**Leitura objetiva:**  
O nome da visita é obtido do timeline (`title_pt` / `title_en`) com fallback para `get_the_title()` e aparece em dois pontos visuais do HERO.

**Classificação:** **IMPLEMENTADO**

---

### Item 2 — Hero com período `start_date -> end_date`

**Evidências:**

- Em `templates/single-vana_visit.php`, há leitura de:
  ```php
  $start_date = (string) get_post_meta( $post_id, '_vana_start_date', true );
  ```
- Em `templates/visit/_bootstrap.php`, o comentário de variáveis disponíveis não expõe `end_date`.
- Em `templates/visit/parts/hero-header.php`, não há leitura nem renderização de:
  - `_vana_start_date`
  - `_vana_end_date`
  - `start_date`
  - `end_date`
  - período formatado

- Não há trecho visual equivalente a algo como:
  ```php
  echo $start_date . ' → ' . $end_date;
  ```

**Leitura objetiva:**  
O `start_date` existe no template principal e é usado em metadata/Schema.org, mas **não é exibido no HERO**. Não foi encontrada leitura nem renderização de `end_date` nos arquivos inspecionados.

**Classificação:** **AUSENTE**

---

### Item 3 — Contador da visita na tour quando houver tour

**Evidências:**

- Em `templates/visit/_bootstrap.php`, o contexto da tour é resolvido:
  ```php
  $tour_id = (int) wp_get_post_parent_id( $visit_id );
  if ( ! $tour_id ) {
      $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
  }
  ```
- Em `templates/visit/parts/hero-header.php`, o contador é montado quando existe `$tour_id`:
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
- O rótulo é construído:
  ```php
  $tour_counter_label = sprintf(
      esc_html__('Visita %d de %d', 'vana'),
      $pos_number,
      $total
  );
  ```
- E exibido no header:
  ```php
  <?php if ($tour_counter_label): ?>
      <span class="vana-header__tour-counter"><?php echo esc_html($tour_counter_label); ?></span>
  <?php endif; ?>
  ```

**Leitura objetiva:**  
Há contador contextual de posição da visita dentro da tour, condicionado à existência de `$tour_id`.

**Classificação:** **IMPLEMENTADO**

---

### Item 4 — Prev/next entre visitas

**Evidências:**

- Em `templates/visit/_bootstrap.php`, a navegação é resolvida:
  ```php
  [ $prev_id, $next_id ] = vana_visit_prev_next_ids( $visit_id, $tour_id );
  $prev_visit = _vana_build_nav_visit( $prev_id, $lang );
  $next_visit = _vana_build_nav_visit( $next_id, $lang );
  ```
- Em `templates/visit/parts/hero-header.php`, há renderização dos links prev/next:
  ```php
  <?php if ($prev_visit): ?>
      <a
          href="<?php echo esc_url($prev_visit['permalink']); ?>"
          class="vana-hero__nav-btn vana-hero__nav-btn--prev"
          data-vana-prev-visit
          data-vana-visit-url="<?php echo esc_url($prev_visit['permalink']); ?>"
          rel="prev"
      >
  ```
  ```php
  <?php if ($next_visit): ?>
      <a
          href="<?php echo esc_url($next_visit['permalink']); ?>"
          class="vana-hero__nav-btn vana-hero__nav-btn--next"
          data-vana-next-visit
          data-vana-visit-url="<?php echo esc_url($next_visit['permalink']); ?>"
          rel="next"
      >
  ```
- Em `assets/js/VanaVisitController.js`, os botões são ligados:
  ```js
  const prevBtn   = document.querySelector( '[data-vana-prev-visit]' );
  const nextBtn   = document.querySelector( '[data-vana-next-visit]' );
  ```
  ```js
  bindNavButton( prevBtn, prevUrl );
  bindNavButton( nextBtn, nextUrl );
  ```

**Leitura objetiva:**  
Existe navegação prev/next funcional no HERO, com SSR e camada JS para transição/prefetch.

**Classificação:** **IMPLEMENTADO**

---

### Item 5 — Prev/next escopado por `_vana_tour_id` quando houver tour

**Evidências:**

- Em `templates/visit/_bootstrap.php`, a própria função documenta o contrário:
  ```php
  // DT-004: Navegação é sempre cronológica global.
  // Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
  // Tour é contexto para o hero, nunca fronteira de navegação.
  ```
- Na função:
  ```php
  function vana_visit_prev_next_ids( int $current_id, int $tour_id = 0 ): array {
  ```
  o parâmetro `$tour_id` **não é usado** para filtrar a query.
- Fallback por query usa somente `_vana_start_date`:
  ```php
  'meta_query' => [ [ 'key' => '_vana_start_date', 'value' => $start, 'compare' => '<', 'type' => 'DATE' ] ],
  ```
  e
  ```php
  'meta_query' => [ [ 'key' => '_vana_start_date', 'value' => $start, 'compare' => '>', 'type' => 'DATE' ] ],
  ```
- Em `assets/js/VanaVisitController.js`, o comentário reforça:
  ```js
  * DT-004: tour_id é APENAS contexto visual (passthrough na URL).
  *         Nunca é filtro de navegação.
  ```

**Leitura objetiva:**  
O código atual **não** implementa prev/next limitado à mesma tour. O contrato solicitado pede escopo por `_vana_tour_id` quando houver tour, mas o código atual explicitamente adota navegação cronológica global.

**Classificação:** **DIVERGENTE**

---

### Item 6 — Fallback cronológico global quando não houver tour

**Evidências:**

- Em `templates/visit/_bootstrap.php`, a função baseia-se em cronologia global:
  ```php
  if ( function_exists( 'vana_get_chronological_visits' ) ) {
      $sequence = vana_get_chronological_visits();
  ```
- Se a sequência não existir, usa `_vana_start_date` global:
  ```php
  $start = get_post_meta( $current_id, '_vana_start_date', true );
  ```
  com queries anteriores/posteriores de `vana_visit`, sem filtro de tour:
  ```php
  'post_type'      => 'vana_visit',
  'post_status'    => 'publish',
  'meta_key'       => '_vana_start_date',
  ```
- A implementação atual já é global por padrão.

**Leitura objetiva:**  
O fallback global existe, mas ele não está condicionado apenas ao cenário “sem tour”; ele é a regra geral atual.

**Classificação:** **PARCIAL**

**Motivo da parcial:**  
O comportamento global existe, porém não como fallback após tentativa por tour; ele substitui completamente a navegação por tour.

---

### Item 7 — Gaveta com cenário com-tour e sem-tour

**Evidências:**

- Em `templates/visit/parts/hero-header.php`, o botão da gaveta existe:
  ```php
  <button
      class="vana-header__tours-btn"
      data-drawer="vana-tour-drawer"
      aria-controls="vana-tour-drawer"
  >
  ```
- Em `templates/visit/parts/hero-header.php`, a partial é requerida:
  ```php
  <?php require VANA_MC_PATH . 'templates/visit/parts/tour-drawer.php'; ?>
  ```
- Em `templates/visit/assets/visit-scripts.php`, os dados da gaveta incluem tour opcional:
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
- O JS carrega **todas as tours** ao abrir:
  ```js
  body: new URLSearchParams({
    action: 'vana_get_tours',
    visit_id: visitId,
    _wpnonce: nonce
  })
  ```
- Depois carrega visitas de uma tour escolhida:
  ```js
  body: new URLSearchParams({
    action: 'vana_get_tour_visits',
    tour_id: tourId,
    visit_id: visitId,
    lang: window.vanaDrawer ? window.vanaDrawer.lang : 'pt',
    _wpnonce: nonce
  })
  ```

**Leitura objetiva:**  
Há infraestrutura de gaveta e há transporte de contexto “com tour” (`tourId`, `tourTitle`, `tourUrl`) e “sem tour” (`null`). Porém o arquivo `tour-drawer.php` **não foi fornecido**, então a estrutura HTML final e o comportamento visual completo da gaveta não podem ser validados integralmente a partir dos arquivos inspecionados.

**Classificação:** **PARCIAL**

---

### Item 8 — Seletor de dia presente

**Evidências:**

Existem **dois mecanismos visíveis** relacionados a dia:

#### 8.1 Day tabs dedicadas
- Em `templates/visit/visit-template.php`:
  ```php
  if ( count( (array) $timeline['days'] ) > 1 ) {
      if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/day-tabs.php' ) ) {
          include VANA_MC_PATH . 'templates/visit/parts/day-tabs.php';
      }
  }
  ```
- Em `templates/visit/parts/day-tabs.php`, renderiza:
  ```php
  <nav class="vana-tabs" role="tablist" ...>
  ```
  com links por dia:
  ```php
  <a href="<?php echo esc_url($tab_url); ?>" class="vana-tab ... " role="tab">
  ```

#### 8.2 Day selector embutido no hero
- Em `templates/visit/parts/hero-header.php`:
  ```php
  <nav class="vana-hero__day-selector" aria-label="<?php echo esc_attr('Seletor de dias'); ?>">
  ```
- Renderiza botões:
  ```php
  <button
      class="vana-hero__day-btn ..."
      data-day-date="<?php echo esc_attr($day_date); ?>"
  >
  ```

**Leitura objetiva:**  
O seletor de dia existe no código atual, inclusive em duplicidade de conceitos: `day-tabs.php` e `vana-hero__day-selector` em `hero-header.php`.

**Classificação:** **IMPLEMENTADO**

---

### Item 9 — Seletor some quando `days === 1`

**Evidências:**

- Em `templates/visit/visit-template.php`, `day-tabs.php` só é incluído quando houver mais de 1 dia:
  ```php
  if ( count( (array) $timeline['days'] ) > 1 ) {
  ```
- Em `templates/visit/parts/day-tabs.php`, há guarda adicional:
  ```php
  if (empty($days) || count($days) <= 1) return;
  ```
- Em `templates/visit/parts/hero-header.php`, o seletor do hero também é condicionado:
  ```php
  $days_count = count($data['days'] ?? []);
  if ($days_count > 1):
  ```

**Leitura objetiva:**  
Tanto as abas de dia quanto o seletor embutido no hero desaparecem quando há apenas 1 dia.

**Classificação:** **IMPLEMENTADO**

---

### Item 10 — Trocar dia recarrega agenda

**Evidências:**

- Em `templates/visit/parts/day-tabs.php`, cada dia aponta para URL da visita com query `day`:
  ```php
  $tab_url = vana_visit_url($visit_id, $date_local, -1, $lang);
  ```
- A função fallback monta:
  ```php
  if ( $v_day ) $url = add_query_arg( 'day', $v_day, $url );
  ```
- Como o seletor usa `<a href="...">`, a troca de dia provoca navegação/reload da página.
- Em `templates/visit/_bootstrap.php`, o contexto do dia ativo é resolvido pelo `VisitStageResolver::resolve( $visit_id )` e exposto em variáveis como:
  ```php
  $active_day, $active_day_date, $active_events, $active_event
  ```
- Em `templates/visit/visit-template.php`, a agenda e seções subsequentes são renderizadas com base nesse contexto já resolvido.

**Leitura objetiva:**  
O mecanismo de troca por `day-tabs.php` recarrega a página via URL com query `day`, o que reprocessa o contexto SSR da visita e, por consequência, recarrega agenda/conteúdo do dia.

**Classificação:** **IMPLEMENTADO**

---

### Item 11 — Trocar dia não troca stage automaticamente

**Evidências:**

#### No `day-tabs.php`
- O link de dia é montado com:
  ```php
  $tab_url = vana_visit_url($visit_id, $date_local, -1, $lang);
  ```
- A função fallback só adiciona `vod` se `>= 0`:
  ```php
  if ( $vod >= 0 ) $url = add_query_arg( 'vod', $vod, $url );
  ```
- Como o parâmetro passado é `-1`, o clique de trocar dia **não força `vod` específico**.

#### No `single-vana_visit.php`
- O stage/VOD ativo depende de:
  ```php
  $active_vod_index = max(
      0,
      (int) sanitize_text_field( wp_unslash( $_GET['vod'] ?? '0' ) )
  );
  ```
- Sem `vod` na URL, o índice cai para `0`.

#### No `hero-header.php`
- O seletor embutido no hero usa `<button data-day-date="...">`, mas nos arquivos inspecionados **não há JS que ligue esses botões a navegação**.

#### No `visit-scripts.php`
- Não foi encontrado listener para:
  - `.vana-hero__day-btn`
  - `[data-day-date]`
- Logo, o seletor embutido no hero aparenta não ter comportamento implementado neste recorte.

**Leitura objetiva:**  
Para `day-tabs.php`, a troca de dia não passa `vod`, então **não preserva nem comanda troca explícita de stage**, deixando o SSR recalcular o estado do dia e o VOD padrão voltar ao índice `0`.  
Isso sugere que não há “troca automática para um VOD específico ao mudar o dia”, mas também significa que o stage **pode mudar indiretamente** para o VOD padrão do novo dia após reload.  
Já o seletor embutido do hero não tem comportamento JS comprovado neste escopo.

**Classificação:** **PARCIAL**

---

## 5. Classificação por item

| Item auditado | Classificação |
|---|---|
| Hero com nome da visita | **IMPLEMENTADO** |
| Hero com período `start_date -> end_date` | **AUSENTE** |
| Contador da visita na tour quando houver tour | **IMPLEMENTADO** |
| Prev/next entre visitas | **IMPLEMENTADO** |
| Prev/next escopado por `_vana_tour_id` quando houver tour | **DIVERGENTE** |
| Fallback cronológico global quando não houver tour | **PARCIAL** |
| Gaveta com cenário com-tour e sem-tour | **PARCIAL** |
| Seletor de dia | **IMPLEMENTADO** |
| Seletor some quando `days === 1` | **IMPLEMENTADO** |
| Trocar dia recarrega agenda | **IMPLEMENTADO** |
| Trocar dia NÃO troca stage automaticamente | **PARCIAL** |

---

## 6. Gaps encontrados

### Gap 1 — Período da visita não aparece no HERO
- Há leitura de `_vana_start_date` em `templates/single-vana_visit.php`.
- Não foi encontrada leitura/renderização de `_vana_end_date`.
- O HERO atual não exibe intervalo `start_date -> end_date`.

### Gap 2 — Prev/next por tour não está aderente ao contrato
- `templates/visit/_bootstrap.php` declara explicitamente:
  ```php
  // DT-004: Navegação é sempre cronológica global.
  // Tour é contexto para o hero, nunca fronteira de navegação.
  ```
- Isso contradiz diretamente a exigência contratual de escopo por `_vana_tour_id` quando houver tour.

### Gap 3 — Fallback global existe, mas não como fallback real
- O comportamento global está presente.
- Porém ele não entra “apenas quando não houver tour”; ele já é a regra universal atual.

### Gap 4 — Gaveta não pôde ser validada por completo
- O arquivo `templates/visit/parts/tour-drawer.php` não foi incluído no material fornecido.
- O JS mostra fluxo de carregamento de tours/visitas, mas o cenário visual e estrutural “com-tour e sem-tour” não pode ser fechado integralmente apenas com os arquivos recebidos.

### Gap 5 — Duplicidade de mecanismos de seleção de dia
- Existe `templates/visit/parts/day-tabs.php` com navegação real por URL.
- Existe também `vana-hero__day-selector` em `hero-header.php`, com botões:
  ```php
  <button class="vana-hero__day-btn" data-day-date="...">
  ```
- Nos arquivos inspecionados, não há JS que conecte esses botões a reload/navegação.
- Isso indica potencial divergência entre UI renderizada e comportamento efetivo.

### Gap 6 — Regra “trocar dia não troca stage automaticamente” não está garantida de forma forte
- `day-tabs.php` não envia `vod`, o que evita comando explícito de troca.
- Mas o reload SSR com novo dia pode redefinir o stage para o VOD padrão do dia.
- Portanto a não-troca automática não está plenamente garantida no sentido contratual mais estrito.

---

## 7. Status do risco R2 (prev/next por tour_id)

**R2: `prev/next` por `tour_id` está `PENDENTE`**

### Evidências
- Em `templates/visit/_bootstrap.php`:
  ```php
  function vana_visit_prev_next_ids( int $current_id, int $tour_id = 0 ): array {
      // DT-004: Navegação é sempre cronológica global.
      // Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
  }
  ```
- Em `assets/js/VanaVisitController.js`:
  ```js
  * DT-004: tour_id é APENAS contexto visual (passthrough na URL).
  *         Nunca é filtro de navegação.
  ```

### Conclusão objetiva
O risco não está resolvido nem parcialmente mitigado no sentido do contrato 6.0.  
O comportamento atual está **em divergência explícita** com a exigência de navegação por `_vana_tour_id` quando houver tour.

---

## 8. Próximo passo recomendado para este bloco

1. **Fechar a regra contratual do prev/next**
   - Definir a precedência:
     - se houver `_vana_tour_id`, navegar dentro da tour;
     - se não houver, usar fallback cronológico global.
   - Hoje esse ponto está em divergência explícita.

2. **Adicionar o período da visita ao HERO**
   - Expor e renderizar `start_date` e `end_date` no bloco hero/header.

3. **Unificar o seletor de dia**
   - Escolher entre:
     - `day-tabs.php` como navegação oficial,
     - ou `vana-hero__day-selector` como navegação oficial.
   - No estado atual, há duplicidade de UI e ausência de evidência de comportamento para os botões do hero.

4. **Especificar a regra do stage ao trocar dia**
   - Definir tecnicamente se:
     - o stage deve permanecer no estado anterior,
     - ou deve resetar para o VOD default do novo dia.
   - O contrato pede que a troca de dia não troque stage automaticamente; isso precisa ficar verificável no código.

5. **Auditar a gaveta com o arquivo estrutural**
   - Para fechar o item “gaveta com cenário com-tour e sem-tour”, é necessário inspecionar também `templates/visit/parts/tour-drawer.php`.
```