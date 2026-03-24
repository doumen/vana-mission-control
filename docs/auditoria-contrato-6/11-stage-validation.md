## 1. Resumo executivo

- O patch adicionou um helper canônico `vana_visit_stage_bootstrap()` e o conectou ao `VisitStageResolver`, ao endpoint REST Stage e ao fragment (caso `item_type === 'event'`).
- Efeito principal: resolver + REST agora leem as mesmas meta keys canônicas (`_vana_visit_timeline_json` com fallback `_vana_visit_data`; `_vana_visit_timezone` com fallback `_vana_tz`), reduzindo divergências de leitura.
- Estado após análise: **MITIGADO** — a maioria das divergências de leitura foi unificada; entretanto permanecem incompatibilidades de variável/contract (notadamente `active_event` vs `active_vod`) e um fallback legado no fluxo `restore` que devem ser resolvidos para marcar como **RESOLVIDO**.

---

## 2. Perguntas obrigatórias (respostas diretas)

1) O novo helper é carregado corretamente antes do uso?
   - Sim. `visit-stage-bootstrap.php` é `require_once` em:
     - `includes/class-visit-stage-resolver.php` (top, antes de uso). [includes/class-visit-stage-resolver.php](wp-content/plugins/vana-mission-control/includes/class-visit-stage-resolver.php#L1)
     - `includes/rest/class-vana-rest-stage.php` (top, antes de construir o HTML). [includes/rest/class-vana-rest-stage.php](wp-content/plugins/vana-mission-control/includes/rest/class-vana-rest-stage.php#L1)
     - `includes/rest/class-vana-rest-stage-fragment.php` é `require_once` dentro de `render_event_stage()` antes de usar o helper. [includes/rest/class-vana-rest-stage-fragment.php](wp-content/plugins/vana-mission-control/includes/rest/class-vana-rest-stage-fragment.php#L1)

2) Existem erros de sintaxe, referência, visibilidade ou include?
   - Não foram encontrados erros de sintaxe nos arquivos modificados (`visit-stage-bootstrap.php`, `class-visit-stage-resolver.php`).
   - A verificação estática (`get_errors`) reportou ~17 problemas no arquivo `class-vana-rest-stage.php` — exemplos: "Call to unknown function register_rest_route", "Use of unknown class WP_REST_Request". Estes são falsos-positivos do analisador estático porque as funções/classes do WordPress não existem fora do ambiente WP. Não são erros de sintaxe; são avisos de análise estática em ambiente não-WP.

3) O retorno de `vana_visit_stage_bootstrap()` contém todas as variáveis mínimas exigidas por `stage.php`?
   - A função retorna: `visit_id`, `timeline`, `overrides`, `visit_tz`, `visit_status`, `visit_city_ref`, `lang`, `event_data`, `active_event`, `active_day`, `active_day_date` — que cobrem o conjunto mínimo exigido por `stage.php` (ver comentário do template). [includes/visit-stage-bootstrap.php](wp-content/plugins/vana-mission-control/includes/visit-stage-bootstrap.php#L1) e [templates/visit/parts/stage.php](wp-content/plugins/vana-mission-control/templates/visit/parts/stage.php#L1)

4) Resolver e REST agora leem as mesmas meta keys com os mesmos fallbacks?
   - Sim. Ambos usam `vana_visit_stage_bootstrap()` como ponto único de leitura:
     - Timeline: `_vana_visit_timeline_json` → fallback `_vana_visit_data`.
     - Timezone: `_vana_visit_timezone` → fallback `_vana_tz` → fallback `'UTC'`.

5) O caso `item_type === event` no fragment agora usa o caminho canônico?
   - Sim. `render_event_stage()` foi alterado para chamar `vana_visit_stage_bootstrap(...)` e preferir o `active_event` resolvido pelo helper antes de buscar manualmente no timeline. [includes/rest/class-vana-rest-stage-fragment.php](wp-content/plugins/vana-mission-control/includes/rest/class-vana-rest-stage-fragment.php#L1)

6) Restam divergências estruturais importantes após o patch?
   - Sim, as duas mais relevantes:
     1. Variáveis passadas ao template: os renderers REST continuam a `extract()` um conjunto que contém `active_vod` / `vod_list` (e não sempre `active_event`). `stage.php` consome `$active_event` em caminhos SSR; a interface usada por REST ainda depende de `active_vod` em alguns renderers — risco de comportamentos diferentes. (Ver `render_stage()` e `render_event_stage()` compact/extracts.) [includes/rest/class-vana-rest-stage.php#L1](wp-content/plugins/vana-mission-control/includes/rest/class-vana-rest-stage.php#L1)
     2. Fluxo `restore` (em `class-vana-rest-stage-fragment.php::render_restore`) ainda usa `_vana_visit_data` e tem um fallback de timezone `America/Sao_Paulo` — diferença semântica frente ao novo padrão UTC/fallback do helper.

7) Os “17 problems found” são bloqueantes ou apenas warnings?
   - São warnings do analisador estático por ausência de runtime WordPress (WP classes/functions). Não são bloqueantes para execução dentro do WordPress; contudo, se desejar CI/phpstan sem o WP stubs, será necessário adicionar stubs ou ajustar o analisador. Em resumo: não bloqueantes para deploy em ambiente WP.

