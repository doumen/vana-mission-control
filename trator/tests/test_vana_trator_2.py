# tests/test_vana_trator.py
# Schema 6.1 — Massa de Testes Completa
# Gerado em: 29/03/2026 · Vana Madhuryam

import pytest
from vana_trator import TratorValidator, TratorIndexBuilder, run_trator

# ═══════════════════════════════════════════════════════════════════
# FACTORIES — construtores reutilizáveis alinhados ao Schema 6.1
# ═══════════════════════════════════════════════════════════════════

def _make_segment(
    segment_id="seg-20260221-004",
    seg_type="harikatha",
    timestamp_start=4801,
    timestamp_end=9000,
    katha_id=None,
):
    seg = {
        "segment_id":      segment_id,
        "type":            seg_type,
        "title_pt":        "Título PT",
        "title_en":        "Title EN",
        "timestamp_start": timestamp_start,
        "timestamp_end":   timestamp_end,
    }
    if katha_id is not None:
        seg["katha_id"] = katha_id
    return seg


def _make_vod(
    vod_key="vod-20260221-002",
    video_id="dQw4w9WgXcQ",
    provider="youtube",
    duration_s=13802,
    vod_part=1,
    segments=None,
):
    return {
        "vod_key":    vod_key,
        "provider":   provider,
        "video_id":   video_id,
        "url":        None,
        "thumb_url":  f"https://img.youtube.com/vi/{video_id}/maxresdefault.jpg",
        "duration_s": duration_s,
        "title_pt":   "Título PT",
        "title_en":   "Title EN",
        "vod_part":   vod_part,
        "segments":   segments if segments is not None else [_make_segment()],
    }


def _make_source(
    vod_key="vod-20260221-002",
    segment_id="seg-20260221-004",
    timestamp_start=4801,
    timestamp_end=9000,
    vod_part=1,
):
    return {
        "vod_key":         vod_key,
        "segment_id":      segment_id,
        "timestamp_start": timestamp_start,
        "timestamp_end":   timestamp_end,
        "vod_part":        vod_part,
    }


def _make_passage(
    passage_id="hkp-20260221-001",
    katha_id=678,
    timestamp_start=4801,
    timestamp_end=5280,
    vod_key="vod-20260221-002",
    segment_id="seg-20260221-004",
):
    return {
        "passage_id":  passage_id,
        "passage_key": passage_id,
        "katha_id":    katha_id,
        "title_pt":    "Título PT",
        "title_en":    "Title EN",
        "teaching_pt": "Ensinamento PT",
        "teaching_en": "Teaching EN",
        "key_quote":   "oṁ ajñāna-timirāndhasya...",
        "source_ref": {
            "vod_key":         vod_key,
            "segment_id":      segment_id,
            "timestamp_start": timestamp_start,
            "timestamp_end":   timestamp_end,
        },
    }


def _make_katha(
    katha_id=678,
    katha_key="katha-20260221-sb1031",
    sources=None,
    passages=None,
):
    return {
        "katha_id":  katha_id,
        "katha_key": katha_key,
        "title_pt":  "SB 10.31 — Gopī-gīta",
        "title_en":  "SB 10.31 — Gopī-gīta",
        "scripture": "SB 10.31",
        "language":  "hi",
        "sources":   sources if sources is not None else [_make_source()],
        "passages":  passages if passages is not None else [_make_passage()],
    }


def _make_event(
    event_key="20260221-1703-programa",
    vods=None,
    kathas=None,
    photos=None,
    sangha=None,
):
    return {
        "event_key": event_key,
        "type":      "programa",
        "title_pt":  "Programa",
        "title_en":  "Program",
        "time":      "17:03",
        "status":    "past",
        "location":  {"name": "Gopīnātha Bhavana", "lat": 27.5794, "lng": 77.6952},
        "vods":      vods   if vods   is not None else [_make_vod()],
        "kathas":    kathas if kathas is not None else [_make_katha()],
        "photos":    photos if photos is not None else [],
        "sangha":    sangha if sangha is not None else [],
    }


def _make_day(
    day_key="2026-02-21",
    events=None,
    primary_event="20260221-1703-programa",
):
    return {
        "day_key":       day_key,
        "label_pt":      "21 fev",
        "label_en":      "Feb 21",
        "primary_event": primary_event,
        "events":        events if events is not None else [_make_event()],
    }


