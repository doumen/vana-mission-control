# FASE 4 — Decision Document
## Scope Alignment Before Implementation

**Status:** ⏳ Needs Decision  
**Date:** 2025-03-21  
**Context:** Fase 3 complete, now planning Fase 4's scope

---

## Current Implementation State

| Phase | Status | Deliverable |
|-------|--------|-------------|
| Fase 1 | ✅ Complete | Schema 5.1 normalization (inc/vana-stage.php) |
| Fase 2 | ✅ Complete | VanaEventController + event-selector navigation |
| Fase 3 | ✅ Complete | render_event_stage() in REST endpoint |
| **Fase 4** | ⏳ **PENDING** | TBD — see options below |
| Fase 5 | ⬜ Planned | Deploy + smoke tests |

---

## Fase 4 Scope Options

### 🔵 OPTION A — REST Semantic Endpoints

**Scope:** Add new RESTful routes for cleaner URLs

```php
// New routes
GET /wp-json/vana/v1/stage/{event_key}
GET /wp-json/vana/v1/section/{section}/{event_key}

// Example
GET /wp-json/vana/v1/stage/event-satsang-20250320
→ Returns rendered stage for that event_key
```

**Pros:**
- ✅ Clean semantics (event_key in URL, not hidden in params)
- ✅ Cache-friendly for CDN/client
- ✅ Mobile app friendly (structured endpoints)
- ✅ Future-proof (separates concerns)

**Cons:**
- ⚠ +1 new REST class file (~30-40 lines of routing)
- ⚠ +1-2 hours implementation time
- ⚠ Requires additional validation for event_key format
- ⚠ Not strictly necessary (Fase 3 already works via /stage-fragment)

**Effort:** ~2 hours  
**Risk:** Low (additive, no changes to existing routes)

---

### 🟡 OPTION B — Data Migration (Schema 5.1 Consolidation)

**Scope:** Migrate existing vana_visit posts to consistent schema 5.1

```php
// Current state (what exists on server)
$timeline_json = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
// May have various formats, inconsistent event_key presence

// Target state (after migration)
// All posts normalized to:
// {
//   visit_status: "live|scheduled|completed|cancelled",
//   days: [{
//     date: "2025-03-20",
//     active_events: [{
//       event_key: "unique-key",        ← guaranteed present
//       title, scheduled_at,
//       vod: { provider, video_id, ... },
//       gallery: [],
//       sangha_links: [...]
//     }]
//   }]
// }
```

**Pros:**
- ✅ All Fase 1-3 functions work on 100% of production data
- ✅ Eliminates edge cases/inconsistencies
- ✅ Safer for future development
- ✅ Cleaner data for reporting/APIs

**Cons:**
- ⚠ Requires full backup before execution
- ⚠ Irreversible if something goes wrong
- ⚠ Must handle edge cases (empty posts, malformed JSON)
- ⚠ ~3-4 hours to write safe migrator

**Effort:** ~3-4 hours  
**Risk:** Medium → High (data mutation, must have rollback plan)

---

### 🟢 OPTION C — Both in Sequence (Recommended)

**Phase 4a → Data Migration (Day 1)**
```
1. Backup all vana_visit posts
2. Run migration script (with dry-run first)
3. Validate 100% post success
4. Commit to git with migration record
```

**Phase 4b → REST Endpoints (Day 2)**
```
1. Create /stage/{event_key} route
2. Tests on normalized data
3. Deprecate /stage-fragment?item_type=event (keep for backward compat)
4. Performance test on 1000+ requests
```

**Total Effort:** ~5-6 hours  
**Risk:** Low → Medium (phased approach reduces risk)

---

## 📊 Decision Matrix

| Criterion | Opt A | Opt B | Opt C |
|-----------|-------|-------|-------|
| **Implementation Time** | 2h | 3-4h | 5-6h |
| **Data Safety** | N/A | ⚠ Medium | ✅ Managed |
| **Production Ready** | ✅ | ⚠ After migration | ✅ Optimal |
| **Future Maintenance** | ✅ | ✅ | ✅✅ |
| **Risk Profile** | Low | Medium-High | Low-Medium |
| **Recommended** | No (premature) | Not first | **YES** |

---

## 🔍 Critical Information Needed

**Before choosing Option B or C, run this on production:**

```bash
cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html
wp eval '
$posts = get_posts(["post_type"=>"vana_visit","posts_per_page"=>5,"post_status"=>"any"]);
echo count($posts) . " posts encontrados\n";
foreach($posts as $p){
  $raw = get_post_meta($p->ID, "_vana_visit_timeline_json", true);
  if(empty($raw)) {
    echo "ID:{$p->ID} | VAZIO\n";
    continue;
  }
  $data = json_decode($raw, true);
  $keys = $data ? array_keys($data) : [];
  $visit_status = $data["visit_status"] ?? "N/A";
  echo "ID:{$p->ID} | status:{$visit_status} | schema_keys:" . implode(",", $keys) . "\n";
  if(!empty($data["days"])) {
    $day = $data["days"][0];
    if(!empty($day["active_events"])) {
      $evt = $day["active_events"][0];
      $evt_key = $evt["event_key"] ?? $evt["key"] ?? "N/A";
      echo "  └─ Event key: {$evt_key}\n";
    }
  }
}
' --allow-root
```

**What this tells us:**
- ✅ Number of vana_visit posts in production
- ✅ Which have timeline_json data
- ✅ Current schema structure (visit_status, days, active_events)
- ✅ Whether event_key is present (critical for Fase 3)
- ✅ Whether migration is needed

---

## 🎯 Recommendation

### **OPTION C (Recommended Flow)**

```
PHASE 4A — Data Safety & Normalization
├─ Backup production vana_visit posts (automated)
├─ Create migration script with dry-run mode
├─ Validate all posts successfully normalized
├─ Commit migration record to git
└─ Full rollback plan documented

PHASE 4B — REST Semantic Endpoints
├─ Create /stage/{event_key} route
├─ Performance testing
├─ Keep /stage-fragment for backward compat
└─ Document endpoint deprecation timeline
```

**Why?**
1. **Levitra safety first** — backup + dry-run before any mutations
2. **Clean data** — all functions work on normalized schema
3. **Better endpoints** — semantic URLs for future growth
4. **No technical debt** — migration is one-time effort
5. **Easy rollback** — backup + git record

---

## ✋ Before I Generate Fase 4

**Please choose:**

### Option A ✗ (REST endpoints only, skip migration)
```bash
# Skip migration, go straight to /stage/{event_key} endpoints
# Risk: Assumes all production data already matches schema 5.1
```

### Option B ✗ (Migration only, skip semantic endpoints)
```bash
# Normalize all data to schema 5.1
# Keep using /stage-fragment endpoint
# Risk: Good cleanup, but no URL improvement
```

### Option C ✓ RECOMMENDED (Both in sequence)
```bash
# Phase 4a: Backup + Migration (safe, auditable)
# Phase 4b: New REST endpoints (builds on clean data)
# Risk: Low → Medium (phased, each step is reversible)
```

---

## 📋 Next Actions

1. **Choose option** above ☝️
2. **Optionally run schema inspection** (provides data confidence)
3. **I generate Fase 4 implementation** (with or without inspection)

**Timeline Estimate:**  
- Option A: Deploy in **2 hours**
- Option B: Deploy in **4-6 hours** (includes testing)
- Option C: Deploy in **2-3 days** (split into 4a/4b)

---

**Status:** Waiting for decision ⏳  
**Last Updated:** 2025-03-21  
**Fase 3:** ✅ Complete (ready to move on)
