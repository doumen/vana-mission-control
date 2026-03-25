# CONTRATO TÉCNICO — Vana Tour Hero
> Versão: 1.0 | MVP | PT/EN
> Última revisão: 2026-03-24
> Mantenedor: Marcel / Vana Madhuryam

---

## 1. PROPÓSITO

Este documento é a fonte da verdade técnica do bloco Hero da
página de visitas (Visit Page). Qualquer decisão de produto,
regra de negócio ou contrato de dados deve ser registrada aqui
antes de ser implementada.

---

## 2. ARQUITETURA DE ARQUIVOS

```text
DOCUMENTAÇÃO
  CONTRATO.md                         ← este arquivo
  EDITORIAL.md                        ← textos editáveis PT/EN

LÓGICA
  includes/class-vana-utils.php       ← helper global (t, pick_i18n*)

TEMPLATES
  templates/visit/hero-header.php
  templates/visit/partials/
    _hero-badges.php
    _hero-day-selector.php
    _hero-nav.php

PATCH
  templates/visit/day-tabs.php

VISUAL
  assets/css/vana-hero.css
```

---

## 3. INTERNACIONALIZAÇÃO (i18n)

### 3.1 Regra geral

- **Nenhum texto visível ao usuário** pode ser hardcoded em PHP ou CSS.
- Todo texto passa por `Vana_Utils::t($key, $lang)`.
- `vana_t($key, $lang)` é o alias global (wrapper) para os templates.
- O idioma é detectado por `Vana_Utils::lang_from_request()` via `?lang=en`.
- Fallback: PT → se chave não existe → retorna a própria `$key`.

### 3.2 Hierarquia de fontes

```text
1. EDITORIAL.md          → fonte humana (editor atualiza aqui)
2. class-vana-utils.php  → array PHP (dev sincroniza do editorial)
3. Template              → consome vana_t() / pick_i18n_key()
```

### 3.3 Dados do tour (JSON do timeline)

```text
Campos de conteúdo usam sufixo:   title_pt / title_en
Campos de código são invariáveis: season_code, region_code
Seleção via:                      Vana_Utils::pick_i18n_key($obj, 'title', $lang)
```

---

## 4. CÓDIGOS DE PERÍODO (season_code)

> O código é sempre invariável (inglês, maiúsculo, 3 letras).
> Apenas o label muda por língua.

| Código | PT              | EN              | Observação              |
|--------|-----------------|-----------------|-------------------------|
| `WIN`  | Inverno         | Winter          |                         |
| `SUM`  | Verão           | Summer          |                         |
| `SPR`  | Primavera       | Spring          |                         |
| `AUT`  | Outono          | Autumn          |                         |
| `KAR`  | Kartik          | Kartik          | Calendário vaishnava    |
| `GAU`  | Gaura Purnima   | Gaura Purnima   | Calendário vaishnava    |

---

## 5. CÓDIGOS DE REGIÃO (region_code)

| Código | PT        | EN        |
|--------|-----------|-----------|
| `AME`  | Américas  | Americas  |
| `EUR`  | Europa    | Europe    |
| `IND`  | Índia     | India     |
| `ASI`  | Ásia      | Asia      |
| `AFR`  | África    | Africa    |

---

## 6. CASOS DO SELETOR DE DIAS

| Caso | Condição                        | Comportamento                        |
|------|---------------------------------|--------------------------------------|
| 1    | Tour sem dias                   | Mensagem `day.empty`                 |
| 2    | Tour com 1 dia                  | Sem seletor, exibe direto            |
| 3    | Tour com N dias, hoje incluso   | Seleciona dia atual automaticamente  |
| 4    | Tour com N dias, hoje não incluso | Seleciona primeiro dia             |
| 5    | Dia selecionado sem eventos     | Mensagem `day.empty`                 |

---

## 7. BADGES — CONDIÇÕES DE EXIBIÇÃO

| Badge    | Campo fonte       | Exibe quando                  |
|----------|-------------------|-------------------------------|
| Região   | `region_code`     | Sempre que preenchido         |
| Período  | `season_code`     | Sempre que preenchido         |
| Live     | `has_live`        | `true`                        |
| Novo     | `is_new`          | `true` e visita < 30 dias     |

---

## 8. DEGRADAÇÃO DO HERO

```text
A → Tour completo:     exibe hero completo
B → Tour incompleto:   exibe hero + aviso 'hero.incomplete'
C → Sem tour:          exibe placeholder 'hero.no_tour'
```

---

## 9. REGRAS DE BLOQUEIO

```text
- Nenhum campo opcional bloqueia a renderização.
- Badge sem código → simplesmente não renderiza.
- Dia sem eventos → renderiza estado vazio (não quebra o layout).
- URL de vídeo inválida → não renderiza player (não quebra o card).
```

---

## 10. DADOS MÍNIMOS vs RICOS

```text
MÍNIMO (renderiza hero):
  → tour_title_pt  ou  tour_title_en
  → pelo menos 1 dia com data

RICOS (hero completo):
  → region_code, season_code
  → thumbnail ou video_url
  → has_live, is_new
  → descrição do dia (day_description_pt / day_description_en)
```

---

## 11. PRÓXIMAS VERSÕES (fora do MVP)

```text
- [ ] Suporte a ES (Espanhol) como terceiro locale
- [ ] Período customizado (campo livre além dos códigos)
- [ ] Badge de destaque manual (editorial marca como featured)
```
