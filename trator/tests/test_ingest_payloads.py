# tests/test_ingest_payloads.py
# -*- coding: utf-8 -*-
"""
Testes de ingestão de payloads reais no pipeline do vana_trator.
Cobre: happy path, cenário fragmentado, erros bloqueantes e warnings.
"""

import json
import pytest
from pathlib import Path
from vana_trator import run_trator, TratorValidator, TratorResult

PAYLOADS = Path(__file__).parent / "payloads"


# ══════════════════════════════════════════════════════════════════════
# HELPERS
# ══════════════════════════════════════════════════════════════════════

def load(filename: str) -> dict:
    with open(PAYLOADS / filename, encoding="utf-8") as f:
        return json.load(f)


def error_codes(result: TratorResult) -> list[str]:
    return [e.code for e in result.errors]


def warning_codes(result: TratorResult) -> list[str]:
    return [w.code for w in result.warnings]


def index_of(result: TratorResult) -> dict:
    return result.processed.get("index", {})


def stats_of(result: TratorResult) -> dict:
    return result.processed.get("stats", {})


# ══════════════════════════════════════════════════════════════════════
# GRUPO 1 — CENÁRIO 1: HK dentro de vod de programa
# ══════════════════════════════════════════════════════════════════════

class TestCenario1HappyPath:

    @pytest.fixture(scope="class")
    def result(self):
        return run_trator(load("payload_cenario1_happy_path.json"), dry_run=True)

    # ── Pipeline ─────────────────────────────────────────────────────

    def test_pipeline_success(self, result):
        assert result.success is True

    def test_no_blocking_errors(self, result):
        assert result.errors == []

    def test_wp_action_is_dry_run(self, result):
        assert result.wp_action == "dry_run"

    def test_processed_not_none(self, result):
        assert result.processed is not None

    # ── Stats ─────────────────────────────────────────────────────────

    def test_stats_total_days(self, result):
        assert stats_of(result)["total_days"] == 1

    def test_stats_total_events(self, result):
        assert stats_of(result)["total_events"] == 2

    def test_stats_total_vods(self, result):
        assert stats_of(result)["total_vods"] == 2

    def test_stats_total_segments(self, result):
        assert stats_of(result)["total_segments"] == 6   # 1 arati + 5 programa

    def test_stats_total_kathas(self, result):
        assert stats_of(result)["total_kathas"] == 1

    def test_stats_total_passages(self, result):
        assert stats_of(result)["total_passages"] == 3

    def test_stats_total_photos(self, result):
        assert stats_of(result)["total_photos"] == 2

    def test_stats_total_sangha(self, result):
        assert stats_of(result)["total_sangha"] == 2

    # ── Index — Days ──────────────────────────────────────────────────

    def test_index_day_exists(self, result):
        assert "2026-02-21" in index_of(result)["days"]

    def test_index_day_position(self, result):
        assert index_of(result)["days"]["2026-02-21"]["position"] == 0

    def test_index_day_lists_both_events(self, result):
        events = index_of(result)["days"]["2026-02-21"]["events"]
        assert "20260221-0530-mangala"  in events
        assert "20260221-1703-programa" in events

    def test_index_day_primary_event(self, result):
        day = index_of(result)["days"]["2026-02-21"]
        assert day["primary_event"] == "20260221-1703-programa"

    # ── Index — Events ────────────────────────────────────────────────

    def test_index_event_mangala_exists(self, result):
        assert "20260221-0530-mangala" in index_of(result)["events"]

    def test_index_event_programa_exists(self, result):
        assert "20260221-1703-programa" in index_of(result)["events"]

    def test_index_event_programa_has_katha(self, result):
        ev = index_of(result)["events"]["20260221-1703-programa"]
        assert 678 in ev["kathas"]

    def test_index_event_programa_has_vod(self, result):
        ev = index_of(result)["events"]["20260221-1703-programa"]
        assert "vod-20260221-002" in ev["vods"]

    def test_index_event_programa_has_photos(self, result):
        ev = index_of(result)["events"]["20260221-1703-programa"]
        assert "ph-20260221-002" in ev["photos"]

    def test_index_event_mangala_no_kathas(self, result):
        ev = index_of(result)["events"]["20260221-0530-mangala"]
        assert ev["kathas"] == []

    # ── Index — Vods ──────────────────────────────────────────────────

    def test_index_vod_mangala_exists(self, result):
        assert "vod-20260221-001" in index_of(result)["vods"]

    def test_index_vod_programa_exists(self, result):
        assert "vod-20260221-002" in index_of(result)["vods"]

    def test_index_vod_provider_youtube(self, result):
        vod = index_of(result)["vods"]["vod-20260221-002"]
        assert vod["provider"] == "youtube"

    def test_index_vod_video_id(self, result):
        vod = index_of(result)["vods"]["vod-20260221-002"]
        assert vod["video_id"] == "dQw4w9WgXcQ"

    def test_index_vod_has_5_segments(self, result):
        vod = index_of(result)["vods"]["vod-20260221-002"]
        assert len(vod["segments"]) == 5

    # ── Index — Segments ──────────────────────────────────────────────

    def test_index_segment_harikatha_exists(self, result):
        assert "seg-20260221-004" in index_of(result)["segments"]

    def test_index_segment_harikatha_type(self, result):
        seg = index_of(result)["segments"]["seg-20260221-004"]
        assert seg["type"] == "harikatha"

    def test_index_segment_harikatha_katha_id(self, result):
        seg = index_of(result)["segments"]["seg-20260221-004"]
        assert seg["katha_id"] == 678

    def test_index_segment_harikatha_event_key(self, result):
        seg = index_of(result)["segments"]["seg-20260221-004"]
        assert seg["event_key"] == "20260221-1703-programa"

    def test_index_segment_harikatha_timestamps(self, result):
        seg = index_of(result)["segments"]["seg-20260221-004"]
        assert seg["timestamp_start"] == 4801
        assert seg["timestamp_end"]   == 9000

    def test_index_segment_kirtan_no_katha_id(self, result):
        seg = index_of(result)["segments"]["seg-20260221-002"]
        assert seg["katha_id"] is None

    # ── Index — Kathas ────────────────────────────────────────────────

    def test_index_katha_678_exists(self, result):
        assert "678" in index_of(result)["kathas"]

    def test_index_katha_678_scripture(self, result):
        assert index_of(result)["kathas"]["678"]["scripture"] == "SB 10.31"

    def test_index_katha_678_passage_count(self, result):
        assert index_of(result)["kathas"]["678"]["passage_count"] == 3

    def test_index_katha_678_passages_list(self, result):
        passages = index_of(result)["kathas"]["678"]["passages"]
        assert "hkp-20260221-001" in passages
        assert "hkp-20260221-002" in passages
        assert "hkp-20260221-003" in passages

    def test_index_katha_678_source_vod(self, result):
        sources = index_of(result)["kathas"]["678"]["sources"]
        assert sources[0]["vod_key"] == "vod-20260221-002"

    # ── Index — Passages ──────────────────────────────────────────────

    def test_index_passage_001_exists(self, result):
        assert "hkp-20260221-001" in index_of(result)["passages"]

    def test_index_passage_003_seek_chain(self, result):
        """Cadeia completa: passage → vod_key + timestamp → video_id."""
        p = index_of(result)["passages"]["hkp-20260221-003"]
        assert p["vod_key"]         == "vod-20260221-002"
        assert p["timestamp_start"] == 5761
        assert p["timestamp_end"]   == 9000

        vod = index_of(result)["vods"][p["vod_key"]]
        assert vod["provider"]  == "youtube"
        assert vod["video_id"]  == "dQw4w9WgXcQ"

    def test_index_passage_order_assigned(self, result):
        p1 = index_of(result)["passages"]["hkp-20260221-001"]
        p2 = index_of(result)["passages"]["hkp-20260221-002"]
        p3 = index_of(result)["passages"]["hkp-20260221-003"]
        assert p1["order"] == 1
        assert p2["order"] == 2
        assert p3["order"] == 3

    def test_index_passage_segment_id(self, result):
        p = index_of(result)["passages"]["hkp-20260221-001"]
        assert p["segment_id"] == "seg-20260221-004"

    # ── Index — Photos ────────────────────────────────────────────────

    def test_index_photo_exists(self, result):
        assert "ph-20260221-002" in index_of(result)["photos"]

    def test_index_photo_event_key(self, result):
        ph = index_of(result)["photos"]["ph-20260221-002"]
        assert ph["event_key"] == "20260221-1703-programa"

    # ── Index — Sangha ────────────────────────────────────────────────

    def test_index_sangha_exists(self, result):
        assert "sg-20260221-002" in index_of(result)["sangha"]

    def test_index_sangha_provider(self, result):
        sg = index_of(result)["sangha"]["sg-20260221-002"]
        assert sg["provider"] == "direct"

    # ── Warnings ─────────────────────────────────────────────────────

    def test_no_blocking_errors_only_warnings(self, result):
        assert result.success is True
        # thumb_url está presente em todos os vods → sem W-VOD-01


