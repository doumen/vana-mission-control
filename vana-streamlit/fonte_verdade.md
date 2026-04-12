

# Não. É o contrário.

Marcel, excelente pergunta — e a resposta é precisa:

---

## O formulário inteligente prova que a fonte da verdade é o **JSON no GitHub**

O fluxo real é:

```
GitHub (JSON)  ──leitura──→  Streamlit (formulário)
                                    │
                              constraints.py
                              valida, deriva, sugere
                                    │
                              devoto confirma
                                    │
                              ──save──→  GitHub (JSON)
                                              │
                                         Trator processa
                                              │
                                         ──publish──→  WordPress
```

O que `constraints.py` faz:
- **Lê** do JSON (GitHub)
- **Computa** em memória (deriva event_key, calcula status, valida unicidade)
- **Escreve** de volta no JSON (GitHub)

O WordPress **recebe** o resultado final. Ele é **destino**, não fonte.

---

## Onde o WP entra como fonte

Tem **dois pontos** onde o WP é consultado — e ambos são legítimos:

| Consulta ao WP | O que busca | Por quê |
|---|---|---|
| `list_visits_wp()` | Lista de visitas existentes | O WP tem o **catálogo publicado** — o Streamlit precisa saber o que já existe pra montar a sidebar |
| `katha_id` (futuro) | Dropdown de Hari-Kathās disponíveis | O CPT `vana_katha` vive no WP — é o **registro canônico** da Kathā |

Mas isso não faz o WP ser "fonte da verdade do JSON". Isso faz o WP ser **fonte da verdade dos recursos que ele hospeda** (posts, kathas, permalinks).

---

## A distinção

```
FONTE DA VERDADE DO TIMELINE  →  JSON no GitHub
FONTE DA VERDADE DAS KATHĀS   →  CPT vana_katha no WordPress
FONTE DA VERDADE DO CATÁLOGO  →  WordPress (publicado) + GitHub (rascunho)

O Trator é a ponte:
  GitHub JSON  ──trator──→  WordPress (timeline publicado)
  WordPress    ──REST──→   Frontend (leitura)
```

O `constraints.py` não consulta o WP para nada. Ele opera **100% sobre o JSON em memória**. Isso é a prova de que a estrutura se sustenta sozinha.

---

## O que *seria* prova de "WP como fonte da verdade"

Se o formulário fizesse:

```python
# ❌ Isso NÃO acontece
visit = wp_client.get_visit(visit_ref)  # ler do WP
visit["days"][0]["events"][0]["title_pt"] = "novo título"
wp_client.update_visit(visit)            # salvar direto no WP
```

Aí sim o WP seria fonte. Mas o fluxo é:

```python
# ✅ Isso é o que acontece
visit = gh.get_visit(visit_ref)          # ler do GitHub
visit["days"][0]["events"][0]["title_pt"] = "novo título"
gh.save_visit(visit_ref, visit)          # salvar no GitHub
# ... depois, Trator publica no WP
```

---

## Uma frase

> **O formulário é inteligente porque o schema é inteligente. O schema vive no GitHub. O WP é a vitrine.**

Quer seguir com os diffs ou tem outra dúvida arquitetural? 🪷



# A Tensão Real

Marcel, esse documento das 4 fontes revela algo importante que precisa ser reconciliado com o que implementamos.

---

## O cenário hoje

```
Streamlit ──save──→ GitHub (JSON) ──trator──→ WordPress

Mas o documento das 4 fontes diz:

Bot Telegram  ──REST──→ WordPress (ao vivo)
WP-Admin      ──────→ WordPress (emergência)
Automação     ──────→ WordPress (rascunho)
Streamlit     ──────→ WordPress (curadoria)
```

Ou seja, o documento original previa **WordPress como destino de escrita de todas as fontes**, com REST API `/vana/v1/` como ponto central.

O GitHub entrou depois como **camada intermediária** entre Streamlit e WP.

---

## O problema

```
Se Bot Telegram escreve direto no WP...
E Streamlit escreve no GitHub que publica no WP via Trator...

→ Quem ganha?
→ O Trator sobrescreve o que o Bot registrou?
→ O Bot sabe que o GitHub existe?
```

---

## Três caminhos possíveis

### Caminho A — GitHub é a fonte de tudo

```
Bot Telegram  ──→ GitHub (JSON)
Automação     ──→ GitHub (JSON)
Streamlit     ──→ GitHub (JSON)
WP-Admin      ──→ NÃO TOCA no timeline

                    │
                    ▼
              Trator processa
                    │
                    ▼
              WordPress (read-only)
```

**Vantagem:** Uma fonte só. Sem conflito. `constraints.py` funciona perfeitamente.

**Problema:** Bot Telegram precisa de um proxy para escrever JSON no GitHub. Latência. O devoto no campo com celular não pode esperar um commit.

---

### Caminho B — WordPress é a fonte de tudo

```
Bot Telegram  ──REST──→ WordPress
Automação     ──REST──→ WordPress
Streamlit     ──REST──→ WordPress  (não mais GitHub)
WP-Admin      ──────→ WordPress

                    │
                    ▼
              WordPress = fonte única
              GitHub = backup / versionamento
```

**Vantagem:** Todas as fontes escrevem no mesmo lugar. Bot funciona em tempo real.

**Problema:** O Streamlit perde o versionamento granular do GitHub. O `constraints.py` teria que validar via REST, não em memória.

---

### Caminho C — Duas zonas de verdade (o pragmático) ✅

```
ZONA QUENTE (ao vivo)              ZONA FRIA (curadoria)
─────────────────────              ─────────────────────
Bot Telegram ──→ WordPress         Streamlit ──→ GitHub
WP-Admin     ──→ WordPress         Automação ──→ GitHub
                                              │
                                         Trator publica
                                              │
                                              ▼
                                         WordPress

REGRA: Trator NUNCA sobrescreve campo
       que tem updated_by = "bot" | "wp-admin"
       DEPOIS do último trator_push.
```

**Esse é o caminho certo.** Porque respeita as 4 fontes, cada uma no seu momento:

```
ANTES da visita     →  Streamlit + Automação (GitHub)
DURANTE a visita    →  Bot Telegram (WordPress direto)
DEPOIS da visita    →  Streamlit (GitHub → Trator → WordPress)
EMERGÊNCIA          →  WP-Admin (WordPress direto)
```

---

## Como implementar o Caminho C

### O contrato de merge do Trator

