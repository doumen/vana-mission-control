## 1. Fechamento da auditoria do Stage

Após a aplicação do helper canônico `vana_visit_stage_bootstrap()` e sua integração nos principais caminhos SSR e REST, o núcleo contratual do Stage foi harmonizado.

Status final desta frente: **RESOLVIDO**

---

## 2. O que foi resolvido

### 2.1 Bootstrap canônico
Foi criado um helper único para leitura e resolução mínima do contexto do Stage:

```php
vana_visit_stage_bootstrap( int $visit_id, array $opts = [] ): array

Esse helper passou a centralizar:

    timeline
    overrides
    timezone
    visit status
    city ref
    active event
    active day
    active day date

2.2 Meta keys canônicas

A leitura agora segue o padrão único:
Timeline

    _vana_visit_timeline_json
    fallback: _vana_visit_data

Timezone

    _vana_visit_timezone
    fallback: _vana_tz
    fallback final: UTC

2.3 SSR e REST passaram a compartilhar a mesma base

Os seguintes pontos agora utilizam o bootstrap canônico:

    VisitStageResolver
    Vana_REST_Stage
    Vana_REST_Stage_Fragment no caso item_type === event

Com isso, SSR e REST passaram a usar a mesma origem de dados para:

    timeline
    timezone
    contexto do evento ativo

2.4 Contrato do template foi alinhado

Antes, os renderers REST nem sempre expunham active_event ao template do Stage, trabalhando mais com active_vod.

Agora:

    active_event é explicitamente enviado também nos caminhos REST principais
    active_vod e vod_list foram preservados para compatibilidade

Isso reduz divergência entre SSR e REST no consumo de stage.php.
2.5 Restore timezone harmonizado

O fluxo restore deixou de depender de fallback legado isolado:
php

America/Sao_Paulo

e passou a respeitar o mesmo padrão canônico do helper.
3. Classificação final
Stage core

RESOLVIDO
Motivo

Os pontos centrais de divergência identificados na auditoria anterior foram tratados:

    leitura de timeline unificada
    leitura de timezone unificada
    resolução de contexto centralizada
    contrato active_event harmonizado nos renderers principais
    fallback de timezone no restore alinhado

4. O que permanece como follow-up baixo

Os itens abaixo não bloqueiam o fechamento desta frente:

    caminhos específicos legados em fragmentos não-event:
        vod
        gallery
        sangha

    warnings de análise estática fora de ambiente WordPress
        ex.: símbolos WP não reconhecidos pelo analisador sem stubs

    possíveis limpezas menores de legibilidade
        expressões compactas com ?? e ?:
        pequenas redundâncias locais

5. Conclusão

A divergência estrutural principal entre SSR e REST no Stage foi resolvida.

O Stage agora possui uma fonte canônica mínima para bootstrap e um contrato mais estável para renderização, reduzindo risco de mismatch entre caminhos de execução.

Frente encerrada como: RESOLVIDO