"""
bot.py — Vana Madhuryam Daily Bot v1.0
Fluxo conversacional para registro de vídeos via Telegram.

Comandos:
  /novo     → inicia registro de um vídeo
  /cancelar → cancela a sessão atual
  /ajuda    → lista comandos

Dependências:
  pip install python-telegram-bot==21.* python-dotenv
"""

from __future__ import annotations

import logging
import os
from datetime import date

from dotenv import load_dotenv
from telegram import (
    Update,
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    ReplyKeyboardMarkup,
    ReplyKeyboardRemove,
)
from telegram.constants import ParseMode
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    ContextTypes,
    ConversationHandler,
    MessageHandler,
    filters,
)

from src.infer import infer
from src.state import S, Session

# ──────────────────────────────────────────────
# Config
# ──────────────────────────────────────────────

load_dotenv()
TOKEN          = os.getenv("TELEGRAM_TOKEN")
ALLOWED_CHAT   = int(os.getenv("ALLOWED_CHAT_ID", "0"))

logging.basicConfig(
    format="%(asctime)s | %(levelname)s | %(message)s",
    level=logging.INFO,
)
log = logging.getLogger(__name__)

# Mapeamento de estados para o ConversationHandler
(
    ST_TITLE,
    ST_CONFIRM,
    ST_PERIOD,
    ST_LOCATION,
    ST_DATE,
    ST_LANG,
) = range(6)

PERIODS   = ["MORNING", "MIDDAY", "EVENING", "NIGHT"]
LOCATIONS = ["TEMPLE", "WALK", "PARIKRAMA", "ROOM-DARSHAN", "AIRPORT", "PROGRAM"]
LANGS     = ["EN", "PT"]


# ──────────────────────────────────────────────
# Guard de segurança
# ──────────────────────────────────────────────

def _allowed(update: Update) -> bool:
    """Só aceita mensagens do chat autorizado."""
    return update.effective_chat.id == ALLOWED_CHAT


# ──────────────────────────────────────────────
# Teclados
# ──────────────────────────────────────────────

def _kb_period() -> ReplyKeyboardMarkup:
    return ReplyKeyboardMarkup(
        [PERIODS[:2], PERIODS[2:]],
        one_time_keyboard=True,
        resize_keyboard=True,
    )

def _kb_location() -> ReplyKeyboardMarkup:
    return ReplyKeyboardMarkup(
        [LOCATIONS[:3], LOCATIONS[3:]],
        one_time_keyboard=True,
        resize_keyboard=True,
    )

def _kb_lang() -> ReplyKeyboardMarkup:
    return ReplyKeyboardMarkup(
        [LANGS],
        one_time_keyboard=True,
        resize_keyboard=True,
    )

def _kb_confirm(show_edit: bool = True) -> InlineKeyboardMarkup:
    buttons = [
        [InlineKeyboardButton("✅ Confirmar", callback_data="confirm")],
    ]
    if show_edit:
        buttons.append([
            InlineKeyboardButton("✏️ Period",   callback_data="edit_period"),
            InlineKeyboardButton("📍 Location", callback_data="edit_location"),
        ])
    buttons.append([InlineKeyboardButton("❌ Cancelar", callback_data="cancel")])
    return InlineKeyboardMarkup(buttons)


# ──────────────────────────────────────────────
# /ajuda
# ──────────────────────────────────────────────

