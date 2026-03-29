# tests/test_vana_trator.py
# -*- coding: utf-8 -*-
"""
Suite de testes — Vana Trator Schema 6.1
Cobre: TratorValidator, TratorIndexBuilder, TratorPublisher, run_trator()

Dependências:
    pip install pytest pytest-mock responses
"""

import hashlib
import hmac
import json
import time
from unittest.mock import MagicMock, patch

import pytest
import responses as responses_lib

from vana_trator import (
    TratorIndexBuilder,
    TratorPublisher,
    TratorResult,
    TratorValidator,
    ValidationError,
    run_trator,
)


# ══════════════════════════════════════════════════════════════════════
# FIXTURES — BUILDERS DE VISIT.JSON
# ══════════════════════════════════════════════════════════════════════

def _make_passage(
    passage_id:      str   = "passage-001",
    vod_key:         str   = "vod-20260101-001",
    segment_id:      str   = "seg-001",
    timestamp_start: int   = 0,
    timestamp_end:   int   = 120,
    key_quote:       str   = "Hare Krishna",
) -> dict:
    return {
        "passage_id":  passage_id,
        "key_quote":   key_quote,
        "teaching_pt": "Ensinamento em português.",
        "teaching_en": "Teaching in English.",
        "source_ref": {
            "vod_key":         vod_key,
            "segment_id":      segment_id,
            "timestamp_start": timestamp_start,
            "timestamp_end":   timestamp_end,
        },
    }


def _make_katha(
    katha_id: str = "katha-001",
    passages: list | None = None,
) -> dict:
    return {
        "katha_id":  katha_id,
        "katha_key": katha_id,
        "title_pt":  "Palestra de teste",
        "title_en":  "Test lecture",
        "scripture": "Bhagavad-gita",
        "language":  "pt",
        "sources":   [{"vod_key": "vod-20260101-001", "segment_id": "seg-001"}],
        "passages":  passages if passages is not None else [_make_passage()],
    }


def _make_segment(
    segment_id: str = "seg-001",
    seg_type:   str = "harikatha",
    katha_id:   str | None = "katha-001",
) -> dict:
    seg = {
        "segment_id":      segment_id,
        "type":            seg_type,
        "timestamp_start": 0,
        "timestamp_end":   3600,
    }
    if katha_id is not None:
        seg["katha_id"] = katha_id
    return seg


def _make_vod(vod_key: str = "vod-20260101-001", segments: list | None = None) -> dict:
    return {
        "vod_key":    vod_key,
        "provider":   "youtube",
        "video_id":   "abc123",
        "url":        "https://youtube.com/watch?v=abc123",
        "thumb_url":  "https://img.youtube.com/vi/abc123/0.jpg",
        "duration_s": 3600,
        "segments":   segments if segments is not None else [_make_segment()],
    }


def _make_event(
    event_key: str = "event-001",
    kathas:    list | None = None,
    vods:      list | None = None,
) -> dict:
    return {
        "event_key": event_key,
        "type":      "harikatha",
        "title_pt":  "Evento de teste",
        "location":  "Templo principal",
        "vods":      vods    if vods    is not None else [_make_vod()],
        "kathas":    kathas  if kathas  is not None else [_make_katha()],
    }


def _make_day(day_key: str = "day-01", events: list | None = None) -> dict:
    return {
        "day_key":  day_key,
        "label_pt": "Dia 1",
        "label_en": "Day 1",
        "events":   events if events is not None else [_make_event()],
    }


def _make_visit(
    visit_ref: str = "visit-india-2026-001",
    days: list | None = None,
) -> dict:
    return {
        "visit_ref": visit_ref,
        "days":      days if days is not None else [_make_day()],
        "orphans":   {},
        "metadata":  {"city_pt": "Vrindavan"},
    }


# ══════════════════════════════════════════════════════════════════════
# 1. TratorValidator — casos de ERRO (R-BLOCK-*)
# ══════════════════════════════════════════════════════════════════════