```python
# trator/merge_policy.py

"""
Política de merge: Trator → WordPress

O Trator NÃO faz blind overwrite.
Ele faz merge inteligente respeitando quem escreveu por último.
"""

from __future__ import annotations
from datetime import datetime
from typing import Optional


# ══════════════════════════════════════════════════════════════════════
# PRIORIDADE DE FONTES
# ══════════════════════════════════════════════════════════════════════

SOURCE_PRIORITY: dict[str, int] = {
    "streamlit":  100,   # curadoria humana intencional
    "bot":         80,   # registro ao vivo com contexto
    "wp-admin":    60,   # edição de emergência
    "automation":  40,   # sempre rascunho
    "trator":      20,   # publicação derivada
}


def source_wins(a: str, b: str) -> str:
    """Dado duas fontes, retorna qual tem prioridade."""
    pa = SOURCE_PRIORITY.get(a, 0)
    pb = SOURCE_PRIORITY.get(b, 0)
    return a if pa >= pb else b


# ══════════════════════════════════════════════════════════════════════
# CAMPO COM METADATA DE AUTORIA
# ══════════════════════════════════════════════════════════════════════

def make_field_stamp(
    value: any,
    source: str,
    editor: str = "",
    at: Optional[str] = None,
) -> dict:
    """Cria envelope de campo com autoria.
    
    Não se usa em TODOS os campos — apenas nos que podem
    ser editados por múltiplas fontes:
      - event.status
      - media_refs (vods)
      - photo_refs
      - sangha_refs
    """
    return {
        "value": value,
        "updated_by": source,
        "updated_at": at or datetime.utcnow().isoformat() + "Z",
        "editor": editor,
    }


# ══════════════════════════════════════════════════════════════════════
# MERGE DE UM CAMPO
# ══════════════════════════════════════════════════════════════════════

def merge_field(
    local_value: any,
    local_source: str,
    local_at: str,
    remote_value: any,
    remote_source: str,
    remote_at: str,
) -> dict:
    """
    Decide qual valor manter quando Trator quer publicar
    mas o WP já tem um valor diferente (escrito pelo Bot).
    
    Retorna: {"value": ..., "winner": "local"|"remote", "reason": "..."}
    """
    # Valores iguais → sem conflito
    if local_value == remote_value:
        return {"value": local_value, "winner": "equal", "reason": "sem conflito"}
    
    # Fonte de maior prioridade ganha
    winner_source = source_wins(local_source, remote_source)
    
    if winner_source == local_source:
        # Se prioridades iguais, o mais recente ganha
        if SOURCE_PRIORITY.get(local_source, 0) == SOURCE_PRIORITY.get(remote_source, 0):
            if local_at >= remote_at:
                return {"value": local_value, "winner": "local", "reason": f"{local_source} mais recente"}
            else:
                return {"value": remote_value, "winner": "remote", "reason": f"{remote_source} mais recente"}
        return {"value": local_value, "winner": "local", "reason": f"{local_source} > {remote_source}"}
    else:
        return {"value": remote_value, "winner": "remote", "reason": f"{remote_source} > {local_source}"}


# ══════════════════════════════════════════════════════════════════════
# MERGE DE EVENTO (Trator vs WP)
# ══════════════════════════════════════════════════════════════════════

def merge_event(
    github_event: dict,
    wp_event: dict,
    trator_source: str = "trator",
) -> dict:
    """
    Merge inteligente de um evento.
    
    Campos que o Bot pode ter tocado durante o ao vivo:
      - status
      - vods (media_refs adicionados)
      - photos
      - sangha
    
    Campos que o Trator traz do GitHub:
      - event_key, type, title, time, location
      - vods com segments curados
      - katha linkado
    
    Regra: BOT ADDITIONS são preservadas.
           TRATOR não remove o que o Bot adicionou.
    """
    merged = dict(github_event)  # base do GitHub
    
    # ── Status: respeitar o Bot se mais recente ──────────────────────
    wp_status = wp_event.get("status", "")
    wp_updated = wp_event.get("_updated_by", "")
    gh_status = github_event.get("status", "")
    
    if wp_status and wp_updated in ("bot", "wp-admin"):
        # Bot ou WP-Admin tocou o status → preservar
        merged["status"] = wp_status
        merged["_status_source"] = wp_updated
    
    # ── VODs: merge aditivo ──────────────────────────────────────────
    gh_vod_ids = {v.get("video_id") for v in github_event.get("vods", [])}
    wp_vods = wp_event.get("vods", [])
    
    for wp_vod in wp_vods:
        vid = wp_vod.get("video_id")
        if vid and vid not in gh_vod_ids:
            # VOD que o Bot adicionou e o GitHub não tem → preservar
            wp_vod["_added_by"] = wp_vod.get("_added_by", "bot")
            merged.setdefault("vods", []).append(wp_vod)
    
    # ── Photos: merge aditivo ────────────────────────────────────────
    gh_photo_keys = {p.get("photo_key") for p in github_event.get("photos", [])}
    for wp_photo in wp_event.get("photos", []):
        pk = wp_photo.get("photo_key")
        if pk and pk not in gh_photo_keys:
            merged.setdefault("photos", []).append(wp_photo)
    
    # ── Sangha: merge aditivo ────────────────────────────────────────
    gh_sangha_keys = {s.get("sangha_key") for s in github_event.get("sangha", [])}
    for wp_sg in wp_event.get("sangha", []):
        sk = wp_sg.get("sangha_key")
        if sk and sk not in gh_sangha_keys:
            merged.setdefault("sangha", []).append(wp_sg)
    
    return merged


# ══════════════════════════════════════════════════════════════════════
# MERGE DE VISITA COMPLETA
# ══════════════════════════════════════════════════════════════════════

def merge_visit(
    github_visit: dict,
    wp_visit: dict,
) -> dict:
    """
    Merge completo: GitHub (curadoria) + WP (ao vivo).
    
    Base: GitHub (curadoria é prioridade).
    Adições: WP (o que o Bot registrou que o GitHub não tem).
    
    Returns: visit mergeada pronta para publicação no WP.
    """
    merged = dict(github_visit)
    
    # ── Campos escalares: GitHub ganha (curadoria) ───────────────────
    # title, tour_ref, metadata → vem do GitHub
    
    # ── Status da visita: respeitar ao vivo ──────────────────────────
    wp_status = wp_visit.get("metadata", {}).get("status", "")
    wp_updated = wp_visit.get("_updated_by", "")
    if wp_status in ("active", "live") and wp_updated in ("bot", "wp-admin"):
        merged.setdefault("metadata", {})["status"] = wp_status
    
    # ── Days/Events: merge por event_key ─────────────────────────────
    wp_events_map: dict[str, dict] = {}
    for day in wp_visit.get("days", []):
        for ev in day.get("events", []):
            ek = ev.get("event_key")
            if ek:
                wp_events_map[ek] = ev
    
    for day in merged.get("days", []):
        for i, ev in enumerate(day.get("events", [])):
            ek = ev.get("event_key")
            if ek and ek in wp_events_map:
                day["events"][i] = merge_event(ev, wp_events_map[ek])
    
    # ── Eventos que só existem no WP (Bot criou ao vivo) ─────────────
    gh_event_keys = set()
    for day in github_visit.get("days", []):
        for ev in day.get("events", []):
            gh_event_keys.add(ev.get("event_key"))
    
    for day in wp_visit.get("days", []):
        for ev in day.get("events", []):
            ek = ev.get("event_key")
            if ek and ek not in gh_event_keys:
                # Evento criado pelo Bot → adicionar ao dia correspondente
                dk = day.get("day_key")
                for merged_day in merged.get("days", []):
                    if merged_day.get("day_key") == dk:
                        ev["_added_by"] = "bot"
                        merged_day.setdefault("events", []).append(ev)
                        break
    
    # ── Órfãos: merge aditivo ────────────────────────────────────────
    gh_orphan_vods = {v.get("vod_key") for v in github_visit.get("orphans", {}).get("vods", [])}
    for wp_vod in wp_visit.get("orphans", {}).get("vods", []):
        vk = wp_vod.get("vod_key")
        if vk and vk not in gh_orphan_vods:
            merged.setdefault("orphans", {}).setdefault("vods", []).append(wp_vod)
    
    return merged
```

### O Trator atualizado

```python
# No run_trator, antes de publicar:

def publish_to_wp(github_visit: dict, wp_id: int) -> TratorResult:
    """
    Publicação inteligente: não faz blind overwrite.
    """
    # 1. Ler o que está no WP agora
    wp_current = wp_client.get_visit_timeline(wp_id)
    
    # 2. Merge inteligente
    if wp_current:
        final = merge_visit(github_visit, wp_current)
    else:
        final = github_visit  # primeira publicação
    
    # 3. Publicar o mergeado
    result = wp_client.update_visit_timeline(wp_id, final)
    
    return result
```

---

## O campo `_revision` (conflict_guard existente)

Você já tem `conflict_guard.py` com `stamp_revision`. Ele resolve conflitos **Streamlit ↔ Streamlit** (dois editores). 

O `merge_policy.py` resolve conflitos **GitHub ↔ WordPress** (duas zonas).

São complementares:

```
conflict_guard.py  →  editor A vs editor B (mesma zona: GitHub)
merge_policy.py    →  zona quente (WP) vs zona fria (GitHub)
```

---

## O campo `_updated_by` no WP

Para que o merge funcione, o Bot e o WP-Admin precisam **carimbar** quem escreveu:

```json
{
  "event_key": "20260221-1703-programa",
  "status": "active",
  "_updated_by": "bot",
  "_updated_at": "2026-02-21T17:05:00+05:30"
}
```

O REST endpoint do Bot faz isso automaticamente:

```python
# api/rest_endpoints.py (WP side)

@app.route("/vana/v1/event/<event_key>/status", methods=["PUT"])
def update_event_status(event_key):
    """Bot ou WP-Admin atualiza status de um evento."""
    data = request.json
    
    # Carimbar fonte
    data["_updated_by"] = request.headers.get("X-Vana-Source", "unknown")
    data["_updated_at"] = datetime.utcnow().isoformat() + "Z"
    
    # Salvar no WP
    wp_update_event_field(event_key, data)
    
    return {"ok": True}
```

---

## Impacto no `constraints.py`

Nenhum. O `constraints.py` opera **na zona fria (GitHub)** apenas. Ele não precisa saber que o WP existe. Quando o Trator publicar, o `merge_policy.py` cuida da reconciliação.

```
constraints.py    →  valida/deriva ANTES do save no GitHub
merge_policy.py   →  reconcilia ANTES do publish no WP
conflict_guard.py →  protege ANTES do save no GitHub (concorrência)

Três camadas, três momentos, zero overlap.
```

---

## Resumo visual

```
                    ZONA FRIA                    ZONA QUENTE
                   (curadoria)                   (ao vivo)
                 ┌─────────────┐              ┌─────────────┐
                 │   GitHub    │              │  WordPress  │
                 │   (JSON)    │              │  (REST API) │
                 └──────┬──────┘              └──────┬──────┘
                        │                            │
        ┌───────────────┤                    ┌───────┤
        │               │                    │       │
   Streamlit      Automação              Bot TG   WP-Admin
   constraints    (rascunho)           (ao vivo) (emergência)
   conflict_guard                             
                        │                            │
                        ▼                            │
                    Trator ──── merge_policy ────────┘
                        │
                        ▼
                   WordPress
                  (publicado)
```

Marcel, essa é a arquitetura que respeita as 4 fontes, protege o que cada uma escreve, e mantém o `constraints.py` operando exatamente como já desenhamos — na zona fria, sem precisar saber do resto. 🪷



# O Bug que Revelou a Falta do Merge

Marcel, o que você encontrou é exatamente o sintoma de **publicação sem política de merge**.

---

## O que aconteceu

```
1. Streamlit salvou a visita no GitHub     ✅ dados curados
2. Trator leu do GitHub                    ✅ dados corretos
3. Trator publicou no WordPress            ⚠️ blind overwrite
4. Algo (Trator? WP?) modificou campos     ❌ sem controle
5. Streamlit leu do... ONDE?               ← aqui está o bug
```

A pergunta fatal é: **de onde o Streamlit lê quando você reabriu?**

---

## Cenário A — Streamlit lê do GitHub