# ══════════════════════════════════════════════════════════════════════
# GRUPO 2 — CENÁRIO 3: HK fragmentado em 2 vods
# ══════════════════════════════════════════════════════════════════════

class TestCenario3Fragmentado:

    @pytest.fixture(scope="class")
    def result(self):
        return run_trator(load("payload_cenario3_fragmentado.json"), dry_run=True)

    def test_pipeline_success(self, result):
        assert result.success is True

    def test_no_blocking_errors(self, result):
        assert result.errors == []

    # ── Stats ─────────────────────────────────────────────────────────

    def test_stats_total_vods(self, result):
        # 2 vods do evento + 1 orphan
        assert stats_of(result)["total_vods"] == 3

    def test_stats_total_segments(self, result):
        assert stats_of(result)["total_segments"] == 2

    def test_stats_total_kathas(self, result):
        assert stats_of(result)["total_kathas"] == 1

    def test_stats_total_passages(self, result):
        assert stats_of(result)["total_passages"] == 2

    # ── Índice — vods ─────────────────────────────────────────────────

    def test_index_vod1_exists(self, result):
        assert "vod-20260222-001" in index_of(result)["vods"]

    def test_index_vod2_exists(self, result):
        assert "vod-20260222-002" in index_of(result)["vods"]

    def test_index_vod1_part(self, result):
        assert index_of(result)["vods"]["vod-20260222-001"]["vod_part"] == 1

    def test_index_vod2_part(self, result):
        assert index_of(result)["vods"]["vod-20260222-002"]["vod_part"] == 2

    # ── Índice — katha 679 ────────────────────────────────────────────

    def test_index_katha_679_exists(self, result):
        assert "679" in index_of(result)["kathas"]

    def test_index_katha_679_two_sources(self, result):
        sources = index_of(result)["kathas"]["679"]["sources"]
        assert len(sources) == 2

    def test_index_katha_679_passage_count(self, result):
        assert index_of(result)["kathas"]["679"]["passage_count"] == 2

    # ── Passages em vods diferentes ───────────────────────────────────

    def test_passage_p1_points_to_vod1(self, result):
        p = index_of(result)["passages"]["hkp-20260222-001"]
        assert p["vod_key"] == "vod-20260222-001"

    def test_passage_p2_points_to_vod2(self, result):
        p = index_of(result)["passages"]["hkp-20260222-002"]
        assert p["vod_key"] == "vod-20260222-002"

    def test_passage_p1_within_vod1_bounds(self, result):
        p   = index_of(result)["passages"]["hkp-20260222-001"]
        vod = index_of(result)["vods"][p["vod_key"]]
        seg = index_of(result)["segments"][p["segment_id"]]
        assert p["timestamp_start"] >= seg["timestamp_start"]
        assert p["timestamp_end"]   <= seg["timestamp_end"]

    def test_passage_p2_within_vod2_bounds(self, result):
        p   = index_of(result)["passages"]["hkp-20260222-002"]
        seg = index_of(result)["segments"][p["segment_id"]]
        assert p["timestamp_start"] >= seg["timestamp_start"]
        assert p["timestamp_end"]   <= seg["timestamp_end"]

    def test_passage_p1_order(self, result):
        assert index_of(result)["passages"]["hkp-20260222-001"]["order"] == 1

    def test_passage_p2_order(self, result):
        assert index_of(result)["passages"]["hkp-20260222-002"]["order"] == 2

    # ── Órfão ─────────────────────────────────────────────────────────

    def test_orphan_vod_indexed(self, result):
        assert "vod-orphan-001" in index_of(result)["vods"]

    def test_orphan_vod_event_key_null(self, result):
        vod = index_of(result)["vods"]["vod-orphan-001"]
        assert vod["event_key"] is None

    def test_orphan_vod_provider_facebook(self, result):
        vod = index_of(result)["vods"]["vod-orphan-001"]
        assert vod["provider"] == "facebook"


