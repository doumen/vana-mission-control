# 📄 Documentação HMAC — Vana Ingest API

## Visão Geral

A API usa **HMAC-SHA256** com anti-replay por nonce. Toda requisição deve carregar 3 query params assinados.

---

## 🔐 Credencial

```
VANA_INGEST_SECRET = 57ab0c97f436f7ed6662db5632c8d6dcec58a0f810569cfa7bd328c4321f8a7d
```
> Definida em `wp-config.php` como constante PHP `VANA_INGEST_SECRET`.  
> **Nunca exponha esse valor em repositórios ou logs.**

---

## 📐 Formato da Assinatura

### Mensagem canônica
```
{timestamp}\n{nonce}\n{raw_body}
```

| Parte | Tipo | Descrição |
|---|---|---|
| `timestamp` | Unix epoch (segundos) | Hora atual em segundos |
| `nonce` | hex 32 chars | 16 bytes aleatórios em hex |
| `raw_body` | string | Body cru da requisição (GET = string vazia `""`) |

### Algoritmo
```
signature = HMAC-SHA256( "{timestamp}\n{nonce}\n{body}", VANA_INGEST_SECRET )
```

### Query params obrigatórios
```
?vana_timestamp={timestamp}&vana_nonce={nonce}&vana_signature={signature}
```

---

## ⏱️ Janela de Validade

| Regra | Valor |
|---|---|
| Tolerância de clock | ± 5 minutos (300s) |
| Nonce queimado após uso | Sim (anti-replay) |
| TTL do nonce no banco | 600s (2× a janela) |

---

## 🛣️ Endpoints Disponíveis

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/vana/v1/visits` | Lista todas as visits |
| `GET` | `/vana/v1/visits/{id}` | Detalhe de uma visit |
| `GET` | `/vana/v1/tours` | Lista todos os tours |
| `GET` | `/vana/v1/tours/{id}` | Detalhe de um tour |
| `POST` | `/vana/v1/ingest` | Ingesta conteúdo |
| `POST` | `/vana/v1/checkin` | Registra check-in |
| `POST` | `/vana/v1/ingest-visit` | Ingesta uma visit |
| `POST` | `/vana/v1/push/subscribe` | Inscrição push |
| `POST` | `/vana/v1/push/send` | Dispara push |
| `POST` | `/vana/v1/schedule-live-update` | Agenda live update |

**Base URL:**
```
https://beta.vanamadhuryamdaily.com/wp-json
```

---

## 💻 Exemplos de Implementação

### PHP
```php
function vana_signed_request(string $method, string $path, array $body = []): array {
    $secret    = VANA_INGEST_SECRET;
    $timestamp = (string) time();
    $nonce     = bin2hex(random_bytes(16)); // 32 hex chars
    $raw_body  = empty($body) ? '' : json_encode($body);
    $message   = $timestamp . "\n" . $nonce . "\n" . $raw_body;
    $signature = hash_hmac('sha256', $message, $secret);

    $base = 'https://beta.vanamadhuryamdaily.com/wp-json';
    $url  = $base . $path . '?' . http_build_query([
        'vana_timestamp' => $timestamp,
        'vana_nonce'     => $nonce,
        'vana_signature' => $signature,
    ]);

    $args = [
        'method'  => strtoupper($method),
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => $raw_body ?: null,
        'timeout' => 15,
    ];

    $resp = wp_remote_request($url, $args);
    return json_decode(wp_remote_retrieve_body($resp), true);
}

// Uso
$visits = vana_signed_request('GET', '/vana/v1/visits');
```

### Python (n8n Code Node / Script externo)
```python
import hmac, hashlib, secrets, time, requests

SECRET = "57ab0c97f436f7ed6662db5632c8d6dcec58a0f810569cfa7bd328c4321f8a7d"
BASE   = "https://beta.vanamadhuryamdaily.com/wp-json"

def vana_request(method: str, path: str, body: dict = None):
    timestamp = str(int(time.time()))
    nonce     = secrets.token_hex(16)           # 32 hex chars
    raw_body  = "" if body is None else __import__('json').dumps(body)
    message   = f"{timestamp}\n{nonce}\n{raw_body}"
    signature = hmac.new(
        SECRET.encode(), message.encode(), hashlib.sha256
    ).hexdigest()

    params = {
        "vana_timestamp": timestamp,
        "vana_nonce":     nonce,
        "vana_signature": signature,
    }
    headers = {"Content-Type": "application/json"}
    url     = BASE + path

    resp = requests.request(method, url, params=params,
                            data=raw_body or None, headers=headers)
    return resp.json()

# Uso
visits = vana_request("GET", "/vana/v1/visits")
print(visits)
```

### JavaScript / n8n Code Node
```javascript
const crypto = require('crypto');

const SECRET = '57ab0c97f436f7ed6662db5632c8d6dcec58a0f810569cfa7bd328c4321f8a7d';
const BASE   = 'https://beta.vanamadhuryamdaily.com/wp-json';

