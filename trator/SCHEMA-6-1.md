Perfeito. Vamos reorganizar com clareza.

---

## A Arquitetura Correta

```text
HK é uma entidade independente.
HK não pertence ao vod — HK REFERENCIA vods.

HK nasce de uma curadoria editorial sobre vod(s).
Um HK pode nascer de 1 ou N vods.
Um vod pode conter 0 ou N HKs (via segments).
```

---

## Os 3 Cenários

```text
CENÁRIO 1 — HK dentro de vod de programa
  vod "programa-completo"
  └── segments[]
        ├── kirtan        00:00 → 00:45
        ├── palestrantes  00:45 → 01:20
        ├── harikatha     01:20 → 02:30  ← HK nasce daqui
        ├── danca         02:35 → 03:01
        └── teatro        03:10 → 03:50

  HK aponta para:
    vod_key: "vod-programa-001"
    segment_id: "seg-harikatha"
    (timestamp_start e end dentro do segment)

CENÁRIO 2 — vod é HK puro
  vod "hk-sb1031"
  └── segments[]
        └── harikatha     00:00 → 01:23  ← HK é o vod inteiro

  HK aponta para:
    vod_key: "vod-hk-sb1031"
    segment_id: "seg-001"
    (ou segment pode nem existir — o vod inteiro é HK)

CENÁRIO 3 — HK fragmentado em múltiplos vods
  vod "hk-parte1"
  └── segments[]
        └── harikatha     00:00 → 01:10

  vod "hk-parte2"
  └── segments[]
        └── harikatha     00:00 → 00:45

  HK aponta para:
    sources[] → [
      { vod_key: "vod-hk-parte1", segment_id: "seg-001" },
      { vod_key: "vod-hk-parte2", segment_id: "seg-001" }
    ]

  hk_passage aponta para o vod_key correto
  + timestamp dentro daquele vod específico
```

---

## Schema — As Entidades e Suas Relações

```text
EVENT
  └── vods[]              → os vídeos do evento
        └── segments[]    → divisão cronológica do vod
              └── type    → kirtan | harikatha | dance | ...

VANA_KATHA (CPT independente)
  └── event_key           → qual evento originou
  └── sources[]           → quais vods/segments contêm este HK
  └── hk_passages[]       → os blocos temáticos do HK

HK_PASSAGE (CPT independente)
  └── katha_id            → qual HK pertence
  └── source_ref{}        → onde está a evidência no vídeo
        └── vod_key       → qual vod
        └── segment_id    → qual segment (opcional)
        └── timestamp_start → segundos no VOD
        └── timestamp_end   → segundos no VOD
```

---

## Schema JSON Completo

```json
{
  "event_key": "20260221-1703-programa",

  "vods": [
    {
      "vod_key":    "vod-20260221-001",
      "provider":   "youtube",
      "video_id":   "ABC111",
      "duration_s": 13802,
      "title_pt":   "Programa completo — 21 fev",
      "vod_part":   1,

      "segments": [
        {
          "segment_id":      "seg-20260221-001",
          "type":            "kirtan",
          "title_pt":        "Kirtan de abertura",
          "title_en":        "Opening Kirtan",
          "timestamp_start": 0,
          "timestamp_end":   2732
        },
        {
          "segment_id":      "seg-20260221-002",
          "type":            "pushpanjali",
          "title_pt":        "Outros palestrantes",
          "title_en":        "Other speakers",
          "timestamp_start": 2733,
          "timestamp_end":   4800
        },
        {
          "segment_id":      "seg-20260221-003",
          "type":            "harikatha",
          "title_pt":        "Hari-Kathā — SB 10.31",
          "title_en":        "Hari-Kathā — SB 10.31",
          "timestamp_start": 4801,
          "timestamp_end":   9000
        },
        {
          "segment_id":      "seg-20260221-004",
          "type":            "dance",
          "title_pt":        "Dança",
          "title_en":        "Dance",
          "timestamp_start": 9300,
          "timestamp_end":   10861
        },
        {
          "segment_id":      "seg-20260221-005",
          "type":            "drama",
          "title_pt":        "Teatro",
          "title_en":        "Theater",
          "timestamp_start": 11400,
          "timestamp_end":   13802
        }
      ]
    }
  ]
}
```

