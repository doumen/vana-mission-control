# 1. Estrutura
mkdir -p vana-crud/api vana-crud/pages vana-crud/components vana-crud/.streamlit
cd vana-crud

# 2. Crie os arquivos acima em cada pasta
touch api/__init__.py

# 3. Instale dependências
pip install -r requirements.txt

# 4. Configure secrets
# edite .streamlit/secrets.toml com seus valores reais

# 5. Rode
streamlit run app.py