# ══════════════════════════════════════════════════════════════════════
# GRUPO 3 — PAYLOAD COM ERROS BLOQUEANTES
# ══════════════════════════════════════════════════════════════════════

class TestPayloadErrosBloqueantes:

    @pytest.fixture(scope="class")
    def result(self):
        return run_trator(load("payload_errors_bloqueantes.json"), dry_run=True)

    def test_pipeline_fails(self, result):
        assert result.success is False

    def test_has_errors(self, result):
        assert len(result.errors) > 0

    # ── Erros esperados ───────────────────────────────────────────────

    def test_error_root_01_visit_ref(self, result):
        assert "R-ROOT-01" in error_codes(result)

    def test_error_root_02_schema_version(self, result):
        assert "R-ROOT-02" in error_codes(result)

    def test_error_key_01_event_key_format(self, result):
        assert "R-KEY-01" in error_codes(result)

    def test_error_key_02_vod_key_format(self, result):
        assert "R-KEY-02" in error_codes(result)

    def test_error_key_03_segment_id_format(self, result):
        assert "R-KEY-03" in error_codes(result)

    def test_error_key_04_passage_id_format(self, result):
        assert "R-KEY-04" in error_codes(result)

    def test_error_key_05_katha_key_format(self, result):
        assert "R-KEY-05" in error_codes(result)

    def test_error_seg_01_invalid_type(self, result):
        assert "R-SEG-01" in error_codes(result)

    def test_error_seg_03_end_before_start(self, result):
        assert "R-SEG-03" in error_codes(result)

    def test_error_seg_04_katha_id_on_kirtan(self, result):
        assert "R-SEG-04" in error_codes(result)

    def test_error_kath_01_katha_id_missing(self, result):
        assert "R-KATH-01" in error_codes(result)

    def test_error_kath_05_vod_key_not_in_event(self, result):
        assert "R-KATH-05" in error_codes(result)

    def test_error_kath_07_vod_part_string(self, result):
        assert "R-KATH-07" in error_codes(result)

    def test_error_pass_01_vod_key_missing(self, result):
        assert "R-PASS-01" in error_codes(result)

    def test_error_pass_04_end_before_start(self, result):
        assert "R-PASS-04" in error_codes(result)

    def test_processed_still_returned_on_failure(self, result):
        """run_trator retorna processed mesmo em falha para diagnóstico."""
        assert result.processed is not None