8) O status do Stage agora é: **MITIGADO**
   - Racional: leituras canônicas e resolução de contexto unificadas reduzem risco de mismatch; ainda há pequenas incompatibilidades de contrato variável que precisam de ajuste para considerar o caso `RESOLVIDO`.

---

## 3. Lista dos problemas encontrados (evidência)

1) REST renderers não expõem sempre `active_event` para o template
   - Local: `includes/rest/class-vana-rest-stage.php` — `render_stage()` compacta `active_vod` (não `active_event`). [includes/rest/class-vana-rest-stage.php](wp-content/plugins/vana-mission-control/includes/rest/class-vana-rest-stage.php#L142)
   - Risco: `stage.php` começa por usar `$active_event` para normalizar o evento; ausência desta variável altera a derivação de `$current_event` e pode alterar a UI renderizada.

2) `render_restore()` usa `_vana_visit_data` + timezone legacy
   - Local: `includes/rest/class-vana-rest-stage-fragment.php::render_restore()` define `$visit_tz = (string) ($visit_data['timezone'] ?? 'America/Sao_Paulo')` — esse fallback diverge do novo padrão (`UTC`). [includes/rest/class-vana-rest-stage-fragment.php](wp-content/plugins/vana-mission-control/includes/rest/class-vana-rest-stage-fragment.php#L1)

3) Static analyzer warnings (~17) no `class-vana-rest-stage.php`
   - Ex.: "Call to unknown function register_rest_route", "Use of unknown class WP_REST_Request" — causados por execução fora do WP; ver saída do analisador. Não são bugs do patch em si.

4) Normalização dupla preservada para tipos não-event
   - `stage-fragment.php` mantém caminhos específicos para `vod`, `gallery`, `sangha` (intencional), o que ainda permite pequenas diferenças de normalização; o patch focou em `event` (baixo risco).

5) Nome de chave ViewModel vs helper
   - Internamente o `VisitStageViewModel` expõe `visit_timezone`, enquanto o helper retorna `visit_tz` — no entanto o SSR `_bootstrap.php` faz a conversão/alias (`$visit_tz_str = $location_meta['tz'] ?? $visit_timezone ?? 'UTC'`) portanto isso não é um bloqueio.

---

## 4. Severidade (classificação)

- Bloqueante:
  - Nenhum bloqueio crítico identificado que impeça execução no WP em ambiente normal.

- Média:
  - REST renderers não expondo `active_event` pode gerar UX divergências (corrigir antes de alterações em produção que dependam do fragment/stage parity).
  - `restore` timezone fallback legado pode provocar horários incorretos em restores.

- Baixa:
  - Static analyzer warnings (ex.: WP symbols ausentes) — apenas relevância para CI estático.
  - Paralel normalização para tipos não-event — aceitável no curto prazo.

---

## 5. Confirmação do contrato mínimo de `stage.php`

- `vana_visit_stage_bootstrap()` fornece as variáveis mínimas exigidas por `stage.php`: `visit_id`, `visit_tz`, `visit_city_ref`, `active_day`, `active_day_date`, `active_event`, `visit_status`, `lang` — portanto o contrato mínimo está presente no helper.
- Contudo, nem todos os renderers que incluem `stage.php` (REST renderers) passam atualmente exatamente o mesmo conjunto de variáveis (`active_event` vs `active_vod` discrepância). Para garantir contrato uniforme, os renderers REST devem extrair/fornecer explicitamente `active_event` (além de manter `active_vod` para compatibilidade).

---

## 6. Status final do patch

- Classificação: **MITIGADO** — unificou leitura de meta-keys e centralizou resolução do contexto (win grande). Ainda resta um trabalho pequeno e localizado para promover a mudança a **RESOLVIDO**.

---

## 7. Recomendação (ação imediata)

1. Corrigir REST renderers para fornecer `active_event` ao template (compatível com `vana_visit_stage_bootstrap()`):
   - Em `Vana_REST_Stage::handle()` e `Vana_REST_Stage_Fragment::render_event_stage()`, antes do `render_stage()` / include, adicionar `active_event` (normalizado) ao array de variáveis passadas ao template; manter `active_vod`/`vod_list` para compatibilidade.
   - Severidade: média. Risco/impacto: baixo, mudança pequena e localizada.

2. Unificar fallback timezone no `render_restore()` para usar o mesmo fallback do helper (`UTC`) ou preferir `visit_tz` do helper.
   - Severidade: média → baixa.

3. (Opcional) Ajustar CI estático com WP stubs ou excluir `includes/rest/*` do analisador para evitar os ~17 warning-false-positives.

4. Testes: adicionar um pequeno smoke-test que chama `Vana_REST_Stage::handle()` e `VisitStageResolver::resolve()` em um ambiente de teste WP para comparar saída HTML e validar que `stage.php` recebe `active_event` com mesma forma em ambos os caminhos.

Recomendação de entrega: apto para commit com follow-ups. Corrija o item (1) antes de dependências que modifiquem render logic, ou marque como follow-up de baixa-média prioridade.

---

Auditado por: equipe técnica / automação (leitura em código, 23/03/2026)
