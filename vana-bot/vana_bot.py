import os
import re
import time
import json
import hmac
import hashlib
import secrets
import requests

from dotenv import load_dotenv

from telegram import Update, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    MessageHandler,
    ContextTypes,
    filters,
    CommandHandler,
)

load_dotenv()

# =========================
# CONFIG & FAIL-FAST
# =========================
WP_BASE = os.environ.get("WP_BASE", "").rstrip("/")
VANA_HMAC_SECRET = os.environ.get("VANA_HMAC_SECRET", "")
TELEGRAM_BOT_TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", "")

if not TELEGRAM_BOT_TOKEN:
    raise SystemExit("ERRO: TELEGRAM_BOT_TOKEN ausente.")
if not VANA_HMAC_SECRET:
    raise SystemExit("ERRO: VANA_HMAC_SECRET ausente.")
if not WP_BASE:
    raise SystemExit("ERRO: WP_BASE ausente.")

WP_LIVE_ENDPOINT = f"{WP_BASE}/wp-json/vana/v1/schedule-live-update"

AUTH_RAW = os.environ.get("AUTHORIZED_USERS", "")
AUTHORIZED_USERS = set(int(x) for x in AUTH_RAW.split(",") if x.strip().isdigit())

TIMEOUT_SEC = int(os.environ.get("TIMEOUT_SEC", "12"))

VALID_STATUSES = {"scheduled", "delayed", "live", "done", "cancelled"}
VALID_ALERT_TYPES = {"info", "warning", "error"}

# Context defaults (podem ser sobrescritos por context.json)
try:
    DEFAULT_VISIT_ID = int(os.environ.get("DEFAULT_VISIT_ID", "0"))
except ValueError:
    DEFAULT_VISIT_ID = 0
DEFAULT_DATE_LOCAL = os.environ.get("DEFAULT_DATE_LOCAL", "")
DEFAULT_EVENT_ID = os.environ.get("DEFAULT_EVENT_ID", "hero")

# Persistência do contexto
CONTEXT_FILE = os.environ.get("CONTEXT_FILE", "context.json")

# =========================
# SAFE TOKEN CACHE (TTL + FIFO)
# =========================
SAFE_CACHE: dict[str, dict] = {}
SAFE_CACHE_TTL_SEC = int(os.environ.get("SAFE_CACHE_TTL_SEC", "600"))
SAFE_CACHE_MAX = int(os.environ.get("SAFE_CACHE_MAX", "2000"))


def cache_gc(max_sweep: int = 200) -> None:
    """Remove apenas itens expirados (varredura limitada)."""
    now = int(time.time())
    n = 0
    for k in list(SAFE_CACHE.keys()):
        if n >= max_sweep:
            break
        item = SAFE_CACHE.get(k)
        if item and int(item.get("exp", 0)) <= now:
            SAFE_CACHE.pop(k, None)
        n += 1


def cache_put(data: dict, ttl: int = SAFE_CACHE_TTL_SEC) -> str:
    """Adiciona ao cache; limpa expirados e aplica FIFO se ainda lotado."""
    if len(SAFE_CACHE) >= SAFE_CACHE_MAX:
        cache_gc(max_sweep=min(SAFE_CACHE_MAX, 500))

    if len(SAFE_CACHE) >= SAFE_CACHE_MAX:
        # FIFO eviction (mesmo não expirado)
        for k in list(SAFE_CACHE.keys())[:50]:
            SAFE_CACHE.pop(k, None)

    tok = "tok_" + secrets.token_urlsafe(8).replace("-", "_")
    SAFE_CACHE[tok] = {"exp": int(time.time()) + int(ttl), "data": data}
    return tok


def cache_get(tok: str) -> dict | None:
    item = SAFE_CACHE.get(tok)
    if not item:
        return None
    if int(time.time()) > int(item.get("exp", 0)):
        SAFE_CACHE.pop(tok, None)
        return None
    return item.get("data")


# =========================
# HMAC (HEX) — compat PHP hash_hmac
# =========================
def vana_sign_body(secret: str, timestamp: int, body_bytes: bytes) -> str:
    msg = str(timestamp).encode("utf-8") + b"." + body_bytes
    return hmac.new(secret.encode("utf-8"), msg, hashlib.sha256).hexdigest()


