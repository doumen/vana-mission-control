# EDITORIAL — Vana Tour Hero
> Fonte humana de todos os textos visíveis ao usuário.
> Edite aqui → sincronize em class-vana-utils.php → nunca edite direto no PHP.
> Versão: 1.0 | MVP | PT/EN
> Última revisão: 2026-03-24

---

## INSTRUÇÕES PARA O EDITOR

1. Cada chave tem exatamente **dois campos**: `pt` e `en`.
2. **Não remova chaves** — apenas edite os valores.
3. Chaves novas devem ser adicionadas **aqui primeiro**,
   depois sincronizadas pelo dev em `class-vana-utils.php`.
4. Textos vaishnavas (Kartik, Gaura Purnima, etc.)
   **não são traduzidos** — repita o mesmo valor em PT e EN.

---

## 1. BADGES

### badge.region.AME
- pt: `Américas`
- en: `Americas`

### badge.region.EUR
- pt: `Europa`
- en: `Europe`

### badge.region.IND
- pt: `Índia`
- en: `India`

### badge.region.ASI
- pt: `Ásia`
- en: `Asia`

### badge.region.AFR
- pt: `África`
- en: `Africa`

---

## 2. PERÍODOS (season_code)

### badge.season.WIN
- pt: `Inverno`
- en: `Winter`

### badge.season.SUM
- pt: `Verão`
- en: `Summer`

### badge.season.SPR
- pt: `Primavera`
- en: `Spring`

### badge.season.AUT
- pt: `Outono`
- en: `Autumn`

### badge.season.KAR
- pt: `Kartik`
- en: `Kartik`

### badge.season.GAU
- pt: `Gaura Purnima`
- en: `Gaura Purnima`

---

## 3. BADGES DE ESTADO

### badge.live
- pt: `Ao Vivo`
- en: `Live`

### badge.new
- pt: `Novo`
- en: `New`

---

## 4. HERO — ESTADOS DO TOUR

### hero.no_tour
- pt: `Nenhuma visita programada.`
- en: `No visit scheduled.`

### hero.incomplete
- pt: `Esta visita está em preparação. Em breve mais informações.`
- en: `This visit is being prepared. More information coming soon.`

---

## 5. SELETOR DE DIAS

### day.empty
- pt: `Nenhum evento registrado neste dia.`
- en: `No events recorded for this day.`

### day.select_label
- pt: `Selecionar dia`
- en: `Select day`

### day.today
- pt: `Hoje`
- en: `Today`

---

## 6. NAVEGAÇÃO ENTRE VISITAS

### day.prev
- pt: `Visita anterior`
- en: `Previous visit`

### day.next
- pt: `Próxima visita`
- en: `Next visit`

---

## 7. ACESSIBILIDADE (aria-labels)

### aria.close_hero
- pt: `Fechar painel da visita`
- en: `Close visit panel`

### aria.badge_region
- pt: `Região da visita`
- en: `Visit region`

### aria.badge_season
- pt: `Período da visita`
- en: `Visit season`

### aria.badge_live
- pt: `Visita com transmissão ao vivo`
- en: `Visit with live broadcast`

### aria.badge_new
- pt: `Visita recente`
- en: `Recent visit`

### aria.day_selector
- pt: `Navegador de dias da visita`
- en: `Visit day navigator`

### aria.nav_prev
- pt: `Ir para a visita anterior`
- en: `Go to previous visit`

### aria.nav_next
- pt: `Ir para a próxima visita`
- en: `Go to next visit`

---

## 8. VÍDEO / MEDIA

> Estas chaves já existem em class-vana-utils.php.
> Listadas aqui para referência editorial completa.

### watch_link
- pt: `Abrir link do vídeo`
- en: `Open video link`

### watch_link_short
- pt: `Abrir link`
- en: `Open link`

### embed_fail_title
- pt: `Não foi possível exibir o vídeo aqui.`
- en: `We couldn't display the video here.`

### embed_fail_hint
- pt: `Sem problema — você pode abrir no navegador.`
- en: `No worries — you can open it in your browser.`

### video_label
- pt: `Vídeo`
- en: `Video`

### photo_label
- pt: `Foto`
- en: `Photo`

---

## 9. OFERENDAS DA SANGHA

> Estas chaves já existem em class-vana-utils.php.
> Listadas aqui para referência editorial completa.

### offerings_title
- pt: `Momentos da Sangha`
- en: `Sangha Moments`

### share_prompt
- pt: `Partilhe os seus momentos e relatos desta visita.`
- en: `Share your moments and reflections of this visit.`

### form_title
- pt: `Enviar oferenda`
- en: `Submit offering`

### name_label
- pt: `Nome`
- en: `Name`

### message_label
- pt: `Mensagem`
- en: `Message`

### video_url_label
- pt: `Link do vídeo (Drive recomendado)`
- en: `Video link (Drive recommended)`

### submit
- pt: `Enviar`
- en: `Submit`

### consent
- pt: `Eu autorizo a publicação desta oferenda nos canais oficiais da missão.`
- en: `I authorize publishing this offering on the mission's official channels.`

### privacy_note
- pt: `Não publique dados pessoais sensíveis. Obrigado por servir a sangha.`
- en: `Please don't include sensitive personal data. Thank you for serving the sangha.`

---

## CHECKLIST DE SINCRONIZAÇÃO

Após editar este arquivo, o dev deve:

- [ ] Adicionar chaves novas em `class-vana-utils.php` → método `t()`
- [ ] Testar `?lang=pt` e `?lang=en` na visit page
- [ ] Verificar fallback: remover `en` de uma chave e confirmar que PT aparece
- [ ] Commitar ambos os arquivos juntos no mesmo PR