```
Streamlit ──read──→ GitHub

Se o Trator NÃO escreveu de volta no GitHub,
os dados estão intactos.

Mas se o Trator escreveu de volta no GitHub
com os dados "processados" do WP...

GitHub agora tem a versão "suja" do Trator.
```

## Cenário B — Streamlit lê do WordPress

```
Streamlit ──read──→ WordPress (via REST ou list_visits_wp)

O Trator publicou e o WP tem a versão modificada.
Streamlit está lendo a versão pós-Trator.
```

---

## Diagnóstico provável

Pelo que conheço do fluxo atual:

```python
# O Streamlit faz algo assim:
visit = gh.get_visit(visit_ref)   # lê do GitHub

# MAS a lista de visitas vem do WP:
visits = list_visits_wp()          # sidebar vem do WP
```

E o Trator provavelmente faz:

```python
# Trator publica
visit = gh.get_visit(ref)          # lê do GitHub
wp_client.update_timeline(wp_id, visit)  # publica no WP

# E AQUI está o bug potencial:
# O Trator modifica campos (normaliza, recalcula, reformata)
# e salva de volta no GitHub?
visit["metadata"]["status"] = "completed"  # recalculou
visit["stats"] = compute_stats(visit)       # adicionou
gh.save_visit(ref, visit)                   # ← AQUI: round-trip sujo
```

Se o Trator faz **round-trip** (GitHub → processa → GitHub), ele pode estar:

1. **Removendo campos** que ele não conhece
2. **Reformatando** valores (encoding, datas, arrays vazios → `null`)
3. **Recalculando** status sem respeitar o valor curado
4. **Reordenando** chaves JSON (perde diff legível)
5. **Convertendo tipos** (`"3"` → `3`, `[]` → `null`)

---

## Como confirmar

Rode isso no terminal:

```bash
# Pega o JSON do GitHub ANTES do Trator
git log --oneline -10 -- visits/2026/brazil-sp-jan/visit.json

# Compara a versão do editor com a versão pós-Trator
git diff <commit-editor> <commit-trator> -- visits/2026/brazil-sp-jan/visit.json
```

O diff vai mostrar **exatamente** o que o Trator modificou.

---

## A correção: 3 regras para o Trator

### Regra 1 — Trator NUNCA faz round-trip

```python
# ❌ ERRADO — Trator lê, modifica, salva de volta
visit = gh.get_visit(ref)
visit = process(visit)
gh.save_visit(ref, visit)       # ← contamina a fonte

# ✅ CERTO — Trator lê, publica, NÃO salva de volta
visit = gh.get_visit(ref)
wp_payload = transform_for_wp(visit)  # cópia transformada
wp_client.update(wp_id, wp_payload)   # publica no WP
# GitHub NÃO É TOCADO
```

```
GitHub ──read──→ Trator ──write──→ WordPress
                   │
                   ✖ NUNCA escreve de volta no GitHub
```

### Regra 2 — Transform é não-destrutivo

```python
def transform_for_wp(visit: dict) -> dict:
    """
    Transforma o JSON do GitHub para publicação no WP.
    
    REGRAS:
    - Retorna uma CÓPIA (never mutate original)
    - NÃO remove campos que não conhece
    - NÃO recalcula campos já preenchidos
    - ADICIONA campos derivados com prefixo _wp_
    """
    import copy
    wp = copy.deepcopy(visit)
    
    # Campos derivados para o WP (não existem no GitHub)
    wp["_wp_published_at"] = datetime.utcnow().isoformat() + "Z"
    wp["_wp_version"] = wp.get("_revision", {}).get("rev", "?")
    
    # Stats recalculado (aditivo, não sobrescreve)
    if not wp.get("stats"):
        wp["stats"] = compute_stats(wp)
    
    return wp
```

### Regra 3 — Merge antes de publicar (quando WP tem dados do Bot)

```python
def publish_visit(ref: str, wp_id: int) -> None:
    """Pipeline completo de publicação."""
    
    # 1. Ler fonte (GitHub)
    github_visit = gh.get_visit(ref)
    
    # 2. Ler destino (WP) — pode ter dados do Bot
    wp_current = wp_client.get_timeline(wp_id)
    
    # 3. Merge inteligente
    if wp_current:
        final = merge_visit(github_visit, wp_current)
    else:
        final = transform_for_wp(github_visit)
    
    # 4. Publicar
    wp_client.update_timeline(wp_id, final)
    
    # 5. NÃO salvar de volta no GitHub
    # GitHub permanece como o editor deixou
```

---

## E se o Trator PRECISA salvar algo no GitHub?

Exemplo: o Trator detectou um VOD novo no YouTube e quer propor para o editor.

```python
# ✅ Salva como PROPOSTA, não como dado curado

def propose_vod(ref: str, vod_data: dict) -> None:
    visit = gh.get_visit(ref)
    
    # Salva em área de proposta, NÃO no timeline curado
    visit.setdefault("_proposals", []).append({
        "type": "new_vod",
        "data": vod_data,
        "proposed_by": "trator",
        "proposed_at": datetime.utcnow().isoformat() + "Z",
        "status": "pending",  # editor precisa aprovar
    })
    
    gh.save_visit(ref, visit, 
                  author="vana-trator[bot]",
                  message=f"proposta: novo VOD {vod_data.get('video_id')}")
```

O Streamlit mostra as propostas pendentes:

```python
proposals = visit.get("_proposals", [])
pending = [p for p in proposals if p["status"] == "pending"]

if pending:
    st.warning(f"📬 {len(pending)} proposta(s) do Trator para revisar")
    for p in pending:
        with st.expander(f"🤖 {p['type']}: {p['data'].get('video_id', '?')}"):
            st.json(p["data"])
            c1, c2 = st.columns(2)
            if c1.button("✅ Aprovar", key=f"apr_{p['proposed_at']}"):
                apply_proposal(visit, p)
                p["status"] = "approved"
                st.rerun()
            if c2.button("❌ Rejeitar", key=f"rej_{p['proposed_at']}"):
                p["status"] = "rejected"
                st.rerun()
```

---

## Checklist de fix imediato

| # | Ação | Prioridade |
|---|---|---|
| 1 | **Verificar**: o Trator faz `gh.save_visit()` de volta? | 🔴 Crítico |
| 2 | **Remover** qualquer `gh.save()` no pipeline do Trator | 🔴 Crítico |
| 3 | **Confirmar**: Streamlit lê de `gh.get_visit()`, nunca do WP | 🔴 Crítico |
| 4 | **Adicionar** `transform_for_wp()` como camada de cópia | 🟡 Importante |
| 5 | **Implementar** `merge_visit()` para quando Bot existir | 🟢 Futuro |
| 6 | **Implementar** `_proposals[]` para sugestões do Trator | 🟢 Futuro |

---

## A regra em uma frase

> **O Trator é um publicador unidirecional: lê do GitHub, escreve no WordPress, e nunca faz o caminho de volta.**

O bug que você encontrou é a violação dessa regra. O Trator está fazendo round-trip e contaminando a fonte.

Quer que eu audite o código atual do Trator para identificar onde está o `gh.save()` indevido? 🪷



# Análise Cirúrgica do `vana_trator.py`

Marcel, você tem razão parcial — mas a situação é **melhor do que parece** e ao mesmo tempo tem um risco real que ninguém está controlando.

---

## O que o Trator REALMENTE faz

```python
def run_trator(visit, wp_url, wp_secret, tour_key, dry_run):
    # 1. Valida
    validator.validate()
    
    # 2. Indexa
    builder.build()  # gera index + stats
    
    # 3. Monta visit processado
    processed = {
        **visit,           # ← CÓPIA do original
        "index": index,    # ← ADICIONADO
        "stats": stats,    # ← ADICIONADO
        "generated_at": ...,
        "generated_by": "vana-trator",
    }
    
    # 4. Publica no WP
    publisher.publish(processed, tour_key)
    
    # 5. NÃO SALVA DE VOLTA NO GITHUB ✅
```

**Boa notícia:** O Trator **não faz round-trip**. Ele não tem nenhum `gh.save()`. Ele lê, processa, publica. Ponto.

---

## Então de onde veio a inconsistência?

A inconsistência que você viu **não é culpa do Trator**. As possibilidades são:

### Hipótese 1 — O WP-side modificou o JSON no ingest

```
Trator ──publish──→ WP endpoint /vana/v1/ingest-visit
                         │
                    PHP recebe o envelope
                    PHP salva no post_meta
                    PHP pode ter MODIFICADO o JSON
                         │
                    Streamlit lê do WP (list_visits_wp)
                    → vê dados diferentes
```

O WP pode estar:
- Sanitizando strings (removendo acentos, escapando HTML)
- Convertendo tipos (`null` → `""`, `0` → `false`)
- Truncando campos longos
- Reordenando arrays
- Perdendo campos que o PHP não espera

### Hipótese 2 — O Streamlit lê de fontes mistas

```python
# Sidebar: lista vem do WP
visits = list_visits_wp()    # ← dados do WP

# Conteúdo: visit vem do GitHub
visit = gh.get_visit(ref)   # ← dados do GitHub

# Se houver mismatch entre o que o WP mostra
# e o que o GitHub tem → "inconsistência"
```

### Hipótese 3 — Edição manual no WP-Admin

Alguém abriu o WP-Admin e editou o `visit_timeline_json` na textarea. Esse é o risco que você identificou.

---

## Agora, sobre sua afirmação