def wp_live_update(payload: dict) -> requests.Response:
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    ts = int(time.time())
    sig = vana_sign_body(VANA_HMAC_SECRET, ts, body)
    headers = {
        "Content-Type": "application/json",
        "X-Vana-Timestamp": str(ts),
        "X-Vana-Signature": sig,
    }
    return requests.post(WP_LIVE_ENDPOINT, data=body, headers=headers, timeout=TIMEOUT_SEC)


# =========================
# AUTH
# =========================
def is_authorized(user_id: int) -> bool:
    return (not AUTHORIZED_USERS) or (user_id in AUTHORIZED_USERS)


# =========================
# CONTEXT persistence
# =========================
def load_context() -> None:
    global DEFAULT_VISIT_ID, DEFAULT_DATE_LOCAL, DEFAULT_EVENT_ID
    if not os.path.exists(CONTEXT_FILE):
        return
    try:
        with open(CONTEXT_FILE, "r", encoding="utf-8") as f:
            data = json.load(f)
        DEFAULT_VISIT_ID = int(data.get("visit_id", DEFAULT_VISIT_ID) or DEFAULT_VISIT_ID)
        DEFAULT_DATE_LOCAL = (data.get("date_local", DEFAULT_DATE_LOCAL) or DEFAULT_DATE_LOCAL).strip()
        DEFAULT_EVENT_ID = (data.get("event_id", DEFAULT_EVENT_ID) or DEFAULT_EVENT_ID).strip() or "hero"
        print(f"✅ Contexto carregado: visit_id={DEFAULT_VISIT_ID} date_local={DEFAULT_DATE_LOCAL} event_id={DEFAULT_EVENT_ID}")
    except Exception as e:
        print(f"❌ Erro ao carregar {CONTEXT_FILE}: {e}")


def save_context() -> None:
    data = {
        "visit_id": int(DEFAULT_VISIT_ID),
        "date_local": str(DEFAULT_DATE_LOCAL),
        "event_id": str(DEFAULT_EVENT_ID),
    }
    try:
        with open(CONTEXT_FILE, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, separators=(",", ":"))
    except Exception as e:
        print(f"❌ Erro ao salvar {CONTEXT_FILE}: {e}")


# =========================
# CALLBACK parsing (HARDEN)
# =========================
class CallbackParseError(Exception):
    pass


def expand_action_value(action: str, code: str) -> object:
    action = action.strip()
    code = code.strip()

    if action == "set_status":
        if code not in VALID_STATUSES:
            raise CallbackParseError(f"Status '{code}' é inválido.")
        return code

    if action == "set_stream":
        if code.startswith("tok_"):
            data = cache_get(code)
            if not data or data.get("kind") != "stream":
                raise CallbackParseError("O link expirou. Por favor, envie o link novamente.")

            provider = (data.get("provider") or "").strip()
            url = (data.get("url") or "").strip()
            video_id = (data.get("video_id") or "").strip()

            if provider not in ("youtube", "facebook"):
                raise CallbackParseError("Provedor inválido no token.")
            if not url.startswith("http"):
                raise CallbackParseError("URL inválida no token.")
            if provider == "youtube" and video_id and not re.fullmatch(r"[A-Za-z0-9_-]{6,20}", video_id):
                raise CallbackParseError("ID do YouTube corrompido no token.")

            return {"provider": provider, "video_id": video_id, "url": url}

        if code.startswith("yt:"):
            vid = code[3:].strip()
            if not re.fullmatch(r"[A-Za-z0-9_-]{6,20}", vid):
                raise CallbackParseError("ID do YouTube com formato inválido.")
            return {"provider": "youtube", "video_id": vid, "url": f"https://youtu.be/{vid}"}

        raise CallbackParseError("Formato de vídeo não reconhecido.")

    if action == "set_alert":
        if code.startswith("altok:"):
            parts = code.split(":", 3)
            if len(parts) != 4:
                raise CallbackParseError("Comando de alerta (token) malformado.")

            _, typ, active_s, tok = parts
            typ = typ.strip().lower()
            if typ not in VALID_ALERT_TYPES:
                raise CallbackParseError("Tipo de alerta (cache) inválido.")

            data = cache_get(tok)
            if not data or data.get("kind") != "text":
                raise CallbackParseError("O texto do alerta expirou no cache.")

            msg = (data.get("text") or "").strip()
            if len(msg) < 3:
                raise CallbackParseError("Texto do alerta muito curto ou vazio.")
            if len(msg) > 250:
                raise CallbackParseError("Texto do alerta muito longo (máx 250).")

            return {"type": typ, "message": msg, "active": active_s.strip() == "1"}

        if code.startswith("al:"):
            parts = code.split(":", 3)
            if len(parts) != 4:
                raise CallbackParseError("Comando de alerta (estático) malformado.")

            _, typ, active_s, msg = parts
            typ = typ.strip().lower()
            if typ not in VALID_ALERT_TYPES:
                raise CallbackParseError("Tipo de alerta inválido.")

            msg = (msg or "").strip()
            if len(msg) > 120:
                raise CallbackParseError("Mensagem muito longa (máx 120 para botões estáticos).")

            return {"type": typ, "message": msg, "active": active_s.strip() == "1"}

        raise CallbackParseError("Formato de alerta não reconhecido.")

    raise CallbackParseError(f"Ação '{action}' desconhecida.")


