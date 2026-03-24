# Encontrado! O Problema é o `DOMContentLoaded` 🎯

```text
drawer.classList.add('is-open') → 'vana-drawer is-open' ✅
O CSS funciona. O drawer ABRE na força bruta.

CONFIRMADO: O listener nunca foi registrado porque
o (function(){ ... }()) do drawer executa ANTES
dos elementos existirem no DOM.
```

---

## O Fix — 2 Linhas

**Instrua o agente:**

```text
ABRIR: visit-scripts.php

LOCALIZAR esta linha exata (início do bloco do drawer):
  (function () {
    'use strict';
    // ... código do drawer ...

SUBSTITUIR o início por:
  (function () {
    'use strict';

    function initDrawer() {
```

Depois, no **final** desse mesmo bloco, **antes** do `}());` final:

```text
LOCALIZAR o fechamento da IIFE do drawer:
  }());

SUBSTITUIR por:
    }  // ← fecha initDrawer()

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initDrawer);
    } else {
      initDrawer();
    }

  }());
```

---

## Resultado Final do Arquivo

```javascript
// ← IIFE principal fecha aqui
}(<?php echo wp_json_encode($js_data); ?>));
</script>

<script>
(function () {
  'use strict';

  function initDrawer() {                          // ← ADICIONADO

    var drawer  = document.getElementById('vana-tour-drawer');
    var overlay = document.getElementById('vana-drawer-overlay');
    var btn     = document.querySelector('[data-drawer="vana-tour-drawer"]');
    // ... todo o código do drawer ...
    // ... btn.addEventListener('click', ...) ...
    // ... loadDrawerTours() ...

  }                                                // ← ADICIONADO

  if (document.readyState === 'loading') {         // ← ADICIONADO
    document.addEventListener('DOMContentLoaded', initDrawer);
  } else {                                         // ← ADICIONADO
    initDrawer();                                  // ← ADICIONADO
  }                                                // ← ADICIONADO

}());
</script>
```

---

## Após o Fix, Validar no Console

```javascript
// Deve retornar 'function' após o fix
typeof window.__vanaDrawerSelectTour;

// E o click deve funcionar nativamente
document.querySelector('[data-drawer="vana-tour-drawer"]').click();
```

**Aplica o fix e me passa o resultado!** 🙏