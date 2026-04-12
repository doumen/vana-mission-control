# preview_katha.py
# -*- coding: utf-8 -*-
"""
Visualiza os campos _en de um JSON _complete.json no terminal.

Uso:
  py preview_katha.py <katha_complete.json> [--section=passages|verses|glossary|lecture]
"""

import sys
import json
import argparse


def hr(char="─", n=60):
    print(char * n)


def preview_lecture(lecture: dict):
    hr("═")
    print("📖 LECTURE")
    hr("═")
    print(f"  title_en  : {lecture.get('title_en', '—')}")
    print(f"\n  excerpt_en:\n  {lecture.get('excerpt_en', '—')}")
    print(f"\n  summary_en:\n  {lecture.get('summary_en', '—')}")


def preview_verses(verses: list):
    hr("═")
    print("📜 VERSES")
    for v in verses:
        hr()
        print(f"  ref    : {v.get('verse_ref')}")
        print(f"  text_en: {v.get('text_en', '—')}")


def preview_glossary(glossary: list):
    hr("═")
    print("📚 GLOSSARY")
    for g in glossary:
        hr()
        print(f"  term               : {g.get('term')}")
        print(f"  definition_short_en: {g.get('definition_short_en', '—')}")
        print(f"  definition_full_en :\n  {g.get('definition_full_en', '—')}")


def preview_passages(passages: list):
    hr("═")
    print("💬 PASSAGES")
    for p in passages:
        hr()
        ref  = p.get("passage_ref", "?")
        conf = "🔒 CONFIDENTIAL" if p.get("contains_confidential_content") else ""
        print(f"  [{ref}] {conf}")
        print(f"  hook_en      : {p.get('hook_en', '—')}")
        print(f"  key_quote_en : {p.get('key_quote_en', '—')}")
        print(f"\n  content_en:\n{p.get('content_en', '—')}")
        print()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("json_file")
    parser.add_argument("--section", choices=["lecture", "verses", "glossary", "passages"],
                        default=None)
    args = parser.parse_args()

    with open(args.json_file, "r", encoding="utf-8") as f:
        data = json.load(f)

    katha_ref = data.get("context", {}).get("katha_ref", "?")
    print(f"\n🕉️  Preview — {katha_ref}\n")

    section = args.section

    if not section or section == "lecture":
        preview_lecture(data.get("lecture", {}))

    if not section or section == "verses":
        preview_verses(data.get("verses_cited", []))

    if not section or section == "glossary":
        preview_glossary(data.get("glossary", []))

    if not section or section == "passages":
        preview_passages(data.get("passages", []))


if __name__ == "__main__":
    main()