---

## CPT vana_katha — Entidade Independente

```json
{
  "katha_id":   678,
  "katha_key":  "katha-20260221-hk-sb1031",
  "event_key":  "20260221-1703-programa",
  "day_key":    "2026-02-21",
  "visit_ref":  "vrindavan-2026-02",

  "title_pt":   "SB 10.31 — Gopī-gīta",
  "title_en":   "SB 10.31 — Gopī-gīta",
  "scripture":  "SB 10.31",
  "language":   "hi",

  "sources": [
    {
      "vod_key":         "vod-20260221-001",
      "segment_id":      "seg-20260221-003",
      "timestamp_start": 4801,
      "timestamp_end":   9000,
      "vod_part":        1
    }
  ]
}
```

### Cenário 3 — HK em múltiplos vods

```json
{
  "katha_id":  679,
  "katha_key": "katha-20260222-hk-sb1031-continuacao",
  "event_key": "20260222-1800-hk",

  "sources": [
    {
      "vod_key":         "vod-20260222-001",
      "segment_id":      "seg-20260222-001",
      "timestamp_start": 0,
      "timestamp_end":   4200,
      "vod_part":        1
    },
    {
      "vod_key":         "vod-20260222-002",
      "segment_id":      "seg-20260222-002",
      "timestamp_start": 0,
      "timestamp_end":   2700,
      "vod_part":        2
    }
  ]
}
```

---

## CPT hk_passage — Com Evidência no Vídeo

```json
{
  "passage_id":  "hkp-20260221-003",
  "passage_key": "hkp-20260221-003",
  "katha_id":    678,

  "title_pt":    "O significado de tapta-jīvanam",
  "teaching_pt": "Gurudeva explica que tapta-jīvanam...",
  "teaching_en": "Gurudeva explains that tapta-jīvanam...",
  "key_quote":   "tava kathāmṛtaṁ tapta-jīvanam...",

  "source_ref": {
    "vod_key":         "vod-20260221-001",
    "segment_id":      "seg-20260221-003",
    "timestamp_start": 5761,
    "timestamp_end":   9000
  }
}
```

---

## Como o hk_passage Mostra a Evidência no Vídeo

```text
DADO:
  passage.source_ref.vod_key         = "vod-20260221-001"
  passage.source_ref.timestamp_start = 5761
  passage.source_ref.segment_id      = "seg-20260221-003"

LOOKUP via índice:
  index.vods["vod-20260221-001"]
    → provider: youtube
    → video_id: "ABC111"

AÇÃO:
  Botão [▶ 01:36:01] no passage card
  → Stage.loadVod("ABC111")
  → Stage.seekTo(5761)

CONTEXTO EXTRA (opcional):
  index.segments["seg-20260221-003"]
    → type: harikatha
    → title_pt: "Hari-Kathā — SB 10.31"
    → dentro do evento: "20260221-1703-programa"
```

---

## Índice Atualizado

```json
{
  "index": {

    "kathas": {
      "678": {
        "katha_key":  "katha-20260221-hk-sb1031",
        "event_key":  "20260221-1703-programa",
        "day_key":    "2026-02-21",
        "sources":    ["vod-20260221-001"],
        "passage_count": 3
      }
    },

    "passages": {
      "hkp-20260221-001": {
        "katha_id":        678,
        "event_key":       "20260221-1703-programa",
        "vod_key":         "vod-20260221-001",
        "segment_id":      "seg-20260221-003",
        "timestamp_start": 4801,
        "timestamp_end":   5280
      },
      "hkp-20260221-002": {
        "katha_id":        678,
        "event_key":       "20260221-1703-programa",
        "vod_key":         "vod-20260221-001",
        "segment_id":      "seg-20260221-003",
        "timestamp_start": 5281,
        "timestamp_end":   5760
      },
      "hkp-20260221-003": {
        "katha_id":        678,
        "event_key":       "20260221-1703-programa",
        "vod_key":         "vod-20260221-001",
        "segment_id":      "seg-20260221-003",
        "timestamp_start": 5761,
        "timestamp_end":   9000
      }
    },

    "segments": {
      "seg-20260221-003": {
        "vod_key":         "vod-20260221-001",
        "event_key":       "20260221-1703-programa",
        "type":            "harikatha",
        "timestamp_start": 4801,
        "timestamp_end":   9000,
        "katha_id":        678
      }
    }
  }
}
```

