# services/r2_service.py
# -*- coding: utf-8 -*-
"""
R2Service — Vana Mission Control
Upload de assets para Cloudflare R2 (S3-compatível).
"""
import io
import hashlib
from datetime import datetime, timezone
from typing import Optional, Tuple

import boto3
import requests
from botocore.client import Config as BotoConfig


class R2Service:

    def __init__(
        self,
        endpoint:    str,
        access_key:  str,
        secret_key:  str,
        bucket:      str,
        public_base: str,
    ):
        self.client = boto3.client(
            "s3",
            endpoint_url          = endpoint,
            aws_access_key_id     = access_key,
            aws_secret_access_key = secret_key,
            config                = BotoConfig(signature_version="s3v4"),
        )
        self.bucket      = bucket
        self.public_base = public_base.rstrip("/")

    # ══════════════════════════════════════════════════════════════
    # REVISTAS (existente)
    # ══════════════════════════════════════════════════════════════

    def upload_pdf(self, visit_ref: str, lang: str, pdf_bytes: bytes) -> str:
        key = "revistas/" + visit_ref + "/" + lang + ".pdf"
        self.client.put_object(
            Bucket       = self.bucket,
            Key          = key,
            Body         = pdf_bytes,
            ContentType  = "application/pdf",
            CacheControl = "public, max-age=31536000",
        )
        return self.public_base + "/" + key

    def upload_cover(
        self,
        visit_ref:    str,
        img_bytes:    bytes,
        content_type: str = "image/jpeg",
    ) -> str:
        key = "revistas/" + visit_ref + "/cover.jpg"
        self.client.put_object(
            Bucket       = self.bucket,
            Key          = key,
            Body         = img_bytes,
            ContentType  = content_type,
            CacheControl = "public, max-age=31536000",
        )
        return self.public_base + "/" + key

    # ══════════════════════════════════════════════════════════════
    # GALERIA — fotos de visitas
    # ══════════════════════════════════════════════════════════════

    def upload_photo(
        self,
        visit_ref:  str,
        day_key:    str,
        img_bytes:  bytes,
        content_type: str = "image/webp",
        filename_hint: str = "",
    ) -> dict:
        """
        Upload de foto para a galeria da visita no R2.

        Estrutura no bucket:
            galeria/{visit_ref}/{day_key}/{hash8}-{ts6}.webp

        Returns dict with url, r2_key, size, hash, uploaded_at
        """
        content_hash = hashlib.sha256(img_bytes).hexdigest()[:8]
        ts = datetime.now(timezone.utc).strftime("%H%M%S")

        ext_map = {
            "image/webp": "webp",
            "image/jpeg": "jpg",
            "image/jpg":  "jpg",
            "image/png":  "png",
            "image/avif": "avif",
        }
        ext = ext_map.get(content_type, "webp")

        key = f"galeria/{visit_ref}/{day_key}/{content_hash}-{ts}.{ext}"

        self.client.put_object(
            Bucket       = self.bucket,
            Key          = key,
            Body         = img_bytes,
            ContentType  = content_type,
            CacheControl = "public, max-age=31536000, immutable",
        )

        return {
            "url":         self.public_base + "/" + key,
            "r2_key":      key,
            "size":        len(img_bytes),
            "hash":        content_hash,
            "uploaded_at": datetime.now(timezone.utc).isoformat(),
        }

    def upload_photo_from_url(
        self,
        source_url: str,
        visit_ref:  str,
        day_key:    str,
        convert_webp: bool = True,
        max_size_mb: float = 10.0,
    ) -> dict:
        resp = requests.get(source_url, timeout=30, stream=True)
        resp.raise_for_status()

        content_length = int(resp.headers.get("content-length", 0))
        if content_length > max_size_mb * 1024 * 1024:
            raise ValueError(
                f"Imagem muito grande: {content_length / 1024 / 1024:.1f}MB "
                f"(máx {max_size_mb}MB)"
            )

        img_bytes = resp.content
        if len(img_bytes) > max_size_mb * 1024 * 1024:
            raise ValueError(
                f"Imagem muito grande: {len(img_bytes) / 1024 / 1024:.1f}MB"
            )

        original_ct = resp.headers.get("content-type", "image/jpeg").split(";")[0].strip()

        if convert_webp and original_ct != "image/webp":
            img_bytes, final_ct = self._convert_to_webp(img_bytes)
        else:
            final_ct = original_ct

        result = self.upload_photo(
            visit_ref=visit_ref,
            day_key=day_key,
            img_bytes=img_bytes,
            content_type=final_ct,
        )

        result["source_url"] = source_url
        result["original_content_type"] = original_ct
        return result

    def delete_photo(self, r2_key: str) -> bool:
        self.client.delete_object(
            Bucket=self.bucket,
            Key=r2_key,
        )
        return True

    def list_photos(
        self,
        visit_ref: str,
        day_key: str = "",
    ) -> list[dict]:
        prefix = f"galeria/{visit_ref}/"
        if day_key:
            prefix += f"{day_key}/"

        resp = self.client.list_objects_v2(
            Bucket=self.bucket,
            Prefix=prefix,
        )

        results = []
        for obj in resp.get("Contents", []):
            results.append({
                "r2_key":        obj["Key"],
                "url":           self.public_base + "/" + obj["Key"],
                "size":          obj["Size"],
                "last_modified": obj["LastModified"].isoformat()
                                 if hasattr(obj["LastModified"], "isoformat")
                                 else str(obj["LastModified"]),
            })

        return results

    # ══════════════════════════════════════════════════════════════
    # HELPERS INTERNOS
    # ══════════════════════════════════════════════════════════════

    @staticmethod
    def _convert_to_webp(
        img_bytes: bytes,
        quality: int = 82,
        max_dimension: int = 2400,
    ) -> Tuple[bytes, str]:
        try:
            from PIL import Image, ImageOps
        except ImportError:
            raise ImportError(
                "Pillow é necessário para conversão WebP. Instale com: pip install Pillow"
            )

        img = Image.open(io.BytesIO(img_bytes))

        if img.mode == "RGBA":
            background = Image.new("RGB", img.size, (255, 255, 255))
            background.paste(img, mask=img.split()[3])
            img = background
        elif img.mode not in ("RGB", "L"):
            img = img.convert("RGB")

        try:
            img = ImageOps.exif_transpose(img)
        except Exception:
            pass

        if max(img.size) > max_dimension:
            img.thumbnail((max_dimension, max_dimension), Image.LANCZOS)

        buffer = io.BytesIO()
        img.save(buffer, format="WEBP", quality=quality, method=4)
        webp_bytes = buffer.getvalue()

        return webp_bytes, "image/webp"

    def r2_key_from_url(self, url: str) -> str:
        prefix = self.public_base + "/"
        if url.startswith(prefix):
            return url[len(prefix):]
        return ""
