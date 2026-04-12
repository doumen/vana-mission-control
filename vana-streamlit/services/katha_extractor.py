# services/katha_extractor.py
# -*- coding: utf-8 -*-
"""
Katha Extractor — Orquestra as 4 fases do prompt v4.1.
"""

import json
import math
from typing import Optional

from services.llm_client import LLMClient

_SYSTEM_PROMPT_PATH = "prompts/katha_extractor_v4_1.md"


def _load_system_prompt() -> str:
    try:
        with open(_SYSTEM_PROMPT_PATH, "r", encoding="utf-8") as f:
            return f.read()
    except FileNotFoundError:
        return (
            "Você é o Vana Katha Extractor v4.1. "
            "Processe transcrições seguindo o protocolo de chunking em 4 fases. "
            "Responda SEMPRE em JSON válido."
        )


class KathaExtractor:
    def __init__(self, llm: LLMClient):
        self.llm = llm
        self.system_prompt = _load_system_prompt()

    def fase_0_mapeamento(self, txt: str, contexto: dict) -> dict:
        gate = self._format_gate(contexto)
        user_msg = f"""
{gate}

## TAREFA: Execute APENAS a FASE 0 — MAPEAMENTO.

Leia o TXT abaixo, avalie a qualidade da transcrição, e gere o CHUNK_MAP.
NÃO extraia nada ainda. NÃO identifique passages. APENAS mapeie.

Responda EXCLUSIVAMENTE com JSON válido (sem markdown, sem explicação).

---
TXT:
{txt}
"""
        result = self.llm.ask(system_prompt=self.system_prompt, user_message=user_msg, temperature=0.2, max_tokens=2048)
        return {"fase": 0, "data": result.get("json") or {"error": "JSON não parseado"}, "usage": result.get("usage", {}), "latency_s": result.get("latency_s", 0), "raw_text": result.get("text", "")}

    def fase_1_chunk(self, chunk_id: int, chunk_text: str, chunk_map: dict, contexto: dict) -> dict:
        gate = self._format_gate(contexto)
        user_msg = f"""
{gate}

## TAREFA: Execute APENAS a FASE 1 para o CHUNK {chunk_id}.

CHUNK_MAP completo (para referência):
```json
{json.dumps(chunk_map, ensure_ascii=False, indent=2)}
```

CHUNK {chunk_id} — texto:
---
{chunk_text}
---

Analise ESTE chunk. Identifique:
- tema_central
- qualidade_local
- fronteira_passage (é fronteira? motivo)
- teaching_point_candidato
- key_quote_candidata (exata do TXT, [reconstruído] se necessário)
- source_units_candidatos (apenas ref + type, sem definição)
- elementos_nao_capturados

Responda EXCLUSIVAMENTE com JSON válido.
"""
        result = self.llm.ask(system_prompt=self.system_prompt, user_message=user_msg, temperature=0.2, max_tokens=2048)
        return {"fase": 1, "chunk_id": chunk_id, "data": result.get("json") or {"error": "JSON não parseado"}, "usage": result.get("usage", {}), "latency_s": result.get("latency_s", 0), "raw_text": result.get("text", "")}

    def fase_2_esqueleto(self, chunk_analyses: list[dict], contexto: dict) -> dict:
        gate = self._format_gate(contexto)
        analyses_json = json.dumps(chunk_analyses, ensure_ascii=False, indent=2)
        user_msg = f"""
{gate}

## TAREFA: Execute APENAS a FASE 2 — ESQUELETO DOS PASSAGES.

Análises da Fase 1 (todos os chunks):
```json
{analyses_json}
```

Com base nessas análises:
1. Defina as fronteiras finais dos passages
2. Agrupe chunks em passages coesos (1 unidade temática = 2-7 min)
3. Para cada passage declare: passage_index, chunks_origem, teaching_point, hook, source_units_refs

NÃO escreva elaboration, transcript_clean ou study_notes.
Responda EXCLUSIVAMENTE com JSON válido.
"""
        result = self.llm.ask(system_prompt=self.system_prompt, user_message=user_msg, temperature=0.3, max_tokens=3072)
        return {"fase": 2, "data": result.get("json") or {"error": "JSON não parseado"}, "usage": result.get("usage", {}), "latency_s": result.get("latency_s", 0), "raw_text": result.get("text", "")}

    def fase_3_passage(self, passage_skeleton: dict, chunks_text: str, contexto: dict) -> dict:
        gate = self._format_gate(contexto)
        skeleton_json = json.dumps(passage_skeleton, ensure_ascii=False, indent=2)
        user_msg = f"""
{gate}

## TAREFA: Execute APENAS a FASE 3 para o PASSAGE {passage_skeleton.get('passage_index', '?')}.

Esqueleto deste passage:
```json
{skeleton_json}
```

Texto dos chunks correspondentes:
---
{chunks_text}
---

Usando APENAS o texto acima, gere o passage completo:
- hook, teaching_point, key_quote
- post_content: elaboration + transcript_clean + study_notes
- teaching, rhetoric
- evidence (com ref_inferred quando aplicável)
- source_units (com definitions APENAS do que Maharaj disse)
- meta, topics, tags

REGRAS:
- Fidelidade total ao TXT
- Nunca expanda narrativas além do que está escrito
- Marque ref_inferred=true quando inferir referência
- key_quote deve ser exata ou [reconstruída]

Responda EXCLUSIVAMENTE com JSON válido.
"""
        result = self.llm.ask(system_prompt=self.system_prompt, user_message=user_msg, temperature=0.4, max_tokens=6144)
        return {"fase": 3, "passage_index": passage_skeleton.get("passage_index"), "data": result.get("json") or {"error": "JSON não parseado"}, "usage": result.get("usage", {}), "latency_s": result.get("latency_s", 0), "raw_text": result.get("text", "")}

    def fase_4_consolidacao(self, passages_completos: list[dict], chunk_analyses: list[dict], contexto: dict) -> dict:
        gate = self._format_gate(contexto)
        passages_json = json.dumps(passages_completos, ensure_ascii=False, indent=2)
        analyses_json = json.dumps([a.get("elementos_nao_capturados", []) for a in chunk_analyses], ensure_ascii=False)
        user_msg = f"""
{gate}

## TAREFA: Execute APENAS a FASE 4 — CONSOLIDAÇÃO FINAL.

Passages completos:
```json
{passages_json}
```

Elementos não capturados (da Fase 1):
{analyses_json}

Monte o JSON final v4.1 completo:
1. vana_katha (post_title, post_content sumário, meta, taxonomies)
2. passages[] (já prontos — incluir como estão)
3. katha_source_units_summary (consolidar todos os source_units únicos)
4. extraction_report (chunks processados, elementos perdidos, refs inferidas, inconsistências)

Valide:
- total_passages = número real
- total_source_units = número real de únicos
- Todos os ref_inferred listados no report

Responda EXCLUSIVAMENTE com JSON válido.
"""
        result = self.llm.ask(system_prompt=self.system_prompt, user_message=user_msg, temperature=0.2, max_tokens=8192)
        return {"fase": 4, "data": result.get("json") or {"error": "JSON não parseado"}, "usage": result.get("usage", {}), "latency_s": result.get("latency_s", 0), "raw_text": result.get("text", "")}

    def _format_gate(self, ctx: dict) -> str:
        return f"""
## GATE DE CONTEXTO
- ORIGEM: {ctx.get('youtube_url', 'null')}
- DATA: {ctx.get('katha_date', 'null')}
- IDIOMA: {ctx.get('language', 'null')}
- VISITA: {ctx.get('visit_ref', 'null')}
- CONTEXTO: {ctx.get('teaching_context', 'null')}
- LOCAL: {ctx.get('location', 'null')}
"""

    @staticmethod
    def split_chunks(txt: str, chunk_size: int = 30) -> list[dict]:
        lines = txt.strip().split("\n")
        total = len(lines)
        chunks = []
        n_chunks = math.ceil(total / chunk_size)
        for i in range(n_chunks):
            start = i * chunk_size
            end = min((i + 1) * chunk_size, total)
            chunk_lines = lines[start:end]
            chunks.append({
                "chunk_id": i + 1,
                "lines": f"{start + 1}-{end}",
                "text": "\n".join(chunk_lines),
                "line_count": len(chunk_lines),
            })
        return chunks

    @staticmethod
    def estimate_cost(total_chunks: int, provider: str = "anthropic") -> dict:
        if provider == "anthropic":
            input_rate = 3.00 / 1_000_000
            output_rate = 15.00 / 1_000_000
        else:
            input_rate = 2.50 / 1_000_000
            output_rate = 10.00 / 1_000_000

        f0_in = 2000
        f0_out = 1000
        f1_in = total_chunks * 800
        f1_out = total_chunks * 500
        f2_in = total_chunks * 500 + 500
        f2_out = 1500
        n_passages = max(3, total_chunks // 2)
        f3_in = n_passages * 1500
        f3_out = n_passages * 2000
        f4_in = n_passages * 2000 + 1000
        f4_out = 3000

        total_in = f0_in + f1_in + f2_in + f3_in + f4_in
        total_out = f0_out + f1_out + f2_out + f3_out + f4_out
        cost_usd = (total_in * input_rate) + (total_out * output_rate)

        return {
            "total_input_tokens": total_in,
            "total_output_tokens": total_out,
            "estimated_calls": 2 + total_chunks + n_passages,
            "estimated_passages": n_passages,
            "cost_usd": round(cost_usd, 4),
            "cost_brl": round(cost_usd * 5.5, 2),
        }
