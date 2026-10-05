"""Package only WordPress runtime files; Python 3.9+, no extra dependencies."""
import argparse
import hashlib
from pathlib import Path
import re
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
FILES = [
    'b2b-matrix-api.php', 'uninstall.php', 'LICENSE', 'README.md',
    'includes/class-b2b-matrix-db.php', 'includes/class-b2b-matrix-settings.php',
    'includes/class-b2b-matrix-catalog.php', 'includes/class-b2b-order-channel.php',
    'includes/class-b2b-preorder-list.php', 'includes/class-b2b-preorder-submission.php',
    'assets/admin-settings.css', 'b2b-matrix/dist/matrix-app.js',
    'b2b-matrix/dist/matrix-style.css',
    'licenses/vue-MIT.txt', 'licenses/pinia-MIT.txt',
]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, help='ZIP output path; defaults to releases/')
    parser.add_argument('--force', action='store_true', help='Replace an existing ZIP')
    args = parser.parse_args()
    header = (ROOT / FILES[0]).read_text(encoding='utf-8')
    version = re.search(r'^ \* Version: (\d+\.\d+\.\d+)$', header, re.M).group(1)
    output = args.output or ROOT / 'releases' / f'b2b-preorder-matrix-{version}.zip'
    if output.exists() and not args.force:
        parser.error(f'{output} already exists; use --force to replace it')
    # Load all inputs first: a missing file must not create a partial release.
    entries = {}
    for name in FILES:
        source = ROOT / name
        if source.is_symlink() or not source.is_file():
            raise ValueError(f'Missing or unsafe runtime file: {source}')
        entries[f'b2b-preorder-matrix/{name}'] = source.read_bytes()
    output.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output, 'w', compression=ZIP_DEFLATED, compresslevel=9) as archive:
        for name, data in entries.items():
            entry = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            entry.compress_type = ZIP_DEFLATED
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, data)
    with ZipFile(output) as archive:
        assert set(archive.namelist()) == set(entries)
        assert archive.testzip() is None
        for name, data in entries.items():
            assert archive.read(name) == data, name
    print(f'{output}: {len(entries)} verified runtime files')
    print(f'SHA256 {hashlib.sha256(output.read_bytes()).hexdigest()}')


if __name__ == '__main__':
    main()
