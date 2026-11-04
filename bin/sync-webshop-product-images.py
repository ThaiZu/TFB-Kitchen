"""Copy WebShop's existing local product photos, preserving bytes and ID mapping.
Run with the WebShop assets/product_pictures directory; no network or image edits.
"""
import hashlib
import json
import re
import shutil
import sys
from pathlib import Path

source = Path(sys.argv[1])
target = Path(__file__).resolve().parents[1] / 'public/assets/webshop-product-images'
target.mkdir(parents=True, exist_ok=True)
index = {}
for photo in sorted(source.iterdir()):
    match = re.fullmatch(r'([0-9]+)\.(jpe?g|png|webp|svg)', photo.name, re.IGNORECASE)
    if not match or match[1] in index:
        continue
    digest = hashlib.sha256(photo.read_bytes()).hexdigest()[:12]
    filename = f'{match[1]}.{digest}.{match[2].lower()}'
    shutil.copyfile(photo, target / filename)
    index[match[1]] = filename
(target / 'index.json').write_text(json.dumps(index, indent=2) + '\n')
print(f'Synced {len(index)} product photos from WebShop.')
