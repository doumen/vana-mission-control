# api/github_client.py
# -*- coding: utf-8 -*-
"""
GitHubClient - Vana Mission Control
Le e escreve visit.json / editorial.json no repositorio GitHub.
Padrao identico ao wp_client.py (sem Streamlit no modulo).
"""

import json
import base64
from datetime import datetime, timezone
from typing   import Optional

import requests


class GitHubClient:
    """
    Cliente GitHub REST v3 para o repositorio vana-mission-control.

    Uso com Streamlit:
        import streamlit as st
        gh = GitHubClient(
            token  = st.secrets["github"]["token"],
            repo   = st.secrets["github"]["repo"],
            branch = st.secrets["github"].get("branch", "main"),
        )

    Uso CLI / testes:
        import os
        gh = GitHubClient(
            token  = os.getenv("GITHUB_TOKEN"),
            repo   = os.getenv("GITHUB_REPO"),
        )
    """

    GITHUB_API = "https://api.github.com"

    def __init__(self, token: str, repo: str, branch: str = "main"):
        if not token:
            raise ValueError("GITHUB_TOKEN ausente.")
        if not repo:
            raise ValueError("GITHUB_REPO ausente (ex: org/vana-mission-control).")
        self.token   = token
        self.repo    = repo
        self.branch  = branch
        self._headers = {
            "Authorization":        "Bearer " + token,
            "Accept":               "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
        }

    # ══════════════════════════════════════════════════════════════
    # VISIT
    # ══════════════════════════════════════════════════════════════

    def get_visit(self, visit_ref: str) -> dict:
        return self._read_json("visits/" + visit_ref + "/visit.json") or {}

    def save_visit(
        self,
        visit_ref: str,
        visit:     dict,
        author:    str,
        action:    str,
    ) -> bool:
        content = json.dumps(visit, ensure_ascii=False, indent=2)
        return self._write_file(
            "visits/" + visit_ref + "/visit.json",
            content,
            "[visit] " + action + " -- " + author,
        )

    def list_visits(self) -> list:
        url  = self.GITHUB_API + "/repos/" + self.repo + "/contents/visits"
        resp = self._get(url)
        if not isinstance(resp, list):
            return []
        return [
            {"visit_ref": i["name"], "path": i["path"]}
            for i in resp
            if i["type"] == "dir"
        ]

    # ══════════════════════════════════════════════════════════════
    # EDITORIAL
    # ══════════════════════════════════════════════════════════════

    def get_editorial(self, visit_ref: str) -> dict:
        path = "visits/" + visit_ref + "/revista/editorial.json"
        data = self._read_json(path)
        return data if data is not None else self._empty_editorial(visit_ref)

    def save_editorial(
        self,
        visit_ref: str,
        editorial: dict,
        author:    str,
        action:    str,
    ) -> bool:
        content = json.dumps(editorial, ensure_ascii=False, indent=2)
        return self._write_file(
            "visits/" + visit_ref + "/revista/editorial.json",
            content,
            "[revista] " + action + " -- " + author,
        )

    # ══════════════════════════════════════════════════════════════
    # TOUR
    # ══════════════════════════════════════════════════════════════

    def get_tour(self, tour_ref: str) -> dict:
        return self._read_json("tours/" + tour_ref + "/tour.json") or {}

    def list_tours(self) -> list:
        url  = self.GITHUB_API + "/repos/" + self.repo + "/contents/tours"
        resp = self._get(url)
        if not isinstance(resp, list):
            return []
        return [
            {"tour_ref": i["name"], "path": i["path"]}
            for i in resp
            if i["type"] == "dir"
        ]

    # ══════════════════════════════════════════════════════════════
    # AUDIT
    # ══════════════════════════════════════════════════════════════

    def append_audit(
        self,
        editorial: dict,
        action:    str,
        by:        str,
        note:      str = None,
        **kwargs,
    ) -> dict:
        entry = {
            "action": action,
            "by":     by,
            "at":     datetime.now(timezone.utc).isoformat(),
            "note":   note,
        }
        entry.update(kwargs)
        editorial.setdefault("audit", []).append(entry)
        return editorial

    # ══════════════════════════════════════════════════════════════
    # PATCH HELPERS
    # ══════════════════════════════════════════════════════════════

    def patch_event(
        self,
        visit_ref: str,
        event_key: str,
        fields:    dict,
        author:    str,
    ) -> bool:
        visit = self.get_visit(visit_ref)
        found = False
        for day in visit.get("days", []):
            for event in day.get("events", []):
                if event.get("event_key") == event_key:
                    event.update(fields)
                    found = True
                    break
            if found:
                break
        if not found:
            return False
        return self.save_visit(
            visit_ref, visit, author,
            "patch event " + event_key + ": " + str(list(fields.keys())),
        )

    def patch_vod(
        self,
        visit_ref:  str,
        event_key:  str,
        vod_index:  int,
        fields:     dict,
        author:     str,
    ) -> bool:
        visit = self.get_visit(visit_ref)
        found = False
        for day in visit.get("days", []):
            for event in day.get("events", []):
                if event.get("event_key") == event_key:
                    vods = event.get("vods", [])
                    if 0 <= vod_index < len(vods):
                        vods[vod_index].update(fields)
                        found = True
                    break
            if found:
                break
        if not found:
            return False
        return self.save_visit(
            visit_ref, visit, author,
            "patch vod " + event_key + "[" + str(vod_index) + "]",
        )

    def patch_day_field(
        self,
        visit_ref: str,
        day_key:   str,
        field:     str,
        value,
        author:    str,
    ) -> bool:
        visit = self.get_visit(visit_ref)
        found = False
        for day in visit.get("days", []):
            if day.get("date_local") == day_key:
                day[field] = value
                found = True
                break
        if not found:
            return False
        return self.save_visit(
            visit_ref, visit, author,
            "patch day " + day_key + "." + field,
        )

    # ══════════════════════════════════════════════════════════════
    # LEITURA RAPIDA
    # ══════════════════════════════════════════════════════════════

    def get_event(self, visit_ref: str, event_key: str) -> Optional[dict]:
        visit = self.get_visit(visit_ref)
        for day in visit.get("days", []):
            for event in day.get("events", []):
                if event.get("event_key") == event_key:
                    return event
        return None

    def get_day(self, visit_ref: str, day_key: str) -> Optional[dict]:
        visit = self.get_visit(visit_ref)
        for day in visit.get("days", []):
            if day.get("date_local") == day_key:
                return day
        return None

    def get_passage_refs(self, visit_ref: str) -> list:
        visit = self.get_visit(visit_ref)
        refs  = []
        for day in visit.get("days", []):
            for event in day.get("events", []):
                if event.get("type") == "katha":
                    ekey     = event["event_key"]
                    passages = event.get("katha_pipeline", {}).get("passages", [])
                    for i, _ in enumerate(passages, start=1):
                        refs.append(
                            "visit:" + visit_ref + "/event:" + ekey + "/" + str(i)
                        )
        return refs

    def get_photo_refs(self, visit_ref: str, only_gurudeva: bool = False) -> list:
        visit = self.get_visit(visit_ref)
        refs  = []
        for day in visit.get("days", []):
            for moment in day.get("sangha_moments", []):
                mid = moment.get("id", "")
                for i, photo in enumerate(moment.get("photos", [])):
                    if only_gurudeva and not photo.get("with_gurudeva"):
                        continue
                    refs.append(str(mid) + "/" + str(i))
        return refs

    def get_performers(self, visit_ref: str) -> list:
        visit      = self.get_visit(visit_ref)
        performers = []
        seen       = set()
        for day in visit.get("days", []):
            for event in day.get("events", []):
                for p in event.get("performers", []):
                    key = (p.get("name"), p.get("role"))
                    if key not in seen:
                        seen.add(key)
                        performers.append(p)
        return performers

    # ══════════════════════════════════════════════════════════════
    # INTERNO
    # ══════════════════════════════════════════════════════════════

    def _read_json(self, path: str) -> Optional[dict]:
        url  = self.GITHUB_API + "/repos/" + self.repo + "/contents/" + path
        resp = self._get(url, params={"ref": self.branch})
        if resp is None:
            return None
        try:
            content = base64.b64decode(resp["content"]).decode("utf-8")
            return json.loads(content)
        except Exception as e:
            raise ValueError("Erro ao decodificar " + path + ": " + str(e))

    def _write_file(self, path: str, content: str, message: str) -> bool:
        url          = self.GITHUB_API + "/repos/" + self.repo + "/contents/" + path
        encoded      = base64.b64encode(content.encode("utf-8")).decode("utf-8")
        existing_sha = self._get_sha(path)
        body = {
            "message": message,
            "content": encoded,
            "branch":  self.branch,
        }
        if existing_sha:
            body["sha"] = existing_sha
        resp = requests.put(
            url,
            headers = self._headers,
            json    = body,
            timeout = 20,
        )
        if not resp.ok:
            raise RuntimeError(
                "GitHub write failed [" + str(resp.status_code) + "]: " + resp.text[:300]
            )
        return True

    def _get_sha(self, path: str) -> Optional[str]:
        url  = self.GITHUB_API + "/repos/" + self.repo + "/contents/" + path
        resp = self._get(url, params={"ref": self.branch})
        return resp.get("sha") if resp else None

    def _get(self, url: str, params: dict = None) -> Optional[dict]:
        resp = requests.get(
            url,
            headers = self._headers,
            params  = params or {},
            timeout = 15,
        )
        if resp.status_code == 404:
            return None
        resp.raise_for_status()
        return resp.json()

    # ══════════════════════════════════════════════════════════════
    # EMPTY EDITORIAL
    # ══════════════════════════════════════════════════════════════

    @staticmethod
    def _empty_editorial(visit_ref: str) -> dict:
        return {
            "schema_version": "1.0",
            "visit_ref":      visit_ref,
            "state":          "coleta",
            "meta": {
                "title_pt":        None,
                "title_en":        None,
                "preview_pt":      None,
                "preview_en":      None,
                "cover_photo_ref": None,
                "started_at":      None,
                "published_at":    None,
                "editor":          None,
                "supervisor":      None,
            },
            "coleta": {
                "opened_at":   datetime.now(timezone.utc).isoformat(),
                "paused":      False,
                "paused_at":   None,
                "paused_by":   None,
                "notify_list": [],
            },
            "exports": {},
            "stats":   {},
            "blocks":  [],
            "audit":   [],
        }