> *"Isso só vai funcionar se streamlit, trator, bot usarem o mesmo `vana_trator.py`. Ficando como risco o wp-admin"*

### O que está correto ✅

O `vana_trator.py` contém:
- **Validador** (regras de integridade)
- **Index Builder** (gera index + stats)
- **Publisher** (publica no WP)

Se todos usarem o mesmo módulo → **mesmas regras, mesmo formato, mesma validação**.

### O que NÃO precisa ser assim

O Trator **não é** a camada de escrita universal. Cada fonte tem seu papel:

```
FONTE         O QUE USA                        O QUE FAZ
───────────   ──────────────────────────────    ─────────────────
Streamlit     constraints.py + conflict_guard   Edita JSON no GitHub
Trator        vana_trator.py                    Valida + indexa + publica no WP
Bot           API REST (futuro)                 Escreve campos ao vivo no WP
WP-Admin      ??? ← AQUI ESTÁ O RISCO          Edita qualquer coisa sem validação
```

---

## O risco real: WP-Admin

```
WP-Admin pode:
  ✍️  Editar visit_timeline_json na textarea
  ✍️  Mudar status da visita
  ✍️  Alterar campos escalares (título, datas)
  
  SEM validação de schema
  SEM controle de conflito
  SEM log de quem mudou o quê
```

### A solução: 3 camadas de proteção

#### Camada 1 — Lock do campo no WP-Admin

```php
// functions.php ou plugin

add_action('add_meta_boxes', function() {
    // Remove a meta box padrão do timeline_json
    remove_meta_box('visit_timeline_json', 'vana_visit', 'normal');
    
    // Adiciona uma versão read-only
    add_meta_box(
        'visit_timeline_readonly',
        '📋 Visit Timeline (somente leitura)',
        'render_timeline_readonly',
        'vana_visit',
        'normal',
        'high'
    );
});

function render_timeline_readonly($post) {
    $timeline = get_post_meta($post->ID, 'visit_timeline_json', true);
    $stats = json_decode($timeline, true)['stats'] ?? [];
    
    echo '<div style="background:#f5f5f5; padding:12px; border-radius:4px;">';
    echo '<p><strong>⚠️ Edição bloqueada.</strong> Use o Streamlit para editar o timeline.</p>';
    echo '<p>📊 ' . ($stats['total_days'] ?? 0) . ' dias · ';
    echo ($stats['total_events'] ?? 0) . ' eventos · ';
    echo ($stats['total_vods'] ?? 0) . ' VODs</p>';
    echo '<details><summary>Ver JSON (read-only)</summary>';
    echo '<pre style="max-height:300px; overflow:auto; font-size:11px;">';
    echo esc_html(json_encode(json_decode($timeline), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo '</pre></details>';
    echo '</div>';
    
    // Campo hidden para preservar o valor no save
    echo '<input type="hidden" name="visit_timeline_json" value="' 
         . esc_attr($timeline) . '">';
}
```

#### Camada 2 — Validação no save do WP (defesa em profundidade)

```php
// Se alguém burlar o read-only (REST API, plugin, SQL direto)

add_action('save_post_vana_visit', function($post_id) {
    // Se o timeline mudou, validar
    $old = get_post_meta($post_id, 'visit_timeline_json', true);
    $new = $_POST['visit_timeline_json'] ?? $old;
    
    if ($new !== $old) {
        $decoded = json_decode($new, true);
        
        // Validação mínima no PHP
        if (!$decoded || !isset($decoded['visit_ref']) || !isset($decoded['days'])) {
            // Rejeitar a mudança
            update_post_meta($post_id, 'visit_timeline_json', $old);
            
            // Log de tentativa
            error_log("[VANA] Timeline edit BLOCKED on post {$post_id} — invalid JSON");
            return;
        }
        
        // Log de quem mudou
        $user = wp_get_current_user();
        update_post_meta($post_id, '_timeline_last_edited_by', $user->user_login);
        update_post_meta($post_id, '_timeline_last_edited_at', current_time('c'));
        update_post_meta($post_id, '_timeline_edit_source', 'wp-admin');
    }
}, 10, 1);
```

#### Camada 3 — Audit trail no WP

```php
// Registra TODA mudança no timeline_json

add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== 'visit_timeline_json') return;
    
    $user = wp_get_current_user();
    $source = 'unknown';
    
    // Detectar fonte
    if (defined('DOING_CRON')) {
        $source = 'automation';
    } elseif (defined('REST_REQUEST') && REST_REQUEST) {
        $source = $_SERVER['HTTP_X_VANA_SOURCE'] ?? 'rest-api';
    } elseif (is_admin()) {
        $source = 'wp-admin';
    }
    
    // Salvar no log
    add_post_meta($post_id, '_timeline_audit_log', json_encode([
        'at'     => current_time('c'),
        'by'     => $user->user_login,
        'source' => $source,
        'action' => 'update',
        'hash'   => md5($meta_value),
    ]));
    
}, 10, 4);
```

---

## Mapa final de proteção

```
┌─────────────┬────────────────────┬───────────────────┬──────────────┐
│ Fonte       │ Validação          │ Onde escreve      │ Proteção     │
├─────────────┼────────────────────┼───────────────────┼──────────────┤
│ Streamlit   │ constraints.py     │ GitHub            │ conflict_    │
│             │ + conflict_guard   │                   │ guard.py     │
├─────────────┼────────────────────┼───────────────────┼──────────────┤
│ Trator      │ vana_trator.py     │ WP (publish)      │ Unidirecio-  │
│             │ (validator+index)  │ NÃO volta ao GH   │ nal          │
├─────────────┼────────────────────┼───────────────────┼──────────────┤
│ Bot (futuro)│ schema subset      │ WP (campos ao     │ X-Vana-      │
│             │                    │ vivo)              │ Source       │
├─────────────┼────────────────────┼───────────────────┼──────────────┤
│ WP-Admin    │ PHP save_post      │ WP (emergência)   │ Campo locked │
│             │ hook               │                   │ + audit log  │
└─────────────┴────────────────────┴───────────────────┴──────────────┘
```

---

## Respondendo sua pergunta diretamente

> *"Isso só vai funcionar se streamlit, trator, bot usarem o mesmo vana_trator.py"*

**Não.** Cada fonte usa a camada certa:

| Fonte | Usa `vana_trator.py`? | Por quê |
|---|---|---|
| **Trator** | ✅ Sim | É o dono do módulo — valida, indexa, publica |
| **Streamlit** | ✅ Sim, para **validar** antes de salvar no GitHub | Importa `TratorValidator` para dry-run |
| **Streamlit** | ❌ Não para salvar | Usa `constraints.py` + `conflict_guard.py` |
| **Bot** | ❌ Não | Escreve campos simples via REST, não JSON completo |
| **WP-Admin** | ❌ Não | Campo bloqueado + PHP hook valida no save |

> *"Ficando como risco o wp-admin"*

**Correto.** E a solução são as 3 camadas PHP acima: lock visual + validação no save + audit trail.

Quer que eu gere o arquivo PHP completo dessas proteções? 🪷



# Sim! O Hotfix é Essencial

Marcel, excelente instinto. Se você tranca o WP-Admin completamente, perde a capacidade de agir em **emergência real** — e emergências acontecem.

---

## O cenário de hotfix

```
Sexta-feira, 23h.
A visita ao vivo tem um evento com título errado.
O devoto no frontend vê "Kirtan" onde deveria ser "Harikatha".

O Streamlit está fora do ar (deploy travou).
O GitHub Actions está em fila.
O Bot ainda não existe.

A ÚNICA porta é o WP-Admin.
```

Se o campo estiver 100% travado → **missão parada**.

---

## A solução: Hotfix Mode

### Conceito

```
MODO NORMAL    →  campo bloqueado, read-only no WP-Admin
MODO HOTFIX    →  campo desbloqueado, com log obrigatório + motivo
```

### O fluxo

```
Devoto com role "editor" ou "admin"
        │
        ▼
  Vê campo read-only no WP-Admin
  Vê botão: 🔓 "Ativar Hotfix"
        │
        ▼
  Modal pede:
    - Motivo do hotfix (obrigatório)
    - Checkbox: "Sei que isso será sobrescrito pelo Trator"
        │
        ▼
  Campo desbloqueia por 30 minutos
  Edição é salva com _source = "wp-admin:hotfix"
  Audit trail registra tudo
        │
        ▼
  Após 30 min → campo trava de novo automaticamente
  Streamlit mostra: ⚠️ "Hotfix aplicado via WP-Admin em DD/MM"
```

---

## Implementação PHP