async def cmd_ajuda(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> None:
    if not _allowed(update):
        return
    await update.message.reply_text(
        "🙏 *Vana Madhuryam Daily Bot*\n\n"
        "*/novo* — Registrar um novo vídeo\n"
        "*/cancelar* — Cancelar o registro em andamento\n"
        "*/ajuda* — Esta mensagem\n\n"
        "_Hare Krishna!_",
        parse_mode=ParseMode.MARKDOWN,
    )


# ──────────────────────────────────────────────
# /novo → pede título
# ──────────────────────────────────────────────

async def cmd_novo(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    if not _allowed(update):
        return ConversationHandler.END

    ctx.user_data["session"] = Session()
    await update.message.reply_text(
        "🎬 *Novo Registro*\n\n"
        "Cole o *título do vídeo* (YouTube, Facebook ou manual):",
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=ReplyKeyboardRemove(),
    )
    return ST_TITLE


# ──────────────────────────────────────────────
# Recebe título → infere → decide próximo passo
# ──────────────────────────────────────────────

async def recv_title(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    if not _allowed(update):
        return ConversationHandler.END

    sess: Session = ctx.user_data["session"]
    sess.title = update.message.text.strip()
    sess.state = S.AWAIT_CONFIRM

    # Inferência automática
    result = infer(title=sess.title)
    sess.period        = result.period
    sess.location      = result.location
    sess.prk_seq       = result.prk_seq
    sess.confidence    = result.confidence
    sess.matched_rules = result.matched_rules

    # Próximo passo: pedir data
    await update.message.reply_text(
        f"📅 *Data da gravação*\n\n"
        f"Digite no formato `AAAA-MM-DD`\n"
        f"_(ou envie `.` para usar hoje: `{date.today().isoformat()}`)_",
        parse_mode=ParseMode.MARKDOWN,
    )
    return ST_DATE


# ──────────────────────────────────────────────
# Recebe data → pede idioma
# ──────────────────────────────────────────────

async def recv_date(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    if not _allowed(update):
        return ConversationHandler.END

    sess: Session = ctx.user_data["session"]
    raw = update.message.text.strip()

    if raw == ".":
        sess.date_local = date.today().isoformat()
    else:
        # Validação simples de formato
        import re
        if not re.match(r"^\d{4}-\d{2}-\d{2}$", raw):
            await update.message.reply_text(
                "⚠️ Formato inválido. Use `AAAA-MM-DD` ou `.` para hoje.",
                parse_mode=ParseMode.MARKDOWN,
            )
            return ST_DATE
        sess.date_local = raw

    await update.message.reply_text(
        "🌐 *Idioma do vídeo:*",
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=_kb_lang(),
    )
    return ST_LANG


# ──────────────────────────────────────────────
# Recebe idioma → mostra confirmação
# ──────────────────────────────────────────────

async def recv_lang(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    if not _allowed(update):
        return ConversationHandler.END

    sess: Session = ctx.user_data["session"]
    lang = update.message.text.strip().upper()

    if lang not in LANGS:
        await update.message.reply_text(
            "⚠️ Escolha entre EN ou PT.",
            reply_markup=_kb_lang(),
        )
        return ST_LANG

    sess.lang = lang

    # Gera slug
    from src.infer import InferenceResult
    ir = InferenceResult(
        period   = sess.period,
        location = sess.location,
        prk_seq  = sess.prk_seq,
    )
    sess.slug = ir.filename_slug(sess.date_local, lang=sess.lang)

    # Alerta de baixa confidence
    alert = ""
    if sess.confidence in ("none", "low"):
        alert = (
            "\n\n⚠️ *Confidence baixa* — revise Period e Location antes de confirmar."
        )

    await update.message.reply_text(
        sess.to_summary() + alert,
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=_kb_confirm(),
    )
    return ST_CONFIRM


# ──────────────────────────────────────────────
# Callbacks dos botões inline
# ──────────────────────────────────────────────

async def cb_confirm(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    query = update.callback_query
    await query.answer()
    sess: Session = ctx.user_data["session"]

    if query.data == "confirm":
        sess.state = S.DONE
        await query.edit_message_text(
            f"✅ *Registro salvo!*\n\n"
            f"📁 `{sess.slug}`\n\n"
            f"_Use /novo para registrar outro vídeo._",
            parse_mode=ParseMode.MARKDOWN,
        )
        log.info("REGISTRO | %s", sess.slug)
        # ← aqui você chama save_to_db(sess) ou emit_event(sess) futuramente
        return ConversationHandler.END

    elif query.data == "edit_period":
        await query.edit_message_text(
            "✏️ *Escolha o Period correto:*",
            parse_mode=ParseMode.MARKDOWN,
        )
        await ctx.bot.send_message(
            chat_id=update.effective_chat.id,
            text="Selecione:",
            reply_markup=_kb_period(),
        )
        return ST_PERIOD

    elif query.data == "edit_location":
        await query.edit_message_text(
            "📍 *Escolha a Location correta:*",
            parse_mode=ParseMode.MARKDOWN,
        )
        await ctx.bot.send_message(
            chat_id=update.effective_chat.id,
            text="Selecione:",
            reply_markup=_kb_location(),
        )
        return ST_LOCATION

    elif query.data == "cancel":
        await query.edit_message_text("❌ Registro cancelado.")
        ctx.user_data.pop("session", None)
        return ConversationHandler.END

    return ST_CONFIRM


# ──────────────────────────────────────────────
# Correção manual de Period
# ──────────────────────────────────────────────

async def recv_period_fix(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    if not _allowed(update):
        return ConversationHandler.END

    sess: Session = ctx.user_data["session"]
    val = update.message.text.strip().upper()

    if val not in PERIODS:
        await update.message.reply_text(
            "⚠️ Opção inválida.", reply_markup=_kb_period()
        )
        return ST_PERIOD

    sess.period = val
    sess.source = "manual"

    # Recalcula slug
    from src.infer import InferenceResult
    ir = InferenceResult(period=sess.period, location=sess.location, prk_seq=sess.prk_seq)
    sess.slug = ir.filename_slug(sess.date_local, lang=sess.lang)

    await update.message.reply_text(
        sess.to_summary(),
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=_kb_confirm(),
    )
    return ST_CONFIRM


# ──────────────────────────────────────────────
# Correção manual de Location
# ──────────────────────────────────────────────

async def recv_location_fix(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    if not _allowed(update):
        return ConversationHandler.END

    sess: Session = ctx.user_data["session"]
    val = update.message.text.strip().upper()

    if val not in LOCATIONS:
        await update.message.reply_text(
            "⚠️ Opção inválida.", reply_markup=_kb_location()
        )
        return ST_LOCATION

    sess.location = val
    sess.source   = "manual"

    from src.infer import InferenceResult
    ir = InferenceResult(period=sess.period, location=sess.location, prk_seq=sess.prk_seq)
    sess.slug = ir.filename_slug(sess.date_local, lang=sess.lang)

    await update.message.reply_text(
        sess.to_summary(),
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=_kb_confirm(),
    )
    return ST_CONFIRM


# ──────────────────────────────────────────────
# /cancelar
# ──────────────────────────────────────────────

async def cmd_cancelar(update: Update, ctx: ContextTypes.DEFAULT_TYPE) -> int:
    if not _allowed(update):
        return ConversationHandler.END
    ctx.user_data.pop("session", None)
    await update.message.reply_text(
        "❌ Sessão cancelada. Use /novo para começar.",
        reply_markup=ReplyKeyboardRemove(),
    )
    return ConversationHandler.END


# ──────────────────────────────────────────────
# Entry point
# ──────────────────────────────────────────────

def main() -> None:
    app = Application.builder().token(TOKEN).build()

    conv = ConversationHandler(
        entry_points=[CommandHandler("novo", cmd_novo)],
        states={
            ST_TITLE:    [MessageHandler(filters.TEXT & ~filters.COMMAND, recv_title)],
            ST_DATE:     [MessageHandler(filters.TEXT & ~filters.COMMAND, recv_date)],
            ST_LANG:     [MessageHandler(filters.TEXT & ~filters.COMMAND, recv_lang)],
            ST_CONFIRM:  [CallbackQueryHandler(cb_confirm)],
            ST_PERIOD:   [MessageHandler(filters.TEXT & ~filters.COMMAND, recv_period_fix)],
            ST_LOCATION: [MessageHandler(filters.TEXT & ~filters.COMMAND, recv_location_fix)],
        },
        fallbacks=[CommandHandler("cancelar", cmd_cancelar)],
        allow_reentry=True,
    )

    app.add_handler(conv)
    app.add_handler(CommandHandler("ajuda", cmd_ajuda))

    log.info("Bot iniciado — aguardando mensagens...")
    app.run_polling(allowed_updates=Update.ALL_TYPES)


if __name__ == "__main__":
    main()