def parse_callback_data(data: str) -> dict:
    parts = data.split("|")
    if len(parts) < 6 or parts[0] != "vana":
        raise CallbackParseError("Estrutura de comando inválida.")

    _, visit_id_str, date_local, event_id, action, code = parts[:6]

    visit_id_str = visit_id_str.strip()
    if not visit_id_str.isdigit() or int(visit_id_str) <= 0:
        raise CallbackParseError("ID da Visita inválido.")

    date_local = date_local.strip()
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", date_local):
        raise CallbackParseError("Formato de data local inválido.")

    event_id = event_id.strip()
    if not event_id:
        raise CallbackParseError("ID do Evento ausente.")

    action = action.strip()
    code = code.strip()

    return {
        "visit_id": int(visit_id_str),
        "date_local": date_local,
        "event_id": event_id,
        "action": action,
        "value": expand_action_value(action, code),
    }


def cbdata(visit_id: int, date_local: str, event_id: str, action: str, code: str) -> str:
    return f"vana|{visit_id}|{date_local}|{event_id}|{action}|{code}"


# =========================
# UI keyboards
# =========================
def build_ops_keyboard() -> InlineKeyboardMarkup:
    vid = int(DEFAULT_VISIT_ID)
    dt = str(DEFAULT_DATE_LOCAL)
    ev = str(DEFAULT_EVENT_ID)
    kb = [
        [
            InlineKeyboardButton("🔴 Ao vivo", callback_data=cbdata(vid, dt, ev, "set_status", "live")),
            InlineKeyboardButton("⏳ Atrasar", callback_data=cbdata(vid, dt, ev, "set_status", "delayed")),
        ],
        [
            InlineKeyboardButton("✅ Encerrar", callback_data=cbdata(vid, dt, ev, "set_status", "done")),
            InlineKeyboardButton("🚫 Cancelar", callback_data=cbdata(vid, dt, ev, "set_status", "cancelled")),
        ],
        [
            InlineKeyboardButton("🟢 Agendado", callback_data=cbdata(vid, dt, ev, "set_status", "scheduled")),
            InlineKeyboardButton("🧹 Limpar Alerta", callback_data=cbdata(vid, dt, ev, "set_alert", "al:info:0:-")),
        ],
    ]
    return InlineKeyboardMarkup(kb)


# =========================
# Handlers
# =========================
YOUTUBE_RE = re.compile(r"(https?://)?(www\.)?(youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,20})")
FACEBOOK_RE = re.compile(r"https?://(www\.)?facebook\.com/\S+|https?://fb\.watch/\S+", re.IGNORECASE)


