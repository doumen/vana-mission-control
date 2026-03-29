# listar_r2.py
import boto3
import toml
import os

# Lê o secrets.toml diretamente
secrets_path = os.path.join(".streamlit", "secrets.toml")
cfg = toml.load(secrets_path)["r2"]

s3 = boto3.client(
    "s3",
    endpoint_url          = cfg["endpoint"],
    aws_access_key_id     = cfg["access_key"],
    aws_secret_access_key = cfg["secret_key"],
)

print("=== Listando objetos no bucket ===\n")
resp = s3.list_objects_v2(Bucket=cfg["bucket"], Prefix="visits/")

contents = resp.get("Contents", [])
if not contents:
    print("⚠️  Nenhum arquivo encontrado com prefixo 'visits/'")
else:
    for obj in contents:
        print(obj["Key"])

print(f"\nTotal: {len(contents)} arquivo(s)")
