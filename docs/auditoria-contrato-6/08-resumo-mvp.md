```markdown
# docs/auditoria-contrato-6/08-resumo-mvp.md

## 1. Status geral do MVP

O estado atual do **MVP da página `single-vana_visit.php`** está em **funcionamento parcial com base estrutural sólida**, porém **ainda não aderente ao Contrato 6.0 como um todo**.

Com base nas auditorias já criadas em `docs/auditoria-contrato-6/`:

- há uma **base SSR funcional** para visita, dia ativo, navegação, stage, agenda e seções
- há **gavetas funcionais** (agenda e tour) e **infraestrutura real de frontend**
- há **embed de mídia, chips, sections e timeline operacional**
- porém persistem **divergências contratuais relevantes**
- e alguns pontos centrais ainda estão **parciais, ausentes ou inconsistentes**

Em termos executivos:

- o MVP **não está quebrado**
- o MVP **já entrega uma experiência navegável**
- mas o MVP **ainda não pode ser considerado aderente ao Contrato 6.0**

---

## 2. O que está implementado

Com base principalmente em `01-hero-e-day-selector.md`, `02-stage.md`, `03-agenda-drawer.md`, `04-chip-bar-e-secoes.md`, `05-tour-drawer-prev-next.md` e `06-dados-e-timeline.md`, os seguintes blocos aparecem como **IMPLEMENTADOS** no recorte auditado:

### 2.1 Hero / navegação principal
- Hero com nome da visita
- contador da visita na tour quando houver tour
- prev/next entre visitas
- seletor de dia presente
- seletor de dia some quando `days === 1`
- troca de dia recarrega agenda/conteúdo do dia

### 2.2 Stage
- embed YouTube protegido com `youtube-nocookie.com`
- segments abaixo dos controles
- clique em segment faz seek

### 2.3 Agenda drawer
- estado padrão fechado
- formas básicas de acionamento
- anatomia interna da agenda
- tabs de dias e estrutura navegável

### 2.4 Chip bar e sections
- chip bar sticky
- chips de HK / Galeria / Sangha / Revista
- estrutura base das seções existe

### 2.5 Tour drawer
- cenário com tour
- cenário sem tour / legado
- tour opcional sem quebrar renderização
- drawer funcional
- handlers AJAX do drawer confirmados no PHP
- `window.vanaDrawer` está sendo populado e consumido com fallback defensivo

### 2.6 Estrutura de dados mínima operacional
- `_vana_start_date` registrado e usado
- `days` usados no resolver/bootstrap/template
- `events` usados no resolver
- estrutura `Visit -> Day -> Event` operacional
- compatibilidade com visit sem tour
- coerência SSR mínima entre resolver -> bootstrap -> template

---

## 3. O que está parcial

Os seguintes blocos aparecem como **PARCIAIS** nas auditorias já feitas:

### 3.1 Hero / day selector
- fallback cronológico global existe, mas não como fallback verdadeiro por ausência de tour
- gaveta com cenário com-tour/sem-tour foi inicialmente tratada como parcial em auditoria antiga por ausência de arquivo, embora depois tenha sido melhor confirmada
- trocar dia não troca stage automaticamente: comportamento não está garantido de forma forte
- duplicidade entre `day-tabs.php` e seletor de dias no hero

### 3.2 Stage
- modos vídeo / áudio / neutro / aguardando
- autoplay curado
- ausência de recomendados externos
- controles esperados
- integração Stage -> Agenda
- integração Stage -> HK

### 3.3 Agenda drawer
- estados de evento: passado / ativo / futuro / sem mídia
- idioma PT/EN afeta HK e não áudio
- agenda troca mídia do stage
- integração Agenda -> Stage

### 3.4 Zonas 4 e 5
- sangha temporal por evento
- revista com estados `coleta / edição / publicada`

### 3.5 Gaveta esquerda / prev-next
- status por visita
- fallback cronológico global quando `_vana_tour_id` é `null` (existe, mas é a regra universal atual)

### 3.6 Estrutura de dados
- `_vana_tour_id` usado, mas não formalmente registrado
- `label` canônico inexistente, com uso de `label_pt` / `label_en`
- `title` canônico inexistente, com uso de `title_pt` / `title_en`
- `active_events` como compatibilidade
- coerência CPT/meta -> renderização apenas parcial

### 3.7 Consolidação de riscos
- R3: duplicação de implementação do drawer em `visit-scripts.php`

---

## 4. O que está ausente

Os seguintes pontos aparecem como **AUSENTES** nas auditorias:

### 4.1 Hero
- período `start_date -> end_date` visível no hero

### 4.2 Stage
- modo explícito de áudio
- segmento ativo acompanhando `currentTime`

### 4.3 Agenda drawer
- exceções de abertura automática
- botões condicionais `[▶]`, `[📖 HK]`, `[🔔]`
- avanço automático para próximo evento quando o stage termina
- integração Agenda -> HK

### 4.4 Zonas 4 e 5
- HK com listagem por `event_id`
- passages com timestamp clicável **na auditoria restrita da zona 4/5** não puderam ser comprovadas naquele recorte
- reactions
- filtros por taxonomia
- galeria temporal por evento

### 4.5 Estrutura de dados
- `_vana_location`
- `_visit_timeline_json` como nome contratual
- `day_key`
- `time` como campo contratual comprovado nesse recorte
- `media_ref`
- `segments` como contrato estrutural comprovado
- `katha_refs`
- `photo_refs`
- `sangha_refs`

---

## 5. Principais divergências

As divergências mais relevantes em relação ao Contrato 6.0 são:

### 5.1 Prev/next por tour não existe
Esta é a divergência mais clara e repetida nas auditorias.

Evidência consolidada:
- `01-hero-e-day-selector.md`
- `05-tour-drawer-prev-next.md`
- `07-riscos-r1-r4.md`

O código atual explicita:

```php
// DT-004: Navegação é sempre cronológica global.
// Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
```

### 5.2 Agenda aparece no chip bar
Em `04-chip-bar-e-secoes.md`, isso foi classificado como **DIVERGENTE**:
- o contrato diz que agenda não pertence ao chip bar
- o código atual inclui `vana-section-schedule`

### 5.3 Contrato do Stage está arquiteturalmente inconsistente
Em `02-stage.md`, a relação entre:
- `stage.php`
- `VisitStageResolver`
- `VisitStageViewModel`
- REST Stage
- REST Stage Fragment

foi classificada como **DIVERGENTE**.

Motivos centrais:
- `stage.php` espera `active_event`
- endpoints REST não entregam `active_event`
- divergência de meta keys (`_vana_tz` vs `_vana_visit_timezone`)
- divergência de fonte (`_vana_visit_timeline_json` vs `_vana_visit_data`)

### 5.4 Estrutura de dados diverge em nomenclatura do contrato
Em `06-dados-e-timeline.md`:
- contrato espera `_visit_timeline_json`
- código usa `_vana_visit_timeline_json`

---

## 6. Riscos críticos antes de codar

Com base em `07-riscos-r1-r4.md` e nas demais auditorias, os riscos críticos antes de iniciar nova implementação são:

### 6.1 R2 — prev/next precisa de condicional por `tour_id`
**Status:** `PENDENTE`  
**Impacto:** alto

É o principal risco funcional aberto porque afeta:
- coerência da tour
- navegação do hero
- expectativa contratual de agrupamento por tour

### 6.2 R3 — duplicação/fonte de verdade difusa do drawer
**Status:** `PARCIAL`  
**Impacto:** médio

O template aponta `VanaVisitController.js` como controller da gaveta, mas a implementação concreta está no JS inline de `visit-scripts.php`.

### 6.3 Divergência estrutural do Stage
Mesmo não nomeado como R1-R4, este é um risco técnico crítico:
- endpoints REST e SSR não compartilham contrato único
- qualquer novo patch em stage pode aumentar acoplamento e regressão

### 6.4 Contrato de dados ainda não congelado
Sem consolidar:
- meta canônica da timeline
- campos canônicos de day/event
- registro formal de `_vana_tour_id`

novas implementações correm risco de reforçar inconsistências já existentes.

---

## 7. Próxima fase proposta

A próxima fase proposta, com base nas auditorias consolidadas, é:

# **Fase de estabilização contratual do núcleo da página de visita**

Essa fase deveria priorizar:

### 7.1 Navegação contratual
- fechar a regra de prev/next por tour vs fallback global

### 7.2 Contrato estrutural do Stage
- alinhar SSR, REST, fragment e view model
- eliminar divergência de payload e meta keys

### 7.3 Contrato de dados da visita
- congelar nomenclatura das metas
- definir contrato canônico de `days` e `events`
- registrar formalmente campos usados em runtime

### 7.4 Só depois expandir features de UX
Como:
- states mais ricos da agenda
- HK por evento
- galeria/sangha por evento
- revista editorial completa
- reactions e taxonomias

---

## 8. Decisão de produto aplicada e recomendação atualizada

Por decisão de produto (DT-004), a navegação `prev/next` permanecerá cronológica global; a opção de escopar por `tour_id` foi reclassificada como decisão de produto e não será aplicada como patch imediato.

### Recomendação prioridade (após decisão)

1. Priorizar alinhamento e estabilização do contrato do `Stage` (SSR, REST e fragmentos)
2. Em seguida, consolidar o contrato de dados da visita (meta keys, payloads `timeline_json`)
3. Depois disso, avaliar novas mudanças na navegação apenas se a decisão de produto for revertida

Racional: com a decisão tomada, o maior risco funcional remaneja para inconsistências do `Stage` e do contrato de dados, que têm maior impacto técnico e de regressão.

---

## 9. Gaps

### Gap 1 — O MVP funciona, mas não está contratualmente fechado
Há blocos sólidos e operacionais, mas o contrato 6.0 ainda não foi consolidado em navegação, dados e stage.

### Gap 2 — O sistema já tem infraestrutura suficiente para evoluir, porém com divergências centrais
Os pontos mais perigosos não são cosméticos; são:
- navegação por tour
- contrato do stage
- contrato de dados

### Gap 3 — Há risco de construir features novas sobre base ainda divergente
Exemplos:
- expandir agenda
- expandir HK
- expandir stage
- expandir sections

sem antes estabilizar o núcleo pode aumentar dívida técnica.

---

## 10. Próximo passo recomendado

1. **Executar primeiro o patch de prev/next por `tour_id`**
2. **Na sequência, estabilizar o contrato do Stage**
3. **Depois congelar o contrato de dados da visita**
4. **Só então avançar para agenda rica, HK por evento, galeria/sangha temporal e revista editorial completa**
```