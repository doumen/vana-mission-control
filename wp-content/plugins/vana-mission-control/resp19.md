# Análise dos Arquivos — Diagnóstico do Botão Tours 🔍

Marcel, li todos os arquivos. Encontrei o problema.

---

## O Bug: Conflito de Event Listeners

```text
FLUXO ATUAL:

hero-header.php gera:
  <button class="vana-header__tours-btn"
          data-drawer="vana-tour-drawer"    ← atributo usado pelo JS
          aria-controls="vana-tour-drawer">

visit-scripts.php linha ~1103 busca:
  var btn = document.querySelector('[data-drawer="vana-tour-drawer"]');
  ✅ Encontra o botão

  var drawer = document.getElementById('vana-tour-drawer');
  ❓ O drawer existe? Qual é o ID exato?
```

---

## O Problema Real: `tour-drawer.php` não está no inventário

```text
hero-header.php linha final:
  <?php require VANA_MC_PATH . 'templates/visit/parts/tour-drawer.php'; ?>

MAS: na busca por "**/vana-tour-drawer*" → NO MATCHES
     na busca por "**/vana-drawer*"      → NO MATCHES

CONCLUSÃO:
  O arquivo tour-drawer.php NÃO EXISTE no repositório local!
  
  Portanto o HTML do drawer nunca é renderizado.
  O JS busca getElementById('vana-tour-drawer') → null
  A linha: if (!drawer || !btn) return;  ← PARA AQUI
  
  O botão existe, mas a gaveta não existe no DOM.
```

---

## Verificação Rápida

**Instrução para o agente:**

```text
PASSO 1: Confirmar que o arquivo não existe localmente
  dir "C:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\"
  REPORTAR: tour-drawer.php aparece na lista?

PASSO 2: Verificar se existe no servidor (mas não localmente)
  curl.exe -s "https://beta.vanamadhuryamdaily.com/visit/sao-paulo-janeiro-2026/" |
  Select-String "vana-tour-drawer" -ErrorAction SilentlyContinue
  REPORTAR: o HTML retornado contém id="vana-tour-drawer"?

PASSO 3: Verificar erro PHP no servidor
  curl.exe -s "https://beta.vanamadhuryamdaily.com/visit/sao-paulo-janeiro-2026/" |
  Select-String "require.*tour-drawer|Warning|Fatal" -ErrorAction SilentlyContinue
  REPORTAR: há algum erro PHP visível?
```

---

## Se tour-drawer.php Não Existir: Criar Agora

```text
CAMINHO: templates/visit/parts/tour-drawer.php

CONTEÚDO NECESSÁRIO (baseado no CSS já existente em visit-style.php):
```

```php
<?php
/**
 * Tour Drawer
 * templates/visit/parts/tour-drawer.php
 */
defined('ABSPATH') || exit;
?>

<!-- Overlay -->
<div id="vana-drawer-overlay"
     class="vana-drawer__overlay"
     aria-hidden="true"></div>

<!-- Drawer -->
<aside id="vana-tour-drawer"
       class="vana-drawer"
       role="dialog"
       aria-modal="true"
       aria-label="<?php echo esc_attr(vana_t('hero.tours', $lang ?? 'pt')); ?>"
       hidden>

  <!-- Header -->
  <div class="vana-drawer__header">
    <span class="vana-drawer__header-title">
      <?php echo esc_html(vana_t('hero.tours', $lang ?? 'pt')); ?>
    </span>
    <button class="vana-drawer__close"
            type="button"
            aria-label="Fechar">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
        <path d="M2 2l12 12M14 2L2 14"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"/>
      </svg>
    </button>
  </div>

  <!-- Body: Lista de Tours (Nível 1) -->
  <div id="vana-drawer-body" class="vana-drawer__body">
    <!-- Loading spinner -->
    <div id="vana-drawer-loading" class="vana-drawer__loading">
      <div class="vana-drawer__spinner"></div>
    </div>

    <!-- Lista de tours -->
    <ul id="vana-drawer-tour-list"
        class="vana-drawer__tour-list"
        hidden></ul>
  </div>

  <!-- Body: Lista de Visitas (Nível 2) -->
  <div id="vana-drawer-visits" class="vana-drawer__body" hidden>
    <!-- Loading spinner -->
    <div id="vana-drawer-visits-loading" class="vana-drawer__loading" hidden>
      <div class="vana-drawer__spinner"></div>
    </div>

    <!-- Lista de visitas -->
    <ul id="vana-drawer-visit-list"
        class="vana-drawer__visit-list"
        hidden></ul>
  </div>

</aside>
```

```text
DEPOIS DE CRIAR:
  python deploy_smart_git.py
  
VERIFICAR no browser:
  Botão Tours → gaveta abre?
```

---

## Por que o Spinner Duplicado Aparecia Antes

```text
Agora faz sentido! O spinner que "nunca parava" era o spinner
do loading do drawer tentando esconder um elemento que não existia.

Com o tour-drawer.php criado:
  ✅ drawer existe no DOM
  ✅ JS encontra getElementById('vana-tour-drawer')
  ✅ if (!drawer || !btn) return  → NÃO para mais
  ✅ Spinner some após carregar
  ✅ Tours aparecem na lista
```

---

```text
RESUMO:
  CAUSA RAIZ: tour-drawer.php ausente do repositório
  FIX: criar o arquivo com o HTML correto
  DEPLOY: 1 arquivo apenas
```

🙏 Marcel, confirma se o arquivo existe na pasta `parts/`!