# ══════════════════════════════════════════════════════════════════════
# GRUPO 4 — PAYLOAD COM WARNINGS (sem erros bloqueantes)
# ══════════════════════════════════════════════════════════════════════

class TestPayloadWarningsOnly:

    @pytest.fixture(scope="class")
    def result(self):
        return run_trator(load("payload_warnings_only.json"), dry_run=True)

    def test_pipeline_success(self, result):
        assert result.success is True

    def test_no_blocking_errors(self, result):
        assert result.errors == []

    def test_has_warnings(self, result):
        assert len(result.warnings) > 0

    def test_warn_vod_no_thumb(self, result):
        assert "W-VOD-01" in warning_codes(result)

    def test_warn_event_no_location(self, result):
        assert "W-EVT-01" in warning_codes(result)

    def test_warn_katha_no_passages(self, result):
        assert "W-KATH-01" in warning_codes(result)

    def test_warn_katha_source_no_timestamp_start(self, result):
        assert "W-KATH-02" in warning_codes(result)

    def test_stats_still_computed(self, result):
        s = stats_of(result)
        assert s["total_days"]    == 1
        assert s["total_kathas"]  == 1
        assert s["total_passages"] == 0   # katha sem passages


# ══════════════════════════════════════════════════════════════════════
# GRUPO 5 — ENVELOPE DE PUBLICAÇÃO (TratorPublisher)
# ══════════════════════════════════════════════════════════════════════