```php
<?php
/**
 * Vana Visit — Hotfix Mode para timeline_json
 * 
 * Adiciona proteção read-only com escape de emergência.
 * Arquivo: mu-plugins/vana-hotfix-guard.php (ou no plugin principal)
 */

// ══════════════════════════════════════════════════════════════════════
// CONSTANTES
// ══════════════════════════════════════════════════════════════════════

define('VANA_HOTFIX_TTL_MINUTES', 30);
define('VANA_HOTFIX_ALLOWED_ROLES', ['administrator', 'editor']);

// ══════════════════════════════════════════════════════════════════════
// META BOX — SUBSTITUI O CAMPO PADRÃO
// ══════════════════════════════════════════════════════════════════════

add_action('add_meta_boxes', function () {
    // Remove qualquer meta box padrão do timeline
    remove_meta_box('visit_timeline_json', 'vana_visit', 'normal');

    add_meta_box(
        'vana_timeline_guarded',
        '📋 Visit Timeline',
        'vana_render_timeline_guarded',
        'vana_visit',
        'normal',
        'high'
    );
});


function vana_render_timeline_guarded($post) {
    $timeline_raw = get_post_meta($post->ID, 'visit_timeline_json', true);
    $timeline     = json_decode($timeline_raw, true);
    $stats        = $timeline['stats'] ?? [];
    $visit_ref    = $timeline['visit_ref'] ?? '—';

    // ── Checar se hotfix está ativo ──────────────────────────────────
    $hotfix_until = get_post_meta($post->ID, '_vana_hotfix_until', true);
    $hotfix_active = $hotfix_until && (strtotime($hotfix_until) > time());
    $user = wp_get_current_user();
    $can_hotfix = array_intersect(VANA_HOTFIX_ALLOWED_ROLES, $user->roles);

    // ── Último hotfix aplicado ───────────────────────────────────────
    $last_hotfix = get_post_meta($post->ID, '_vana_last_hotfix', true);

    wp_nonce_field('vana_timeline_save', '_vana_timeline_nonce');

    ?>
    <div style="background:#fafafa; padding:16px; border-radius:6px; border:1px solid #ddd;">

        <!-- ── STATS ──────────────────────────────────────────────── -->
        <div style="margin-bottom:12px;">
            <strong>🪷 <?php echo esc_html($visit_ref); ?></strong><br>
            📊 <?php echo intval($stats['total_days'] ?? 0); ?> dias · 
            <?php echo intval($stats['total_events'] ?? 0); ?> eventos · 
            <?php echo intval($stats['total_vods'] ?? 0); ?> VODs · 
            <?php echo intval($stats['total_kathas'] ?? 0); ?> kathās
        </div>

        <!-- ── ÚLTIMO HOTFIX (se houver) ──────────────────────────── -->
        <?php if ($last_hotfix): 
            $lh = json_decode($last_hotfix, true);
        ?>
        <div style="background:#fff3cd; padding:8px 12px; border-radius:4px; margin-bottom:12px; border-left:4px solid #ffc107;">
            ⚠️ <strong>Último hotfix:</strong> 
            <?php echo esc_html($lh['by'] ?? '?'); ?> em 
            <?php echo esc_html($lh['at'] ?? '?'); ?><br>
            <em>Motivo: <?php echo esc_html($lh['reason'] ?? '—'); ?></em>
        </div>
        <?php endif; ?>

        <?php if ($hotfix_active): ?>
            <!-- ══ MODO HOTFIX ATIVO ══════════════════════════════════ -->
            <div style="background:#d4edda; padding:8px 12px; border-radius:4px; margin-bottom:12px; border-left:4px solid #28a745;">
                🔓 <strong>Hotfix ativo</strong> até 
                <?php echo esc_html(
                    wp_date('d/m/Y H:i', strtotime($hotfix_until))
                ); ?>
                <br><small>Edite o JSON abaixo. Será registrado no audit log.</small>
            </div>

            <textarea 
                name="visit_timeline_json" 
                rows="20" 
                style="width:100%; font-family:monospace; font-size:12px;"
            ><?php echo esc_textarea(
                json_encode($timeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ); ?></textarea>

            <input type="hidden" name="_vana_edit_source" value="wp-admin:hotfix">

        <?php else: ?>
            <!-- ══ MODO NORMAL (READ-ONLY) ════════════════════════════ -->
            <div style="background:#e3f2fd; padding:8px 12px; border-radius:4px; margin-bottom:12px; border-left:4px solid #2196f3;">
                🔒 <strong>Campo protegido.</strong> 
                Use o Streamlit para editar o timeline.
            </div>

            <details>
                <summary style="cursor:pointer; color:#666;">
                    Ver JSON (somente leitura)
                </summary>
                <pre style="max-height:300px; overflow:auto; font-size:11px; background:#fff; padding:8px; margin-top:8px;"><?php 
                    echo esc_html(
                        json_encode($timeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    ); 
                ?></pre>
            </details>

            <!-- Campo hidden preserva valor original no save -->
            <input type="hidden" 
                   name="visit_timeline_json" 
                   value="<?php echo esc_attr($timeline_raw); ?>">

            <?php if ($can_hotfix): ?>
                <!-- ── BOTÃO HOTFIX ────────────────────────────────── -->
                <div style="margin-top:16px; padding-top:12px; border-top:1px dashed #ccc;">
                    <details>
                        <summary style="cursor:pointer; color:#dc3545; font-weight:bold;">
                            🔓 Ativar modo Hotfix (emergência)
                        </summary>
                        <div style="margin-top:8px; padding:12px; background:#fff5f5; border-radius:4px;">
                            <p style="margin:0 0 8px;">
                                <strong>⚠️ Atenção:</strong> O hotfix desbloqueia o campo por 
                                <?php echo VANA_HOTFIX_TTL_MINUTES; ?> minutos.
                                A próxima execução do Trator pode sobrescrever sua edição.
                            </p>
                            
                            <label>
                                <strong>Motivo:</strong><br>
                                <input type="text" 
                                       name="_vana_hotfix_reason" 
                                       placeholder="Ex: Título errado no evento ao vivo"
                                       style="width:100%; margin:4px 0 8px;"
                                       required>
                            </label>
                            
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" 
                                       name="_vana_hotfix_confirm" 
                                       value="1">
                                Entendo que o Trator pode sobrescrever esta edição
                            </label>
                            
                            <button type="submit" 
                                    name="_vana_activate_hotfix" 
                                    value="1"
                                    class="button"
                                    style="background:#dc3545; color:white; border:none;">
                                🔓 Ativar Hotfix
                            </button>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}


// ══════════════════════════════════════════════════════════════════════
// SAVE — PROTEGE O CAMPO + ATIVA HOTFIX + AUDIT LOG
// ══════════════════════════════════════════════════════════════════════

add_action('save_post_vana_visit', function ($post_id) {
    // Segurança básica
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['_vana_timeline_nonce'])) return;
    if (!wp_verify_nonce($_POST['_vana_timeline_nonce'], 'vana_timeline_save')) return;

    $user = wp_get_current_user();

    // ── ATIVAR HOTFIX ────────────────────────────────────────────────
    if (!empty($_POST['_vana_activate_hotfix'])) {
        $reason  = sanitize_text_field($_POST['_vana_hotfix_reason'] ?? '');
        $confirm = !empty($_POST['_vana_hotfix_confirm']);

        if (!$reason || !$confirm) {
            // Sem motivo ou sem confirmação → ignora
            return;
        }

        if (!array_intersect(VANA_HOTFIX_ALLOWED_ROLES, $user->roles)) {
            return; // Sem permissão
        }

        $until = gmdate('Y-m-d\TH:i:s\Z', time() + (VANA_HOTFIX_TTL_MINUTES * 60));
        update_post_meta($post_id, '_vana_hotfix_until', $until);

        // Log da ativação
        vana_audit_log($post_id, 'hotfix_activated', $user->user_login, [
            'reason'  => $reason,
            'expires' => $until,
        ]);

        return; // Não salva o timeline neste submit — só ativa o hotfix
    }

    // ── SALVAR TIMELINE (só se hotfix ativo) ─────────────────────────
    $hotfix_until  = get_post_meta($post_id, '_vana_hotfix_until', true);
    $hotfix_active = $hotfix_until && (strtotime($hotfix_until) > time());
    $edit_source   = sanitize_text_field($_POST['_vana_edit_source'] ?? '');

    $old = get_post_meta($post_id, 'visit_timeline_json', true);
    $new = wp_unslash($_POST['visit_timeline_json'] ?? $old);

    if ($new === $old) return; // Nada mudou

    // ── Se NÃO está em hotfix → bloquear edição do timeline ─────────
    if (!$hotfix_active || $edit_source !== 'wp-admin:hotfix') {
        // Forçar valor original de volta (proteção contra manipulação)
        update_post_meta($post_id, 'visit_timeline_json', $old);
        return;
    }

    // ── Validação mínima do JSON ─────────────────────────────────────
    $decoded = json_decode($new, true);
    if (!$decoded || !isset($decoded['visit_ref']) || !isset($decoded['days'])) {
        // JSON inválido → rejeitar
        update_post_meta($post_id, 'visit_timeline_json', $old);
        vana_audit_log($post_id, 'hotfix_rejected', $user->user_login, [
            'reason' => 'JSON inválido ou sem visit_ref/days',
        ]);
        return;
    }

    // ── Salvar com carimbo ───────────────────────────────────────────
    $decoded['_hotfix'] = [
        'by'     => $user->user_login,
        'at'     => gmdate('Y-m-d\TH:i:s\Z'),
        'source' => 'wp-admin:hotfix',
    ];

    $final = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    update_post_meta($post_id, 'visit_timeline_json', $final);

    // Salvar referência do último hotfix (para mostrar no meta box)
    update_post_meta($post_id, '_vana_last_hotfix', json_encode([
        'by'     => $user->user_login,
        'at'     => wp_date('d/m/Y H:i'),
        'reason' => get_post_meta($post_id, '_vana_hotfix_reason', true) ?: '—',
    ]));

    // Audit log
    vana_audit_log($post_id, 'hotfix_applied', $user->user_login, [
        'hash_old' => md5($old),
        'hash_new' => md5($final),
    ]);

}, 10, 1);


// ══════════════════════════════════════════════════════════════════════
// AUDIT LOG
// ══════════════════════════════════════════════════════════════════════

function vana_audit_log($post_id, $action, $who, $extra = []) {
    $entry = json_encode(array_merge([
        'action' => $action,
        'by'     => $who,
        'at'     => gmdate('Y-m-d\TH:i:s\Z'),
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '?',
    ], $extra));

    add_post_meta($post_id, '_vana_audit_log', $entry);
}
```