async def on_button(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    q = update.callback_query
    if not q or not q.data:
        return

    cache_gc(max_sweep=500)

    user = q.from_user
    user_id = user.id if user else 0
    
    # LOG DE ENTRADA: Isso DEVE aparecer no console assim que você clicar
    print(f"📥 Clique recebido de {user_id}: {q.data}")

    if not is_authorized(user_id):
        print(f"🚫 Usuário {user_id} não autorizado!")
        await q.answer("❌ Acesso Negado.", show_alert=True)
        return

    try:
        parsed = parse_callback_data(q.data)
    except CallbackParseError as e:
        print(f"❌ Erro no parsing do botão: {e}")
        await q.answer(f"❌ Botão inválido: {e}", show_alert=True)
        return

    await q.answer("⏳ Aplicando no site...")

    wp_payload = {
        "visit_id": parsed["visit_id"],
        "date_local": parsed["date_local"],
        "event_id": parsed["event_id"],
        "request_id": f"tg_{int(time.time())}_{user_id}_{parsed['action']}",
        "action": parsed["action"],
        "value": parsed["value"],
        "issued_by": {
            "system": "telegram_bot",
            "telegram_user_id": user_id,
            "telegram_username": (user.username or "") if user else "",
        },
    }

    print(f"🛰️ Enviando comando '{parsed['action']}' para o WP...")

    try:
        resp = wp_live_update(wp_payload)
        # LOG DE RESPOSTA: Isso dirá o que o WP respondeu
        print(f"🌐 WP respondeu: Status {resp.status_code} | Body: {resp.text}")

        if resp.status_code == 200:
            await q.answer("✅ Sucesso! O site foi atualizado.", show_alert=True)
        else:
            await q.answer(f"⚠️ Servidor recusou (HTTP {resp.status_code})", show_alert=True)
    except requests.exceptions.Timeout:
        print("❌ TIMEOUT: O WordPress demorou demais para responder.")
        await q.answer("❌ Timeout ao contactar o WP.", show_alert=True)
    except requests.exceptions.RequestException as e:
        print(f"❌ ERRO DE REDE: {e}")
        await q.answer(f"❌ Erro de rede: {e}", show_alert=True)
    except Exception as e:
        print(f"❌ ERRO INTERNO: {e}")
        await q.answer(f"❌ Erro interno: {e}", show_alert=True)

async def on_group_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    msg = update.message
    if not msg or not msg.text:
        return

    # se não temos contexto, não sugerir botões
    if int(DEFAULT_VISIT_ID) <= 0 or not str(DEFAULT_DATE_LOCAL).strip():
        return

    text = msg.text.strip()
    bot_username = (context.bot.username or "").lower()

    is_mention = bool(bot_username) and re.search(rf"@{re.escape(bot_username)}\b", text, re.IGNORECASE)
    is_reply = bool(msg.reply_to_message and msg.reply_to_message.from_user and msg.reply_to_message.from_user.is_bot)

    if not (is_mention or is_reply):
        return

    user = msg.from_user
    if not user or not is_authorized(user.id):
        return

    # remove menção do conteúdo
    if bot_username:
        text = re.sub(rf"@{re.escape(bot_username)}\b", "", text, flags=re.IGNORECASE).strip()

    yt = YOUTUBE_RE.search(text)
    fb = FACEBOOK_RE.search(text)

    # Link de vídeo
    if yt or fb:
        if yt:
            vid = yt.group(4)
            code = f"yt:{vid}"
            preview = f"https://youtu.be/{vid}"
            label = "📺 Colocar YouTube na Home"
            cb = cbdata(int(DEFAULT_VISIT_ID), str(DEFAULT_DATE_LOCAL), str(DEFAULT_EVENT_ID), "set_stream", code)
        else:
            url = fb.group(0)
            tok = cache_put({"kind": "stream", "provider": "facebook", "video_id": "", "url": url})
            preview = url
            label = "📺 Colocar Facebook na Home"
            cb = cbdata(int(DEFAULT_VISIT_ID), str(DEFAULT_DATE_LOCAL), str(DEFAULT_EVENT_ID), "set_stream", tok)

        kb = InlineKeyboardMarkup([[InlineKeyboardButton(label, callback_data=cb)]])
        await msg.reply_text(
            f"Detectei um link de vídeo:\n{preview}\n\nAplicar no destaque da Home?",
            reply_markup=kb,
            disable_web_page_preview=True,
        )
        return

    # Texto de alerta
    if len(text) < 3 or len(text) > 250:
        return

    tok = cache_put({"kind": "text", "text": text})
    kb = InlineKeyboardMarkup([
        [InlineKeyboardButton("🔵 Info", callback_data=cbdata(int(DEFAULT_VISIT_ID), str(DEFAULT_DATE_LOCAL), str(DEFAULT_EVENT_ID), "set_alert", f"altok:info:1:{tok}"))],
        [InlineKeyboardButton("⚠️ Warning", callback_data=cbdata(int(DEFAULT_VISIT_ID), str(DEFAULT_DATE_LOCAL), str(DEFAULT_EVENT_ID), "set_alert", f"altok:warning:1:{tok}"))],
        [InlineKeyboardButton("🔴 Error", callback_data=cbdata(int(DEFAULT_VISIT_ID), str(DEFAULT_DATE_LOCAL), str(DEFAULT_EVENT_ID), "set_alert", f"altok:error:1:{tok}"))],
    ])

    await msg.reply_text(
        f"Criar Banner de Alerta no Site com a seguinte mensagem?\n\n« {text} »",
        reply_markup=kb,
    )


async def send_ops(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    msg = update.message
    if not msg or not msg.from_user:
        return
    if not is_authorized(msg.from_user.id):
        return
    if int(DEFAULT_VISIT_ID) <= 0 or not str(DEFAULT_DATE_LOCAL).strip():
        await msg.reply_text("⚠️ Contexto não definido. Use /setcontext primeiro.")
        return
    await msg.reply_text("🎛️ Painel de Controle da Home", reply_markup=build_ops_keyboard())


async def show_context(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    msg = update.message
    if not msg or not msg.from_user:
        return
    if not is_authorized(msg.from_user.id):
        return

    await msg.reply_text(
        "📍 *Contexto Atual da Missão*\n"
        f"🆔 Visit ID: `{DEFAULT_VISIT_ID}`\n"
        f"📅 Data: `{DEFAULT_DATE_LOCAL}`\n"
        f"🏷️ Event ID: `{DEFAULT_EVENT_ID}`\n"
        f"💾 Arquivo: `{CONTEXT_FILE}`",
        parse_mode="Markdown",
    )


async def set_context(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    msg = update.message
    if not msg or not msg.from_user:
        return
    if not is_authorized(msg.from_user.id):
        return

    # Uso: /setcontext ID DATA [EVENT_ID]
    if len(context.args) < 2:
        await msg.reply_text(
            "Uso:\n"
            "`/setcontext VISIT_ID YYYY-MM-DD [EVENT_ID]`\n\n"
            "Exemplos:\n"
            "`/setcontext 1234 2026-02-18 hero`\n"
            "`/setcontext 1234 2026-02-18 stage_main`",
            parse_mode="Markdown",
        )
        return

    try:
        new_visit_id = int(context.args[0].strip())
        new_date = context.args[1].strip()
        new_event = (context.args[2].strip() if len(context.args) > 2 else "hero") or "hero"

        if new_visit_id <= 0:
            raise ValueError("VISIT_ID deve ser > 0")
        if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", new_date):
            raise ValueError("DATA deve ser YYYY-MM-DD")
        if not re.fullmatch(r"[A-Za-z0-9_-]{1,64}", new_event):
            raise ValueError("EVENT_ID inválido (use letras, números, _ ou -)")

        global DEFAULT_VISIT_ID, DEFAULT_DATE_LOCAL, DEFAULT_EVENT_ID
        DEFAULT_VISIT_ID = new_visit_id
        DEFAULT_DATE_LOCAL = new_date
        DEFAULT_EVENT_ID = new_event

        save_context()

        await msg.reply_text(
            "🎯 *Contexto Atualizado*\n"
            f"🆔 Visit ID: `{DEFAULT_VISIT_ID}`\n"
            f"📅 Data: `{DEFAULT_DATE_LOCAL}`\n"
            f"🏷️ Event ID: `{DEFAULT_EVENT_ID}`\n\n"
            "A partir de agora, links e alertas apontarão para este destino.",
            parse_mode="Markdown",
        )
    except Exception as e:
        await msg.reply_text(f"❌ Erro ao definir contexto: {e}")


# =========================
# MAIN
# =========================
def main() -> None:
    load_context()

    if int(DEFAULT_VISIT_ID) <= 0 or not str(DEFAULT_DATE_LOCAL).strip():
        print("⚠️ Contexto inicial não definido (DEFAULT_VISIT_ID/DEFAULT_DATE_LOCAL). Use /setcontext após subir o bot.")

    app = Application.builder().token(TELEGRAM_BOT_TOKEN).build()

    app.add_handler(CallbackQueryHandler(on_button, pattern=r"^vana\|"))
    app.add_handler(MessageHandler(filters.TEXT & (~filters.COMMAND), on_group_message))

    app.add_handler(CommandHandler("ops", send_ops))
    app.add_handler(CommandHandler("context", show_context))
    app.add_handler(CommandHandler("setcontext", set_context))

    print("🚀 Bot Vana Mission Control iniciado (Vrindavan 1.0).")
    app.run_polling(allowed_updates=["callback_query", "message"])


if __name__ == "__main__":
    main()