class TestValidatorErrors:

    def test_valid_visit_passes(self):
        v = TratorValidator(_make_visit())
        assert v.validate() is True
        assert v.errors == []

    # ── R-BLOCK-01 ────────────────────────────────────────────────────

    def test_missing_visit_ref(self):
        visit = _make_visit()
        visit["visit_ref"] = ""
        v = TratorValidator(visit)
        assert v.validate() is False
        codes = [e.code for e in v.errors]
        assert "R-BLOCK-01" in codes

    # ── R-BLOCK-02 ────────────────────────────────────────────────────

    def test_missing_days(self):
        visit = _make_visit()
        del visit["days"]
        v = TratorValidator(visit)
        assert v.validate() is False
        codes = [e.code for e in v.errors]
        assert "R-BLOCK-02" in codes

    def test_days_not_list(self):
        visit = _make_visit()
        visit["days"] = "not a list"
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-02" for e in v.errors)

    # ── R-BLOCK-03 ────────────────────────────────────────────────────

    def test_missing_day_key(self):
        day = _make_day()
        del day["day_key"]
        visit = _make_visit(days=[day])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-03" for e in v.errors)

    # ── R-BLOCK-04 ────────────────────────────────────────────────────

    def test_missing_event_key(self):
        event = _make_event()
        del event["event_key"]
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-04" for e in v.errors)

    # ── R-BLOCK-05 ────────────────────────────────────────────────────

    def test_missing_vod_key(self):
        vod = _make_vod()
        del vod["vod_key"]
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-05" for e in v.errors)

    # ── R-BLOCK-06 ────────────────────────────────────────────────────

    def test_missing_segment_id(self):
        seg = _make_segment()
        del seg["segment_id"]
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-06" for e in v.errors)

    # ── R-BLOCK-07 ────────────────────────────────────────────────────

    def test_invalid_segment_type(self):
        seg = _make_segment(seg_type="lecture")   # inválido
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-07" for e in v.errors)

    @pytest.mark.parametrize("valid_type", [
        "kirtan", "harikatha", "pushpanjali", "arati",
        "dance", "drama", "darshan", "interval", "noise", "announcement",
    ])
    def test_all_valid_segment_types_pass(self, valid_type):
        seg = _make_segment(seg_type=valid_type, katha_id=None)
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod], kathas=[])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        codes = [e.code for e in v.errors]
        assert "R-BLOCK-07" not in codes

    # ── R-BLOCK-08 ────────────────────────────────────────────────────

    def test_katha_id_on_non_harikatha_segment(self):
        seg = _make_segment(seg_type="kirtan", katha_id="katha-001")
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-08" for e in v.errors)

    def test_katha_id_absent_on_kirtan_is_ok(self):
        seg = _make_segment(seg_type="kirtan", katha_id=None)
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod], kathas=[])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert "R-BLOCK-08" not in [e.code for e in v.errors]

    # ── R-BLOCK-09 ────────────────────────────────────────────────────

    def test_missing_passage_id(self):
        passage = _make_passage()
        del passage["passage_id"]
        katha   = _make_katha(passages=[passage])
        event   = _make_event(kathas=[katha])
        visit   = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-09" for e in v.errors)

    # ── R-BLOCK-10 ────────────────────────────────────────────────────

    def test_missing_source_ref_vod_key(self):
        passage = _make_passage()
        del passage["source_ref"]["vod_key"]
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-10" for e in v.errors)

    # ── R-BLOCK-11 / R-BLOCK-12 ───────────────────────────────────────

    def test_missing_timestamp_start(self):
        passage = _make_passage()
        del passage["source_ref"]["timestamp_start"]
        katha = _make_katha(passages=[passage])
        visit = _make_visit(days=[_make_day(events=[_make_event(kathas=[katha])])])
        v = TratorValidator(visit)
        v.validate()
        assert any(e.code == "R-BLOCK-11" for e in v.errors)

    def test_missing_timestamp_end(self):
        passage = _make_passage()
        del passage["source_ref"]["timestamp_end"]
        katha = _make_katha(passages=[passage])
        visit = _make_visit(days=[_make_day(events=[_make_event(kathas=[katha])])])
        v = TratorValidator(visit)
        v.validate()
        assert any(e.code == "R-BLOCK-12" for e in v.errors)

    # ── R-BLOCK-13 ────────────────────────────────────────────────────

    def test_timestamp_end_less_than_start(self):
        passage = _make_passage(timestamp_start=200, timestamp_end=100)
        katha   = _make_katha(passages=[passage])
        visit   = _make_visit(days=[_make_day(events=[_make_event(kathas=[katha])])])
        v = TratorValidator(visit)
        v.validate()
        assert any(e.code == "R-BLOCK-13" for e in v.errors)

    def test_timestamp_end_equals_start(self):
        passage = _make_passage(timestamp_start=100, timestamp_end=100)
        katha   = _make_katha(passages=[passage])
        visit   = _make_visit(days=[_make_day(events=[_make_event(kathas=[katha])])])
        v = TratorValidator(visit)
        v.validate()
        assert any(e.code == "R-BLOCK-13" for e in v.errors)

    # ── R-BLOCK-14 ────────────────────────────────────────────────────

    def test_duplicate_passage_id(self):
        p1 = _make_passage(passage_id="passage-001")
        p2 = _make_passage(passage_id="passage-001", timestamp_start=200, timestamp_end=300)
        katha = _make_katha(passages=[p1, p2])
        visit = _make_visit(days=[_make_day(events=[_make_event(kathas=[katha])])])
        v = TratorValidator(visit)
        assert v.validate() is False
        assert any(e.code == "R-BLOCK-14" for e in v.errors)

    def test_duplicate_passage_id_across_kathas(self):
        """Duplicata entre kathas diferentes no mesmo visit."""
        p1 = _make_passage(passage_id="passage-DUP")
        p2 = _make_passage(passage_id="passage-DUP", timestamp_start=500, timestamp_end=600)
        k1 = _make_katha(katha_id="katha-001", passages=[p1])
        k2 = _make_katha(katha_id="katha-002", passages=[p2])
        event = _make_event(kathas=[k1, k2])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        v.validate()
        assert any(e.code == "R-BLOCK-14" for e in v.errors)