function vanaSign(path, body = '') {
  const timestamp = String(Math.floor(Date.now() / 1000));
  const nonce     = crypto.randomBytes(16).toString('hex'); // 32 hex chars
  const rawBody   = body ? JSON.stringify(body) : '';
  const message   = `${timestamp}\n${nonce}\n${rawBody}`;
  const signature = crypto.createHmac('sha256', SECRET)
                          .update(message)
                          .digest('hex');
  const params = new URLSearchParams({
    vana_timestamp: timestamp,
    vana_nonce:     nonce,
    vana_signature: signature,
  });
  return { url: `${BASE}${path}?${params}`, rawBody };
}

// Uso — GET /vana/v1/visits
const { url } = vanaSign('/vana/v1/visits');
const resp    = await $http.get(url);
return resp.data;
```

---

## 🔴 Erros Comuns

| HTTP | Código | Causa |
|---|---|---|
| `401` | `rest_forbidden` | Parâmetros HMAC ausentes ou inválidos |
| `401` | `HMAC_SECRET_MISSING` | `VANA_INGEST_SECRET` não definido no `wp-config.php` |
| `401` | `HMAC_TIMESTAMP_OUT_OF_WINDOW` | Clock desincronizado > 5 min |
| `401` | `HMAC_REPLAY_DETECTED` | Nonce já usado — gere um novo |
| `401` | `HMAC_SIGNATURE_MISMATCH` | Secret errado ou body alterado em trânsito |

---

> 🙏 Documentação gerada em **23/02/2026** — `vana-mission-control v4.2.4`

# ✅ Todos os Endpoints Operacionais!

## Resultado Final

| Endpoint | HTTP | Action | Status |
|---|---|---|---|
| `POST /vana/v1/checkin` | `201` | `submission_id: 356` | ✅ Perfeito |
| `POST /vana/v1/ingest` (visit) | `201` | `created` | ✅ Perfeito |
| `POST /vana/v1/ingest` (tour) | `201` | `placeholder` | ✅ Perfeito |
| `POST /vana/v1/ingest-visit` | `201` | `created` | ✅ Perfeito |

---

## ⚠️ Único Ponto Pendente — `tour_updated: false`

Ambas as visits foram criadas mas **não vincularam à tour pai** porque a tour `tour:india_2026` não tem o meta `_vana_origin_key` gravado. Corrija assim:

```bash
cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html

wp eval '
// Tour Espiritual Índia 2026 = post ID 309
$tour_id    = 309;
$origin_key = "tour:india_2026";

update_post_meta($tour_id, "_vana_origin_key", $origin_key);

// Verifica se gravou
$check = get_post_meta($tour_id, "_vana_origin_key", true);
echo "Tour ID {$tour_id} → _vana_origin_key = \"{$check}\"\n";

// Mesma correção para as outras tours
$tours = [
    319 => "tour:south_america_2026",
    326 => "tour:holland_2026",
];
foreach ($tours as $id => $key) {
    update_post_meta($id, "_vana_origin_key", $key);
    $v = get_post_meta($id, "_vana_origin_key", true);
    echo "Tour ID {$id} → _vana_origin_key = \"{$v}\"\n";
}
echo "\n🙏 Origin keys gravados em todas as tours\n";
' --allow-root
```

Depois rode um `/ingest` com `parent_origin_key: "tour:india_2026"` e `tour_updated` virá `true` com o `tour_id` correto.

---

## 📋 Documentação Atualizada — Payloads Canônicos

### `/checkin` — Público, sem HMAC
```json
{
  "visit_id": 317,
  "consent_publish": 1,
  "sender_name": "Nome do Devoto",
  "message": "Mensagem opcional",
  "external_url": "https://youtu.be/xxx",
  "website": ""
}
```

### `/ingest` kind=visit — Com HMAC
```json
{
  "kind": "visit",
  "origin_key": "visit:tour_slug:cidade_data",
  "parent_origin_key": "tour:india_2026",
  "title": "Título da Visita",
  "data": {
    "schema_version": "3.1",
    "updated_at": "2026-02-23T19:57:00-03:00",
    "days": [
      {
        "date_local": "2026-02-23",
        "hero": {
          "provider": "youtube",
          "video_id": "VIDEO_ID",
          "title_pt": "Título PT",
          "title_en": "Title EN"
        },
        "schedule": [],
        "community_links": [],
        "vod": [],
        "galleries": []
      }
    ]
  }
}
```

### `/ingest` kind=tour — Com HMAC (placeholder)
```json
{
  "kind": "tour",
  "origin_key": "tour:india_2026",
  "data": {
    "title": "Tour Espiritual Índia 2026"
  }
}
```

---

> 🙏 API 100% funcional. Próximo passo: gravar os `origin_key` nas tours e testar o vínculo automático `tour_updated: true`.