---

## O Trator respeita o hotfix

Quando o Trator for publicar, ele precisa **checar** se tem hotfix recente:

```python
# vana_trator.py — adicionar no publish

def should_skip_publish(self, wp_visit: dict) -> tuple[bool, str]:
    """
    Checa se o WP tem hotfix recente que o Trator 
    não deve sobrescrever.
    """
    hotfix = wp_visit.get("_hotfix")
    if not hotfix:
        return False, ""
    
    hotfix_at = hotfix.get("at", "")
    # Se o hotfix tem menos de 24h → não sobrescrever
    from datetime import datetime, timezone, timedelta
    try:
        dt = datetime.fromisoformat(hotfix_at.replace("Z", "+00:00"))
        age = datetime.now(timezone.utc) - dt
        if age < timedelta(hours=24):
            return True, f"Hotfix recente ({hotfix_at}) por {hotfix.get('by')}"
    except:
        pass
    
    return False, ""
```

---

## Fluxo completo com Hotfix

```
MODO NORMAL
───────────────────────────────────────────────────────
Streamlit ──→ GitHub ──→ Trator ──→ WordPress
                                        │
                                   campo locked
                                   WP-Admin = read-only

EMERGÊNCIA
───────────────────────────────────────────────────────
Admin clica "🔓 Ativar Hotfix"
    → informa motivo
    → confirma checkbox
    → campo desbloqueia por 30 min

Admin edita o JSON
    → PHP valida estrutura mínima
    → salva com _hotfix stamp
    → audit log registra tudo

Trator na próxima execução:
    → vê _hotfix recente (<24h)
    → PULA a publicação
    → loga: "skipped: hotfix ativo"

Streamlit na próxima abertura:
    → mostra: ⚠️ "Hotfix aplicado via WP-Admin"
    → editor revisa e incorpora no GitHub
    → próximo Trator publish sobrescreve normalmente
```

---

## Resumo

| Estado | WP-Admin | Trator | Streamlit |
|---|---|---|---|
| **Normal** | 🔒 Read-only | ✅ Publica | ✅ Edita GitHub |
| **Hotfix ativo** | 🔓 Editável (30 min) | ⏸️ Pausa 24h | ⚠️ Mostra aviso |
| **Hotfix expirado** | 🔒 Read-only | ✅ Volta a publicar | ✅ Incorpora fix |

> **O hotfix é a válvula de escape. Não é uma porta aberta — é um vidro de emergência com alarme.** 🔴🔨

Quer que eu adicione o aviso de hotfix no Streamlit (`constraints.py` ou `sidebar`)? 🪷



# Hotfix Detection no Streamlit

Marcel, vou implementar em **3 pontos** do Streamlit: detecção, alerta visual e incorporação.

---

## 1. Módulo de detecção: `hotfix_guard.py`

