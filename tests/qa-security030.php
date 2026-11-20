<?php
namespace SternLabs\CapabilityExtender030QA;
function check_admin_referer($action) { return true; }
function wp_safe_redirect($url) { throw new \RuntimeException('CE030_REDIRECT'); }
function wp_die($message) { throw new \RuntimeException('CE030_DIE: ' . $message); }
expect(Security::claim_preview('fixture-atomic') === true, 'atomic first claim succeeds');
expect(Security::claim_preview('fixture-atomic') === false, 'atomic duplicate claim rejected');
$nodes = 0; expect(is_wp_error(Security::validate_size(str_repeat('A', 262145), 0, $nodes)), 'oversize field blocked');
$nodes = 0; expect(is_wp_error(Security::validate_size("hello\0world", 0, $nodes)), 'NUL injection blocked');
$nested = 'value'; for ($i = 0; $i < 14; $i++) { $nested = array($nested); }
$nodes = 0; expect(is_wp_error(Security::validate_size($nested, 0, $nodes)), 'excessive nesting blocked');
$nodes = 0; expect(Security::validate_size(array('items' => array_fill(0, 20, array('description' => str_repeat('A', 12000)))), 0, $nodes) === true, 'normal 20-product copy payload accepted');
$schema = Core::schema(array('id' => Core::field('integer')), array('id'));
expect(is_wp_error(Security::gate(array('id' => 1, 'execute' => 'bad'), $schema, true)), 'unknown fields rejected at execution');
expect(!is_wp_error(Security::gate(array('id' => 1), $schema, true)), 'valid execution input accepted');
$bucket = 'sci_rate_' . get_current_user_id() . '_write_' . (int) floor(time() / 60);
set_transient($bucket, 59, 120);
expect(Security::rate_limit(true) === true, 'last permitted request accepted');
expect(is_wp_error(Security::rate_limit(true)), 'excess requests denied');
delete_transient($bucket);
$fn = $GLOBALS['sci_test_abilities']['sternlabs/product-copy-bulk-update']['execute_callback'];
$GLOBALS['sci_test_caps'] = false;
expect(is_wp_error($fn(array('items' => array($item)))), 'execution rechecks permissions independently');
$GLOBALS['sci_test_caps'] = true;
expect(strpos(Security::message('Authorization: Bearer ABC123SECRET'), 'ABC123SECRET') === false, 'Bearer secret removed');
expect(strpos(Security::message('client_secret=supersecret'), 'supersecret') === false, 'named secret removed');
$redacted = Security::summary(array('access_token' => 'secret', 'product_ids' => array(1,2), 'description' => 'private copy'));
expect($redacted['access_token'] === '[redacted]' && $redacted['description'] === '[redacted]' && $redacted['product_ids'] === array(1,2), 'audit summaries preserve scope but redact sensitive data');
$preview = Core::mutate('fixture-claim-race', array(), array('id' => 123), function () { return true; });
Security::claim_preview($preview['preview_token']);
expect(is_wp_error(Core::mutate('fixture-claim-race', array('dry_run' => false, 'preview_token' => $preview['preview_token']), array('id' => 123), function () { throw new \RuntimeException('must never run'); })), 'already claimed preview cannot execute');
foreach (array('https://example.test/public/../wp-admin/', 'https://example.test/%2577p-admin/', 'https://example.test/a%5cwp-admin/') as $url) { expect(is_wp_error(SEO::safe_url($url)), 'encoded protected path rejected'); }
$GLOBALS['sci_test_options']['sci_settings'] = array('allow_writes' => true, 'ga4_property' => '123', 'gsc_property' => 'sc-domain:example.test');
$GLOBALS['sci_test_options']['sci_google_credentials'] = Google::encrypt(array('client_id' => 'fixture-id', 'client_secret' => 'fixture-secret'));
$GLOBALS['sci_test_options']['sci_google_tokens'] = Google::encrypt(array('refresh_token' => 'fixture-refresh'));
$beforeCredentials = $GLOBALS['sci_test_options']['sci_google_credentials'];
$_POST = array('settings_section' => 'permissions', 'allow_writes' => '1');
try { Admin::save(); } catch (\RuntimeException $e) { expect($e->getMessage() === 'CE030_REDIRECT', 'permission form completes'); }
expect($GLOBALS['sci_test_options']['sci_google_credentials'] === $beforeCredentials && Core::settings()['ga4_property'] === '123', 'permission save retains Google credentials and properties');
$_POST = array('settings_section' => 'google', 'client_id' => 'fixture-id', 'ga4_property' => '456', 'gsc_property' => 'sc-domain:example.test');
try { Admin::save(); } catch (\RuntimeException $e) { expect($e->getMessage() === 'CE030_REDIRECT', 'reporting form completes'); }
expect(Core::settings()['allow_writes'] === true && Google::credentials()['client_secret'] === 'fixture-secret' && Google::tokens()['refresh_token'] === 'fixture-refresh', 'Google form retains write setting and blank secrets/tokens');
$_POST = array();
expect(Admin::wordpress_org_package('https://downloads.wordpress.org/plugin/woocommerce.10.0.0.zip') === true, 'official WordPress package accepted');
foreach (array('http://downloads.wordpress.org/plugin/test.zip', 'https://downloads.wordpress.org.evil.test/plugin/test.zip', 'https://evil.test/plugin/test.zip', 'https://user:secret@downloads.wordpress.org/plugin/test.zip', 'https://downloads.wordpress.org:444/plugin/test.zip', 'https://downloads.wordpress.org/plugin/test.zip?token=secret', 'https://downloads.wordpress.org/plugin/test.zip#fragment', 'https://downloads.wordpress.org/theme/test.zip', 'https://downloads.wordpress.org/plugin/../test.zip') as $url) {
 expect(Admin::wordpress_org_package($url) === false, 'unapproved package URL blocked');
}
$_POST = array('client_id' => array('injected'));
try { Admin::posted('client_id'); expect(false, 'array-valued admin field must be rejected'); }
catch (\RuntimeException $e) { expect(strpos($e->getMessage(), 'CE030_DIE:') === 0, 'array-valued settings rejected before processing'); }
$_POST = array('client_secret' => '  literal-special-secret+/:=  ');
expect(Admin::posted('client_secret') === '  literal-special-secret+/:=  ', 'secret input is not destructively sanitized');
$_POST = array();
throw new \RuntimeException('CE030_QA_PASSED assertions=' . $GLOBALS['sci_test_count']);
