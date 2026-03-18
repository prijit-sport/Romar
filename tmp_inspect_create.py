from pathlib import Path
text = Path('modules/assets.php').read_text(encoding='utf-8')
start = text.index('<button class="btn btn-primary" onclick="openCreateModal()">')
end = text.index('<?php endif; ?>', start)
print(text[start:end])
