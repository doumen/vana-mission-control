Use `streamlit run` em vez de executar `app.py` diretamente — o Streamlit precisa do seu runner para funcionar (session_state, UI, etc).

Passos rápidos (PowerShell):

1) Instale dependências (a partir da pasta do projeto):
```powershell
cd C:\Users\marce\Desktop\vanamadhuryamdaily\vana-mission-control-final\vana-streamlit
python -m pip install -r requirements.txt
```

2) Crie `secrets` (opcional mas necessário para `st.secrets` usado no app):
- Crie a pasta `.streamlit` e o arquivo `.streamlit/secrets.toml` com algo assim:
```toml
[vana]
api_base = "https://vanamadhuryam.com"
ingest_secret = "SUA_CHAVE_AQUI"
tour_key = "tour:india-2026"
```

3) Inicie o app:
```powershell
streamlit run app.py
```
(ou, a partir da raiz do repo)
```powershell
streamlit run vana-streamlit/app.py
```

Opções úteis:
- Porta custom: `streamlit run app.py --server.port 8502`
- Acesso remoto (dev): `--server.address 0.0.0.0`

Observação: os warnings que você viu aparecem quando roda `py app.py` — ignore-os e use `streamlit run` para evitar perda de funcionalidades (ex.: `session_state`). Quer que eu gere um `.streamlit/secrets.toml` de exemplo para você?