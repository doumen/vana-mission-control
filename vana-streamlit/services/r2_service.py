# services/r2_service.py
# -*- coding: utf-8 -*-
import boto3
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
