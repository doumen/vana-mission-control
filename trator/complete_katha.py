# complete_katha.py
# -*- coding: utf-8 -*-
"""
Completa os campos _en de um JSON de katha (schema 3.2) via Gemini.

Uso:
  py complete_katha.py <katha.json> [--force] [--dry-run]

Flags:
  --force    Sobrescreve campos _en já preenchidos
  --dry-run  Mostra o diff sem salvar

.env esperado:
  GEMINI_API_KEY=sua_chave_aqui
"""

import os
import sys
import json
import copy
import argparse
from dotenv import load_dotenv
import google.generativeai as genai

# ── Configuração ──────────────────────────────────────────────────────────────

SCHEMA_VERSION   = "3.2"
MODEL_NAME       = "gemini-2.0-flash"
LANGUAGE_SOURCE  = "pt-BR"
LANGUAGE_TARGET  = "en"

SYSTEM_PROMPT = """
You are a sacred Sanskrit-Vaishnava translator assistant for Srila Bhaktivedanta Vana Goswami Maharaj's Hari-katha.

RULES:
- Translate devotional content from Brazilian Portuguese to English
- Preserve ALL Sanskrit/Bengali terms in IAST transliteration (do NOT translate them)
- Preserve ALL markdown formatting (bold, italic, bullet points, blockquotes)
- Maintain the devotional, humble tone of the original
- Return ONLY the translated text — no explanations, no quotes wrapping

EXAMPLES of terms to preserve:
  dhāma, sevā, hari-kathā, Vaiṣṇava, Bhagavān, kṛpā, pratiṣṭhā,
  vipralambha, sambhoga, sphurti, lābha, pūjā, rasa-tattva, etc.
""".strip()


# ── Helpers ───────────────────────────────────────────────────────────────────

def translate(model, text: str) -> str:
    """Traduz um texto PT → EN via Gemini."""
    if not text or not text.strip():
        return ""
    response = model.generate_content(
        f"{SYSTEM_PROMPT}\n\nTranslate to English:\n\n{text}"
    )
    return response.text.strip()


def is_empty(value) -> bool:
    """Verifica se um campo está vazio (None, '', [], {})."""
    if value is None:
        return True
    if isinstance(value, str):
        return value.strip() == ""
    return False


def diff_summary(original: dict, completed: dict, path: str = "") -> list[str]:
    """Retorna lista de campos alterados para dry-run."""
    changes = []
    for key in completed:
        full_path = f"{path}.{key}" if path else key
        orig_val  = original.get(key)
        new_val   = completed.get(key)
        if isinstance(new_val, dict):
            changes += diff_summary(orig_val or {}, new_val, full_path)
        elif isinstance(new_val, list):
            for i, item in enumerate(new_val):
                if isinstance(item, dict):
                    orig_item = orig_val[i] if orig_val and i < len(orig_val) else {}
                    changes  += diff_summary(orig_item, item, f"{full_path}[{i}]")
        else:
            if new_val and new_val != orig_val:
                preview = str(new_val)[:80].replace("\n", " ")
                changes.append(f"  + {full_path}: {preview}...")
    return changes


# ── Completadores por seção ───────────────────────────────────────────────────

def complete_lecture(model, lecture: dict, force: bool) -> dict:
    """Completa title_en, excerpt_en, summary_en."""
    result = copy.deepcopy(lecture)
    fields = [
        ("title",   "title_en"),
        ("excerpt", "excerpt_en"),
        ("summary", "summary_en"),
    ]
    for src, tgt in fields:
        if force or is_empty(result.get(tgt)):
            source_text = result.get(src, "")
            if source_text:
                print(f"  🔤 lecture.{tgt}...")
                result[tgt] = translate(model, source_text)
    return result


def complete_verses(model, verses: list, force: bool) -> list:
    """Completa text_en de cada verso."""
    result = []
    for i, verse in enumerate(verses):
        v = copy.deepcopy(verse)
        ref = v.get("verse_ref", f"verso {i+1}")

        # text_en: traduz a partir de text_pt se disponível
        if force or is_empty(v.get("text_en")):
            source = v.get("text_pt") or v.get("text_transliteration", "")
            if source:
                print(f"  📖 verses[{i}].text_en ({ref[:40]})...")
                v["text_en"] = translate(model, source)

        result.append(v)
    return result


def complete_glossary(model, glossary: list, force: bool) -> list:
    """Completa definition_short_en e definition_full_en."""
    result = []
    for i, entry in enumerate(glossary):
        g = copy.deepcopy(entry)
        term = g.get("term", f"termo {i+1}")

        if force or is_empty(g.get("definition_short_en")):
            src = g.get("definition_short", "")
            if src:
                print(f"  📚 glossary[{i}].definition_short_en ({term})...")
                g["definition_short_en"] = translate(model, src)

        if force or is_empty(g.get("definition_full_en")):
            src = g.get("definition_full", "")
            if src:
                print(f"  📚 glossary[{i}].definition_full_en ({term})...")
                g["definition_full_en"] = translate(model, src)

        result.append(g)
    return result


