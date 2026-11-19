"""Assemble the isolated PHP regression harness for a disposable WP/WooCommerce test site."""
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
NAMESPACE = 'SternLabs\\CapabilityExtender030QA'
parts = []
for name in ['security', 'core', 'catalog', 'reports', 'seo', 'google', 'admin']:
    text = (ROOT / 'includes' / ('class-' + name + '.php')).read_text(encoding='utf-8').removeprefix('<?php')
    if name == 'seo':
        start = text.index('    public static function provider()')
        end = text.index('\n', start)
        text = text[:start] + "    public static function provider() { return $GLOBALS['ce_provider'] ?? 'yoast'; }" + text[end:]
    parts.append(text)
fixtures = (ROOT / 'tests/qa-fixtures.php').read_text(encoding='utf-8').removeprefix('<?php')
fixtures = fixtures.replace("function current_user_can($cap, ...$args) { return $GLOBALS['sci_test_caps']; }", "function current_user_can($cap, ...$args) { return $GLOBALS['sci_test_caps'] && empty($GLOBALS['ce_denied_caps'][$cap]) && ($cap !== 'edit_post' || empty($GLOBALS['ce_denied_posts'][$args[0] ?? 0])); }")
fixtures = fixtures.replace('function wc_get_product($id) { return null; }', "function wc_get_product($id) { return isset($GLOBALS['ce_products'][$id]) ? clone $GLOBALS['ce_products'][$id] : null; }")
fixtures = fixtures.replace("echo 'SCI_QA_PASSED assertions=' . $GLOBALS['sci_test_count'];", '')
parts.append(fixtures)
parts.append((ROOT / 'tests/qa-extender-fixtures.php').read_text(encoding='utf-8').removeprefix('<?php'))
security = (ROOT / 'tests/qa-security030.php').read_text(encoding='utf-8').removeprefix('<?php')
security = security.replace("throw new \\RuntimeException('CE030_QA_PASSED assertions=' . $GLOBALS['sci_test_count']);", "echo '\\nCE030_QA_PASSED assertions=' . $GLOBALS['sci_test_count'] . PHP_EOL;")
parts.append(security)
code = ('<?php\n' + '\n'.join(parts)).replace('namespace SternLabs\\CommerceSEO;', 'namespace ' + NAMESPACE + ';')
target = ROOT / 'build/regression.php'
target.parent.mkdir(exist_ok=True)
target.write_text(code, encoding='utf-8')
print(target)
