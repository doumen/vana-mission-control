# Diagnóstico Definitivo — Problema de Deploy 🎯

Marcel, agora está 100% claro. Não é bug de código — é **deploy incompleto**.

---

## O Que o HTML do Beta Revelou

```text
EVIDÊNCIA 1: Backend desatualizado
  → Beta ainda renderiza initDrawer() e AJAX de tour
  → Esses blocos já foram REMOVIDOS localmente
  → Conclusão: PHP/templates antigos no servidor

EVIDÊNCIA 2: VanaAgendaController.js ausente
  → Não aparece no HTML do beta
  → Não é problema de enqueue — o template antigo
    simplesmente não conhece esse arquivo

EVIDÊNCIA 3: Não é só JS
  → Se fosse só JS faltando, o PHP estaria correto
  → O PHP também está desatualizado no servidor
```

---

## Lista Completa de Deploy

```text
ARQUIVOS QUE PRECISAM SUBIR:

  PHP / Templates:
  ├── vana-mission-control.php
  ├── templates/visit/parts/hero-header.php
  ├── templates/visit/parts/anchor-chips.php
  ├── templates/visit/parts/sections.php
  ├── templates/visit/parts/agenda-drawer.php
  └── templates/visit/assets/visit-scripts.php

  JavaScript:
  ├── assets/js/VanaVisitController.js
  ├── assets/js/VanaChipController.js      ← ES5 convertido hoje
  └── assets/js/VanaAgendaController.js    ← ausente no servidor
```

---

## Checklist Pós-Deploy (menos de 1 minuto)

```bash
# 1. Confirmar que os 3 JS estão no servidor
ls -la wp-content/plugins/vana-mission-control/assets/js/Vana*Controller.js

# 2. Confirmar versão do PHP principal (data de modificação)
stat wp-content/plugins/vana-mission-control/vana-mission-control.php

# 3. Limpar OPcache (se disponível)
php -r "opcache_reset();"

# 4. Verificar no HTML que os scripts aparecem
curl -s "https://beta.vanamadhuryamdaily.com/visit/teste-probe-via-ingest-2026-02-23-195722/?v_day=2026-03-22&lang=pt" \
  | grep -o "Vana[A-Za-z]*Controller\.js"

# 5. Confirmar HTTP 200 para cada JS
curl -I "https://beta.vanamadhuryamdaily.com/wp-content/plugins/vana-mission-control/assets/js/VanaAgendaController.js"
curl -I "https://beta.vanamadhuryamdaily.com/wp-content/plugins/vana-mission-control/assets/js/VanaChipController.js"
```

---

## Sequência de Deploy Recomendada

```text
ORDEM IMPORTA:

  1. Subir PHP/templates primeiro
     → O enqueue do vana-mission-control.php
       precisa referenciar os novos JS

  2. Subir JS depois
     → Garante que quando o PHP pedir o arquivo,
       ele já existe no servidor

  3. Limpar cache
     → OPcache (PHP)
     → Plugin de cache WordPress (WP Rocket, etc.)
     → CDN se houver (Cloudflare, etc.)

  4. Hard refresh no browser (Ctrl+F5)

  5. Rodar os 5 comandos do checklist acima
```

---

```text
PRÓXIMO PASSO: fazer o deploy dos 9 arquivos listados
DEPOIS: rodar o checklist de 5 comandos
RESULTADO ESPERADO:
  → VanaChipController.js   → 200 ✅
  → VanaAgendaController.js → 200 ✅
  → initDrawer() antigo     → SUMIU do HTML ✅
  → Botão agenda            → aparece no DOM ✅
```

Faz o deploy, Marcel, e roda o checklist. Qualquer resultado diferente do esperado, me traz a saída dos comandos. 🙏