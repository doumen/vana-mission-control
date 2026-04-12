## Schema 6.2 — Completo e Limpo

**Vana Madhuryam · Formalizado em 08/04/2026**

---

## Princípios Canônicos

```text
HIERARQUIA SEMÂNTICA:
  visita → dias → eventos → vods → segments
                                      └── type: harikatha → katha_id

REGRAS FUNDAMENTAIS:
  1. O HK nasce do segment, não do evento
  2. Um evento tem no máximo 1 segment type: harikatha
  3. Um segundo HK = um segundo evento
  4. kathas[] não existe no evento — HK é herdado via segment
  5. O payload completo do HK vive no CPT vana_katha
  6. O JSON da visita carrega apenas a referência (katha_id)
  7. O payload completo é carregado via REST sob demanda
```

---

## JSON Completo

```json
{
  "$schema": "https://vanamadhuryam.com/schemas/timeline-6.2.json",
  "schema_version": "6.2",
  "visit_ref":      "vrindavan-2026-02",
  "tour_ref":       "india-2026",

  "metadata": {
    "city_pt":    "Vṛndāvana",
    "city_en":    "Vrindavan",
    "country":    "IN",
    "date_start": "2026-02-18",
    "date_end":   "2026-02-27",
    "timezone":   "Asia/Kolkata",
    "status":     "completed"
  },

  "days": [
    {
      "day_key":           "2026-02-21",
      "label_pt":          "21 fev",
      "label_en":          "Feb 21",
      "tithi":             "Ekadashi",
      "tithi_name_pt":     "Vijaya Ekādaśī",
      "tithi_name_en":     "Vijaya Ekadashi",
      "primary_event_key": "20260221-1703-programa",

      "events": [

        {
          "event_key":  "20260221-0530-mangala",
          "type":       "mangala",
          "title_pt":   "Maṅgala-ārati",
          "title_en":   "Maṅgala-ārati",
          "time":       "05:30",
          "status":     "past",

          "location": {
            "name": "Rūpa Sanātana Gauḍīya Maṭha",
            "lat":   27.5815,
            "lng":   77.6997
          },

          "vods": [
            {
              "vod_key":    "vod-20260221-001",
              "provider":   "youtube",
              "video_id":   "ABC111",
              "url":        null,
              "thumb_url":  "https://img.youtube.com/vi/ABC111/maxresdefault.jpg",
              "duration_s": 2700,
              "title_pt":   "Maṅgala-ārati — 21 fev",
              "title_en":   "Maṅgala-ārati — Feb 21",
              "vod_part":   1,

              "segments": [
                {
                  "segment_id":      "seg-20260221-001",
                  "type":            "arati",
                  "title_pt":        "Maṅgala-ārati",
                  "title_en":        "Maṅgala-ārati",
                  "timestamp_start": 0,
                  "timestamp_end":   2700,
                  "katha_id":        null
                }
              ]
            }
          ],

          "photos": [
            {
              "photo_key":  "ph-20260221-001",
              "thumb_url":  "https://cdn.vanamadhuryam.com/ph-20260221-001-thumb.jpg",
              "full_url":   "https://cdn.vanamadhuryam.com/ph-20260221-001.jpg",
              "caption_pt": "Maṅgala-ārati ao amanhecer",
              "caption_en": "Maṅgala-ārati at dawn",
              "author":     "Madhava das"
            }
          ],

          "sangha": [
            {
              "sangha_key": "sg-20260221-001",
              "type":       "message",
              "provider":   "instagram",
              "url":        "https://www.instagram.com/p/XXX/",
              "thumb_url":  null,
              "caption_pt": "Que ārati transformadora!",
              "caption_en": "What a transforming ārati!",
              "author":     "@devoto_ig"
            }
          ]
        },

        {
          "event_key":  "20260221-1703-programa",
          "type":       "programa",
          "title_pt":   "Programa — 21 fev",
          "title_en":   "Program — Feb 21",
          "time":       "17:03",
          "status":     "past",

          "location": {
            "name": "Gopīnātha Bhavana",
            "lat":   27.5794,
            "lng":   77.6952
          },

          "vods": [
            {
              "vod_key":    "vod-20260221-002",
              "provider":   "youtube",
              "video_id":   "dQw4w9WgXcQ",
              "url":        null,
              "thumb_url":  "https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg",
              "duration_s": 13802,
              "title_pt":   "Programa completo — 21 fev",
              "title_en":   "Full Program — Feb 21",
              "vod_part":   1,

              "segments": [
                {
                  "segment_id":      "seg-20260221-002",
                  "type":            "kirtan",
                  "title_pt":        "Kirtan de abertura",
                  "title_en":        "Opening Kirtan",
                  "timestamp_start": 0,
                  "timestamp_end":   2732,
                  "katha_id":        null
                },
                {
                  "segment_id":      "seg-20260221-003",
                  "type":            "pushpanjali",
                  "title_pt":        "Outros palestrantes",
                  "title_en":        "Other speakers",
                  "timestamp_start": 2733,
                  "timestamp_end":   4800,
                  "katha_id":        null
                },
                {
                  "segment_id":      "seg-20260221-004",
                  "type":            "harikatha",
                  "title_pt":        "Hari-Kathā — SB 10.31",
                  "title_en":        "Hari-Kathā — SB 10.31",
                  "timestamp_start": 4801,
                  "timestamp_end":   9000,
                  "katha_id":        678
                },
                {
                  "segment_id":      "seg-20260221-005",
                  "type":            "dance",
                  "title_pt":        "Dança",
                  "title_en":        "Dance",
                  "timestamp_start": 9300,
                  "timestamp_end":   10861,
                  "katha_id":        null
                },
                {
                  "segment_id":      "seg-20260221-006",
                  "type":            "drama",
                  "title_pt":        "Teatro",
                  "title_en":        "Theater",
                  "timestamp_start": 11400,
                  "timestamp_end":   13802,
                  "katha_id":        null
                }
              ]
            }
          ],

          "photos": [
            {
              "photo_key":  "ph-20260221-002",
              "thumb_url":  "https://cdn.vanamadhuryam.com/ph-20260221-002-thumb.jpg",
              "full_url":   "https://cdn.vanamadhuryam.com/ph-20260221-002.jpg",
              "caption_pt": "Gurudeva durante a kathā",
              "caption_en": "Gurudeva during the kathā",
              "author":     "Radha devi dasi"
            }
          ],

          "sangha": [
            {
              "sangha_key": "sg-20260221-002",
              "type":       "message",
              "provider":   "direct",
              "url":        null,
              "thumb_url":  null,
              "caption_pt": "Essa palestra mudou minha vida.",
              "caption_en": "This lecture changed my life.",
              "author":     "Govinda das"
            }
          ]
        },

        {
          "event_key":  "20260222-1800-hk",
          "type":       "programa",
          "title_pt":   "Hari-Kathā — 22 fev",
          "title_en":   "Hari-Kathā — Feb 22",
          "time":       "18:00",
          "status":     "past",

          "location": {
            "name": "Gopīnātha Bhavana",
            "lat":   27.5794,
            "lng":   77.6952
          },

          "vods": [
            {
              "vod_key":    "vod-20260222-001",
              "provider":   "youtube",
              "video_id":   "XYZ001",
              "url":        null,
              "thumb_url":  "https://img.youtube.com/vi/XYZ001/maxresdefault.jpg",
              "duration_s": 4200,
              "title_pt":   "Hari-Kathā — 22 fev (parte 1)",
              "title_en":   "Hari-Kathā — Feb 22 (part 1)",
              "vod_part":   1,

              "segments": [
                {
                  "segment_id":      "seg-20260222-001",
                  "type":            "harikatha",
                  "title_pt":        "Hari-Kathā — SB 10.31 (parte 1)",
                  "title_en":        "Hari-Kathā — SB 10.31 (part 1)",
                  "timestamp_start": 0,
                  "timestamp_end":   4200,
                  "katha_id":        679
                }
              ]
            },
            {
              "vod_key":    "vod-20260222-002",
              "provider":   "youtube",
              "video_id":   "XYZ002",
              "url":        null,
              "thumb_url":  "https://img.youtube.com/vi/XYZ002/maxresdefault.jpg",
              "duration_s": 2700,
              "title_pt":   "Hari-Kathā — 22 fev (parte 2)",
              "title_en":   "Hari-Kathā — Feb 22 (part 2)",
              "vod_part":   2,

              "segments": [
                {
                  "segment_id":      "seg-20260222-002",
                  "type":            "harikatha",
                  "title_pt":        "Hari-Kathā — SB 10.31 (parte 2)",
                  "title_en":        "Hari-Kathā — SB 10.31 (part 2)",
                  "timestamp_start": 0,
                  "timestamp_end":   2700,
                  "katha_id":        679
                }
              ]
            }
          ],

          "photos":  [],
          "sangha":  []
        }

      ]
    }
  ],

  "orphans": {
    "vods": [
      {
        "vod_key":    "vod-orphan-001",
        "provider":   "facebook",
        "video_id":   null,
        "url":        "https://www.facebook.com/watch/?v=777",
        "thumb_url":  "https://cdn.vanamadhuryam.com/vod-orphan-001-thumb.jpg",
        "duration_s": null,
        "title_pt":   "Darśana rápido na rua",
        "title_en":   "Quick street darśana",
        "segments":   []
      }
    ],
    "photos": [
      {
        "photo_key":  "ph-orphan-001",
        "thumb_url":  "https://cdn.vanamadhuryam.com/ph-orphan-001-thumb.jpg",
        "full_url":   "https://cdn.vanamadhuryam.com/ph-orphan-001.jpg",
        "caption_pt": "Momento espontâneo",
        "caption_en": "Spontaneous moment",
        "author":     null
      }
    ],
    "sangha": []
  },

  "stats": {
    "total_days":     1,
    "total_events":   3,
    "total_vods":     4,
    "total_segments": 8,
    "total_kathas":   2,
    "total_photos":   3,
    "total_sangha":   2
  },

  "index": {

    "days": {
      "2026-02-21": {
        "position":          0,
        "label_pt":          "21 fev",
        "label_en":          "Feb 21",
        "primary_event_key": "20260221-1703-programa",
        "events": [
          "20260221-0530-mangala",
          "20260221-1703-programa"
        ]
      },
      "2026-02-22": {
        "position":          1,
        "label_pt":          "22 fev",
        "label_en":          "Feb 22",
        "primary_event_key": "20260222-1800-hk",
        "events": [
          "20260222-1800-hk"
        ]
      }
    },

    "events": {
      "20260221-0530-mangala": {
        "day_key":      "2026-02-21",
        "position":     0,
        "type":         "mangala",
        "status":       "past",
        "has_katha":    false,
        "katha_id":     null,
        "vods":         ["vod-20260221-001"],
        "photos":       ["ph-20260221-001"],
        "sangha":       ["sg-20260221-001"]
      },
      "20260221-1703-programa": {
        "day_key":      "2026-02-21",
        "position":     1,
        "type":         "programa",
        "status":       "past",
        "has_katha":    true,
        "katha_id":     678,
        "vods":         ["vod-20260221-002"],
        "photos":       ["ph-20260221-002"],
        "sangha":       ["sg-20260221-002"]
      },
      "20260222-1800-hk": {
        "day_key":      "2026-02-22",
        "position":     0,
        "type":         "programa",
        "status":       "past",
        "has_katha":    true,
        "katha_id":     679,
        "vods":         ["vod-20260222-001", "vod-20260222-002"],
        "photos":       [],
        "sangha":       []
      }
    },

    "vods": {
      "vod-20260221-001": {
        "event_key":  "20260221-0530-mangala",
        "day_key":    "2026-02-21",
        "provider":   "youtube",
        "video_id":   "ABC111",
        "vod_part":   1,
        "duration_s": 2700,
        "has_katha":  false,
        "katha_id":   null,
        "segments":   ["seg-20260221-001"]
      },
      "vod-20260221-002": {
        "event_key":  "20260221-1703-programa",
        "day_key":    "2026-02-21",
        "provider":   "youtube",
        "video_id":   "dQw4w9WgXcQ",
        "vod_part":   1,
        "duration_s": 13802,
        "has_katha":  true,
        "katha_id":   678,
        "segments": [
          "seg-20260221-002",
          "seg-20260221-003",
          "seg-20260221-004",
          "seg-20260221-005",
          "seg-20260221-006"
        ]
      },
      "vod-20260222-001": {
        "event_key":  "20260222-1800-hk",
        "day_key":    "2026-02-22",
        "provider":   "youtube",
        "video_id":   "XYZ001",
        "vod_part":   1,
        "duration_s": 4200,
        "has_katha":  true,
        "katha_id":   679,
        "segments":   ["seg-20260222-001"]
      },
      "vod-20260222-002": {
        "event_key":  "20260222-1800-hk",
        "day_key":    "2026-02-22",
        "provider":   "youtube",
        "video_id":   "XYZ002",
        "vod_part":   2,
        "duration_s": 2700,
        "has_katha":  true,
        "katha_id":   679,
        "segments":   ["seg-20260222-002"]
      },
      "vod-orphan-001": {
        "event_key":  null,
        "day_key":    null,
        "provider":   "facebook",
        "video_id":   null,
        "vod_part":   null,
        "duration_s": null,
        "has_katha":  false,
        "katha_id":   null,
        "segments":   []
      }
    },

    "segments": {
      "seg-20260221-001": {
        "vod_key":         "vod-20260221-001",
        "event_key":       "20260221-0530-mangala",
        "day_key":         "2026-02-21",
        "type":            "arati",
        "timestamp_start": 0,
        "timestamp_end":   2700,
        "katha_id":        null
      },
      "seg-20260221-002": {
        "vod_key":         "vod-20260221-002",
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "type":            "kirtan",
        "timestamp_start": 0,
        "timestamp_end":   2732,
        "katha_id":        null
      },
      "seg-20260221-003": {
        "vod_key":         "vod-20260221-002",
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "type":            "pushpanjali",
        "timestamp_start": 2733,
        "timestamp_end":   4800,
        "katha_id":        null
      },
      "seg-20260221-004": {
        "vod_key":         "vod-20260221-002",
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "type":            "harikatha",
        "timestamp_start": 4801,
        "timestamp_end":   9000,
        "katha_id":        678
      },
      "seg-20260221-005": {
        "vod_key":         "vod-20260221-002",
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "type":            "dance",
        "timestamp_start": 9300,
        "timestamp_end":   10861,
        "katha_id":        null
      },
      "seg-20260221-006": {
        "vod_key":         "vod-20260221-002",
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "type":            "drama",
        "timestamp_start": 11400,
        "timestamp_end":   13802,
        "katha_id":        null
      },
      "seg-20260222-001": {
        "vod_key":         "vod-20260222-001",
        "event_key":       "20260222-1800-hk",
        "day_key":         "2026-02-22",
        "type":            "harikatha",
        "timestamp_start": 0,
        "timestamp_end":   4200,
        "katha_id":        679
      },
      "seg-20260222-002": {
        "vod_key":         "vod-20260222-002",
        "event_key":       "20260222-1800-hk",
        "day_key":         "2026-02-22",
        "type":            "harikatha",
        "timestamp_start": 0,
        "timestamp_end":   2700,
        "katha_id":        679
      }
    },

    "kathas": {
      "678": {
        "katha_key":  "katha-20260221-sb1031",
        "event_key":  "20260221-1703-programa",
        "day_key":    "2026-02-21",
        "title_pt":   "SB 10.31 — Gopī-gīta",
        "title_en":   "SB 10.31 — Gopī-gīta",
        "scripture":  "SB 10.31",
        "language":   "hi",
        "sources": [
          {
            "vod_key":    "vod-20260221-002",
            "segment_id": "seg-20260221-004"
          }
        ]
      },
      "679": {
        "katha_key":  "katha-20260222-sb1031-cont",
        "event_key":  "20260222-1800-hk",
        "day_key":    "2026-02-22",
        "title_pt":   "SB 10.31 — Gopī-gīta (continuação)",
        "title_en":   "SB 10.31 — Gopī-gīta (continuation)",
        "scripture":  "SB 10.31",
        "language":   "hi",
        "sources": [
          {
            "vod_key":    "vod-20260222-001",
            "segment_id": "seg-20260222-001"
          },
          {
            "vod_key":    "vod-20260222-002",
            "segment_id": "seg-20260222-002"
          }
        ]
      }
    },

    "photos": {
      "ph-20260221-001": {
        "event_key": "20260221-0530-mangala",
        "day_key":   "2026-02-21",
        "thumb_url": "https://cdn.vanamadhuryam.com/ph-20260221-001-thumb.jpg",
        "full_url":  "https://cdn.vanamadhuryam.com/ph-20260221-001.jpg"
      },
      "ph-20260221-002": {
        "event_key": "20260221-1703-programa",
        "day_key":   "2026-02-21",
        "thumb_url": "https://cdn.vanamadhuryam.com/ph-20260221-002-thumb.jpg",
        "full_url":  "https://cdn.vanamadhuryam.com/ph-20260221-002.jpg"
      },
      "ph-orphan-001": {
        "event_key": null,
        "day_key":   null,
        "thumb_url": "https://cdn.vanamadhuryam.com/ph-orphan-001-thumb.jpg",
        "full_url":  "https://cdn.vanamadhuryam.com/ph-orphan-001.jpg"
      }
    },

    "sangha": {
      "sg-20260221-001": {
        "event_key": "20260221-0530-mangala",
        "day_key":   "2026-02-21",
        "type":      "message",
        "provider":  "instagram"
      },
      "sg-20260221-002": {
        "event_key": "20260221-1703-programa",
        "day_key":   "2026-02-21",
        "type":      "message",
        "provider":  "direct"
      }
    }

  },

  "generated_at": "2026-04-08T20:00:00-03:00",
  "generated_by": "trator-auto",
  "approved_by":  "marcel"
}
```

