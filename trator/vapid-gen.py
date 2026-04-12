from py_vapid import Vapid
from cryptography.hazmat.primitives import serialization # Added this

vapid = Vapid()
vapid.generate_keys()

# Salva em arquivos
vapid.save_key('vapid_private.pem')
vapid.save_public_key('vapid_public.pem')

# Imprime em base64url direto
print("Public:", vapid.public_key.public_bytes(
    encoding=serialization.Encoding.X962,
    format=serialization.PublicFormat.UncompressedPoint
).hex())