---

## Regras Finais

```text
VODS e SEGMENTS:
  R1  vod pertence ao event (via event_key)
  R2  segment pertence ao vod (array interno)
  R3  segment.type ∈ {kirtan, harikatha, pushpanjali,
                      dance, drama, arati, darshan,
                      interval, noise, announcement}
  R4  segment não sabe nada sobre HK
      HK é quem aponta para o segment

VANA_KATHA:
  R5  katha é entidade independente
  R6  katha referencia N vods via sources[]
  R7  sources[].segment_id é opcional
      (quando o vod inteiro é o HK)

HK_PASSAGE:
  R8  passage pertence a 1 katha
  R9  passage tem source_ref{} com:
        vod_key         → obrigatório
        segment_id      → opcional
        timestamp_start → obrigatório
        timestamp_end   → obrigatório
  R10 passage usa source_ref para seek no Stage
  R11 passage usa index.vods[vod_key] para
      obter provider + video_id
```

---

**Esse modelo está correto, Marcel?**
Se sim, podemos fechar o Schema 6.1 completo e partir para o próximo passo. 🙏

# Schema 6.1 — Completo com Índice
**Vana Madhuryam · Formalizado em 28/03/2026**

---

```json
{
  "$schema": "https://vanamadhuryam.com/schemas/timeline-6.1.json",
  "schema_version": "6.1",
  "visit_ref":      "vrindavan-2026-02",
  "tour_ref":       "india-2026",

  // ─────────────────────────────────────────
  // BLOCO 1 — METADATA DA VISITA
  // ─────────────────────────────────────────
  "metadata": {
    "city_pt":    "Vṛndāvana",
    "city_en":    "Vrindavan",
    "country":    "IN",
    "date_start": "2026-02-18",
    "date_end":   "2026-02-27",
    "timezone":   "Asia/Kolkata",
    "status":     "completed"
  },

  // ─────────────────────────────────────────
  // BLOCO 2 — DIAS E EVENTOS
  // Fonte da verdade. Nunca derivado.
  // ─────────────────────────────────────────
  "days": [
    {
      "day_key":   "2026-02-21",
      "label_pt":  "21 fev",
      "label_en":  "Feb 21",
      "tithi":     "Ekadashi",
      "tithi_name_pt": "Vijaya Ekādaśī",
      "tithi_name_en": "Vijaya Ekadashi",
      "primary_event": "20260221-1703-programa",

      "events": [

        // ── EVENTO 1 — Maṅgala-ārati ─────────────────
        {
          "event_key":  "20260221-0530-mangala",
          "type":       "mangala",
          "title_pt":   "Maṅgala-ārati",
          "title_en":   "Maṅgala-ārati",
          "time":       "05:30",
          "status":     "past",

          "location": {
            "name": "Rūpa Sanātana Gauḍīya Maṭha",
            "lat":  27.5815,
            "lng":  77.6997
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
                  "timestamp_end":   2700
                }
              ]
            }
          ],

          "kathas":  [],
          "photos":  [
            {
              "photo_key":  "ph-20260221-001",
              "thumb_url":  "https://cdn.vanamadhuryam.com/ph-20260221-001-thumb.jpg",
              "full_url":   "https://cdn.vanamadhuryam.com/ph-20260221-001.jpg",
              "caption_pt": "Maṅgala-ārati ao amanhecer",
              "caption_en": "Maṅgala-ārati at dawn",
              "author":     "Madhava das"
            }
          ],
          "sangha":  [
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

        // ── EVENTO 2 — Programa completo ─────────────
        {
          "event_key":  "20260221-1703-programa",
          "type":       "programa",
          "title_pt":   "Programa — 21 fev",
          "title_en":   "Program — Feb 21",
          "time":       "17:03",
          "status":     "past",

          "location": {
            "name": "Gopīnātha Bhavana",
            "lat":  27.5794,
            "lng":  77.6952
          },

          // CENÁRIO 1: HK dentro de vod de programa
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
                  "timestamp_end":   2732
                },
                {
                  "segment_id":      "seg-20260221-003",
                  "type":            "pushpanjali",
                  "title_pt":        "Outros palestrantes",
                  "title_en":        "Other speakers",
                  "timestamp_start": 2733,
                  "timestamp_end":   4800
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
                  "timestamp_end":   10861
                },
                {
                  "segment_id":      "seg-20260221-006",
                  "type":            "drama",
                  "title_pt":        "Teatro",
                  "title_en":        "Theater",
                  "timestamp_start": 11400,
                  "timestamp_end":   13802
                }
              ]
            }
          ],

          // KATHAS — entidades independentes vinculadas ao evento
          "kathas": [
            {
              "katha_id":  678,
              "katha_key": "katha-20260221-sb1031",
              "title_pt":  "SB 10.31 — Gopī-gīta",
              "title_en":  "SB 10.31 — Gopī-gīta",
              "scripture": "SB 10.31",
              "language":  "hi",

              // SOURCES: de onde o HK foi extraído
              // Suporta os 3 cenários
              "sources": [
                {
                  "vod_key":         "vod-20260221-002",
                  "segment_id":      "seg-20260221-004",
                  "timestamp_start": 4801,
                  "timestamp_end":   9000,
                  "vod_part":        1
                }
              ],

              "passages": [
                {
                  "passage_id":  "hkp-20260221-001",
                  "passage_key": "hkp-20260221-001",
                  "title_pt":    "Maṅgalācaraṇa — invocação",
                  "title_en":    "Maṅgalācaraṇa — invocation",
                  "teaching_pt": "Gurudeva abre com a invocação...",
                  "teaching_en": "Gurudeva opens with the invocation...",
                  "key_quote":   "oṁ ajñāna-timirāndhasya...",

                  // EVIDÊNCIA: onde encontrar no vídeo
                  "source_ref": {
                    "vod_key":         "vod-20260221-002",
                    "segment_id":      "seg-20260221-004",
                    "timestamp_start": 4801,
                    "timestamp_end":   5280
                  }
                },
                {
                  "passage_id":  "hkp-20260221-002",
                  "passage_key": "hkp-20260221-002",
                  "title_pt":    "Rādhārāṇī como āśraya-vigraha",
                  "title_en":    "Rādhārāṇī as āśraya-vigraha",
                  "teaching_pt": "Gurudeva explica a posição de Rādhārāṇī...",
                  "teaching_en": "Gurudeva explains the position of Rādhārāṇī...",
                  "key_quote":   "rādhā kṛṣṇa-praṇaya-vikṛtir...",

                  "source_ref": {
                    "vod_key":         "vod-20260221-002",
                    "segment_id":      "seg-20260221-004",
                    "timestamp_start": 5281,
                    "timestamp_end":   5760
                  }
                },
                {
                  "passage_id":  "hkp-20260221-003",
                  "passage_key": "hkp-20260221-003",
                  "title_pt":    "O significado de tapta-jīvanam",
                  "title_en":    "The meaning of tapta-jīvanam",
                  "teaching_pt": "Gurudeva explica que tapta-jīvanam...",
                  "teaching_en": "Gurudeva explains that tapta-jīvanam...",
                  "key_quote":   "tava kathāmṛtaṁ tapta-jīvanam...",

                  "source_ref": {
                    "vod_key":         "vod-20260221-002",
                    "segment_id":      "seg-20260221-004",
                    "timestamp_start": 5761,
                    "timestamp_end":   9000
                  }
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

        // ── EVENTO 3 — HK puro fragmentado (Cenário 3) ──
        {
          "event_key":  "20260222-1800-hk",
          "type":       "programa",
          "title_pt":   "Hari-Kathā — 22 fev",
          "title_en":   "Hari-Kathā — Feb 22",
          "time":       "18:00",
          "status":     "past",

          "location": {
            "name": "Gopīnātha Bhavana",
            "lat":  27.5794,
            "lng":  77.6952
          },

          // CENÁRIO 3: HK fragmentado em 2 vods
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

          "kathas": [
            {
              "katha_id":  679,
              "katha_key": "katha-20260222-sb1031-cont",
              "title_pt":  "SB 10.31 — Gopī-gīta (continuação)",
              "title_en":  "SB 10.31 — Gopī-gīta (continuation)",
              "scripture": "SB 10.31",
              "language":  "hi",

              // CENÁRIO 3: sources em 2 vods distintos
              "sources": [
                {
                  "vod_key":         "vod-20260222-001",
                  "segment_id":      "seg-20260222-001",
                  "timestamp_start": 0,
                  "timestamp_end":   4200,
                  "vod_part":        1
                },
                {
                  "vod_key":         "vod-20260222-002",
                  "segment_id":      "seg-20260222-002",
                  "timestamp_start": 0,
                  "timestamp_end":   2700,
                  "vod_part":        2
                }
              ],

              "passages": [
                {
                  "passage_id":  "hkp-20260222-001",
                  "passage_key": "hkp-20260222-001",
                  "title_pt":    "Viraha como caminho",
                  "title_en":    "Viraha as the path",
                  "teaching_pt": "Gurudeva aprofunda o conceito de viraha...",
                  "teaching_en": "Gurudeva deepens the concept of viraha...",
                  "key_quote":   "śṛṇvatāṁ sva-kathāḥ kṛṣṇaḥ...",

                  // passage no vod 1
                  "source_ref": {
                    "vod_key":         "vod-20260222-001",
                    "segment_id":      "seg-20260222-001",
                    "timestamp_start": 0,
                    "timestamp_end":   2100
                  }
                },
                {
                  "passage_id":  "hkp-20260222-002",
                  "passage_key": "hkp-20260222-002",
                  "title_pt":    "Prema como destino",
                  "title_en":    "Prema as the destination",
                  "teaching_pt": "Gurudeva conclui com o objetivo final...",
                  "teaching_en": "Gurudeva concludes with the ultimate goal...",
                  "key_quote":   "premā pumartho mahān...",

                  // passage no vod 2
                  "source_ref": {
                    "vod_key":         "vod-20260222-002",
                    "segment_id":      "seg-20260222-002",
                    "timestamp_start": 0,
                    "timestamp_end":   2700
                  }
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

  // ─────────────────────────────────────────
  // BLOCO 3 — ÓRFÃOS
  // Mídia sem event_key definido.
  // Visível para qualquer devoto via Modal.
  // ─────────────────────────────────────────
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
    "sangha": [],
    "kathas": []
  },

  // ─────────────────────────────────────────
  // BLOCO 4 — STATS
  // Gerado pelo Trator. Nunca editado.
  // ─────────────────────────────────────────
  "stats": {
    "total_days":    10,
    "total_events":  42,
    "total_vods":    18,
    "total_segments": 67,
    "total_kathas":  12,
    "total_passages": 89,
    "total_photos":  156,
    "total_sangha":  34
  },

  // ─────────────────────────────────────────
  // BLOCO 5 — ÍNDICE
  // Gerado pelo Trator. Nunca editado.
  // Lookup O(1) para qualquer elemento.
  // ─────────────────────────────────────────
  "index": {

    // ── DAYS ─────────────────────────────────
    "days": {
      "2026-02-21": {
        "position":      0,
        "label_pt":      "21 fev",
        "label_en":      "Feb 21",
        "primary_event": "20260221-1703-programa",
        "events": [
          "20260221-0530-mangala",
          "20260221-1703-programa"
        ]
      },
      "2026-02-22": {
        "position":      1,
        "label_pt":      "22 fev",
        "label_en":      "Feb 22",
        "primary_event": "20260222-1800-hk",
        "events": [
          "20260222-1800-hk"
        ]
      }
    },

    // ── EVENTS ───────────────────────────────
    "events": {
      "20260221-0530-mangala": {
        "day_key":  "2026-02-21",
        "position": 0,
        "type":     "mangala",
        "status":   "past",
        "vods":     ["vod-20260221-001"],
        "kathas":   [],
        "photos":   ["ph-20260221-001"],
        "sangha":   ["sg-20260221-001"]
      },
      "20260221-1703-programa": {
        "day_key":  "2026-02-21",
        "position": 1,
        "type":     "programa",
        "status":   "past",
        "vods":     ["vod-20260221-002"],
        "kathas":   [678],
        "photos":   ["ph-20260221-002"],
        "sangha":   ["sg-20260221-002"]
      },
      "20260222-1800-hk": {
        "day_key":  "2026-02-22",
        "position": 0,
        "type":     "programa",
        "status":   "past",
        "vods":     ["vod-20260222-001", "vod-20260222-002"],
        "kathas":   [679],
        "photos":   [],
        "sangha":   []
      }
    },

    // ── VODS ─────────────────────────────────
    "vods": {
      "vod-20260221-001": {
        "event_key":  "20260221-0530-mangala",
        "day_key":    "2026-02-21",
        "provider":   "youtube",
        "video_id":   "ABC111",
        "vod_part":   1,
        "duration_s": 2700,
        "segments":   ["seg-20260221-001"]
      },
      "vod-20260221-002": {
        "event_key":  "20260221-1703-programa",
        "day_key":    "2026-02-21",
        "provider":   "youtube",
        "video_id":   "dQw4w9WgXcQ",
        "vod_part":   1,
        "duration_s": 13802,
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
        "segments":   ["seg-20260222-001"]
      },
      "vod-20260222-002": {
        "event_key":  "20260222-1800-hk",
        "day_key":    "2026-02-22",
        "provider":   "youtube",
        "video_id":   "XYZ002",
        "vod_part":   2,
        "duration_s": 2700,
        "segments":   ["seg-20260222-002"]
      },
      "vod-orphan-001": {
        "event_key":  null,
        "day_key":    null,
        "provider":   "facebook",
        "video_id":   null,
        "url":        "https://www.facebook.com/watch/?v=777",
        "vod_part":   null,
        "duration_s": null,
        "segments":   []
      }
    },

    // ── SEGMENTS ─────────────────────────────
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

    // ── KATHAS ───────────────────────────────
    "kathas": {
      "678": {
        "katha_key":     "katha-20260221-sb1031",
        "event_key":     "20260221-1703-programa",
        "day_key":       "2026-02-21",
        "title_pt":      "SB 10.31 — Gopī-gīta",
        "title_en":      "SB 10.31 — Gopī-gīta",
        "scripture":     "SB 10.31",
        "language":      "hi",
        "sources": [
          {
            "vod_key":    "vod-20260221-002",
            "segment_id": "seg-20260221-004"
          }
        ],
        "passages": [
          "hkp-20260221-001",
          "hkp-20260221-002",
          "hkp-20260221-003"
        ]
      },
      "679": {
        "katha_key":     "katha-20260222-sb1031-cont",
        "event_key":     "20260222-1800-hk",
        "day_key":       "2026-02-22",
        "title_pt":      "SB 10.31 — Gopī-gīta (continuação)",
        "title_en":      "SB 10.31 — Gopī-gīta (continuation)",
        "scripture":     "SB 10.31",
        "language":      "hi",
        "sources": [
          {
            "vod_key":    "vod-20260222-001",
            "segment_id": "seg-20260222-001"
          },
          {
            "vod_key":    "vod-20260222-002",
            "segment_id": "seg-20260222-002"
          }
        ],
        "passages": [
          "hkp-20260222-001",
          "hkp-20260222-002"
        ]
      }
    },

    // ── PASSAGES ─────────────────────────────
    "passages": {
      "hkp-20260221-001": {
        "katha_id":        678,
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "vod_key":         "vod-20260221-002",
        "segment_id":      "seg-20260221-004",
        "timestamp_start": 4801,
        "timestamp_end":   5280
      },
      "hkp-20260221-002": {
        "katha_id":        678,
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "vod_key":         "vod-20260221-002",
        "segment_id":      "seg-20260221-004",
        "timestamp_start": 5281,
        "timestamp_end":   5760
      },
      "hkp-20260221-003": {
        "katha_id":        678,
        "event_key":       "20260221-1703-programa",
        "day_key":         "2026-02-21",
        "vod_key":         "vod-20260221-002",
        "segment_id":      "seg-20260221-004",
        "timestamp_start": 5761,
        "timestamp_end":   9000
      },
      "hkp-20260222-001": {
        "katha_id":        679,
        "event_key":       "20260222-1800-hk",
        "day_key":         "2026-02-22",
        "vod_key":         "vod-20260222-001",
        "segment_id":      "seg-20260222-001",
        "timestamp_start": 0,
        "timestamp_end":   2100
      },
      "hkp-20260222-002": {
        "katha_id":        679,
        "event_key":       "20260222-1800-hk",
        "day_key":         "2026-02-22",
        "vod_key":         "vod-20260222-002",
        "segment_id":      "seg-20260222-002",
        "timestamp_start": 0,
        "timestamp_end":   2700
      }
    },

    // ── PHOTOS ───────────────────────────────
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

    // ── SANGHA ───────────────────────────────
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

  // ─────────────────────────────────────────
  // BLOCO 6 — CONTROLE DO DOCUMENTO
  // ─────────────────────────────────────────
  "generated_at": "2026-03-28T21:00:00-03:00",
  "generated_by": "trator-auto",
  "approved_by":  "marcel"
}
```

