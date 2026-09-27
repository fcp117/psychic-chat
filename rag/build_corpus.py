"""One-time build helper. Runtime retrieval is PHP-only."""
import json
from pathlib import Path
from pypdf import PdfReader

root = Path(__file__).resolve().parent
chunks = []
for pdf in (root / 'Corpus').glob('*.pdf'):
    for page_no, page in enumerate(PdfReader(str(pdf)).pages, start=1):
        text = (page.extract_text() or '').replace('\x00', ' ').strip()
        for offset in range(0, len(text), 1100):
            content = text[offset:offset + 1300].strip()
            if len(content) >= 100:
                chunks.append({'source': pdf.stem, 'page': page_no, 'content': content})
(root / 'corpus.json').write_text(json.dumps(chunks, ensure_ascii=False), encoding='utf-8')
print(f'Built {len(chunks)} corpus passages.')