def complete_passages(model, passages: list, force: bool) -> list:
    """Completa hook_en, key_quote_en, content_en de cada passage."""
    result = []
    for i, passage in enumerate(passages):
        p    = copy.deepcopy(passage)
        ref  = p.get("passage_ref", f"p{i+1:02d}")

        fields = [
            ("hook",      "hook_en"),
            ("key_quote", "key_quote_en"),
            ("content",   "content_en"),
        ]
        for src, tgt in fields:
            if force or is_empty(p.get(tgt)):
                source_text = p.get(src, "")
                if source_text:
                    print(f"  💬 passages[{ref}].{tgt}...")
                    p[tgt] = translate(model, source_text)

        result.append(p)
    return result


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    load_dotenv()

    # ── Args ──────────────────────────────────────────────────────────
    parser = argparse.ArgumentParser(description="Completa campos _en de um JSON de katha via Gemini.")
    parser.add_argument("json_file",          help="Caminho do JSON de entrada")
    parser.add_argument("--force",            action="store_true", help="Sobrescreve _en já preenchidos")
    parser.add_argument("--dry-run",          action="store_true", help="Mostra diff sem salvar")
    parser.add_argument("--only",             choices=["lecture", "verses", "glossary", "passages"],
                                              help="Processa apenas uma seção")
    parser.add_argument("--output", "-o",     help="Arquivo de saída (padrão: <input>_complete.json)")
    args = parser.parse_args()

    # ── Validações ────────────────────────────────────────────────────
    if not os.path.isfile(args.json_file):
        print(f"❌ Arquivo não encontrado: {args.json_file}")
        sys.exit(1)

    api_key = os.getenv("GEMINI_API_KEY")
    if not api_key:
        print("❌ GEMINI_API_KEY não definida no .env")
        sys.exit(1)

    # ── Carregar JSON ─────────────────────────────────────────────────
    with open(args.json_file, "r", encoding="utf-8") as f:
        try:
            data = json.load(f)
        except json.JSONDecodeError as e:
            print(f"❌ JSON inválido: {e}")
            sys.exit(1)

    if data.get("schema_version") != SCHEMA_VERSION:
        print(f"⚠️  schema_version esperado: {SCHEMA_VERSION} — encontrado: {data.get('schema_version')}")
        sys.exit(1)

    katha_ref = data.get("context", {}).get("katha_ref", "?")
    print(f"\n{'═'*60}")
    print(f"🕉️  Vana Complete — {katha_ref}")
    if args.dry_run:
        print(f"   [DRY-RUN]")
    if args.force:
        print(f"   [FORCE]")
    print(f"{'═'*60}\n")

    # ── Inicializar Gemini ────────────────────────────────────────────
    genai.configure(api_key=api_key)
    model = genai.GenerativeModel(MODEL_NAME)

    # ── Processar seções ──────────────────────────────────────────────
    original  = copy.deepcopy(data)
    completed = copy.deepcopy(data)

    only = args.only

    if not only or only == "lecture":
        print("📖 Completando lecture...")
        completed["lecture"] = complete_lecture(model, completed.get("lecture", {}), args.force)

    if not only or only == "verses":
        print("\n📜 Completando verses_cited...")
        completed["verses_cited"] = complete_verses(model, completed.get("verses_cited", []), args.force)

    if not only or only == "glossary":
        print("\n📚 Completando glossary...")
        completed["glossary"] = complete_glossary(model, completed.get("glossary", []), args.force)

    if not only or only == "passages":
        print("\n💬 Completando passages...")
        completed["passages"] = complete_passages(model, completed.get("passages", []), args.force)

    # Atualizar output_languages
    langs = completed.get("lecture", {}).get("output_languages") or \
            completed.get("context", {}).get("output_languages", [])
    if "en" not in langs:
        langs.append("en")

    # ── Dry-run: mostrar diff ─────────────────────────────────────────
    if args.dry_run:
        print("\n📋 DIFF (campos que seriam alterados):")
        changes = diff_summary(original, completed)
        if changes:
            for c in changes:
                print(c)
        else:
            print("  (nenhuma alteração)")
        print("\n⏭️  Dry-run — nada salvo.")
        return

    # ── Validar JSON resultante ───────────────────────────────────────
    try:
        json.dumps(completed, ensure_ascii=False)
    except (TypeError, ValueError) as e:
        print(f"\n❌ JSON resultante inválido: {e}")
        sys.exit(1)

    # ── Salvar ────────────────────────────────────────────────────────
    base, ext = os.path.splitext(args.json_file)
    output_path = args.output or f"{base}_complete{ext}"

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(completed, f, ensure_ascii=False, indent=2)

    print(f"\n✅ JSON completo salvo em: {output_path}")
    print(f"\n▶️  Próximo passo:")
    print(f"   py ingest_katha.py {output_path}\n")


if __name__ == "__main__":
    main()
