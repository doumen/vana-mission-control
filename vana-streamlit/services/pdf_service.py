# services/pdf_service.py
# -*- coding: utf-8 -*-
from weasyprint import HTML, CSS
from jinja2 import Environment, FileSystemLoader
from datetime import datetime
import os


class PDFService:

    def __init__(self, templates_dir: str = "templates/revista"):
        self.jinja = Environment(
            loader = FileSystemLoader(templates_dir)
        )

    def generate(
        self,
        editorial: dict,
        visit:     dict,
        lang:      str,
        passages:  dict,
    ) -> bytes:
        template = self.jinja.get_template("revista_" + lang + ".html")
        html_str = template.render(
            editorial = editorial,
            visit     = visit,
            lang      = lang,
            passages  = passages,
            generated = datetime.utcnow().strftime("%d/%m/%Y"),
        )
        css_path = os.path.join(
            self.jinja.loader.searchpath[0],
            "style_" + lang + ".css"
        )
        stylesheets = [CSS(filename=css_path)] if os.path.exists(css_path) else []
        return HTML(string=html_str).write_pdf(stylesheets=stylesheets)