# ══════════════════════════════════════════════════════════════════════
# 2. TratorValidator — WARNINGS (W-*)
# ══════════════════════════════════════════════════════════════════════

class TestValidatorWarnings:

    def test_warn_katha_empty_sources(self):
        katha = _make_katha()
        katha["sources"] = []
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        v.validate()
        assert any(w.code == "W-01" for w in v.warnings)

    def test_warn_passage_no_key_quote(self):
        passage = _make_passage(key_quote="")
        katha   = _make_katha(passages=[passage])
        visit   = _make_visit(days=[_make_day(events=[_make_event(kathas=[katha])])])
        v = TratorValidator(visit)
        v.validate()
        assert any(w.code == "W-02" for w in v.warnings)

    def test_warn_vod_no_thumb_url(self):
        vod = _make_vod()
        del vod["thumb_url"]
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        v.validate()
        assert any(w.code == "W-03" for w in v.warnings)

    def test_warn_event_no_location(self):
        event = _make_event()
        del event["location"]
        visit = _make_visit(days=[_make_day(events=[event])])
        v = TratorValidator(visit)
        v.validate()
        assert any(w.code == "W-04" for w in v.warnings)

    def test_valid_visit_has_no_warnings(self):
        v = TratorValidator(_make_visit())
        v.validate()
        assert v.warnings == []


# ══════════════════════════════════════════════════════════════════════
# 3. TratorIndexBuilder
# ══════════════════════════════════════════════════════════════════════

