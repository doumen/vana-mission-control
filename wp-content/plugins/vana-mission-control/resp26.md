## Sim — essa é a direção correta.

Pelo que você trouxe, o diagnóstico refinado é:

- **não é necessário reintroduzir o partial antigo** se `sections.php` é a arquitetura nova;
- o problema é que o **loader JS ficou acoplado ao markup antigo**;
- então o ajuste mais limpo agora é **adaptar o loader JS ao painel atual `#vana-section-hk`**.

---

# Recomendação

## Melhor caminho
**Adaptar o loader JS para suportar o markup atual de `sections.php` e manter retrocompatibilidade com `hari-katha.php`.**

Assim você evita:

- duplicação de template;
- regressão visual;
- manter duas árvores HTML diferentes para o mesmo recurso.

---

# Estratégia correta

O loader deve procurar nesta ordem:

## Root candidates
1. `#vana-section-hari-katha` — legado
2. `#vana-section-hk` — novo painel

E também deve aceitar múltiplos nomes de atributos:

### Visit ID
- `data-visit-id`

### Day
- `data-day`
- `data-v-day` se existir
- ou outro atributo usado no `sections.php`

### Lang
- `data-lang`

---

# O que mudar no JS

No `visit-scripts.php`, no módulo Hari-kathā, ajuste o `init()` para algo nesta linha:

```javascript
function init() {
  root =
    document.getElementById('vana-section-hari-katha') ||
    document.getElementById('vana-section-hk');

  if (!root) return;

  introEl =
    root.querySelector('.vana-hk__intro') ||
    root.querySelector('[data-role="hk-intro"]') ||
    root.querySelector('.vana-section-empty');

  listEl =
    root.querySelector('[data-role="katha-list"]') ||
    root.querySelector('.vana-hk__list') ||
    root.querySelector('[data-role="hk-list"]');

  passagesEl =
    root.querySelector('[data-role="passage-list"]') ||
    root.querySelector('.vana-hk__passages') ||
    root.querySelector('[data-role="hk-passages"]');

  state.visitId =
    root.getAttribute('data-visit-id') ||
    root.getAttribute('data-visit') ||
    '';

  state.activeDay =
    root.getAttribute('data-day') ||
    root.getAttribute('data-v-day') ||
    '';

  state.lang =
    root.getAttribute('data-lang') ||
    document.documentElement.getAttribute('lang') ||
    'pt';

  if (!state.visitId || !state.activeDay) return;

  fetchKathas();
}
```

---

# Mas há um detalhe importante

## Só adaptar o selector não basta
Se `sections.php` usa estrutura diferente, o loader também precisa saber **onde renderizar**:

- lista de kathas;
- lista de passagens;
- intro/empty state.

Então o ideal é alinhar o HTML novo com hooks estáveis.

---

# Melhor prática no template novo

No `sections.php`, dentro do painel `#vana-section-hk`, inclua alvos explícitos:

```php
<div
  id="vana-section-hk"
  data-visit-id="<?php echo esc_attr( $visit_id ); ?>"
  data-day="<?php echo esc_attr( $day ); ?>"
  data-lang="<?php echo esc_attr( $lang ); ?>"
>
  <p class="vana-hk__intro" data-role="hk-intro">
    <?php echo esc_html( $lang === 'en' ? 'Loading…' : 'Carregando…' ); ?>
  </p>

  <div class="vana-hk__list" data-role="katha-list"></div>
  <div class="vana-hk__passages" data-role="passage-list" hidden></div>
</div>
```

---

# Solução ideal: híbrida

## Faça os dois:
### 1. Adaptar o JS
Para aceitar:
- `#vana-section-hari-katha`
- `#vana-section-hk`

### 2. Padronizar o HTML novo
Adicionar em `sections.php`:
- `data-visit-id`
- `data-day`
- `data-lang`
- `data-role="katha-list"`
- `data-role="passage-list"`

Isso deixa o sistema resiliente.

---

# Por que essa opção é melhor do que re-incluir o partial antigo?

Porque re-incluir o `hari-katha.php`:
- pode duplicar conteúdo;
- pode conflitar com a navegação/anchors nova;
- pode te prender num markup legado.

Adaptar o loader ao painel atual respeita a evolução do template.

---

# Minha recomendação objetiva

## Faça agora:
### Em `sections.php`
Garanta que `#vana-section-hk` tenha:

```php
data-visit-id="359"
data-day="2026-02-14"
data-lang="pt"
```

e placeholders com `data-role`.

### Em `visit-scripts.php`
Troque a busca rígida:

```javascript
document.getElementById('vana-section-hari-katha')
```

por fallback duplo:

```javascript
document.getElementById('vana-section-hari-katha') ||
document.getElementById('vana-section-hk')
```

---

# Resposta direta à sua pergunta

## Sim:
**adapte o loader JS para funcionar com o painel atual `#vana-section-hk`**  
e, ao mesmo tempo, **alinhe o HTML do `sections.php` com os data-attributes esperados**.

---

# Próximo passo que eu posso fazer agora

Posso te entregar uma destas duas opções:

## Opção A — patch JS
Um bloco pronto para substituir o `init()` e a captura de elementos no `visit-scripts.php`.

## Opção B — patch completo
Um patch com:
- ajuste no `sections.php`
- ajuste no `visit-scripts.php`

### Minha recomendação:
**B**.

Se quiser, eu já te devolvo o **patch completo pronto para colar**.