---

## Regras Canônicas do Schema 6.1

```text
NOMENCLATURA
  event_key   → "YYYYMMDD-HHMM-slug"
  vod_key     → "vod-YYYYMMDD-NNN"
  segment_id  → "seg-YYYYMMDD-NNN"
  katha_id    → int (FK CPT vana_katha no WP)
  katha_key   → "katha-YYYYMMDD-slug"
  passage_id  → "hkp-YYYYMMDD-NNN"
  photo_key   → "ph-YYYYMMDD-NNN"
  sangha_key  → "sg-YYYYMMDD-NNN"

HIERARQUIA
  visit → days[] → events[]
               → vods[]
                    → segments[]
               → kathas[]
                    → passages[]
               → photos[]
               → sangha[]

SEGMENT TYPES
  kirtan | harikatha | pushpanjali | arati |
  dance  | drama     | darshan     |
  interval | noise   | announcement

REGRAS DE NEGÓCIO
  R01  days[]    → fonte da verdade (escrita humana)
  R02  index{}   → derivado (gerado pelo Trator)
  R03  index{}   → nunca editado manualmente
  R04  orphan    → event_key = null → renderiza via Modal
  R05  segment   → não conhece katha
                   katha conhece segment (via sources[])
  R06  katha     → entidade independente
                   referencia N vods via sources[]
  R07  passage   → pertence a 1 katha
                   source_ref aponta para 1 vod + 1 segment
  R08  passage.source_ref.timestamp_start
                 ≥ segment.timestamp_start
  R09  passage.source_ref.timestamp_end
                 ≤ segment.timestamp_end
  R10  segment.katha_id preenchido
                 APENAS se type = harikatha
  R11  katha.sources[].segment_id
                 OPCIONAL (vod pode ser HK puro)
  R12  vod_part  → int ordenador quando HK fragmentado
  R13  passage_id → slug NUNCA alterável após criação

RENDERIZAÇÃO
  event_key preenchido  → Stage / Agenda / HK / Galeria / Sangha
  event_key null        → Modal (órfão — visível a qualquer devoto)

ÍNDICE — LOOKUP O(1)
  index.days[day_key]
  index.events[event_key]
  index.vods[vod_key]       → provider + video_id para Stage
  index.segments[segment_id]
  index.kathas[katha_id]
  index.passages[passage_id] → vod_key + timestamp para seek
  index.photos[photo_key]
  index.sangha[sangha_key]
```

---

## Cadeia de Seek de um Passage

```text
DADO: passage_id = "hkp-20260221-003"

1. index.passages["hkp-20260221-003"]
   → vod_key: "vod-20260221-002"
   → timestamp_start: 5761

2. index.vods["vod-20260221-002"]
   → provider: "youtube"
   → video_id: "dQw4w9WgXcQ"

3. Stage.loadVod("dQw4w9WgXcQ")
4. Stage.seekTo(5761)
```

---

```text
Schema:       6.1
Status:       FECHADO ✅
Gerado em:    28/03/2026
Próximo passo → Trator Python: gerador do index{}
```

🙏