def _make_visit(days=None, orphans=None):
    return {
        "$schema":       "https://vanamadhuryam.com/schemas/timeline-6.1.json",
        "schema_version": "6.1",
        "visit_ref":     "vrindavan-2026-02",
        "tour_ref":      "india-2026",
        "metadata": {
            "city_pt":    "Vṛndāvana",
            "city_en":    "Vrindavan",
            "country":    "IN",
            "date_start": "2026-02-18",
            "date_end":   "2026-02-27",
            "timezone":   "Asia/Kolkata",
            "status":     "completed",
        },
        "days":    days    if days    is not None else [_make_day()],
        "orphans": orphans if orphans is not None else {
            "vods": [], "photos": [], "sangha": [], "kathas": []
        },
    }


# ═══════════════════════════════════════════════════════════════════
# GRUPO 1 — VALIDAÇÃO DA RAIZ DO VISIT
# ═══════════════════════════════════════════════════════════════════

class TestSchemaRoot:

    def test_valid_visit_passes(self):
        assert TratorValidator(_make_visit()).validate() is True

    def test_missing_visit_ref(self):
        v = _make_visit()
        del v["visit_ref"]
        val = TratorValidator(v)
        val.validate()
        assert any(e.code == "R-ROOT-01" for e in val.errors)

    def test_missing_schema_version(self):
        v = _make_visit()
        del v["schema_version"]
        val = TratorValidator(v)
        val.validate()
        assert any(e.code == "R-ROOT-02" for e in val.errors)

    def test_wrong_schema_version(self):
        v = _make_visit()
        v["schema_version"] = "5.0"
        val = TratorValidator(v)
        val.validate()
        assert any(e.code == "R-ROOT-02" for e in val.errors)

    def test_missing_days(self):
        v = _make_visit()
        del v["days"]
        val = TratorValidator(v)
        val.validate()
        assert any(e.code == "R-ROOT-03" for e in val.errors)

    def test_days_not_list(self):
        v = _make_visit()
        v["days"] = "not a list"
        val = TratorValidator(v)
        assert val.validate() is False

    def test_missing_metadata(self):
        v = _make_visit()
        del v["metadata"]
        val = TratorValidator(v)
        val.validate()
        assert any(e.code == "R-ROOT-04" for e in val.errors)

    def test_empty_days_list_is_valid(self):
        """days[] vazio é permitido (visita sem dias indexados ainda)."""
        v = _make_visit(days=[])
        assert TratorValidator(v).validate() is True


# ═══════════════════════════════════════════════════════════════════
# GRUPO 2 — NOMENCLATURA DAS CHAVES (Regras Canônicas)
# ═══════════════════════════════════════════════════════════════════

class TestKeyNomenclature:
    """event_key → YYYYMMDD-HHMM-slug  /  vod_key → vod-YYYYMMDD-NNN"""

    def test_event_key_valid_format(self):
        visit = _make_visit()
        assert TratorValidator(visit).validate() is True

    def test_event_key_invalid_format(self):
        event = _make_event(event_key="evento-invalido")
        visit = _make_visit(days=[_make_day(events=[event], primary_event="evento-invalido")])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KEY-01" for e in val.errors)

    def test_vod_key_invalid_format(self):
        vod = _make_vod(vod_key="video-abc")
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KEY-02" for e in val.errors)

    def test_segment_id_invalid_format(self):
        seg = _make_segment(segment_id="segmento-abc")
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KEY-03" for e in val.errors)

    def test_passage_id_invalid_format(self):
        passage = _make_passage(passage_id="passage-abc")
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KEY-04" for e in val.errors)

    def test_katha_key_invalid_format(self):
        katha = _make_katha(katha_key="katha_invalida")
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KEY-05" for e in val.errors)


# ═══════════════════════════════════════════════════════════════════
# GRUPO 3 — REGRAS DE SEGMENT (R03, R05, R10)
# ═══════════════════════════════════════════════════════════════════

