#!/usr/bin/env python3
"""Genera el PDF del manual de implementación + matriz de cumplimiento.
Markdown -> HTML (con CSS de impresión) -> PDF (Playwright/Chromium)."""
import pathlib, subprocess, sys, markdown

ROOT = pathlib.Path(__file__).resolve().parent.parent
DOCS = ROOT / "docs"
OUT_HTML = ROOT / "docs" / "_manual.html"
OUT_PDF = ROOT / "docs" / "Manual_Implementacion_PnkSecurity.pdf"

md = markdown.Markdown(extensions=["fenced_code", "tables", "toc", "sane_lists"])

parts = []
for name in ["MANUAL_IMPLEMENTACION.md", "MATRIZ_CUMPLIMIENTO.md"]:
    md.reset()
    parts.append(md.convert((DOCS / name).read_text(encoding="utf-8")))
body = '\n<div class="page-break"></div>\n'.join(parts)

CSS = """
@page { size: A4; margin: 18mm 16mm 20mm 16mm; }
* { box-sizing: border-box; }
body { font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  font-size: 10.5pt; line-height: 1.5; color: #1a1a1a; }
h1 { font-size: 20pt; color: #0b2e4f; border-bottom: 3px solid #e5308a; padding-bottom: 6px;
  margin-top: 26px; }
h2 { font-size: 14pt; color: #0b2e4f; border-bottom: 1px solid #d0d7de; padding-bottom: 3px;
  margin-top: 22px; page-break-after: avoid; }
h3 { font-size: 11.5pt; color: #333; margin-top: 16px; page-break-after: avoid; }
p, li { text-align: justify; }
code { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace;
  font-size: 9pt; background: #f3f4f6; padding: 1px 4px; border-radius: 3px; }
pre { background: #0b2e4f; color: #eaf2f8; padding: 10px 12px; border-radius: 6px;
  overflow-x: auto; font-size: 8.6pt; line-height: 1.4; page-break-inside: avoid; }
pre code { background: transparent; color: inherit; padding: 0; font-size: 8.6pt; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 8.8pt;
  page-break-inside: avoid; }
th, td { border: 1px solid #c9d1d9; padding: 5px 7px; text-align: left; vertical-align: top; }
th { background: #0b2e4f; color: #fff; }
tr:nth-child(even) td { background: #f6f8fa; }
.page-break { page-break-before: always; }
a { color: #0b66c3; text-decoration: none; }
"""

html = f"""<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Manual de Implementacion - PnkSecurity</title><style>{CSS}</style></head>
<body>{body}</body></html>"""
OUT_HTML.write_text(html, encoding="utf-8")

render = f"""
const {{ chromium }} = require('playwright');
(async () => {{
  const b = await chromium.launch();
  const p = await b.newPage();
  await p.goto('file://{OUT_HTML}', {{ waitUntil: 'networkidle' }});
  await p.pdf({{
    path: '{OUT_PDF}', format: 'A4', printBackground: true,
    margin: {{ top: '0', bottom: '0', left: '0', right: '0' }},
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: '<div style="width:100%;font-size:8px;color:#888;padding:0 16mm;text-align:right;">PnkSecurity · Carta Digital — Página <span class="pageNumber"></span>/<span class="totalPages"></span></div>'
  }});
  await b.close();
}})();
"""
js = ROOT / "scripts" / "_render.js"
js.write_text(render, encoding="utf-8")
subprocess.run(["node", str(js)], check=True)
js.unlink(missing_ok=True)
OUT_HTML.unlink(missing_ok=True)
print("PDF:", OUT_PDF, OUT_PDF.stat().st_size, "bytes")
