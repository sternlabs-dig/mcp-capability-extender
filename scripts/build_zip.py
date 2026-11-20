"""Build an installable WordPress ZIP without development files or site data."""
from pathlib import Path
import re
import zipfile

ROOT = Path(__file__).resolve().parent.parent
FOLDER = 'sternlabs-commerce-seo'
ENTRY = 'sternlabs-commerce-seo.php'

def collect(root):
    root = Path(root)
    header = (root / ENTRY).read_text(encoding='utf-8')
    readme = (root / 'readme.txt').read_text(encoding='utf-8')
    version = re.search(r'^\s*\* Version:\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?)\s*$', header, re.M)
    constant = re.search(r"define\('SCI_VERSION', '([^']+)'\)", header)
    stable = re.search(r'^Stable tag:\s*(\S+)\s*$', readme, re.M)
    if not version or not constant or not stable or len({version[1], constant[1], stable[1]}) != 1:
        raise ValueError('Plugin header, SCI_VERSION and readme stable tag must match.')
    required = [ENTRY, 'uninstall.php', 'readme.txt', 'LICENSE', 'assets/LICENSE.txt', 'external-services.txt']
    required += ['includes/class-' + module + '.php' for module in ['security', 'privacy', 'core', 'catalog', 'reports', 'seo', 'google', 'admin']]
    required += ['includes/dashboard.php', 'assets/dashboard.css', 'assets/dashboard.js', 'assets/sternlabs-logo.png', 'languages/sternlabs-commerce-seo.pot']
    paths = required
    files = []
    for name in paths:
        path = root / name
        if not path.is_file() or path.is_symlink() or root.resolve() not in path.resolve().parents:
            raise ValueError('Required release file is missing, linked or outside the source tree: ' + name)
        if path.suffix in ['.php', '.js', '.css']:
            content = path.read_text(encoding='utf-8')
            if re.search(r'-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----|Authorization\s*[:=]\s*[\x22\x27]?Basic\s+[A-Za-z0-9+/]{16,}', content, re.I):
                raise ValueError('Possible credential found in a release source file: ' + name)
        files.append((path, FOLDER + '/' + name))
    return version[1], files

def build(root=ROOT, output=None):
    version, files = collect(root)
    target = Path(output) if output is not None else Path(root) / 'dist' / ('mcp-capability-extender-' + version + '.zip')
    target.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_DEFLATED) as archive:
        for source, member in sorted(files, key=lambda item: item[1]):
            info = zipfile.ZipInfo(member, date_time=(2026, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, source.read_bytes())
    with zipfile.ZipFile(target) as archive:
        if archive.testzip() is not None:
            raise ValueError('ZIP integrity failure.')
        for source, member in files:
            if archive.read(member) != source.read_bytes():
                raise ValueError('ZIP content mismatch: ' + member)
    return target

if __name__ == '__main__':
    print(build())
