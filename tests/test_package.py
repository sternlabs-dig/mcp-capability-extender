from pathlib import Path
import importlib.util
import shutil
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('builder', ROOT / 'scripts/build_zip.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)

class ReleasePackagingTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name) / 'source'
        self.root.mkdir()
        for path, member in builder.collect(ROOT)[1]:
            dest = self.root / member.removeprefix(builder.FOLDER + '/')
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(path, dest)

    def tearDown(self):
        self.temp.cleanup()

    def test_installable_folder_and_exact_content(self):
        with zipfile.ZipFile(builder.build(self.root)) as archive:
            self.assertIn(builder.FOLDER + '/' + builder.ENTRY, archive.namelist())
            for source, member in builder.collect(self.root)[1]:
                self.assertEqual(archive.read(member), source.read_bytes())

    def test_development_and_site_data_are_excluded(self):
        for name in ['.env', 'wp-config.php', 'customers.sql', 'tests/leak.txt', '.github/workflows/deploy.yml']:
            dest = self.root / name
            dest.parent.mkdir(parents=True, exist_ok=True)
            dest.write_text('do not ship')
        with zipfile.ZipFile(builder.build(self.root)) as archive:
            members = archive.namelist()
            self.assertFalse(any(any(v in name for v in ['.env', 'wp-config', '.sql', '/tests/', '/.github/']) for name in members))

    def test_inconsistent_release_versions_fail(self):
        path = self.root / 'readme.txt'
        version = builder.collect(self.root)[0]
        path.write_text(path.read_text().replace('Stable tag: ' + version, 'Stable tag: 99.0.0'))
        with self.assertRaises(ValueError):
            builder.build(self.root)

    def test_missing_license_fails(self):
        (self.root / 'LICENSE').unlink()
        with self.assertRaises(ValueError):
            builder.build(self.root)

    def test_private_key_in_source_fails(self):
        with (self.root / 'assets/dashboard.js').open('a') as file:
            file.write('\n// -----BEGIN PRIVATE KEY-----')
        with self.assertRaises(ValueError):
            builder.build(self.root)

    def test_archives_are_reproducible(self):
        first = builder.build(self.root, Path(self.temp.name) / 'first.zip').read_bytes()
        second = builder.build(self.root, Path(self.temp.name) / 'second.zip').read_bytes()
        self.assertEqual(first, second)

if __name__ == '__main__':
    unittest.main()
