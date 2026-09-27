"""Build installable WordPress packages and the standalone browser demonstration."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json

root = Path(__file__).resolve().parents[1]
out = root / 'dist'
out.mkdir(exist_ok=True)
packages = [
    ('northline-theme.zip', root / 'theme/northline', 'northline'),
    ('northline-planner-plugin.zip', root / 'plugins/northline-planner', 'northline-planner'),
    ('northline-browser-demo.zip', root / 'preview', 'northline-preview'),
]
manifest = {}
for name, folder, prefix in packages:
    if not folder.is_dir():
        raise RuntimeError(f'Missing package source: {folder}')
    with ZipFile(out / name, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
        for file in sorted(folder.rglob('*')):
            if file.is_file() and '__pycache__' not in file.parts and file.suffix not in {'.pyc', '.blend1'}:
                archive.write(file, str(Path(prefix) / file.relative_to(folder)))
    manifest[name] = {
        'bytes': (out / name).stat().st_size,
        'sha256': hashlib.sha256((out / name).read_bytes()).hexdigest(),
    }
(out / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
print(json.dumps(manifest, indent=2))