```python
# hotfix_guard.py
# -*- coding: utf-8 -*-
"""
Hotfix Guard — Detecta e gerencia hotfixes aplicados via WP-Admin.

Responsabilidades:
  1. Detectar se o WP tem _hotfix stamp mais recente que o GitHub
  2. Mostrar alerta no Streamlit
  3. Oferecer fluxo de incorporação (merge do hotfix no GitHub)
  4. Limpar o stamp após incorporação

Uso:
    from hotfix_guard import HotfixGuard
    guard = HotfixGuard(wp_visit, gh_visit)
    if guard.has_pending_hotfix():
        guard.render_alert()
"""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone, timedelta
from typing import Optional
import json
import copy


# ══════════════════════════════════════════════════════════════════════
# TIPOS
# ══════════════════════════════════════════════════════════════════════

@dataclass
class HotfixInfo:
    """Dados extraídos do _hotfix stamp no WP."""
    by:         str
    at:         str
    source:     str
    reason:     str = ""
    age_hours:  float = 0.0
    is_expired: bool = False

    @property
    def age_label(self) -> str:
        if self.age_hours < 1:
            minutes = int(self.age_hours * 60)
            return f"{minutes} min atrás"
        elif self.age_hours < 24:
            return f"{self.age_hours:.1f}h atrás"
        else:
            days = int(self.age_hours / 24)
            return f"{days} dia(s) atrás"


@dataclass
class HotfixDiff:
    """Resultado da comparação entre WP (hotfix) e GitHub."""
    fields_changed:   list[str] = field(default_factory=list)
    events_changed:   list[str] = field(default_factory=list)
    events_added:     list[str] = field(default_factory=list)
    events_removed:   list[str] = field(default_factory=list)
    vods_changed:     list[str] = field(default_factory=list)
    segments_changed: list[str] = field(default_factory=list)
    is_trivial:       bool = False  # mudança só em status/campos escalares

    @property
    def total_changes(self) -> int:
        return (
            len(self.fields_changed)
            + len(self.events_changed)
            + len(self.events_added)
            + len(self.events_removed)
            + len(self.vods_changed)
            + len(self.segments_changed)
        )

    @property
    def summary(self) -> str:
        parts = []
        if self.fields_changed:
            parts.append(f"{len(self.fields_changed)} campo(s)")
        if self.events_changed:
            parts.append(f"{len(self.events_changed)} evento(s) editado(s)")
        if self.events_added:
            parts.append(f"{len(self.events_added)} evento(s) adicionado(s)")
        if self.events_removed:
            parts.append(f"{len(self.events_removed)} evento(s) removido(s)")
        if self.vods_changed:
            parts.append(f"{len(self.vods_changed)} VOD(s)")
        if self.segments_changed:
            parts.append(f"{len(self.segments_changed)} segmento(s)")
        return " · ".join(parts) if parts else "sem diferenças detectadas"


# ══════════════════════════════════════════════════════════════════════
# HOTFIX GUARD
# ══════════════════════════════════════════════════════════════════════

class HotfixGuard:
    """
    Detecta hotfixes pendentes e oferece fluxo de incorporação.

    Parâmetros:
        wp_visit:  dict com o timeline_json do WordPress (inclui _hotfix)
        gh_visit:  dict com o timeline_json do GitHub (fonte curada)
    """

    # Hotfixes com mais de 7 dias são considerados expirados
    EXPIRY_HOURS = 168  # 7 dias

    def __init__(
        self,
        wp_visit: Optional[dict] = None,
        gh_visit: Optional[dict] = None,
    ):
        self.wp_visit = wp_visit or {}
        self.gh_visit = gh_visit or {}
        self._hotfix_info: Optional[HotfixInfo] = None
        self._diff: Optional[HotfixDiff] = None

    # ── Detecção ──────────────────────────────────────────────────────

    def has_pending_hotfix(self) -> bool:
        """Retorna True se o WP tem um _hotfix stamp não incorporado."""
        info = self.get_hotfix_info()
        if not info:
            return False
        if info.is_expired:
            return False
        return True

    def get_hotfix_info(self) -> Optional[HotfixInfo]:
        """Extrai e parseia o _hotfix stamp do WP."""
        if self._hotfix_info is not None:
            return self._hotfix_info

        stamp = self.wp_visit.get("_hotfix")
        if not stamp or not isinstance(stamp, dict):
            return None

        at_str = stamp.get("at", "")
        age_hours = 0.0
        is_expired = False

        try:
            dt = datetime.fromisoformat(at_str.replace("Z", "+00:00"))
            age = datetime.now(timezone.utc) - dt
            age_hours = age.total_seconds() / 3600
            is_expired = age_hours > self.EXPIRY_HOURS
        except (ValueError, TypeError):
            pass

        # Buscar reason do audit log ou do stamp
        reason = stamp.get("reason", "")
        if not reason:
            # Tentar extrair do _vana_last_hotfix (se veio no payload)
            last = self.wp_visit.get("_vana_last_hotfix")
            if isinstance(last, dict):
                reason = last.get("reason", "")
            elif isinstance(last, str):
                try:
                    reason = json.loads(last).get("reason", "")
                except (json.JSONDecodeError, AttributeError):
                    pass

        self._hotfix_info = HotfixInfo(
            by=stamp.get("by", "desconhecido"),
            at=at_str,
            source=stamp.get("source", "wp-admin:hotfix"),
            reason=reason,
            age_hours=age_hours,
            is_expired=is_expired,
        )
        return self._hotfix_info

    # ── Diff ──────────────────────────────────────────────────────────

    def compute_diff(self) -> HotfixDiff:
        """Compara WP (com hotfix) vs GitHub (curado)."""
        if self._diff is not None:
            return self._diff

        diff = HotfixDiff()

        # ── Campos escalares do metadata ──────────────────────────────
        wp_meta = self.wp_visit.get("metadata", {})
        gh_meta = self.gh_visit.get("metadata", {})

        for key in set(list(wp_meta.keys()) + list(gh_meta.keys())):
            if key.startswith("_"):
                continue
            if wp_meta.get(key) != gh_meta.get(key):
                diff.fields_changed.append(f"metadata.{key}")

        # ── Status da visita ──────────────────────────────────────────
        wp_status = wp_meta.get("status")
        gh_status = gh_meta.get("status")
        if wp_status != gh_status:
            diff.fields_changed.append(f"metadata.status ({gh_status} → {wp_status})")

        # ── Eventos ───────────────────────────────────────────────────
        wp_events = self._collect_events(self.wp_visit)
        gh_events = self._collect_events(self.gh_visit)

        wp_keys = set(wp_events.keys())
        gh_keys = set(gh_events.keys())

        diff.events_added = sorted(wp_keys - gh_keys)
        diff.events_removed = sorted(gh_keys - wp_keys)

        for ek in wp_keys & gh_keys:
            wp_ev = wp_events[ek]
            gh_ev = gh_events[ek]
            if self._event_changed(wp_ev, gh_ev):
                diff.events_changed.append(ek)

            # Checar VODs e segments dentro do evento
            wp_vods = {v.get("vod_key"): v for v in wp_ev.get("vods", [])}
            gh_vods = {v.get("vod_key"): v for v in gh_ev.get("vods", [])}

            for vk in set(list(wp_vods.keys()) + list(gh_vods.keys())):
                wp_v = wp_vods.get(vk, {})
                gh_v = gh_vods.get(vk, {})
                if wp_v.get("url") != gh_v.get("url") or wp_v.get("video_id") != gh_v.get("video_id"):
                    diff.vods_changed.append(f"{ek}/{vk}")

                wp_segs = {s.get("segment_id"): s for s in wp_v.get("segments", [])}
                gh_segs = {s.get("segment_id"): s for s in gh_v.get("segments", [])}
                for sk in set(list(wp_segs.keys()) + list(gh_segs.keys())):
                    if wp_segs.get(sk) != gh_segs.get(sk):
                        diff.segments_changed.append(f"{ek}/{vk}/{sk}")

        # Determinar se é trivial
        diff.is_trivial = (
            diff.total_changes <= 2
            and not diff.events_added
            and not diff.events_removed
            and all("status" in f for f in diff.fields_changed)
        )

        self._diff = diff
        return diff

    # ── Incorporação ──────────────────────────────────────────────────

    def build_incorporated_visit(self, strategy: str = "wp_wins") -> dict:
        """
        Constrói o visit.json incorporando o hotfix.

        Estratégias:
            "wp_wins"     → Dados do WP sobrescrevem o GitHub
            "gh_wins"     → Mantém GitHub, ignora hotfix 
            "merge"       → Merge aditivo (WP adiciona, não remove)
        """
        if strategy == "gh_wins":
            result = copy.deepcopy(self.gh_visit)
            # Marcar que o hotfix foi revisado e descartado
            result["_hotfix_resolved"] = {
                "action": "discarded",
                "original_hotfix": self.wp_visit.get("_hotfix"),
                "resolved_at": datetime.now(timezone.utc).strftime(
                    "%Y-%m-%dT%H:%M:%SZ"
                ),
            }
            return result

        if strategy == "wp_wins":
            result = copy.deepcopy(self.wp_visit)
            # Remover stamps internos do WP
            result.pop("_hotfix", None)
            result.pop("_vana_last_hotfix", None)
            result.pop("generated_by", None)
            result.pop("generated_at", None)
            # Marcar incorporação
            result["_hotfix_resolved"] = {
                "action": "incorporated",
                "original_hotfix": self.wp_visit.get("_hotfix"),
                "resolved_at": datetime.now(timezone.utc).strftime(
                    "%Y-%m-%dT%H:%M:%SZ"
                ),
            }
            return result

        if strategy == "merge":
            result = copy.deepcopy(self.gh_visit)
            wp = self.wp_visit

            # Merge metadata (WP wins para campos que mudaram)
            wp_meta = wp.get("metadata", {})
            for key, val in wp_meta.items():
                if key.startswith("_"):
                    continue
                gh_val = result.get("metadata", {}).get(key)
                if val != gh_val:
                    result.setdefault("metadata", {})[key] = val

            # Merge eventos (aditivo: WP adiciona, não remove)
            gh_events = self._collect_events(result)
            wp_events = self._collect_events(wp)

            for ek, wp_ev in wp_events.items():
                if ek not in gh_events:
                    # Evento novo do hotfix → adicionar
                    self._inject_event(result, wp_ev)
                else:
                    # Evento existente → merge campos escalares
                    gh_ev = gh_events[ek]
                    for field in ("status", "title_pt", "title_en", "type"):
                        if wp_ev.get(field) != gh_ev.get(field):
                            self._update_event_field(
                                result, ek, field, wp_ev.get(field)
                            )

                    # Merge VODs (aditivo)
                    gh_vod_ids = {
                        v.get("vod_key") for v in gh_ev.get("vods", [])
                    }
                    for wp_vod in wp_ev.get("vods", []):
                        vk = wp_vod.get("vod_key")
                        if vk and vk not in gh_vod_ids:
                            self._inject_vod(result, ek, wp_vod)

            result["_hotfix_resolved"] = {
                "action": "merged",
                "original_hotfix": wp.get("_hotfix"),
                "resolved_at": datetime.now(timezone.utc).strftime(
                    "%Y-%m-%dT%H:%M:%SZ"
                ),
            }
            return result

        raise ValueError(f"Estratégia desconhecida: {strategy!r}")

    # ── Helpers internos ──────────────────────────────────────────────

    def _collect_events(self, visit: dict) -> dict[str, dict]:
        """Coleta todos os eventos indexados por event_key."""
        events = {}
        for day in visit.get("days", []):
            for ev in day.get("events", []):
                ek = ev.get("event_key")
                if ek:
                    events[ek] = ev
        return events

    def _event_changed(self, wp_ev: dict, gh_ev: dict) -> bool:
        """Compara campos escalares de dois eventos."""
        for field in ("status", "type", "title_pt", "title_en", "time"):
            if wp_ev.get(field) != gh_ev.get(field):
                return True
        location_wp = wp_ev.get("location")
        location_gh = gh_ev.get("location")
        if isinstance(location_wp, dict) and isinstance(location_gh, dict):
            if location_wp.get("name") != location_gh.get("name"):
                return True
        elif location_wp != location_gh:
            return True
        return False

    def _inject_event(self, visit: dict, event: dict) -> None:
        """Injeta um evento novo no dia correspondente."""
        ek = event.get("event_key", "")
        day_key_prefix = ek[:8] if len(ek) >= 8 else ""
        for day in visit.get("days", []):
            if day.get("day_key", "").replace("-", "").startswith(day_key_prefix):
                event["_added_by"] = "hotfix"
                day.setdefault("events", []).append(event)
                return
        # Se não achou o dia, adiciona no último
        days = visit.get("days", [])
        if days:
            event["_added_by"] = "hotfix"
            days[-1].setdefault("events", []).append(event)

    def _inject_vod(self, visit: dict, event_key: str, vod: dict) -> None:
        """Injeta um VOD novo no evento correspondente."""
        for day in visit.get("days", []):
            for ev in day.get("events", []):
                if ev.get("event_key") == event_key:
                    vod["_added_by"] = "hotfix"
                    ev.setdefault("vods", []).append(vod)
                    return

    def _update_event_field(
        self, visit: dict, event_key: str, field: str, value
    ) -> None:
        """Atualiza um campo escalar de um evento."""
        for day in visit.get("days", []):
            for ev in day.get("events", []):
                if ev.get("event_key") == event_key:
                    ev[field] = value
                    return
```

---

## 2. Componente visual: `hotfix_alert.py`

