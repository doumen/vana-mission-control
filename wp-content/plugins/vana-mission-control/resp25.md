## Perfeito. Vamos de **Opção 2**: checklist de correção do template Hari-kathā.

O diagnóstico já está sólido:

- o endpoint REST funciona;
- o JS do loader existe;
- **o elemento `#vana-section-hari-katha` não está no DOM final**;
- quem está renderizando hoje é `templates/visit/parts/sections.php`, não o partial `parts/hari-katha.php`.

---

# Causa raiz

Você tem **duas implementações concorrentes** da seção Hari-kathā:

## Implementação antiga/esperada pelo JS
Arquivo:

```bash
templates/visit/parts/hari-katha.php
```

Ele contém o root:

```php
id="vana-section-hari-katha"
```

## Implementação atual visível na página
Arquivo:

```bash
templates/visit/parts/sections.php
```

Esse está renderizando:

- `sections.hari_katha`
- `sections.empty`
- `sections.gallery`
- `sections.sangha`

Mas **sem** o root esperado pelo JS.

---

# O que corrigir

## Objetivo
Fazer a seção Hari-kathā renderizada em `sections.php` incluir o markup mínimo que o loader precisa.

---

# Markup mínimo obrigatório

A seção precisa conter algo como:

```php
<section
  id="vana-section-hari-katha"
  class="vana-scroll-target"
  data-visit-id="<?php echo esc_attr( $visit_id ); ?>"
  data-day="<?php echo esc_attr( $day ); ?>"
  data-lang="<?php echo esc_attr( $lang ); ?>"
>
  <p class="vana-hk__intro"><?php echo esc_html( $loading_text ); ?></p>
  <div data-role="katha-list"></div>
  <div data-role="passage-list" hidden></div>
</section>
```

Sem isso, o JS nunca vai funcionar.

---

# Checklist técnico exato

## 1. Abrir o arquivo atual que está renderizando a tela
Arquivo:

```bash
/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/parts/sections.php
```

---

## 2. Localizar o bloco Hari-kathā
Pelo grep, ele começa perto da linha 47.

Procure por algo assim:

```php
<h3 class="vana-section-title">🙏 <?php echo esc_html( vana_t( 'sections.hari_katha', $lang ) ?: 'Hari-Katha' ); ?></h3>
```

---

## 3. Substituir o conteúdo interno da seção Hari-kathā
Hoje ele provavelmente faz fallback server-side com `sections.empty`.

Você deve trocar esse miolo pelo container que o JS espera.

---

# Versão sugerida do bloco Hari-kathā

> **Adapte os nomes das variáveis se no arquivo estiverem diferentes**  
> talvez seja `$visit`, `$visit_id`, `$current_day`, `$day_date`, etc.

```php
<section class="vana-section-block">
  <h3 class="vana-section-title">
    🙏 <?php echo esc_html( vana_t( 'sections.hari_katha', $lang ) ?: 'Hari-Katha' ); ?>
  </h3>

  <div
    id="vana-section-hari-katha"
    class="vana-scroll-target vana-hk"
    data-visit-id="<?php echo esc_attr( $visit_id ); ?>"
    data-day="<?php echo esc_attr( $day ); ?>"
    data-lang="<?php echo esc_attr( $lang ); ?>"
  >
    <p class="vana-hk__intro">
      <?php echo esc_html( $lang === 'en' ? 'Loading…' : 'Carregando…' ); ?>
    </p>

    <div data-role="katha-list"></div>
    <div data-role="passage-list" hidden></div>
  </div>
</section>
```

---

# Atenção crítica: nomes das variáveis

Esse é o ponto mais importante.

No seu backend, o endpoint funcionou com:

- `visit_id = 359`
- `day = 2026-02-14`

Então o HTML precisa receber exatamente isso.

## Se em `sections.php` você tiver outras variáveis
Verifique quais existem no escopo. Por exemplo:

- `$visit_id`
- `$visit->ID`
- `$visit->id`
- `$current_day`
- `$day`
- `$date`
- `$active_day`

Você precisa preencher assim:

### `data-visit-id`
Tem que ser o **ID numérico** `359`

### `data-day`
Tem que ser a data no formato:

```text
2026-02-14
```

Não pode ser:
- slug,
- label,
- “14 Feb 2026”,
- nem `v_day`.

---

# Como descobrir as variáveis corretas no PHP

No arquivo `sections.php`, procure por variáveis já usadas no bloco de gallery/sangha.

Se estiver em dúvida, temporariamente logue:

```php
<?php
error_log('VANA sections vars: ' . print_r([
  'visit_id' => $visit_id ?? null,
  'day'      => $day ?? null,
  'lang'     => $lang ?? null,
], true));
?>
```

Ou até renderize temporariamente no HTML:

```php
<pre>
<?php var_dump($visit_id ?? null, $day ?? null, $lang ?? null); ?>
</pre>
```

Depois remova.

---

# Melhor opção estrutural

## Em vez de duplicar markup dentro de `sections.php`
O ideal é **reusar o partial** já existente:

```bash
templates/visit/parts/hari-katha.php
```

Se esse partial já está pronto, então em `sections.php` você pode trocar o bloco por algo como:

```php
<?php include __DIR__ . '/hari-katha.php'; ?>
```

ou, se o plugin usa helper próprio:

```php
<?php vana_render_template_part( 'visit/parts/hari-katha', [
  'visit_id' => $visit_id,
  'day'      => $day,
  'lang'     => $lang,
] ); ?>
```

Depende de como seu plugin organiza includes.

### Vantagem
- evita duplicação;
- mantém o JS alinhado com o HTML esperado;
- reduz chance de divergência futura.

---

# Sobre os textos `sections.hari_katha` aparecendo crus

Isso mostra que `vana_t()` está retornando a própria chave.

Exemplo:

```php
vana_t( 'sections.hari_katha', $lang )
```

retorna:

```text
sections.hari_katha
```

e como string não vazia, o fallback `?: 'Hari-Katha'` **nunca entra**.

---

## Correção segura para isso

Troque:

```php
<?php echo esc_html( vana_t( 'sections.hari_katha', $lang ) ?: 'Hari-Katha' ); ?>
```

por algo assim:

```php
<?php
$hk_title = vana_t( 'sections.hari_katha', $lang );
if ( ! $hk_title || $hk_title === 'sections.hari_katha' ) {
    $hk_title = ( $lang === 'en' ) ? 'Hari-Katha' : 'Hari-kathā';
}
echo esc_html( $hk_title );
?>
```

E para `sections.empty`:

```php
<?php
$empty_text = vana_t( 'sections.empty', $lang );
if ( ! $empty_text || $empty_text === 'sections.empty' ) {
    $empty_text = ( $lang === 'en' ) ? 'No content for this day' : 'Sem conteúdo para este dia';
}
echo esc_html( $empty_text );
?>
```

---

# O que fazer agora, passo a passo

## Passo 1
Abra:

```bash
nano /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/parts/sections.php
```

## Passo 2
No bloco Hari-kathā, substitua o conteúdo atual pelo container mínimo:

```php
<div
  id="vana-section-hari-katha"
  class="vana-scroll-target vana-hk"
  data-visit-id="<?php echo esc_attr( $visit_id ); ?>"
  data-day="<?php echo esc_attr( $day ); ?>"
  data-lang="<?php echo esc_attr( $lang ); ?>"
>
  <p class="vana-hk__intro">
    <?php echo esc_html( $lang === 'en' ? 'Loading…' : 'Carregando…' ); ?>
  </p>

  <div data-role="katha-list"></div>
  <div data-role="passage-list" hidden></div>
</div>
```

## Passo 3
Corrija a tradução do título para não mostrar a chave crua.

## Passo 4
Salve e recarregue a página.

## Passo 5
No console, valide:

```javascript
document.getElementById('vana-section-hari-katha')
```

Agora deve retornar o elemento.

## Passo 6
Valide os atributos:

```javascript
(function () {
  var el = document.getElementById('vana-section-hari-katha');
  return {
    visitId: el && el.getAttribute('data-visit-id'),
    day: el && el.getAttribute('data-day'),
    lang: el && el.getAttribute('data-lang')
  };
}())
```

Deve retornar algo como:

```javascript
{ visitId: "359", day: "2026-02-14", lang: "pt" }
```

## Passo 7
Na aba Network, agora deve aparecer:

```text
/wp-json/vana/v1/kathas?visit_id=359&day=2026-02-14
```

---

# Sobre a gaveta

Ainda falta o retorno do AJAX. Mas como você pediu Opção 2, foque primeiro no Hari-kathā.

Depois disso, o próximo teste é este:

```javascript
fetch(window.vanaDrawer.ajaxUrl, {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    action: 'vana_get_tours',
    visit_id: window.vanaDrawer.visitId,
    _wpnonce: window.vanaDrawer.nonce
  })
}).then(r => r.text()).then(console.log)
```

Se quiser, no próximo passo eu posso te entregar **o bloco pronto de `sections.php` já reescrito**, para colar direto, em vez de só o checklist.