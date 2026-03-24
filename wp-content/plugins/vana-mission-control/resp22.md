# Achei o Bug! É um Erro de Escopo Clássico 🎯

Olhando o arquivo completo, encontrei **2 problemas simultâneos**:

---

## Problema 1 — `initDrawer()` nunca é chamada

```javascript
// O que está no arquivo agora:
(function() {
  function initDrawer() {
    // ... todo o código ...
  } // ← fecha initDrawer()

  // ⚠️ FALTA CHAMAR initDrawer() AQUI!

}());
```

---

## Problema 2 — `tourLoading`/`visitsLoading` no `finally` do Hari-Kathā

```javascript
// No bloco HARI-KATHĀ (fetchPassages), linha ~860:
.finally(function () {
  if (tourLoading) tourLoading.hidden = true;   // ← tourLoading é undefined aqui!
  if (visitsLoading) visitsLoading.hidden = true; // ← idem!
});
```

Essas variáveis pertencem ao escopo do `initDrawer()`, **não** ao Hari-Kathā Loader.

---

## O Fix Completo

### Fix 1 — Chamar `initDrawer()` com DOMContentLoaded

**No final da IIFE do drawer**, após o `} // ← fecha initDrawer()`:

```javascript
        } // ← fecha initDrawer()  ✅ já existe

        // ADICIONAR estas linhas:
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', initDrawer);
        } else {
          initDrawer();
        }

      }()); // ← fecha a IIFE externa
```

---

### Fix 2 — Remover o `finally` indevido do Hari-Kathā

**Localizar** em `fetchPassages` (bloco Hari-Kathā) e **remover** o `.finally`:

```javascript
// ANTES (errado):
          .catch(function (e) {
            state.loading = false;
            var inner2 = document.getElementById('vana-hk-passages-inner');
            if (inner2) inner2.innerHTML = '<p class="vana-hk__error">' + t('errP') + '</p>';
          }).finally(function () {
            // Safety: sempre esconder ambos os spinners ao final
            if (tourLoading) tourLoading.hidden = true;      // ← REMOVER
            if (visitsLoading) visitsLoading.hidden = true;  // ← REMOVER
          });
        }
      }

// DEPOIS (correto):
          .catch(function (e) {
            state.loading = false;
            var inner2 = document.getElementById('vana-hk-passages-inner');
            if (inner2) inner2.innerHTML = '<p class="vana-hk__error">' + t('errP') + '</p>';
          });
        }
      }
```

---

## Instrução Para o Agente

```text
ABRIR: visit-scripts.php

FIX 1 — Localizar linha com:
  } // ← fecha initDrawer()

Logo ABAIXO dessa linha, ANTES do }()); final, INSERIR:
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDrawer);
  } else {
    initDrawer();
  }

FIX 2 — Localizar o bloco .finally dentro de fetchPassages 
do HARI-KATHĀ LOADER (NÃO o do drawer):
  .finally(function () {
    if (tourLoading) tourLoading.hidden = true;
    if (visitsLoading) visitsLoading.hidden = true;
  });

REMOVER esse .finally inteiro.
Deixar apenas o ; após o .catch(...)

SALVAR e fazer deploy.
```

---

## Validação Após o Fix

```javascript
// Deve retornar 'function'
typeof window.__vanaDrawerSelectTour;

// Deve abrir o drawer
document.querySelector('[data-drawer="vana-tour-drawer"]').click();
```

**Esses 2 fixes resolvem o problema.** 🙏