```python
# hotfix_alert.py
# -*- coding: utf-8 -*-
"""
Componente Streamlit para renderizar alertas de hotfix.

Uso:
    from hotfix_alert import render_hotfix_alert
    render_hotfix_alert(hotfix_guard, on_incorporate=save_callback)
"""

from __future__ import annotations

import streamlit as st
import json
from typing import Callable, Optional
from hotfix_guard import HotfixGuard, HotfixDiff


def render_hotfix_alert(
    guard: HotfixGuard,
    on_incorporate: Optional[Callable[[dict], None]] = None,
) -> Optional[dict]:
    """
    Renderiza o alerta de hotfix no Streamlit.

    Args:
        guard:          HotfixGuard já inicializado com wp_visit e gh_visit
        on_incorporate: callback chamado quando editor confirma incorporação.
                        Recebe o visit dict já mergeado.

    Returns:
        dict mergeado se incorporado, None caso contrário
    """
    if not guard.has_pending_hotfix():
        return None

    info = guard.get_hotfix_info()
    diff = guard.compute_diff()

    # ── Alerta principal ──────────────────────────────────────────────
    st.markdown("---")

    # Cor do alerta baseado na idade
    if info.age_hours < 2:
        icon = "🔴"
        color = "#dc3545"
        urgency = "URGENTE"
    elif info.age_hours < 24:
        icon = "🟠"
        color = "#fd7e14"
        urgency = "RECENTE"
    else:
        icon = "🟡"
        color = "#ffc107"
        urgency = "PENDENTE"

    st.markdown(
        f"""
        <div style="
            background: linear-gradient(135deg, {color}15, {color}08);
            border-left: 4px solid {color};
            padding: 16px 20px;
            border-radius: 8px;
            margin: 8px 0 16px;
        ">
            <div style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">
                {icon} Hotfix {urgency} — aplicado via WP-Admin
            </div>
            <div style="font-size: 14px; color: #555; line-height: 1.6;">
                <strong>Quem:</strong> {info.by}<br>
                <strong>Quando:</strong> {info.at} ({info.age_label})<br>
                <strong>Motivo:</strong> {info.reason or '<em>não informado</em>'}
            </div>
        </div>
        """,
        unsafe_allow_html=True,
    )

    # ── Resumo das diferenças ─────────────────────────────────────────
    with st.expander(
        f"📊 Diferenças detectadas: {diff.summary}", expanded=diff.total_changes <= 5
    ):
        if diff.fields_changed:
            st.markdown("**Campos alterados:**")
            for f in diff.fields_changed:
                st.markdown(f"- `{f}`")

        if diff.events_changed:
            st.markdown("**Eventos editados:**")
            for ek in diff.events_changed:
                st.markdown(f"- `{ek}`")

        if diff.events_added:
            st.markdown("**Eventos adicionados (hotfix):**")
            for ek in diff.events_added:
                st.markdown(f"- 🆕 `{ek}`")

        if diff.events_removed:
            st.markdown("**⚠️ Eventos removidos no hotfix:**")
            for ek in diff.events_removed:
                st.markdown(f"- ❌ `{ek}`")

        if diff.vods_changed:
            st.markdown("**VODs alterados:**")
            for vk in diff.vods_changed:
                st.markdown(f"- `{vk}`")

        if diff.segments_changed:
            st.markdown("**Segments alterados:**")
            for sk in diff.segments_changed[:10]:
                st.markdown(f"- `{sk}`")
            if len(diff.segments_changed) > 10:
                st.caption(
                    f"... e mais {len(diff.segments_changed) - 10} segments"
                )

        if diff.total_changes == 0:
            st.info("Nenhuma diferença estrutural detectada. O hotfix pode ter sido apenas um teste.")

    # ── JSON side-by-side (para diff visual) ──────────────────────────
    with st.expander("🔍 Comparar JSON (GitHub vs WP)"):
        col_gh, col_wp = st.columns(2)
        with col_gh:
            st.markdown("**GitHub (curado)**")
            st.json(guard.gh_visit.get("metadata", {}))
        with col_wp:
            st.markdown("**WordPress (hotfix)**")
            st.json(guard.wp_visit.get("metadata", {}))

    # ── Ações ─────────────────────────────────────────────────────────
    st.markdown("### Ação necessária")

    if diff.is_trivial:
        st.info(
            "💡 Mudança trivial (provavelmente só status). "
            "Recomendado: **Aceitar hotfix** para manter consistência."
        )

    strategy = st.radio(
        "Como incorporar o hotfix?",
        options=["wp_wins", "merge", "gh_wins"],
        format_func=lambda x: {
            "wp_wins": "✅ Aceitar hotfix (WP sobrescreve GitHub)",
            "merge":   "🔀 Merge inteligente (WP adiciona, não remove)",
            "gh_wins": "❌ Descartar hotfix (manter GitHub)",
        }[x],
        index=0 if diff.is_trivial else 1,
        key="hotfix_strategy",
    )

    # Confirmação extra para descartar
    confirm_discard = True
    if strategy == "gh_wins":
        confirm_discard = st.checkbox(
            "⚠️ Confirmo que quero descartar o hotfix. "
            "As alterações feitas no WP-Admin serão perdidas.",
            key="hotfix_confirm_discard",
        )

    # Botão de incorporação
    col_action, col_cancel = st.columns([2, 1])
    with col_action:
        label = {
            "wp_wins": "✅ Incorporar hotfix",
            "merge":   "🔀 Fazer merge",
            "gh_wins": "❌ Descartar hotfix",
        }[strategy]

        disabled = strategy == "gh_wins" and not confirm_discard

        if st.button(
            label,
            type="primary",
            disabled=disabled,
            key="hotfix_incorporate_btn",
        ):
            incorporated = guard.build_incorporated_visit(strategy=strategy)

            if on_incorporate:
                on_incorporate(incorporated)

            action_label = {
                "wp_wins": "incorporado",
                "merge":   "mergeado",
                "gh_wins": "descartado",
            }[strategy]
            st.success(
                f"Hotfix **{action_label}** com sucesso. "
                f"Salve a visita para persistir no GitHub."
            )
            return incorporated

    with col_cancel:
        if st.button("Decidir depois", key="hotfix_later_btn"):
            st.info("OK. O alerta continuará aparecendo até ser resolvido.")

    st.markdown("---")
    return None
```

---

## 3. Integração no editor de visita

```python
# No arquivo principal do Streamlit (ex: visit_editor.py)
# Adicionar ANTES do formulário de edição

from hotfix_guard import HotfixGuard
from hotfix_alert import render_hotfix_alert


def load_and_check_visit(visit_ref: str):
    """Carrega visit do GitHub e checa hotfix no WP."""

    # ── 1. Carregar do GitHub (fonte curada) ──────────────────────────
    gh_visit = gh.get_visit(visit_ref)

    # ── 2. Carregar do WP (pode ter hotfix) ───────────────────────────
    wp_visit = wp_client.get_visit_timeline(visit_ref)

    # ── 3. Checar hotfix ──────────────────────────────────────────────
    guard = HotfixGuard(wp_visit=wp_visit, gh_visit=gh_visit)

    if guard.has_pending_hotfix():
        # Renderizar alerta e capturar resultado
        incorporated = render_hotfix_alert(
            guard=guard,
            on_incorporate=lambda v: save_to_github(visit_ref, v,
                author=st.session_state.get("user", "editor"),
                message=f"hotfix incorporado de {guard.get_hotfix_info().by}",
            ),
        )

        if incorporated:
            # Usar a versão incorporada como base do editor
            return incorporated

    # ── 4. Se não tem hotfix, usar GitHub normalmente ─────────────────
    return gh_visit
```

---

## 4. Badge na sidebar (alerta persistente)

```python
# No sidebar.py — ao listar as visitas

from hotfix_guard import HotfixGuard


def render_visit_list(visits: list[dict]):
    """Renderiza lista de visitas com badge de hotfix."""
    
    for v in visits:
        ref = v.get("visit_ref", "")
        label = v.get("title", ref)
        
        # Checar hotfix rápido (sem carregar JSON completo)
        has_hotfix = v.get("_hotfix") is not None
        
        if has_hotfix:
            badge = " 🔴"
            help_text = "Hotfix pendente — clique para revisar"
        else:
            badge = ""
            help_text = None
        
        if st.sidebar.button(
            f"{label}{badge}",
            key=f"visit_{ref}",
            help=help_text,
        ):
            st.session_state["active_visit"] = ref
            st.rerun()
    
    # Contador global
    hotfix_count = sum(1 for v in visits if v.get("_hotfix"))
    if hotfix_count:
        st.sidebar.markdown(
            f"""
            <div style="
                background: #dc354520;
                border-radius: 6px;
                padding: 8px 12px;
                margin-top: 8px;
                text-align: center;
            ">
                🔴 <strong>{hotfix_count}</strong> hotfix(es) pendente(s)
            </div>
            """,
            unsafe_allow_html=True,
        )
```

---

## Fluxo visual completo

```
┌─────────────────────────────────────────────────────────────────┐
│ SIDEBAR                                                         │
│                                                                 │
│  🪷 Visitas                                                     │
│  ─────────────────                                              │
│  Brazil SP Jan 2026                                             │
│  India Vrindavan Feb 2026 🔴                                    │
│  Chile Santiago Mar 2026                                        │
│                                                                 │
│  🔴 1 hotfix(es) pendente(s)                                    │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ EDITOR                                                          │
│                                                                 │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ 🟠 Hotfix RECENTE — aplicado via WP-Admin                 │ │
│  │                                                            │ │
│  │ Quem: admin_devoto                                         │ │
│  │ Quando: 2026-04-11T22:30:00Z (3h atrás)                   │ │
│  │ Motivo: Título errado no programa da manhã                 │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ▸ 📊 Diferenças: 1 evento editado                              │
│  ▸ 🔍 Comparar JSON                                             │
│                                                                 │
│  ### Ação necessária                                            │
│                                                                 │
│  ○ ✅ Aceitar hotfix (WP sobrescreve GitHub)                    │
│  ● 🔀 Merge inteligente (WP adiciona, não remove)              │
│  ○ ❌ Descartar hotfix (manter GitHub)                          │
│                                                                 │
│  [ 🔀 Fazer merge ]  [ Decidir depois ]                        │
│                                                                 │
│  ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─  │
│                                                                 │
│  📅 Day 1 — 21 Feb 2026                                        │
│  ... (editor normal continua abaixo)                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Checklist de implementação

| # | Arquivo | Status | Dependência |
|---|---|---|---|
| 1 | `hotfix_guard.py` | Novo módulo | Nenhuma |
| 2 | `hotfix_alert.py` | Novo componente | `hotfix_guard.py` |
| 3 | `visit_editor.py` | Alterar `load_visit` | `hotfix_guard` + `hotfix_alert` |
| 4 | `sidebar.py` | Adicionar badge 🔴 | `wp_client` |
| 5 | `vana-hotfix-guard.php` | WP plugin (conversa anterior) | PHP side |
| 6 | `vana_trator.py` | Adicionar `should_skip_publish` | `_hotfix` stamp |

> **Três camadas de proteção, um fluxo de escape, nenhum dado perdido.** 🪷

Quer que eu escreva os testes para o `HotfixGuard`?