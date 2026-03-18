from pathlib import Path
lines = Path('modules/assets.php').read_text(encoding='utf-8').splitlines()
for idx, line in enumerate(lines):
    if 'placeholder=' in line or 'label for=\"create_asset_name\"' in line:
        print(idx+1, line.encode('unicode_escape'))