class TestTratorIndexBuilder:

    @pytest.fixture
    def built(self):
        visit         = _make_visit()
        builder       = TratorIndexBuilder(visit)
        index, stats  = builder.build()
        return index, stats, visit

    def test_index_days_key(self, built):
        index, _, _ = built
        assert "day-01" in index["days"]

    def test_index_events_key(self, built):
        index, _, _ = built
        assert "event-001" in index["events"]

    def test_index_vods_key(self, built):
        index, _, _ = built
        assert "vod-20260101-001" in index["vods"]

    def test_index_segments_key(self, built):
        index, _, _ = built
        assert "seg-001" in index["segments"]

    def test_index_kathas_key(self, built):
        index, _, _ = built
        assert "katha-001" in index["kathas"]

    def test_index_passages_key(self, built):
        index, _, _ = built
        assert "passage-001" in index["passages"]

    def test_stats_totals(self, built):
        _, stats, _ = built
        assert stats["total_days"]     == 1
        assert stats["total_events"]   == 1
        assert stats["total_vods"]     == 1
        assert stats["total_segments"] == 1
        assert stats["total_kathas"]   == 1
        assert stats["total_passages"] == 1

    def test_passage_order_injected(self, built):
        """Builder deve injetar order=1 no primeiro passage."""
        index, _, _ = built
        assert index["passages"]["passage-001"]["order"] == 1

    def test_passage_order_sequence(self):
        """Dois passages no mesmo katha devem ter order 1 e 2."""
        p1 = _make_passage(passage_id="p-001", timestamp_start=0,   timestamp_end=60)
        p2 = _make_passage(passage_id="p-002", timestamp_start=60,  timestamp_end=120)
        katha   = _make_katha(passages=[p1, p2])
        event   = _make_event(kathas=[katha])
        visit   = _make_visit(days=[_make_day(events=[event])])
        builder = TratorIndexBuilder(visit)
        index, stats = builder.build()

        assert index["passages"]["p-001"]["order"] == 1
        assert index["passages"]["p-002"]["order"] == 2
        assert stats["total_passages"] == 2

    def test_event_lists_kathas_and_vods(self, built):
        index, _, _ = built
        event_entry = index["events"]["event-001"]
        assert "katha-001" in event_entry["kathas"]
        assert "vod-20260101-001" in event_entry["vods"]

    def test_vod_entry_has_segments(self, built):
        index, _, _ = built
        assert "seg-001" in index["vods"]["vod-20260101-001"]["segments"]

    def test_segment_references_vod_and_event(self, built):
        index, _, _ = built
        seg = index["segments"]["seg-001"]
        assert seg["vod_key"]   == "vod-20260101-001"
        assert seg["event_key"] == "event-001"
        assert seg["day_key"]   == "day-01"

    def test_day_references_event(self, built):
        index, _, _ = built
        assert "event-001" in index["days"]["day-01"]["events"]

    def test_multiple_days_and_events(self):
        day1  = _make_day(day_key="day-01", events=[_make_event("ev-01")])
        day2  = _make_day(day_key="day-02", events=[
            _make_event("ev-02", kathas=[_make_katha(
                katha_id="katha-002",
                passages=[_make_passage("p-002", timestamp_start=0, timestamp_end=10)]
            )])
        ])
        visit = _make_visit(days=[day1, day2])
        builder = TratorIndexBuilder(visit)
        index, stats = builder.build()

        assert stats["total_days"]   == 2
        assert stats["total_events"] == 2
        assert "day-01" in index["days"]
        assert "day-02" in index["days"]

    def test_orphan_vods_indexed(self):
        visit = _make_visit()
        visit["orphans"] = {
            "vods": [_make_vod(vod_key="vod-orphan-001")],
        }
        builder = TratorIndexBuilder(visit)
        index, stats = builder.build()

        assert "vod-orphan-001" in index["vods"]
        assert index["vods"]["vod-orphan-001"]["event_key"] is None
        assert stats["total_vods"] == 2   # 1 normal + 1 orphan

    def test_orphan_photos_indexed(self):
        visit = _make_visit()
        visit["orphans"] = {
            "photos": [{"photo_key": "photo-orphan-001", "thumb_url": "http://x.com/t.jpg"}],
        }
        builder = TratorIndexBuilder(visit)
        index, stats = builder.build()

        assert "photo-orphan-001" in index["photos"]
        assert index["photos"]["photo-orphan-001"]["event_key"] is None

    def test_katha_index_has_passage_count(self, built):
        index, _, _ = built
        assert index["kathas"]["katha-001"]["passage_count"] == 1