class TestPublisherEnvelope:
    """Testa a montagem do envelope HMAC sem fazer requests reais."""

    def test_envelope_kind_is_visit(self):
        from vana_trator import TratorPublisher
        pub      = TratorPublisher("https://wp.test", "secret123")
        visit    = load("payload_cenario1_happy_path.json")
        envelope = pub._build_envelope(visit, tour_key="tour:india-2026")
        assert envelope["kind"] == "visit"

    def test_envelope_origin_key_prefixed(self):
        from vana_trator import TratorPublisher
        pub      = TratorPublisher("https://wp.test", "secret123")
        visit    = load("payload_cenario1_happy_path.json")
        envelope = pub._build_envelope(visit, tour_key="tour:india-2026")
        assert envelope["origin_key"].startswith("visit:")

    def test_envelope_parent_key_prefixed(self):
        from vana_trator import TratorPublisher
        pub      = TratorPublisher("https://wp.test", "secret123")
        visit    = load("payload_cenario1_happy_path.json")
        envelope = pub._build_envelope(visit, tour_key="india-2026")
        assert envelope["parent_origin_key"] == "tour:india-2026"

    def test_envelope_data_has_schema_version(self):
        from vana_trator import TratorPublisher
        pub      = TratorPublisher("https://wp.test", "secret123")
        visit    = load("payload_cenario1_happy_path.json")
        envelope = pub._build_envelope(visit, tour_key="tour:india-2026")
        assert envelope["data"]["schema_version"] == "6.1"

    def test_hmac_sign_returns_three_params(self):
        from vana_trator import TratorPublisher
        pub    = TratorPublisher("https://wp.test", "secret123")
        params = pub._sign("payload-teste")
        assert "vana_timestamp" in params
        assert "vana_nonce"     in params
        assert "vana_signature" in params

    def test_hmac_sign_signature_is_hex(self):
        from vana_trator import TratorPublisher
        import re
        pub    = TratorPublisher("https://wp.test", "secret123")
        params = pub._sign("payload-teste")
        assert re.match(r'^[0-9a-f]{64}$', params["vana_signature"])

    def test_hmac_sign_nonce_is_hex(self):
        from vana_trator import TratorPublisher
        import re
        pub    = TratorPublisher("https://wp.test", "secret123")
        params = pub._sign("payload-teste")
        assert re.match(r'^[0-9a-f]{32}$', params["vana_nonce"])

    def test_hmac_sign_different_body_different_signature(self):
        from vana_trator import TratorPublisher
        pub = TratorPublisher("https://wp.test", "secret123")
        s1  = pub._sign("corpo-a")["vana_signature"]
        s2  = pub._sign("corpo-b")["vana_signature"]
        assert s1 != s2

    def test_run_trator_no_wp_url_returns_failure(self):
        visit  = load("payload_cenario1_happy_path.json")
        result = run_trator(visit, dry_run=False, wp_url=None, wp_secret="secret")
        assert result.success is False
        assert "R-PUB-01" in error_codes(result)

    def test_run_trator_no_wp_secret_returns_failure(self):
        visit  = load("payload_cenario1_happy_path.json")
        result = run_trator(visit, dry_run=False,
                            wp_url="https://wp.test", wp_secret=None)
        assert result.success is False
        assert "R-PUB-01" in error_codes(result)