class TestSegmentRules:

    @pytest.mark.parametrize("seg_type", [
        "kirtan", "harikatha", "pushpanjali", "arati",
        "dance", "drama", "darshan", "interval", "noise", "announcement",
    ])
    def test_all_valid_segment_types(self, seg_type):
        katha_id = 678 if seg_type == "harikatha" else None
        seg = _make_segment(seg_type=seg_type, katha_id=katha_id)
        vod = _make_vod(segments=[seg])
        kathas = [_make_katha()] if seg_type == "harikatha" else []
        event = _make_event(vods=[vod], kathas=kathas)
        visit = _make_visit(days=[_make_day(events=[event])])
        assert TratorValidator(visit).validate() is True

    def test_invalid_segment_type(self):
        seg = _make_segment(seg_type="palestra")
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-SEG-01" for e in val.errors)

    def test_segment_timestamps_required(self):
        seg = _make_segment()
        del seg["timestamp_start"]
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-SEG-02" for e in val.errors)

    def test_segment_end_greater_than_start(self):
        seg = _make_segment(timestamp_start=5000, timestamp_end=2000)
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-SEG-03" for e in val.errors)

    # R10 — katha_id em segment APENAS quando type == harikatha
    def test_katha_id_only_on_harikatha_segment(self):
        seg = _make_segment(seg_type="kirtan", katha_id=678)
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-SEG-04" for e in val.errors)

    def test_harikatha_segment_without_katha_id_is_ok(self):
        """segment harikatha sem katha_id é permitido (ainda não curado)."""
        seg = _make_segment(seg_type="harikatha")  # sem katha_id
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod], kathas=[])
        visit = _make_visit(days=[_make_day(events=[event])])
        assert TratorValidator(visit).validate() is True

    def test_segment_missing_segment_id(self):
        seg = _make_segment()
        del seg["segment_id"]
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-SEG-05" for e in val.errors)

    def test_duplicate_segment_id_within_vod(self):
        seg1 = _make_segment(segment_id="seg-20260221-004", timestamp_start=0, timestamp_end=1000)
        seg2 = _make_segment(segment_id="seg-20260221-004", timestamp_start=1001, timestamp_end=2000)
        vod = _make_vod(segments=[seg1, seg2])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-SEG-06" for e in val.errors)

    def test_duplicate_segment_id_across_vods(self):
        seg1 = _make_segment(segment_id="seg-20260221-004")
        seg2 = _make_segment(segment_id="seg-20260221-004",
                             timestamp_start=0, timestamp_end=100)
        vod1 = _make_vod(vod_key="vod-20260221-002", segments=[seg1])
        vod2 = _make_vod(vod_key="vod-20260221-003", video_id="OTHER1", segments=[seg2])
        event = _make_event(vods=[vod1, vod2])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-SEG-06" for e in val.errors)


# ═══════════════════════════════════════════════════════════════════
# GRUPO 4 — REGRAS DE PASSAGE (R07, R08, R09, R13)
# ═══════════════════════════════════════════════════════════════════

