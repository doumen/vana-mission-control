import streamlit as st
from datetime import datetime, timedelta

def render_days_editor(visits: list) -> list:
    """
    Renderiza editor de dias/visitas.
    Recebe lista de visits e retorna lista atualizada.
    """
    st.subheader("📅 Editor de Visitas")

    if not visits:
        st.info("Nenhuma visita registrada ainda.")
        return visits

    updated_visits = []

    for i, visit in enumerate(visits):
        with st.expander(f"Visita {i+1} — {visit.get('date', 'sem data')}"):
            col1, col2 = st.columns(2)

            with col1:
                date_val = visit.get("date", str(datetime.today().date()))
                try:
                    date_obj = datetime.strptime(date_val, "%Y-%m-%d").date()
                except:
                    date_obj = datetime.today().date()

                new_date = st.date_input(
                    "Data",
                    value=date_obj,
                    key=f"date_{i}"
                )

            with col2:
                new_title = st.text_input(
                    "Título",
                    value=visit.get("title", ""),
                    key=f"title_{i}"
                )

            new_notes = st.text_area(
                "Notas",
                value=visit.get("notes", ""),
                key=f"notes_{i}"
            )

            updated_visits.append({
                **visit,
                "date":  str(new_date),
                "title": new_title,
                "notes": new_notes
            })

    return updated_visits
