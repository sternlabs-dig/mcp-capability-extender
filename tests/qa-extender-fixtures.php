<?php
namespace SternLabs\CommerceSEO;

// All persistence below is in-memory and namespace-local. No live WordPress data is written.
$GLOBALS['ce_products'] = array(); $GLOBALS['ce_meta'] = array(); $GLOBALS['ce_saves'] = 0;
$GLOBALS['ce_builds'] = array(); $GLOBALS['ce_provider'] = 'yoast';
class CEProduct {
    public $data;
    public function __construct($id, $type = 'simple', $status = 'publish') {
        $this->data = array('id' => $id, 'parent_id' => 0, 'type' => $type, 'name' => 'Main title ' . $id, 'status' => $status,
            'sku' => 'CE-' . $id, 'description' => 'Old description', 'short_description' => 'Old short', 'price' => '100', 'regular_price' => '100', 'sale_price' => '',
            'date_on_sale_from' => null, 'date_on_sale_to' => null, 'date_modified' => null, 'manage_stock' => true, 'stock_quantity' => 4, 'stock_status' => 'instock', 'backorders' => 'no',
            'category_ids' => array(), 'attributes' => array(), 'default_attributes' => array());
    }
    public function __call($method, $args) {
        if ($method === 'get_attributes') { $GLOBALS['ce_attribute_reads'] = ($GLOBALS['ce_attribute_reads'] ?? 0) + 1; }
        if (strpos($method, 'get_') === 0) { return $this->data[substr($method, 4)] ?? null; }
        if (strpos($method, 'set_') === 0) { $this->data[substr($method, 4)] = $args[0]; return; }
        throw new \RuntimeException('Unknown fixture method ' . $method);
    }
    public function is_type($type) { return in_array($this->data['type'], (array) $type, true); }
    public function get_permalink() { return 'https://example.test/product/' . $this->data['id'] . '/'; }
    public function save() {
        $id = $this->data['id']; $GLOBALS['ce_saves']++;
        if (($GLOBALS['ce_throw_save'] ?? 0) === $id) { throw new \RuntimeException('Simulated save failure'); }
        if (($GLOBALS['ce_drop_save'] ?? 0) !== $id) { $GLOBALS['ce_products'][$id] = clone $this; }
        return $id;
    }
}
function get_post($id) { return isset($GLOBALS['ce_products'][$id]) ? (object) array('ID' => $id) : null; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['ce_meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { if (($GLOBALS['ce_drop_meta'] ?? '') !== $key) { $GLOBALS['ce_meta'][$id][$key] = \wp_unslash($value); } return true; }
function clean_post_cache($id) {}
function wc_delete_product_transients($id) {}
function get_woocommerce_currency() { return 'NGN'; }
function wc_get_products($query) {
    $GLOBALS['ce_last_query'] = $query;
    $found = array_values(array_filter($GLOBALS['ce_products'], function ($p) use ($query) { return in_array($p->get_status(), (array) $query['status'], true); }));
    return (object) array('products' => array_map(function ($p) { return clone $p; }, $found), 'total' => count($found), 'max_num_pages' => 1);
}
function get_term($id, $taxonomy) { return $id === 138 ? (object) array('term_id' => 138, 'name' => 'Blazer', 'slug' => 'blazer') : false; }
class CEBuilder { public function build_for_id_and_type($id, $type) { $GLOBALS['ce_builds'][$id] = ($GLOBALS['ce_builds'][$id] ?? 0) + 1; } }
class CEContainer { public function get($class) { return new CEBuilder(); } }
function YoastSEO() { return (object) array('classes' => new CEContainer()); }

foreach (array(900001, 900002, 900003) as $id) { $GLOBALS['ce_products'][$id] = new CEProduct($id, 'simple', $id === 900002 ? 'draft' : 'publish'); }
$GLOBALS['ce_meta'][900001]['_yoast_wpseo_canonical'] = 'https://example.test/keep/';
$GLOBALS['ce_meta'][900001]['_yoast_wpseo_meta-robots-noindex'] = '1';
$bulk_schema = $GLOBALS['sci_test_abilities']['sternlabs/product-copy-bulk-update']['input_schema'];
$item = array('id' => 900001, 'description' => '<p>Fresh narrative.</p>', 'short_description' => '<ul><li>Fresh feature</li></ul>',
    'seo' => array('title' => 'Fresh SEO title', 'description' => 'Fresh meta description', 'focus_keyword' => 'fresh focus phrase'));
expect(!is_wp_error(\rest_validate_value_from_schema(array('items' => array($item)), $bulk_schema)), 'five-field copy schema accepted');
foreach (array('name' => 'Forbidden rename', 'sku' => 'CHANGE', 'status' => 'publish', 'regular_price' => '1', 'attributes' => array()) as $key => $value) {
    $invalid = $item; $invalid[$key] = $value;
    expect(is_wp_error(\rest_validate_value_from_schema(array('items' => array($invalid)), $bulk_schema)), 'copy schema blocks ' . $key);
    expect(is_wp_error(Catalog::copy_bulk(array('items' => array($invalid)))), 'copy callback independently blocks ' . $key);
}
$invalid = $item; $invalid['seo']['noindex'] = true;
expect(is_wp_error(\rest_validate_value_from_schema(array('items' => array($invalid)), $bulk_schema)), 'copy schema blocks indexing edits');
expect(is_wp_error(SEO::prepare_content(900001, array('noindex' => true))), 'SEO helper blocks unknown fields');
expect(is_wp_error(Catalog::copy_bulk(array('items' => array(array('id' => 900001))))), 'id-only copy rejected');
expect(is_wp_error(\rest_validate_value_from_schema(array('items' => array_fill(0, 21, $item)), $bulk_schema)), 'batch capped at twenty');

$preview = Catalog::copy_bulk(array('items' => array($item), 'return_saved_fields' => true));
expect(!is_wp_error($preview) && !$preview['applied'] && $GLOBALS['ce_saves'] === 0 && !isset($GLOBALS['ce_meta'][900001]['_yoast_wpseo_title']), 'combined preview performs no persistence');
expect(isset($preview['plan']['items'][0]['changes']['seo.title']) && isset($preview['plan']['items'][0]['changes']['description']), 'combined preview includes content and SEO');
$apply = array('items' => array($item), 'return_saved_fields' => true, 'dry_run' => false, 'preview_token' => $preview['preview_token']);
$saved = Catalog::copy_bulk($apply);
expect(!is_wp_error($saved) && $saved['result']['all_saved'] && $saved['result']['all_verified'], 'five fields saved and read back');
expect($GLOBALS['ce_saves'] === 1 && $GLOBALS['ce_builds'][900001] === 1, 'one product save and one indexable rebuild');
expect($GLOBALS['ce_products'][900001]->get_name() === 'Main title 900001' && $GLOBALS['ce_products'][900001]->get_stock_quantity() === 4, 'main title and inventory preserved');
expect($GLOBALS['ce_meta'][900001]['_yoast_wpseo_canonical'] === 'https://example.test/keep/' && $GLOBALS['ce_meta'][900001]['_yoast_wpseo_meta-robots-noindex'] === '1', 'canonical and indexing preserved');
expect($saved['result']['items'][0]['expected_hash'] === $saved['result']['items'][0]['saved_hash'], 'receipt matches preview digest');
$receipt = Catalog::bulk_result(array('preview_token' => $preview['preview_token']));
expect($receipt['complete'] && $receipt['all_verified'] && !isset($receipt['owner']), 'completed receipt recoverable without internal owner');
$GLOBALS['sci_test_user'] = 99;
expect(is_wp_error(Catalog::bulk_result(array('preview_token' => $preview['preview_token']))), 'receipt is user-bound');
$GLOBALS['sci_test_user'] = 42;
expect(is_wp_error(Catalog::copy_bulk($apply)), 'combined preview cannot be replayed');
$GLOBALS['ce_denied_posts'][900001] = true;
expect(is_wp_error(Catalog::bulk_result(array('preview_token' => $preview['preview_token']))), 'receipt respects current product permission');
unset($GLOBALS['ce_denied_posts'][900001]);
delete_transient(Catalog::receipt_key($preview['preview_token']));
expect(is_wp_error(Catalog::bulk_result(array('preview_token' => $preview['preview_token']))), 'expired receipt unavailable');

$preview = Catalog::copy_bulk(array('items' => array($item)));
$GLOBALS['ce_meta'][900001]['_yoast_wpseo_focuskw'] = 'Concurrent change';
expect(is_wp_error(Catalog::copy_bulk(array('items' => array($item), 'dry_run' => false, 'preview_token' => $preview['preview_token']))), 'stale SEO preview rejected');
$preview = Catalog::copy_bulk(array('items' => array($item)));
$GLOBALS['ce_products'][900001]->set_name('Concurrent title');
expect(is_wp_error(Catalog::copy_bulk(array('items' => array($item), 'dry_run' => false, 'preview_token' => $preview['preview_token']))), 'stale main-title preview rejected');
$GLOBALS['ce_products'][900001]->set_name('Main title 900001');
$calls = $GLOBALS['ce_saves'];
$GLOBALS['ce_denied_caps']['edit_others_posts'] = true;
expect(is_wp_error(Catalog::copy_bulk(array('items' => array($item)))) && $GLOBALS['ce_saves'] === $calls, 'bulk SEO does not weaken SEO role permissions');
unset($GLOBALS['ce_denied_caps']['edit_others_posts']);
$GLOBALS['ce_provider'] = 'none';
expect(is_wp_error(Catalog::copy_bulk(array('items' => array($item)))) && $GLOBALS['ce_saves'] === $calls, 'SEO unavailable fails before any product write');
$only = array('id' => 900002, 'short_description' => '<p>Only short copy.</p>');
$preview = Catalog::copy_bulk(array('items' => array($only)));
$saved = Catalog::copy_bulk(array('items' => array($only), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect($saved['result']['all_verified'] && $GLOBALS['ce_products'][900002]->get_status() === 'draft', 'content-only batch works without SEO plugin and keeps drafts');
$GLOBALS['ce_provider'] = 'yoast';
expect(is_wp_error(Catalog::copy_bulk(array('items' => array($item, $item)))), 'duplicate product IDs rejected');
$invalid = $item; $invalid['id'] = 999999;
$calls = $GLOBALS['ce_saves'];
expect(is_wp_error(Catalog::copy_bulk(array('items' => array($item, $invalid)))) && $GLOBALS['ce_saves'] === $calls, 'later invalid item prevents all writes');

$items = array(array('id' => 900001, 'short_description' => 'A'), array('id' => 900002, 'short_description' => 'B'), array('id' => 900003, 'short_description' => 'C'));
$GLOBALS['ce_throw_save'] = 900002;
$preview = Catalog::copy_bulk(array('items' => $items));
$saved = Catalog::copy_bulk(array('items' => $items, 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect(!$saved['result']['all_saved'] && $saved['result']['items'][0]['verified'] && !$saved['result']['items'][1]['saved'] && $saved['result']['items'][2]['verified'], 'partial failure reports exact outcome and continues independent items');
expect(get_option('sci_changes')[0]['result'] === 'partial_or_failed', 'partial failures recorded accurately');
$receipt = Catalog::bulk_result(array('preview_token' => $preview['preview_token']));
expect($receipt['complete'] && !$receipt['all_verified'], 'partial receipt survives response loss');
unset($GLOBALS['ce_throw_save']);
$GLOBALS['ce_drop_save'] = 900003;
$drop = array('id' => 900003, 'description' => 'Will not persist');
$preview = Catalog::copy_bulk(array('items' => array($drop)));
$saved = Catalog::copy_bulk(array('items' => array($drop), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect(!$saved['result']['all_verified'] && in_array('description', $saved['result']['items'][0]['mismatched_fields'], true), 'fresh read catches silently dropped content write');
unset($GLOBALS['ce_drop_save']);
$GLOBALS['ce_drop_meta'] = '_yoast_wpseo_title';
$drop = array('id' => 900003, 'seo' => array('title' => 'Will not persist'));
$preview = Catalog::copy_bulk(array('items' => array($drop)));
$saved = Catalog::copy_bulk(array('items' => array($drop), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect(!$saved['result']['all_verified'] && in_array('seo.title', $saved['result']['items'][0]['mismatched_fields'], true), 'fresh read catches silently dropped SEO write');
unset($GLOBALS['ce_drop_meta']);

$GLOBALS['ce_provider'] = 'rank_math';
$slash = array('id' => 900003, 'seo' => array('title' => 'A "quoted" title', 'description' => 'Path C:\\style\\suit', 'focus_keyword' => 'new phrase'));
$GLOBALS['ce_meta'][900003]['rank_math_robots'] = array('noindex', 'nofollow');
$preview = Catalog::copy_bulk(array('items' => array($slash)));
$saved = Catalog::copy_bulk(array('items' => array($slash), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect($saved['result']['all_verified'] && $GLOBALS['ce_meta'][900003]['rank_math_description'] === $slash['seo']['description'], 'Rank Math fields and literal backslashes preserved');
expect($GLOBALS['ce_meta'][900003]['rank_math_robots'] === array('noindex', 'nofollow'), 'Rank Math indexing unchanged');
$GLOBALS['ce_provider'] = 'yoast';

$GLOBALS['ce_products'][900004] = new CEProduct(900004, 'variation');
expect(is_wp_error(Catalog::copy_bulk(array('items' => array(array('id' => 900004, 'seo' => array('title' => 'variation')))))), 'variation SEO rejected');
$legacy = array('id' => 900001, 'sku' => 'UPDATED-SKU');
$preview = Catalog::bulk(array('items' => array($legacy)));
$saved = Catalog::bulk(array('items' => array($legacy), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect($saved['result']['all_verified'] && $GLOBALS['ce_products'][900001]->get_sku() === 'UPDATED-SKU', 'legacy inventory batch remains compatible');
$query = Catalog::products(array('category_id' => 138, 'status' => 'all', 'fields' => array('name', 'sku', 'status')));
expect(count($GLOBALS['ce_last_query']['status']) === 5 && $GLOBALS['ce_last_query']['category'] === array('blazer'), 'all statuses and known category ID in one query');
expect(isset($query['products'][0]['id']) && !isset($query['products'][0]['attributes']) && !isset($query['products'][0]['description']), 'compact query avoids unrequested payload');
$reads = $GLOBALS['ce_attribute_reads'];
Catalog::products(array('status' => 'all', 'fields' => array('name', 'type')));
expect($reads === $GLOBALS['ce_attribute_reads'], 'compact query skips attribute expansion entirely');
expect(is_wp_error(Catalog::products(array('status' => 'all', 'statuses' => array('draft')))), 'conflicting status filters rejected');
expect(is_wp_error(Catalog::products(array('category_id' => 138, 'category' => 'blazer'))), 'conflicting category filters rejected');
$query = Catalog::products(array('statuses' => array('draft'), 'fields' => array('name')));
expect($query['products'][0]['id'] === 900002 && $query['total'] === 1, 'explicit multi-status filter applied');
$GLOBALS['ce_denied_posts'][900002] = true;
$query = Catalog::products(array('status' => 'all', 'fields' => array('id')));
expect($query['permissions_filtered'] && !in_array(900002, array_column($query['products'], 'id'), true), 'broad status query respects per-product access');
unset($GLOBALS['ce_denied_posts'][900002]);

$clear = array('id' => 900003, 'description' => '', 'short_description' => '', 'seo' => array('title' => '', 'description' => '', 'focus_keyword' => ''));
$preview = Catalog::copy_bulk(array('items' => array($clear)));
$saved = Catalog::copy_bulk(array('items' => array($clear), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect($saved['result']['all_verified'] && $GLOBALS['ce_products'][900003]->get_description() === '' && $GLOBALS['ce_meta'][900003]['_yoast_wpseo_title'] === '', 'explicit empty values clear old copy');
$safe = array('id' => 900003, 'description' => '<p>Allowed</p><script>alert(1)</script>');
$preview = Catalog::copy_bulk(array('items' => array($safe)));
$saved = Catalog::copy_bulk(array('items' => array($safe), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect($saved['result']['all_verified'] && strpos($GLOBALS['ce_products'][900003]->get_description(), '<script') === false, 'HTML sanitized consistently in preview and persistence');
$preview = Catalog::copy_bulk(array('items' => array($item)));
expect(is_wp_error(Catalog::bulk(array('items' => array($item), 'dry_run' => false, 'preview_token' => $preview['preview_token']))), 'preview cannot cross copy/general endpoints');
$GLOBALS['ce_products'][900005] = new CEProduct(900005, 'variable');
$variable_copy = $item; $variable_copy['id'] = 900005;
$preview = Catalog::copy_bulk(array('items' => array($variable_copy)));
$saved = Catalog::copy_bulk(array('items' => array($variable_copy), 'dry_run' => false, 'preview_token' => $preview['preview_token']));
expect($saved['result']['all_verified'] && $GLOBALS['ce_products'][900005]->is_type('variable'), 'variable parent type and inventory preserved in combined copy edit');
$standalone = array('post_id' => 900003, 'title' => 'Literal C:\\style', 'description' => 'Standalone SEO', 'focus_keyword' => 'standalone phrase');
$preview = SEO::update_meta($standalone);
$saved = SEO::update_meta(array_merge($standalone, array('dry_run' => false, 'preview_token' => $preview['preview_token'])));
expect($saved['result']['saved_fields']['title'] === $standalone['title'], 'legacy standalone SEO update retains literal slashes');
$GLOBALS['ce_provider'] = 'rank_math';
$legacy_robots = array('post_id' => 900003, 'noindex' => false);
$preview = SEO::update_meta($legacy_robots);
$saved = SEO::update_meta(array_merge($legacy_robots, array('dry_run' => false, 'preview_token' => $preview['preview_token'])));
expect(in_array('index', $GLOBALS['ce_meta'][900003]['rank_math_robots'], true) && in_array('nofollow', $GLOBALS['ce_meta'][900003]['rank_math_robots'], true), 'legacy Rank Math indexing edit preserves other directives');
$GLOBALS['ce_provider'] = 'yoast';
echo ' CE_02_QA_PASSED assertions=' . $GLOBALS['sci_test_count'];