# ══════════════════════════════════════════════════════════════════════
# 4. TratorPublisher — assinatura HMAC
# ══════════════════════════════════════════════════════════════════════

class TestTratorPublisherHMAC:

    SECRET = "minha-chave-secreta"

    def _publisher(self) -> TratorPublisher:
        return TratorPublisher(
            wp_url    = "https://vanamadhuryam.com",
            wp_secret = self.SECRET,
        )

    def test_sign_returns_three_params(self):
        pub    = self._publisher()
        params = pub._sign('{"test":1}')
        assert "vana_timestamp" in params
        assert "vana_nonce"     in params
        assert "vana_signature" in params

    def test_signature_is_valid_hmac_sha256(self):
        pub       = self._publisher()
        body_str  = '{"hello":"world"}'
        params    = pub._sign(body_str)

        timestamp = params["vana_timestamp"]
        nonce     = params["vana_nonce"]
        signature = params["vana_signature"]

        message  = f"{timestamp}\n{nonce}\n{body_str}"
        expected = hmac.new(
            self.SECRET.encode("utf-8"),
            message.encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()

        assert signature == expected

    def test_nonce_is_unique_per_call(self):
        pub    = self._publisher()
        body   = '{"x":1}'
        p1     = pub._sign(body)
        p2     = pub._sign(body)
        assert p1["vana_nonce"] != p2["vana_nonce"]

    def test_timestamp_is_recent(self):
        pub    = self._publisher()
        params = pub._sign('{}')
        ts     = int(params["vana_timestamp"])
        assert abs(ts - int(time.time())) < 5   # max 5s de diferença

    def test_endpoint_url_built_correctly(self):
        pub = TratorPublisher(
            wp_url    = "https://vanamadhuryam.com/",   # trailing slash
            wp_secret = self.SECRET,
        )
        assert pub.endpoint == "https://vanamadhuryam.com/wp-json/vana/v1/ingest-visit"

    @responses_lib.activate
    def test_publish_raises_on_http_error(self):
        responses_lib.add(
            responses_lib.POST,
            "https://vanamadhuryam.com/wp-json/vana/v1/ingest-visit",
            json   = {"error": "unauthorized"},
            status = 401,
        )
        pub   = self._publisher()
        visit = _make_visit()

        with pytest.raises(RuntimeError, match="HTTP 401"):
            pub.publish(visit, tour_key="tour:india-2026")

    @responses_lib.activate
    def test_publish_success_returns_json(self):
        wp_resp = {
            "success": True,
            "data": {
                "action":    "created",
                "visit_id":  42,
                "permalink": "https://vanamadhuryam.com/visit/india-2026/",
            },
        }
        responses_lib.add(
            responses_lib.POST,
            "https://vanamadhuryam.com/wp-json/vana/v1/ingest-visit",
            json   = wp_resp,
            status = 200,
        )
        pub   = self._publisher()
        visit = _make_visit()
        resp  = pub.publish(visit, tour_key="tour:india-2026")
        assert resp["data"]["visit_id"] == 42

    @responses_lib.activate
    def test_publish_adds_tour_prefix(self):
        """tour_key sem prefixo 'tour:' deve receber o prefixo."""
        captured = {}

        def request_callback(request):
            body = json.loads(request.body)
            captured["parent_origin_key"] = body.get("parent_origin_key")
            return (200, {}, json.dumps({"data": {"action": "created", "visit_id": 1}}))

        responses_lib.add_callback(
            responses_lib.POST,
            "https://vanamadhuryam.com/wp-json/vana/v1/ingest-visit",
            callback = request_callback,
            content_type = "application/json",
        )
        pub = self._publisher()
        pub.publish(_make_visit(), tour_key="india-2026")   # sem "tour:"
        assert captured["parent_origin_key"] == "tour:india-2026"


# ══════════════════════════════════════════════════════════════════════
# 5. run_trator() — pipeline completo
# ══════════════════════════════════════════════════════════════════════

class TestRunTrator:

    def test_dry_run_success(self):
        visit  = _make_visit()
        result = run_trator(visit, dry_run=True)

        assert result.success       is True
        assert result.wp_action     == "dry_run"
        assert result.processed     is not None
        assert "index"              in result.processed
        assert "stats"              in result.processed
        assert "generated_by"       in result.processed
        assert result.processed["generated_by"] == "vana-trator"

    def test_dry_run_returns_no_wp_id(self):
        result = run_trator(_make_visit(), dry_run=True)
        assert result.wp_id  is None
        assert result.wp_url is None

    def test_invalid_visit_returns_failure(self):
        visit = _make_visit()
        visit["visit_ref"] = ""
        result = run_trator(visit, dry_run=True)

        assert result.success is False
        assert any(e.code == "R-BLOCK-01" for e in result.errors)
        assert result.processed is None

    def test_dry_run_index_has_all_sections(self):
        result = run_trator(_make_visit(), dry_run=True)
        index  = result.processed["index"]
        for section in ["days", "events", "vods", "segments", "kathas", "passages"]:
            assert section in index

    def test_dry_run_stats_are_correct(self):
        result = run_trator(_make_visit(), dry_run=True)
        stats  = result.processed["stats"]
        assert stats["total_days"]     == 1
        assert stats["total_passages"] == 1

    def test_requires_wp_url_for_publish(self):
        visit = _make_visit()
        with pytest.raises(ValueError, match="wp_url"):
            run_trator(visit, wp_url=None, wp_secret="secret")

    def test_requires_wp_secret_for_publish(self):
        visit = _make_visit()
        with pytest.raises(ValueError, match="wp_secret"):
            run_trator(visit, wp_url="https://x.com", wp_secret=None)

    @patch("vana_trator.TratorPublisher.publish")
    def test_publish_called_with_processed_visit(self, mock_publish: MagicMock):
        mock_publish.return_value = {
            "data": {"action": "created", "visit_id": 7, "permalink": "http://x.com/v"}
        }
        visit  = _make_visit()
        result = run_trator(
            visit,
            wp_url    = "https://vanamadhuryam.com",
            wp_secret = "secret",
            tour_key  = "tour:india-2026",
        )

        assert result.success   is True
        assert result.wp_action == "created"
        assert result.wp_id     == 7

        call_args = mock_publish.call_args
        published = call_args[0][0]  # primeiro argumento posicional
        assert "index"        in published
        assert "generated_by" in published

    @patch("vana_trator.TratorPublisher.publish", side_effect=RuntimeError("timeout"))
    def test_publish_error_returns_failure_result(self, _):
        result = run_trator(
            _make_visit(),
            wp_url    = "https://vanamadhuryam.com",
            wp_secret = "secret",
        )

        assert result.success is False
        assert any(e.code == "WP-PUBLISH-ERROR" for e in result.errors)

    @patch("vana_trator.TratorPublisher.publish")
    def test_result_has_warnings_from_validator(self, mock_publish: MagicMock):
        mock_publish.return_value = {
            "data": {"action": "updated", "visit_id": 1, "permalink": "http://x.com"}
        }
        visit = _make_visit()
        visit["days"][0]["events"][0]["kathas"][0]["sources"] = []  # W-01

        result = run_trator(
            visit,
            wp_url    = "https://vanamadhuryam.com",
            wp_secret = "secret",
        )

        assert result.success is True
        assert any(w.code == "W-01" for w in result.warnings)

    @patch("vana_trator.TratorPublisher.publish")
    def test_wp_noop_action(self, mock_publish: MagicMock):
        mock_publish.return_value = {
            "data": {"action": "noop", "visit_id": 5, "permalink": "http://x.com"}
        }
        result = run_trator(
            _make_visit(),
            wp_url    = "https://vanamadhuryam.com",
            wp_secret = "secret",
        )
        assert result.wp_action == "noop"
        assert result.success   is True
