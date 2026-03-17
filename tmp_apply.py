from pathlib import Path
path = Path('modules/Knowledgebase.php')
text = path.read_text()
marker = '?>'
idx = text.find(marker)
if idx == -1:
    raise SystemExit('Unable to find PHP closing tag')
prefix = text[:idx+2]
layout = Path('tmp_layout.txt').read_text()
path.write_text(prefix + layout)