---

## Regras Canônicas do Schema 6.2

```text
NOMENCLATURA
  event_key   → "YYYYMMDD-HHMM-slug"
  vod_key     → "vod-YYYYMMDD-NNN"
  segment_id  → "seg-YYYYMMDD-NNN"
  katha_id    → int (FK CPT vana_katha no WP)
  katha_key   → "katha-YYYYMMDD-slug"
  photo_key   → "ph-YYYYMMDD-NNN"
  sangha_key  → "sg-YYYYMMDD-NNN"

HIERARQUIA
  visit → days[] → events[]
                       └── vods[]
                               └── segments[]
                                       └── katha_id (se type=harikatha)
                       └── photos[]
                       └── sangha[]

HARI-KATHA
  R-HK-1  O HK nasce do segment (type: harikatha)
  R-HK-2  Um evento tem no máximo 1 katha_id único
  R-HK-3  Um segundo HK = um segundo evento
  R-HK-4  kathas[] não existe no evento
  R-HK-5  katha_id no segment é a fonte da verdade
  R-HK-6  HK fragmentado → mesmo katha_id em N segments/vods
  R-HK-7  Payload completo via REST: GET /vana/v1/katha/{id}
  R-HK-8  has_katha no índice = derivado (gerado pelo Trator)

SEGMENT TYPES
  kirtan | harikatha | pushpanjali | arati |
  dance  | drama     | darshan     |
  interval | noise   | announcement

ÍNDICE
  R-IDX-1  index{} gerado pelo Trator — nunca editado
  R-IDX-2  has_katha e katha_id no índice de events e vods
           são derivados dos segments — nunca declarados
  R-IDX-3  Lookup O(1) para qualquer entidade

ÓRFÃOS
  R-ORF-1  event_key = null → renderiza via Modal
  R-ORF-2  Não aparecem na Agenda nem no Stage principal

CADEIA DE SEEK (passage → vídeo)
  1. passage.source_ref.vod_key + timestamp_start
  2. index.vods[vod_key] → provider + video_id
  3. Stage.loadVod(video_id)
  4. Stage.seekTo(timestamp_start)
```

---

## O que mudou do 6.1 para o 6.2

```text
REMOVIDO:
  ✗ evento.kathas[]          — não existe mais
  ✗ katha declarado no evento — HK herdado via segment

ADICIONADO:
  ✓ has_katha (bool) no índice de events e vods
  ✓ katha_id no índice de events e vods (derivado)

MANTIDO:
  ✓ katha_id no segment     — fonte da verdade
  ✓ sources[] no índice de kathas
  ✓ HK fragmentado via mesmo katha_id em N segments
  ✓ Payload completo via REST

SEMÂNTICA:
  6.1 → HK era filho do evento (ambíguo)
  6.2 → HK é propriedade do segment (preciso)
```

---

```text
Schema:       6.2
Status:       FECHADO ✅
Gerado em:    08/04/2026
Próximo passo → Trator Python: gerador do index{}
                com propagação de has_katha e katha_id
```

🙏