class TestPassageRules:

    # R08 — timestamp_start do passage >= timestamp_start do segment
    def test_passage_start_before_segment_start_fails(self):
        """passage.source_ref.timestamp_start < segment.timestamp_start → erro R08."""
        seg = _make_segment(timestamp_start=4801, timestamp_end=9000)
        passage = _make_passage(timestamp_start=100, timestamp_end=5000)  # 100 < 4801
        katha = _make_katha(passages=[passage])
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod], kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-08" for e in val.errors)

    # R09 — timestamp_end do passage <= timestamp_end do segment
    def test_passage_end_after_segment_end_fails(self):
        """passage.source_ref.timestamp_end > segment.timestamp_end → erro R09."""
        seg = _make_segment(timestamp_start=4801, timestamp_end=9000)
        passage = _make_passage(timestamp_start=4801, timestamp_end=9999)  # 9999 > 9000
        katha = _make_katha(passages=[passage])
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod], kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-09" for e in val.errors)

    def test_passage_timestamps_within_segment_passes(self):
        seg = _make_segment(timestamp_start=4801, timestamp_end=9000)
        passage = _make_passage(timestamp_start=5000, timestamp_end=8000)
        katha = _make_katha(passages=[passage])
        vod = _make_vod(segments=[seg])
        event = _make_event(vods=[vod], kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        assert TratorValidator(visit).validate() is True

    def test_passage_missing_vod_key_in_source_ref(self):
        passage = _make_passage()
        del passage["source_ref"]["vod_key"]
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-01" for e in val.errors)

    def test_passage_missing_timestamp_start(self):
        passage = _make_passage()
        del passage["source_ref"]["timestamp_start"]
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-02" for e in val.errors)

    def test_passage_missing_timestamp_end(self):
        passage = _make_passage()
        del passage["source_ref"]["timestamp_end"]
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-03" for e in val.errors)

    def test_passage_end_less_than_start_fails(self):
        passage = _make_passage(timestamp_start=6000, timestamp_end=5000)
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-04" for e in val.errors)

    def test_passage_end_equals_start_fails(self):
        passage = _make_passage(timestamp_start=5000, timestamp_end=5000)
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-04" for e in val.errors)

    def test_passage_missing_passage_id(self):
        passage = _make_passage()
        del passage["passage_id"]
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-05" for e in val.errors)

    # R13 — passage_id nunca pode ser alterado (unicidade global)
    def test_duplicate_passage_id_within_katha(self):
        p1 = _make_passage(passage_id="hkp-20260221-001")
        p2 = _make_passage(passage_id="hkp-20260221-001",
                           timestamp_start=5500, timestamp_end=6000)
        katha = _make_katha(passages=[p1, p2])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-06" for e in val.errors)

    def test_duplicate_passage_id_across_kathas(self):
        p1 = _make_passage(passage_id="hkp-20260221-001")
        p2 = _make_passage(passage_id="hkp-20260221-001",
                           timestamp_start=5500, timestamp_end=6000)
        k1 = _make_katha(katha_id=678, katha_key="katha-20260221-sb1031", passages=[p1])
        k2 = _make_katha(katha_id=679, katha_key="katha-20260222-sb1031-cont", passages=[p2])
        event = _make_event(kathas=[k1, k2])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-06" for e in val.errors)

    def test_passage_source_ref_vod_key_must_exist_in_vods(self):
        """passage aponta para vod_key inexistente no evento."""
        passage = _make_passage(vod_key="vod-INEXISTENTE-001")
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-PASS-07" for e in val.errors)

    def test_passage_segment_id_optional_in_source_ref(self):
        """segment_id no source_ref é opcional (R11)."""
        passage = _make_passage()
        del passage["source_ref"]["segment_id"]
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        assert TratorValidator(visit).validate() is True


# ═══════════════════════════════════════════════════════════════════
# GRUPO 5 — REGRAS DE KATHA (R05, R06, R07)
# ═══════════════════════════════════════════════════════════════════

class TestKathaRules:

    def test_katha_must_have_katha_id(self):
        katha = _make_katha()
        del katha["katha_id"]
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KATH-01" for e in val.errors)

    def test_katha_must_have_katha_key(self):
        katha = _make_katha()
        del katha["katha_key"]
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KATH-02" for e in val.errors)

    def test_katha_must_have_sources(self):
        katha = _make_katha(sources=[])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KATH-03" for e in val.errors)

    def test_katha_source_vod_key_required(self):
        source = _make_source()
        del source["vod_key"]
        katha = _make_katha(sources=[source])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KATH-04" for e in val.errors)

    # R11 — sources[].segment_id OPCIONAL
    def test_katha_source_segment_id_is_optional(self):
        source = _make_source()
        del source["segment_id"]
        katha = _make_katha(sources=[source])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        assert TratorValidator(visit).validate() is True

    def test_katha_source_vod_key_must_exist_in_event(self):
        """sources[].vod_key aponta para vod inexistente no evento."""
        source = _make_source(vod_key="vod-INEXISTENTE-002")
        katha = _make_katha(sources=[source])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KATH-05" for e in val.errors)

    def test_duplicate_katha_id_across_events(self):
        k1 = _make_katha(katha_id=678)
        k2 = _make_katha(katha_id=678, katha_key="katha-20260222-sb1031-cont")
        e1 = _make_event(event_key="20260221-1703-programa", kathas=[k1])
        e2 = _make_event(event_key="20260222-1800-hk",       kathas=[k2])
        d1 = _make_day(day_key="2026-02-21", events=[e1], primary_event="20260221-1703-programa")
        d2 = _make_day(day_key="2026-02-22", events=[e2], primary_event="20260222-1800-hk")
        visit = _make_visit(days=[d1, d2])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KATH-06" for e in val.errors)

    # R12 — vod_part como int ordenador no sources[]
    def test_katha_sources_vod_part_must_be_int(self):
        source = _make_source()
        source["vod_part"] = "primeiro"  # string inválida
        katha = _make_katha(sources=[source])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(e.code == "R-KATH-07" for e in val.errors)


# ═══════════════════════════════════════════════════════════════════
# GRUPO 6 — CENÁRIO 1: HK dentro de vod de programa
# ═══════════════════════════════════════════════════════════════════

class TestCenario1HKDentroDeVod:
    """HK nasce de um segment harikatha dentro de vod de programa."""

    def _build_visit(self):
        seg_kirtan = _make_segment(
            segment_id="seg-20260221-002", seg_type="kirtan",
            timestamp_start=0, timestamp_end=2732)
        seg_hk = _make_segment(
            segment_id="seg-20260221-004", seg_type="harikatha",
            timestamp_start=4801, timestamp_end=9000, katha_id=678)
        vod = _make_vod(segments=[seg_kirtan, seg_hk])
        passage = _make_passage(
            passage_id="hkp-20260221-001",
            timestamp_start=4801, timestamp_end=5280)
        katha = _make_katha(
            katha_id=678,
            sources=[_make_source(segment_id="seg-20260221-004",
                                  timestamp_start=4801, timestamp_end=9000)],
            passages=[passage])
        event = _make_event(vods=[vod], kathas=[katha])
        return _make_visit(days=[_make_day(events=[event])])

    def test_cenario1_passes_validation(self):
        assert TratorValidator(self._build_visit()).validate() is True

    def test_cenario1_passage_within_segment_bounds(self):
        val = TratorValidator(self._build_visit())
        val.validate()
        assert not any(e.code in ("R-PASS-08", "R-PASS-09") for e in val.errors)

    def test_cenario1_segment_katha_id_only_on_harikatha(self):
        visit = self._build_visit()
        # seg kirtan não deve ter katha_id
        segs = visit["days"][0]["events"][0]["vods"][0]["segments"]
        kirtan_seg = next(s for s in segs if s["type"] == "kirtan")
        assert "katha_id" not in kirtan_seg


# ═══════════════════════════════════════════════════════════════════
# GRUPO 7 — CENÁRIO 2: vod é HK puro (segment_id opcional)
# ═══════════════════════════════════════════════════════════════════

class TestCenario2VodHKPuro:
    """Vod inteiro é o HK — segment_id no sources[] é opcional."""

    def _build_visit(self):
        seg = _make_segment(
            segment_id="seg-20260221-004",
            seg_type="harikatha",
            timestamp_start=0, timestamp_end=4980, katha_id=678)
        vod = _make_vod(duration_s=4980, segments=[seg])
        source = _make_source(timestamp_start=0, timestamp_end=4980)
        del source["segment_id"]  # R11: segment_id OPCIONAL
        passage = _make_passage(timestamp_start=0, timestamp_end=4980)
        del passage["source_ref"]["segment_id"]
        katha = _make_katha(sources=[source], passages=[passage])
        event = _make_event(vods=[vod], kathas=[katha])
        return _make_visit(days=[_make_day(events=[event])])

    def test_cenario2_passes_validation(self):
        assert TratorValidator(self._build_visit()).validate() is True

    def test_cenario2_no_segment_id_required_in_source(self):
        val = TratorValidator(self._build_visit())
        val.validate()
        assert not any(e.code == "R-KATH-04" for e in val.errors)


# ═══════════════════════════════════════════════════════════════════
# GRUPO 8 — CENÁRIO 3: HK fragmentado em múltiplos vods
# ═══════════════════════════════════════════════════════════════════

class TestCenario3HKFragmentado:
    """HK em 2 vods distintos — sources[] com 2 entradas, vod_part ordena."""

    def _build_visit(self):
        seg1 = _make_segment(
            segment_id="seg-20260222-001", seg_type="harikatha",
            timestamp_start=0, timestamp_end=4200, katha_id=679)
        seg2 = _make_segment(
            segment_id="seg-20260222-002", seg_type="harikatha",
            timestamp_start=0, timestamp_end=2700, katha_id=679)
        vod1 = _make_vod(vod_key="vod-20260222-001", video_id="XYZ001",
                         duration_s=4200, vod_part=1, segments=[seg1])
        vod2 = _make_vod(vod_key="vod-20260222-002", video_id="XYZ002",
                         duration_s=2700, vod_part=2, segments=[seg2])
        sources = [
            _make_source(vod_key="vod-20260222-001",
                         segment_id="seg-20260222-001",
                         timestamp_start=0, timestamp_end=4200, vod_part=1),
            _make_source(vod_key="vod-20260222-002",
                         segment_id="seg-20260222-002",
                         timestamp_start=0, timestamp_end=2700, vod_part=2),
        ]
        p1 = _make_passage(
            passage_id="hkp-20260222-001", katha_id=679,
            vod_key="vod-20260222-001", segment_id="seg-20260222-001",
            timestamp_start=0, timestamp_end=2100)
        p2 = _make_passage(
            passage_id="hkp-20260222-002", katha_id=679,
            vod_key="vod-20260222-002", segment_id="seg-20260222-002",
            timestamp_start=0, timestamp_end=2700)
        katha = _make_katha(
            katha_id=679,
            katha_key="katha-20260222-sb1031-cont",
            sources=sources,
            passages=[p1, p2])
        event = _make_event(
            event_key="20260222-1800-hk",
            vods=[vod1, vod2],
            kathas=[katha])
        day = _make_day(
            day_key="2026-02-22",
            events=[event],
            primary_event="20260222-1800-hk")
        return _make_visit(days=[day])

    def test_cenario3_passes_validation(self):
        assert TratorValidator(self._build_visit()).validate() is True

    def test_cenario3_sources_count_is_two(self):
        visit = self._build_visit()
        katha = visit["days"][0]["events"][0]["kathas"][0]
        assert len(katha["sources"]) == 2

    def test_cenario3_vod_part_ordering(self):
        visit = self._build_visit()
        sources = visit["days"][0]["events"][0]["kathas"][0]["sources"]
        parts = [s["vod_part"] for s in sources]
        assert parts == sorted(parts)

    def test_cenario3_passages_span_two_different_vods(self):
        visit = self._build_visit()
        passages = visit["days"][0]["events"][0]["kathas"][0]["passages"]
        vod_keys = {p["source_ref"]["vod_key"] for p in passages}
        assert len(vod_keys) == 2

    def test_cenario3_passage_p1_within_vod1_segment_bounds(self):
        visit = self._build_visit()
        p1 = visit["days"][0]["events"][0]["kathas"][0]["passages"][0]
        assert p1["source_ref"]["timestamp_start"] >= 0
        assert p1["source_ref"]["timestamp_end"]   <= 4200

    def test_cenario3_passage_p2_within_vod2_segment_bounds(self):
        visit = self._build_visit()
        p2 = visit["days"][0]["events"][0]["kathas"][0]["passages"][1]
        assert p2["source_ref"]["timestamp_start"] >= 0
        assert p2["source_ref"]["timestamp_end"]   <= 2700


# ═══════════════════════════════════════════════════════════════════
# GRUPO 9 — ÍNDICE (Bloco 5 — gerado pelo Trator)
# ═══════════════════════════════════════════════════════════════════

class TestIndexBuilder:

    def _visit_full(self):
        """Visit canônico com Cenário 1 (programa com HK)."""
        seg_kirtan = _make_segment(
            segment_id="seg-20260221-002", seg_type="kirtan",
            timestamp_start=0, timestamp_end=2732)
        seg_hk = _make_segment(
            segment_id="seg-20260221-004", seg_type="harikatha",
            timestamp_start=4801, timestamp_end=9000, katha_id=678)
        vod = _make_vod(segments=[seg_kirtan, seg_hk])
        p1 = _make_passage("hkp-20260221-001", timestamp_start=4801, timestamp_end=5280)
        p2 = _make_passage("hkp-20260221-002", timestamp_start=5281, timestamp_end=5760)
        p3 = _make_passage("hkp-20260221-003", timestamp_start=5761, timestamp_end=9000)
        katha = _make_katha(passages=[p1, p2, p3])
        event = _make_event(vods=[vod], kathas=[katha])
        return _make_visit(days=[_make_day(events=[event])])

    # ── Estrutura do índice ──────────────────────────────────────

    def test_index_has_all_required_sections(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        for key in ("days", "events", "vods", "segments", "kathas", "passages"):
            assert key in idx["index"], f"Seção ausente: {key}"

    def test_index_days_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert "2026-02-21" in idx["index"]["days"]

    def test_index_events_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert "20260221-1703-programa" in idx["index"]["events"]

    def test_index_vods_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert "vod-20260221-002" in idx["index"]["vods"]

    def test_index_segments_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert "seg-20260221-004" in idx["index"]["segments"]

    def test_index_kathas_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert "678" in idx["index"]["kathas"]

    def test_index_passages_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert "hkp-20260221-001" in idx["index"]["passages"]

    # ── Lookup O(1) — cadeia de seek ────────────────────────────

    def test_passage_seek_chain(self):
        """index.passages[id] → vod_key + timestamp → index.vods[vod_key] → video_id."""
        idx = TratorIndexBuilder(self._visit_full()).build()
        passage_entry = idx["index"]["passages"]["hkp-20260221-003"]
        assert passage_entry["vod_key"]         == "vod-20260221-002"
        assert passage_entry["timestamp_start"] == 5761
        vod_entry = idx["index"]["vods"][passage_entry["vod_key"]]
        assert vod_entry["video_id"] == "dQw4w9WgXcQ"
        assert vod_entry["provider"] == "youtube"

    def test_segment_lookup_has_event_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        seg = idx["index"]["segments"]["seg-20260221-004"]
        assert seg["event_key"] == "20260221-1703-programa"

    def test_segment_lookup_has_day_key(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        seg = idx["index"]["segments"]["seg-20260221-004"]
        assert seg["day_key"] == "2026-02-21"

    def test_katha_index_has_passage_list(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        katha = idx["index"]["kathas"]["678"]
        assert "hkp-20260221-001" in katha["passages"]
        assert "hkp-20260221-002" in katha["passages"]
        assert "hkp-20260221-003" in katha["passages"]

    def test_katha_index_passage_count(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert idx["index"]["kathas"]["678"]["passage_count"] == 3

    def test_event_index_lists_vods_and_kathas(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        ev = idx["index"]["events"]["20260221-1703-programa"]
        assert "vod-20260221-002" in ev["vods"]
        assert 678 in ev["kathas"]

    def test_day_index_lists_events(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        day = idx["index"]["days"]["2026-02-21"]
        assert "20260221-1703-programa" in day["events"]

    def test_day_index_has_position(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        assert idx["index"]["days"]["2026-02-21"]["position"] == 0

    def test_passage_index_has_segment_id(self):
        idx = TratorIndexBuilder(self._visit_full()).build()
        p = idx["index"]["passages"]["hkp-20260221-001"]
        assert p["segment_id"] == "seg-20260221-004"

    # ── Index é derivado — nunca editado manualmente (R02, R03) ──

    def test_index_is_regenerated_from_days(self):
        """Mudar days[] e rebuildar deve refletir no índice."""
        visit = self._visit_full()
        visit["days"][0]["day_key"] = "2026-02-21"  # mantém igual
        idx1 = TratorIndexBuilder(visit).build()
        assert "2026-02-21" in idx1["index"]["days"]


# ═══════════════════════════════════════════════════════════════════
# GRUPO 10 — STATS (Bloco 4 — gerado pelo Trator)
# ═══════════════════════════════════════════════════════════════════

class TestStats:

    def test_stats_total_days(self):
        visit = _make_visit(days=[_make_day()])
        result = run_trator(visit, dry_run=True)
        assert result["stats"]["total_days"] == 1

    def test_stats_total_vods(self):
        vod1 = _make_vod(vod_key="vod-20260221-002")
        vod2 = _make_vod(vod_key="vod-20260221-003", video_id="OTHER1")
        event = _make_event(vods=[vod1, vod2], kathas=[])
        visit = _make_visit(days=[_make_day(events=[event])])
        result = run_trator(visit, dry_run=True)
        assert result["stats"]["total_vods"] == 2

    def test_stats_total_segments(self):
        seg1 = _make_segment(segment_id="seg-20260221-002", seg_type="kirtan",
                             timestamp_start=0, timestamp_end=2732)
        seg2 = _make_segment(segment_id="seg-20260221-004",
                             timestamp_start=4801, timestamp_end=9000)
        vod = _make_vod(segments=[seg1, seg2])
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        result = run_trator(visit, dry_run=True)
        assert result["stats"]["total_segments"] == 2

    def test_stats_total_kathas(self):
        k1 = _make_katha(katha_id=678)
        k2 = _make_katha(katha_id=679, katha_key="katha-20260222-sb1031-cont")
        event = _make_event(kathas=[k1, k2])
        visit = _make_visit(days=[_make_day(events=[event])])
        result = run_trator(visit, dry_run=True)
        assert result["stats"]["total_kathas"] == 2

    def test_stats_total_passages(self):
        p1 = _make_passage("hkp-20260221-001", timestamp_start=4801, timestamp_end=5280)
        p2 = _make_passage("hkp-20260221-002", timestamp_start=5281, timestamp_end=5760)
        p3 = _make_passage("hkp-20260221-003", timestamp_start=5761, timestamp_end=9000)
        katha = _make_katha(passages=[p1, p2, p3])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        result = run_trator(visit, dry_run=True)
        assert result["stats"]["total_passages"] == 3

    def test_stats_orphan_vods_counted(self):
        orphan_vod = {
            "vod_key": "vod-orphan-001", "provider": "facebook",
            "video_id": None, "url": "https://fb.com/watch/?v=777",
            "thumb_url": None, "duration_s": None,
            "title_pt": "Darśana", "title_en": "Darshana",
            "segments": []
        }
        visit = _make_visit(orphans={"vods": [orphan_vod],
                                     "photos": [], "sangha": [], "kathas": []})
        result = run_trator(visit, dry_run=True)
        assert result["stats"]["total_vods"] >= 1


# ═══════════════════════════════════════════════════════════════════
# GRUPO 11 — ÓRFÃOS (Bloco 3 — event_key = null)
# ═══════════════════════════════════════════════════════════════════

class TestOrphans:

    def test_orphan_vod_indexed_with_null_event_key(self):
        orphan_vod = {
            "vod_key": "vod-orphan-001", "provider": "facebook",
            "video_id": None, "url": "https://fb.com/watch/?v=777",
            "thumb_url": None, "duration_s": None,
            "title_pt": "Darśana rápido", "title_en": "Quick Darshana",
            "segments": []
        }
        visit = _make_visit(orphans={"vods": [orphan_vod],
                                     "photos": [], "sangha": [], "kathas": []})
        idx = TratorIndexBuilder(visit).build()
        assert "vod-orphan-001" in idx["index"]["vods"]
        assert idx["index"]["vods"]["vod-orphan-001"]["event_key"] is None

    def test_orphan_photo_indexed_with_null_event_key(self):
        orphan_photo = {
            "photo_key": "ph-orphan-001",
            "thumb_url": "https://cdn.vanamadhuryam.com/ph-orphan-001-thumb.jpg",
            "full_url":  "https://cdn.vanamadhuryam.com/ph-orphan-001.jpg",
            "caption_pt": "Momento espontâneo",
            "caption_en": "Spontaneous moment",
            "author": None,
        }
        visit = _make_visit(orphans={"vods": [], "photos": [orphan_photo],
                                     "sangha": [], "kathas": []})
        idx = TratorIndexBuilder(visit).build()
        assert "ph-orphan-001" in idx["index"]["photos"]
        assert idx["index"]["photos"]["ph-orphan-001"]["event_key"] is None

    def test_orphan_vod_passes_validation(self):
        orphan_vod = {
            "vod_key": "vod-orphan-001", "provider": "facebook",
            "video_id": None, "url": "https://fb.com/watch/?v=777",
            "thumb_url": None, "duration_s": None,
            "title_pt": "Darśana", "title_en": "Darshana",
            "segments": []
        }
        visit = _make_visit(orphans={"vods": [orphan_vod],
                                     "photos": [], "sangha": [], "kathas": []})
        assert TratorValidator(visit).validate() is True


# ═══════════════════════════════════════════════════════════════════
# GRUPO 12 — WARNINGS (não-bloqueantes)
# ═══════════════════════════════════════════════════════════════════

class TestWarnings:

    def test_warn_passage_no_key_quote(self):
        passage = _make_passage()
        del passage["key_quote"]
        katha = _make_katha(passages=[passage])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        assert val.validate() is True   # warning, não erro
        assert any(w.code == "W-PASS-01" for w in val.warnings)

    def test_warn_katha_no_passages(self):
        katha = _make_katha(passages=[])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        assert val.validate() is True
        assert any(w.code == "W-KATH-01" for w in val.warnings)

    def test_warn_vod_no_thumb_url(self):
        vod = _make_vod()
        vod["thumb_url"] = None
        event = _make_event(vods=[vod])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        assert val.validate() is True
        assert any(w.code == "W-VOD-01" for w in val.warnings)

    def test_warn_event_no_location(self):
        event = _make_event()
        del event["location"]
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        assert val.validate() is True
        assert any(w.code == "W-EVT-01" for w in val.warnings)

    def test_warn_katha_sources_timestamp_empty(self):
        """sources[] com timestamp ausente → warning, não erro bloqueante."""
        source = _make_source()
        del source["timestamp_start"]
        katha = _make_katha(sources=[source])
        event = _make_event(kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert any(w.code == "W-KATH-02" for w in val.warnings)

    def test_no_warnings_on_perfect_visit(self):
        seg = _make_segment(
            segment_id="seg-20260221-004", seg_type="harikatha",
            timestamp_start=4801, timestamp_end=9000, katha_id=678)
        vod = _make_vod(segments=[seg])
        passage = _make_passage(timestamp_start=4801, timestamp_end=9000)
        katha = _make_katha(passages=[passage])
        event = _make_event(vods=[vod], kathas=[katha])
        visit = _make_visit(days=[_make_day(events=[event])])
        val = TratorValidator(visit)
        val.validate()
        assert val.warnings == []


# ═══════════════════════════════════════════════════════════════════
# GRUPO 13 — run_trator() integração end-to-end
# ═══════════════════════════════════════════════════════════════════

class TestRunTrator:

    def test_dry_run_success(self):
        result = run_trator(_make_visit(), dry_run=True)
        assert result["success"] is True

    def test_dry_run_returns_no_wp_id(self):
        result = run_trator(_make_visit(), dry_run=True)
        assert result.get("wp_id") is None

    def test_dry_run_index_has_all_sections(self):
        result = run_trator(_make_visit(), dry_run=True)
        idx = result["index"]
        for key in ("days", "events", "vods", "segments", "kathas", "passages"):
            assert key in idx

    def test_invalid_visit_returns_failure(self):
        bad = _make_visit()
        del bad["visit_ref"]
        result = run_trator(bad, dry_run=True)
        assert result["success"] is False

    def test_requires_wp_url_for_publish(self):
        result = run_trator(_make_visit(), dry_run=False, wp_url=None, wp_secret="secret")
        assert result["success"] is False

    def test_requires_wp_secret_for_publish(self):
        result = run_trator(_make_visit(), dry_run=False,
                            wp_url="https://wp.example.com", wp_secret=None)
        assert result["success"] is False

    def test_result_has_warnings_list(self):
        result = run_trator(_make_visit(), dry_run=True)
        assert "warnings" in result

    def test_result_has_errors_list_on_failure(self):
        bad = _make_visit()
        del bad["visit_ref"]
        result = run_trator(bad, dry_run=True)
        assert "errors" in result
        assert len(result["errors"]) > 0
