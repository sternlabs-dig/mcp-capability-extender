<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class Catalog {
    public static function register() {
        $id = array('id' => Core::field('integer', array('minimum' => 1)));
        Core::ability('products', __('Find products by category, name or SKU', 'sternlabs-commerce-seo'), __('Lists parent products with exact category filtering, attributes and inventory. Does not confuse a name search with category membership.', 'sternlabs-commerce-seo'), Core::schema(array_merge(Core::page_fields(), array(
            'search' => Core::field('string'), 'sku' => Core::field('string'), 'category' => Core::field('string', array('description' => __('Exact product category slug.', 'sternlabs-commerce-seo'))),
            'category_id' => Core::field('integer', array('minimum' => 1, 'description' => __('Known category ID; do not also supply category.', 'sternlabs-commerce-seo'))),
            'status' => Core::field('string', array('enum' => array('publish', 'draft', 'pending', 'private', 'future', 'all'), 'description' => __('Default publish. all includes published, draft, pending, private and scheduled products; excludes trash.', 'sternlabs-commerce-seo'))),
            'statuses' => Core::field('array', array('minItems' => 1, 'maxItems' => 5, 'uniqueItems' => true, 'items' => Core::field('string', array('enum' => array('publish', 'draft', 'pending', 'private', 'future'))))),
            'fields' => Core::field('array', array('minItems' => 1, 'maxItems' => 9, 'uniqueItems' => true, 'description' => __('Optional compact response; id is always included.', 'sternlabs-commerce-seo'), 'items' => Core::field('string', array('enum' => array('id', 'name', 'sku', 'status', 'url', 'type', 'categories', 'description', 'short_description'))))),
            'stock_status' => Core::field('string', array('enum' => array('instock', 'outofstock', 'onbackorder')))
        ))), array(__CLASS__, 'products'), 'manage_woocommerce');
        Core::ability('product', __('Read product or variation', 'sternlabs-commerce-seo'), __('Read main inventory SKU, attributes, defaults, prices and stock. Variable product prices are summaries; individual prices belong to variations.', 'sternlabs-commerce-seo'), Core::schema($id, array('id')), array(__CLASS__, 'product'), 'manage_woocommerce');
        Core::ability('variations', __('Read product variations', 'sternlabs-commerce-seo'), __('Lists size/colour options, variation IDs, individual SKUs, prices, Manage stock and stock quantities.', 'sternlabs-commerce-seo'), Core::schema(array_merge($id, Core::page_fields()), array('id')), array(__CLASS__, 'variations'), 'manage_woocommerce');
        Core::ability('categories', __('List product categories', 'sternlabs-commerce-seo'), __('Search product categories and hierarchy, including empty categories.', 'sternlabs-commerce-seo'), Core::schema(array_merge(Core::page_fields(), array('search' => Core::field('string'), 'parent' => Core::field('integer', array('minimum' => 0))))), array(__CLASS__, 'categories'), 'manage_woocommerce');
        Core::ability('attributes', __('List global product attributes', 'sternlabs-commerce-seo'), __('Lists global attributes and taxonomy names. Use attribute-terms for values.', 'sternlabs-commerce-seo'), Core::schema(), array(__CLASS__, 'attributes'), 'manage_woocommerce');
        Core::ability('attribute-terms', __('List attribute values', 'sternlabs-commerce-seo'), __('Read available options for a global attribute, such as Size 44 or Colour Navy.', 'sternlabs-commerce-seo'), Core::schema(array_merge(array('attribute_id' => Core::field('integer', array('minimum' => 1))), Core::page_fields(), array('search' => Core::field('string'))), array('attribute_id')), array(__CLASS__, 'terms'), 'manage_woocommerce');
        $edit = array_merge($id, Core::mutation_fields(), array(
            'name' => Core::field('string', array('minLength' => 1)), 'sku' => Core::field('string'),
            'description' => Core::field('string'), 'short_description' => Core::field('string'),
            'regular_price' => Core::field('string', array('pattern' => '^(?:[0-9]+(?:\\.[0-9]+)?|)$')),
            'sale_price' => Core::field('string', array('pattern' => '^(?:[0-9]+(?:\\.[0-9]+)?|)$')),
            'date_on_sale_from' => Core::field('string', array('description' => __('ISO 8601 with timezone or empty to clear.', 'sternlabs-commerce-seo'))),
            'date_on_sale_to' => Core::field('string', array('description' => __('ISO 8601 with timezone or empty to clear.', 'sternlabs-commerce-seo'))),
            'manage_stock' => Core::field('boolean'), 'stock_quantity' => Core::field('integer', array('minimum' => 0)),
            'stock_status' => Core::field('string', array('enum' => array('instock', 'outofstock', 'onbackorder'))),
            'backorders' => Core::field('string', array('enum' => array('no', 'notify', 'yes'))),
            'status' => Core::field('string', array('enum' => array('publish', 'draft', 'pending', 'private'))),
            'category_ids' => Core::field('array', array('items' => Core::field('integer', array('minimum' => 1)), 'maxItems' => 50)),
            'category_mode' => Core::field('string', array('enum' => array('append', 'remove', 'replace'), 'default' => 'append')),
            'attributes' => Core::field('array', array('maxItems' => 30, 'items' => Core::schema(array(
                'id' => Core::field('integer', array('minimum' => 0)), 'name' => Core::field('string'),
                'options' => Core::field('array', array('maxItems' => 100, 'items' => Core::field('string'))),
                'visible' => Core::field('boolean'), 'variation' => Core::field('boolean')
            ), array('options')))),
            'variation_attributes' => Core::field('object', array('additionalProperties' => array('type' => 'string'), 'description' => __('For a variation only. Attribute taxonomy/name mapped to an existing parent option slug.', 'sternlabs-commerce-seo'))),
            'default_attributes' => Core::field('object', array('additionalProperties' => array('type' => 'string')))
        ));
        Core::ability('product-update', __('Preview or update product inventory and attributes', 'sternlabs-commerce-seo'), __('Preserves product type. dry_run defaults true. Apply requires the returned preview token and unchanged record. Attributes merge by name; options replace the selected attribute values. Global options are existing term IDs expressed as strings. Category mode defaults append. Does not edit variable parent prices.', 'sternlabs-commerce-seo'), Core::schema($edit, array('id')), array(__CLASS__, 'update'), 'manage_woocommerce', true);
        $item_fields = $edit; unset($item_fields['dry_run'], $item_fields['preview_token']); $item_fields['seo'] = SEO::content_schema();
        $bulk_fields = array_merge(Core::mutation_fields(), array('return_saved_fields' => Core::field('boolean', array('default' => false)), 'items' => Core::field('array', array('minItems' => 1, 'maxItems' => 20, 'items' => Core::schema($item_fields, array('id'))))));
        Core::ability('product-bulk-update', __('Preview or edit up to 20 products or variations', 'sternlabs-commerce-seo'), __('Combined product and optional SEO content updates in one preview/save pair. Validates all items first, preserves types, then reads saved values back and returns per-item verification. Sequential, not transactional. Use product-bulk-result with the preview token if a response is lost.', 'sternlabs-commerce-seo'), Core::schema($bulk_fields, array('items')), array(__CLASS__, 'bulk'), 'manage_woocommerce', true);
        $copy_fields = array_intersect_key($item_fields, array_flip(array('id', 'description', 'short_description', 'seo')));
        $copy_item = Core::schema($copy_fields, array('id'));
        $copy_item['anyOf'] = array(array('required' => array('description')), array('required' => array('short_description')), array('required' => array('seo')));
        $bulk_fields['items']['items'] = $copy_item;
        Core::ability('product-copy-bulk-update', __('Replace product copy and SEO in batches', 'sternlabs-commerce-seo'), __('Preferred copy workflow: up to 20 products with descriptions and SEO title, meta description and focus_keyword together. Main product names, SKUs, prices, stock, categories and attributes cannot be supplied. Existing statuses are preserved. One preview/save pair with server-side read-back verification.', 'sternlabs-commerce-seo'), Core::schema($bulk_fields, array('items')), array(__CLASS__, 'copy_bulk'), 'manage_woocommerce', true);
        Core::ability('product-bulk-result', __('Recover a bulk update result', 'sternlabs-commerce-seo'), __('Read the user-bound result receipt using the original preview_token after a lost response. Receipts last one hour. Incomplete/writing entries must be reconciled before retrying. Does not perform writes.', 'sternlabs-commerce-seo'), Core::schema(array('preview_token' => Core::field('string', array('minLength' => 1))), array('preview_token')), array(__CLASS__, 'bulk_result'), 'manage_woocommerce');
        Core::ability('variation-create', __('Preview or create a variation', 'sternlabs-commerce-seo'), __('Creates one explicit variation using existing parent options. No automatic combinatorial explosion. SKU and prices validated. Preview token required.', 'sternlabs-commerce-seo'), Core::schema(array_merge(Core::mutation_fields(), array(
            'parent_id' => Core::field('integer', array('minimum' => 1)), 'sku' => Core::field('string'),
            'regular_price' => Core::field('string', array('pattern' => '^[0-9]+(?:\\.[0-9]+)?$')),
            'attributes' => Core::field('object', array('additionalProperties' => array('type' => 'string')))
        )), array('parent_id', 'attributes', 'regular_price')), array(__CLASS__, 'create_variation'), 'manage_woocommerce', true);
        Core::ability('taxonomy-create', __('Preview or create category, attribute or option', 'sternlabs-commerce-seo'), __('Create a product category, a global attribute, or one term in an existing global attribute. No unrestricted taxonomy writes.', 'sternlabs-commerce-seo'), Core::schema(array_merge(Core::mutation_fields(), array(
            'kind' => Core::field('string', array('enum' => array('category', 'attribute', 'term'))),
            'name' => Core::field('string', array('minLength' => 1, 'maxLength' => 100)), 'slug' => Core::field('string'),
            'parent' => Core::field('integer', array('minimum' => 0)), 'attribute_id' => Core::field('integer', array('minimum' => 1))
        )), array('kind', 'name')), array(__CLASS__, 'create_taxonomy'), 'manage_product_terms', true);
    }
    public static function require_wc() { return function_exists('wc_get_product') ? true : Core::error(__('WooCommerce is not active.', 'sternlabs-commerce-seo'), 'sci_dependency'); }
    public static function fetch($id) {
        if (is_wp_error($ready = self::require_wc())) { return $ready; }
        $p = wc_get_product(absint($id));
        if (!$p || !current_user_can('edit_post', $p->is_type('variation') ? $p->get_parent_id() : $p->get_id())) { return Core::error(__('Product not found or access denied.', 'sternlabs-commerce-seo'), 'sci_product_access'); }
        return $p;
    }
    public static function data($p) {
        $attrs = array();
        foreach ($p->get_attributes() as $name => $attr) {
            if ($attr instanceof \WC_Product_Attribute) {
                $labels = $attr->is_taxonomy() ? wc_get_product_terms($p->get_id(), $attr->get_name(), array('fields' => 'names')) : $attr->get_options();
                $attrs[] = array('id' => $attr->get_id(), 'name' => $attr->get_name(), 'label' => wc_attribute_label($attr->get_name()),
                    'options' => $attr->get_options(), 'option_labels' => is_wp_error($labels) ? array() : $labels,
                    'visible' => $attr->get_visible(), 'variation' => $attr->get_variation());
            } else { $attrs[] = array('name' => $name, 'value' => $attr); }
        }
        $cats = array();
        foreach ($p->get_category_ids() as $id) {
            $t = get_term($id, 'product_cat');
            if ($t && !is_wp_error($t)) { $cats[] = array('id' => $id, 'name' => $t->name, 'slug' => $t->slug); }
        }
        return array('id' => $p->get_id(), 'parent_id' => $p->get_parent_id(), 'type' => $p->get_type(), 'name' => $p->get_name(),
            'status' => $p->get_status(), 'sku' => $p->get_sku('edit'), 'url' => $p->get_permalink(),
            'currency' => get_woocommerce_currency(), 'price' => $p->get_price('edit'), 'regular_price' => $p->get_regular_price('edit'), 'sale_price' => $p->get_sale_price('edit'),
            'date_on_sale_from' => $p->get_date_on_sale_from() ? $p->get_date_on_sale_from()->date('c') : null,
            'date_on_sale_to' => $p->get_date_on_sale_to() ? $p->get_date_on_sale_to()->date('c') : null,
            'manage_stock' => $p->get_manage_stock('edit'), 'stock_quantity' => $p->get_stock_quantity('edit'),
            'stock_status' => $p->get_stock_status('edit'), 'backorders' => $p->get_backorders('edit'),
            'categories' => $cats, 'attributes' => $attrs, 'default_attributes' => $p->get_default_attributes(),
            'description' => $p->get_description('edit'), 'short_description' => $p->get_short_description('edit'),
            'modified' => $p->get_date_modified() ? $p->get_date_modified()->date('c') : null);
    }
    public static function product($in) {
        $p = self::fetch($in['id']); return is_wp_error($p) ? $p : array('product' => self::data($p));
    }
    public static function products($in) {
        if (is_wp_error($r = self::require_wc())) { return $r; }
        if (isset($in['status'], $in['statuses'])) { return Core::error(__('Use status or statuses, not both.', 'sternlabs-commerce-seo')); }
        if (isset($in['category'], $in['category_id'])) { return Core::error(__('Use category or category_id, not both.', 'sternlabs-commerce-seo')); }
        $statuses = $in['statuses'] ?? (($in['status'] ?? 'publish') === 'all' ? array('publish', 'draft', 'pending', 'private', 'future') : ($in['status'] ?? 'publish'));
        $q = array('limit' => $in['per_page'] ?? 50, 'page' => $in['page'] ?? 1, 'paginate' => true, 'orderby' => 'ID', 'order' => 'ASC', 'status' => $statuses);
        foreach (array('sku', 'stock_status') as $key) { if (isset($in[$key])) { $q[$key] = $in[$key]; } }
        if (!empty($in['search'])) { $q['s'] = sanitize_text_field($in['search']); }
        if (isset($in['category']) || isset($in['category_id'])) {
            $term = isset($in['category_id']) ? get_term($in['category_id'], 'product_cat') : get_term_by('slug', sanitize_title($in['category']), 'product_cat');
            if (!$term || is_wp_error($term)) { return Core::error(__('Category does not exist. Use categories first.', 'sternlabs-commerce-seo')); }
            $q['category'] = array($term->slug);
        }
        $result = wc_get_products($q);
        $rows = array();
        foreach ($result->products as $p) {
            // Broad status queries must not expose a product the caller cannot edit.
            if (!current_user_can('edit_post', $p->get_id())) { continue; }
            $rows[] = isset($in['fields']) ? self::compact_data($p, $in['fields']) : self::data($p);
        }
        return array('products' => $rows, 'total' => $result->total, 'total_pages' => $result->max_num_pages, 'page' => $q['page'], 'returned_count' => count($rows), 'permissions_filtered' => count($rows) !== count($result->products));
    }
    public static function compact_data($p, $fields) {
        $out = array('id' => $p->get_id());
        foreach ($fields as $field) {
            if ($field === 'id') { continue; }
            if ($field === 'url') { $out[$field] = $p->get_permalink(); }
            elseif ($field === 'categories') {
                $out[$field] = array();
                foreach ($p->get_category_ids() as $id) { $t = get_term($id, 'product_cat'); if ($t && !is_wp_error($t)) { $out[$field][] = array('id' => $id, 'name' => $t->name, 'slug' => $t->slug); } }
            } elseif ($field === 'type') { $out[$field] = $p->get_type(); }
            else { $method = 'get_' . $field; $out[$field] = $p->$method('edit'); }
        }
        return $out;
    }
    public static function variations($in) {
        $p = self::fetch($in['id']); if (is_wp_error($p)) { return $p; }
        if (!$p->is_type('variable')) { return Core::error(__('Expected a variable parent product.', 'sternlabs-commerce-seo')); }
        $ids = get_posts(array('post_type' => 'product_variation', 'post_parent' => $p->get_id(), 'post_status' => array('publish', 'private'), 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC'));
        $limit = $in['per_page'] ?? 50; $page = $in['page'] ?? 1;
        $rows = array(); foreach (array_slice($ids, ($page - 1) * $limit, $limit) as $id) { $v = wc_get_product($id); if ($v) { $rows[] = self::data($v); } }
        return array('parent_id' => $p->get_id(), 'variations' => $rows, 'total' => count($ids), 'total_pages' => (int) ceil(count($ids) / $limit), 'page' => $page);
    }
    public static function categories($in) { return self::term_list('product_cat', $in); }
    public static function term_list($taxonomy, $in) {
        if (!taxonomy_exists($taxonomy)) { return Core::error(__('Taxonomy is unavailable. Check WooCommerce and attribute ID.', 'sternlabs-commerce-seo')); }
        $q = array('taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => $in['per_page'] ?? 50, 'offset' => (($in['page'] ?? 1) - 1) * ($in['per_page'] ?? 50), 'orderby' => 'term_id', 'order' => 'ASC');
        foreach (array('search', 'parent') as $key) { if (isset($in[$key])) { $q[$key] = $in[$key]; } }
        $rows = get_terms($q); if (is_wp_error($rows)) { return $rows; }
        $count_q = $q; unset($count_q['number'], $count_q['offset']);
        $total = wp_count_terms($count_q); if (is_wp_error($total)) { return $total; }
        return array('terms' => array_map(function ($t) { return array('id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => $t->parent, 'count' => $t->count); }, $rows), 'total' => (int) $total, 'page' => $in['page'] ?? 1, 'total_pages' => (int) ceil($total / $q['number']));
    }
    public static function attributes() {
        if (is_wp_error($r = self::require_wc())) { return $r; }
        $rows = array(); foreach (wc_get_attribute_taxonomies() as $a) { $rows[] = array('id' => (int) $a->attribute_id, 'name' => $a->attribute_label, 'slug' => $a->attribute_name, 'taxonomy' => wc_attribute_taxonomy_name($a->attribute_name), 'type' => $a->attribute_type); }
        return array('attributes' => $rows);
    }
    public static function terms($in) {
        if (is_wp_error($r = self::require_wc())) { return $r; }
        $a = wc_get_attribute($in['attribute_id']); if (!$a) { return Core::error(__('Attribute does not exist.', 'sternlabs-commerce-seo')); }
        return self::term_list($a->slug, $in);
    }
    public static function validate_options($parent, $values, $complete = false) {
        $out = array();
        foreach ((array) $values as $name => $value) {
            $key = sanitize_title($name); $attrs = $parent->get_attributes();
            if (empty($attrs[$key]) || !$attrs[$key]->get_variation()) { return Core::error(__('Unknown or non-variation parent attribute: ', 'sternlabs-commerce-seo') . $key); }
            $attr = $attrs[$key];
            if ($attr->is_taxonomy()) {
                $term = get_term_by('slug', $value, $attr->get_name());
                if (!$term || !in_array((int) $term->term_id, array_map('intval', $attr->get_options()), true)) { return Core::error(__('Option must be an assigned parent term slug: ', 'sternlabs-commerce-seo') . $key); }
                $out[$key] = $term->slug;
            } else {
                $found = false;
                foreach ($attr->get_options() as $option) { if (sanitize_title($option) === sanitize_title($value)) { $out[$key] = $option; $found = true; break; } }
                if (!$found) { return Core::error(__('Option is not assigned to parent: ', 'sternlabs-commerce-seo') . $key); }
            }
        }
        if ($complete) { foreach ($parent->get_attributes() as $key => $attr) { if ($attr->get_variation() && !isset($out[$key])) { return Core::error(__('Explicit value required for every variation attribute: ', 'sternlabs-commerce-seo') . $key); } } }
        ksort($out); return $out;
    }
    public static function prepare($p, $in) {
        $fields = array('name', 'sku', 'description', 'short_description', 'regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to', 'manage_stock', 'stock_quantity', 'stock_status', 'backorders', 'status');
        if ($p->is_type(array('variable', 'grouped'))) {
            foreach (array('regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to') as $key) { if (array_key_exists($key, $in)) { return Core::error(__('Edit child prices by their IDs; variable or grouped parent type will be preserved.', 'sternlabs-commerce-seo')); } }
        }
        if (isset($in['sku']) && $in['sku'] !== '') { $owner = wc_get_product_id_by_sku($in['sku']); if ($owner && $owner !== $p->get_id()) { return Core::error(__('SKU is already assigned to another product.', 'sternlabs-commerce-seo')); } }
        if ($p->is_type('variation') && (isset($in['category_ids']) || isset($in['attributes']) || isset($in['default_attributes']))) { return Core::error(__('Categories and attribute definitions belong to the parent product.', 'sternlabs-commerce-seo')); }
        foreach ($fields as $key) {
            if (!array_key_exists($key, $in)) { continue; }
            $value = $in[$key];
            if (in_array($key, array('description', 'short_description'), true)) { $value = wp_kses_post($value); }
            if (in_array($key, array('date_on_sale_from', 'date_on_sale_to'), true) && $value !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value) || strtotime($value) === false) { return Core::error(__('Sale dates must be ISO 8601 timestamps including timezone.', 'sternlabs-commerce-seo')); }
            }
            $setter = 'set_' . $key; $p->$setter($value === '' && strpos($key, 'date_') === 0 ? null : $value);
        }
        if (isset($in['stock_quantity']) && !$p->get_manage_stock('edit')) { return Core::error(__('Enable Manage stock to edit stock quantity.', 'sternlabs-commerce-seo')); }
        $regular = $p->get_regular_price('edit'); $sale = $p->get_sale_price('edit');
        if ($sale !== '' && ($regular === '' || (float) $sale > (float) $regular)) { return Core::error(__('Sale price must not exceed the regular price.', 'sternlabs-commerce-seo')); }
        if ($p->get_date_on_sale_from() && $p->get_date_on_sale_to() && $p->get_date_on_sale_from()->getTimestamp() > $p->get_date_on_sale_to()->getTimestamp()) { return Core::error(__('Sale end must not precede sale start.', 'sternlabs-commerce-seo')); }
        if (isset($in['category_ids'])) {
            foreach ($in['category_ids'] as $id) { if (!term_exists($id, 'product_cat')) { return Core::error(__('Category ID does not exist: ', 'sternlabs-commerce-seo') . $id); } }
            $old = $p->get_category_ids(); $mode = $in['category_mode'] ?? 'append';
            $p->set_category_ids($mode === 'replace' ? $in['category_ids'] : ($mode === 'remove' ? array_values(array_diff($old, $in['category_ids'])) : array_values(array_unique(array_merge($old, $in['category_ids'])))));
        }
        if (isset($in['attributes'])) {
            $attrs = $p->get_attributes();
            foreach ($in['attributes'] as $row) {
                $id = (int) ($row['id'] ?? 0); $global = $id ? wc_get_attribute($id) : null;
                if ($id && !$global) { return Core::error(__('Global attribute ID does not exist.', 'sternlabs-commerce-seo')); }
                $name = $global ? $global->slug : sanitize_text_field($row['name'] ?? '');
                if ($name === '' || (!$id && strpos($name, 'pa_') === 0)) { return Core::error(__('Custom attributes need a name without the reserved pa_ prefix.', 'sternlabs-commerce-seo')); }
                $key = sanitize_title($name); $attr = isset($attrs[$key]) ? clone $attrs[$key] : new \WC_Product_Attribute();
                $attr->set_id($id); $attr->set_name($name);
                $options = $row['options'];
                if ($global) { foreach ($options as $term_id) { if (!ctype_digit((string) $term_id) || !term_exists((int) $term_id, $name)) { return Core::error(__('Global attribute options must be existing term IDs expressed as strings.', 'sternlabs-commerce-seo')); } } $options = array_map('intval', $options); }
                else { $options = array_map('sanitize_text_field', $options); }
                $attr->set_options(array_values(array_unique($options)));
                if (isset($row['visible'])) { $attr->set_visible($row['visible']); }
                if (isset($row['variation'])) { $attr->set_variation($row['variation']); }
                if ($p->is_type('variable') && isset($attrs[$key]) && $attrs[$key]->get_variation()) {
                    if (!$attr->get_variation() || array_diff($attrs[$key]->get_options(), $options)) { return Core::error(__('Removing options or disabling Used for variations on an existing variable attribute requires manual review of its variations. Additions and visibility edits are supported.', 'sternlabs-commerce-seo')); }
                }
                $attrs[$key] = $attr;
            }
            $p->set_attributes($attrs);
        }
        if (isset($in['default_attributes'])) { $valid = self::validate_options($p, $in['default_attributes']); if (is_wp_error($valid)) { return $valid; } $p->set_default_attributes(array_merge($p->get_default_attributes(), $valid)); }
        if (isset($in['variation_attributes'])) {
            if (!$p->is_type('variation')) { return Core::error(__('variation_attributes applies only to a variation.', 'sternlabs-commerce-seo')); }
            $parent = self::fetch($p->get_parent_id()); if (is_wp_error($parent)) { return $parent; }
            $valid = self::validate_options($parent, array_merge($p->get_attributes(), (array) $in['variation_attributes']), true);
            if (is_wp_error($valid)) { return $valid; }
            $store = \WC_Data_Store::load('product'); $match = $store->find_matching_product_variation($parent, $valid);
            if ($match && $match !== $p->get_id()) { return Core::error(__('Another variation matches these options.', 'sternlabs-commerce-seo')); }
            $p->set_attributes($valid);
        }
        return $p;
    }
    public static function update($in) {
        return Core::lock('catalog-write', function () use ($in) {
            $p = self::fetch($in['id']); if (is_wp_error($p)) { return $p; }
            $before = self::data($p); $edited = self::prepare(clone $p, $in); if (is_wp_error($edited)) { return $edited; }
            $after = self::data($edited);
            $diff = array(); foreach ($after as $k => $v) { if (($before[$k] ?? null) !== $v) { $diff[$k] = array('before' => $before[$k] ?? null, 'after' => $v); } }
            return Core::mutate('product-update', $in, array('id' => $p->get_id(), 'record_hash' => Core::digest($before), 'changes' => $diff), function () use ($edited, $p) {
                $edited->save(); if ($edited->is_type('variation')) { \WC_Product_Variable::sync($edited->get_parent_id()); }
                wc_delete_product_transients($p->get_id()); return array('product' => self::data($edited));
            });
        });
    }
    public static function create_variation($in) {
        return Core::lock('catalog-write', function () use ($in) {
            $p = self::fetch($in['parent_id']); if (is_wp_error($p)) { return $p; }
            if (!$p->is_type('variable')) { return Core::error(__('Parent must be variable.', 'sternlabs-commerce-seo')); }
            $valid = self::validate_options($p, $in['attributes'], true); if (is_wp_error($valid)) { return $valid; }
            $store = \WC_Data_Store::load('product'); if ($store->find_matching_product_variation($p, $valid)) { return Core::error(__('This option combination already has a matching variation.', 'sternlabs-commerce-seo')); }
            if (!empty($in['sku']) && wc_get_product_id_by_sku($in['sku'])) { return Core::error(__('SKU already exists.', 'sternlabs-commerce-seo')); }
            return Core::mutate('variation-create', $in, array('parent' => self::data($p), 'attributes' => $valid, 'sku' => $in['sku'] ?? '', 'regular_price' => $in['regular_price']), function () use ($p, $valid, $in) {
                $v = new \WC_Product_Variation(); $v->set_parent_id($p->get_id()); $v->set_attributes($valid); $v->set_sku($in['sku'] ?? ''); $v->set_regular_price($in['regular_price']); $v->set_status('publish'); $v->save();
                \WC_Product_Variable::sync($p->get_id()); return array('variation' => self::data($v));
            });
        });
    }
    public static function copy_bulk($in) {
        foreach ($in['items'] as $row) {
            if (array_diff(array_keys($row), array('id', 'description', 'short_description', 'seo')) || count($row) < 2) { return Core::error(__('Copy updates accept id and at least one copy field only. Main product titles cannot be changed.', 'sternlabs-commerce-seo')); }
        }
        return self::bulk($in, 'product-copy-bulk-update');
    }
    public static function verification_fields($before, $after, $row, $seo) {
        $fields = array('id' => $before['id'], 'type' => $before['type']);
        $copy_only = !array_diff(array_keys($row), array('id', 'description', 'short_description', 'seo'));
        // Copy-only receipts also prove commercial fields and the main title were preserved.
        $names = $copy_only ? array('name', 'sku', 'status', 'regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to', 'manage_stock', 'stock_quantity', 'stock_status', 'backorders', 'categories', 'attributes', 'default_attributes') : array();
        foreach (array_keys($row) as $key) {
            if ($key === 'seo' || $key === 'id') { continue; }
            if ($key === 'category_mode') { continue; }
            $names[] = $key === 'category_ids' ? 'categories' : ($key === 'variation_attributes' ? 'attributes' : $key);
        }
        foreach (array_unique($names) as $key) { $fields[$key] = self::verification_value($key, $copy_only && !array_key_exists($key, $row) ? $before[$key] : $after[$key]); }
        if ($seo) { foreach ($seo['changes'] as $key => $value) { $fields['seo.' . $key] = $value; } }
        return $fields;
    }
    public static function verification_value($key, $value) {
        if ($key === 'attributes') {
            foreach ($value as &$attribute) { unset($attribute['label'], $attribute['option_labels']); } unset($attribute);
            usort($value, function ($a, $b) { return strcmp($a['name'], $b['name']); });
        } elseif ($key === 'categories') { usort($value, function ($a, $b) { return $a['id'] <=> $b['id']; }); }
        elseif ($key === 'default_attributes') { ksort($value); }
        return $value;
    }
    public static function saved_fields($id, $expected) {
        $p = self::fetch($id); if (is_wp_error($p)) { return $p; }
        $product = self::data($p); $saved = array(); $seo = null;
        foreach ($expected as $key => $unused) {
            if (strpos($key, 'seo.') === 0) {
                if ($seo === null) { $seo = SEO::meta(array('post_id' => $id)); if (is_wp_error($seo)) { return $seo; } }
                $saved[$key] = $seo['saved_fields'][substr($key, 4)] ?? null;
            } else { $saved[$key] = self::verification_value($key, $product[$key] ?? null); }
        }
        return $saved;
    }
    public static function receipt_key($token) { return 'sci_bulk_' . hash('sha256', $token); }
    public static function store_receipt($token, $result) {
        $result['owner'] = get_current_user_id();
        set_transient(self::receipt_key($token), $result, HOUR_IN_SECONDS);
    }
    public static function bulk_result($in) {
        $result = get_transient(self::receipt_key($in['preview_token']));
        if (!$result || (int) $result['owner'] !== get_current_user_id()) { return Core::error(__('Bulk receipt is unavailable, expired or belongs to another user.', 'sternlabs-commerce-seo'), 'sci_receipt_unavailable'); }
        foreach ($result['items'] as $item) { $p = self::fetch($item['id']); if (is_wp_error($p)) { return $p; } }
        unset($result['owner']);
        $result['recovery_note'] = 'Use verified results for completed items. Pending/writing items may be in progress or interrupted; reconcile actual values and request a fresh preview before retrying. No automatic rollback.';
        return $result;
    }
    public static function bulk($in, $action = 'product-bulk-update') {
        return Core::lock('catalog-write', function () use ($in, $action) {
            $prepared = array(); $plan = array();
            foreach ($in['items'] as $row) {
                if (isset($prepared[$row['id']])) { return Core::error(__('Duplicate product ID in bulk request.', 'sternlabs-commerce-seo')); }
                $p = self::fetch($row['id']); if (is_wp_error($p)) { return $p; }
                $before = self::data($p); $after = self::prepare(clone $p, $row); if (is_wp_error($after)) { return $after; }
                $seo = null;
                if (isset($row['seo'])) {
                    if ($p->is_type('variation')) { return Core::error(__('SEO content belongs to the parent product, not an individual variation.', 'sternlabs-commerce-seo')); }
                    $seo = SEO::prepare_content($p->get_id(), $row['seo']); if (is_wp_error($seo)) { return $seo; }
                }
                $after_data = self::data($after);
                $expected = self::verification_fields($before, $after_data, $row, $seo);
                $prepared[$row['id']] = array('product' => $after, 'seo' => $seo, 'expected' => $expected);
                $changes = array(); foreach (self::data($after) as $key => $value) { if (($before[$key] ?? null) !== $value) { $changes[$key] = array('before' => $before[$key] ?? null, 'after' => $value); } }
                if ($seo) { foreach ($seo['changes'] as $key => $value) { $old = $seo['before']['saved_fields'][$key] ?? ''; if ($old !== $value) { $changes['seo.' . $key] = array('before' => $old, 'after' => $value); } } }
                $plan[] = array('id' => $row['id'], 'record_hash' => Core::digest(array('product' => $before, 'seo' => $seo ? $seo['before'] : null)), 'changes' => $changes, 'expected_hash' => Core::digest($expected));
            }
            $skus = array(); foreach ($prepared as $entry) { $sku = strtolower($entry['product']->get_sku('edit')); if ($sku !== '' && isset($skus[$sku])) { return Core::error(__('Bulk changes would create duplicate SKUs.', 'sternlabs-commerce-seo')); } if ($sku !== '') { $skus[$sku] = true; } }
            return Core::mutate($action, $in, array('items' => $plan, 'transactional' => false, 'return_saved_fields' => !empty($in['return_saved_fields'])), function () use ($prepared, $in) {
                $result = array('items' => array(), 'all_saved' => false, 'all_verified' => false, 'complete' => false, 'note' => __('Sequential saves with read-back verification, not a database transaction. A failed item may be partially written; no automatic rollback.', 'sternlabs-commerce-seo'));
                foreach ($prepared as $id => $entry) { $result['items'][] = array('id' => $id, 'status' => 'pending', 'saved' => null, 'verified' => null); }
                $token = $in['preview_token']; self::store_receipt($token, $result);
                foreach (array_values(array_keys($prepared)) as $offset => $id) {
                    $entry = $prepared[$id]; $p = $entry['product'];
                    $result['items'][$offset]['status'] = 'writing'; self::store_receipt($token, $result);
                    try {
                        if (!Core::allowed('manage_woocommerce', true)) { throw new \RuntimeException(esc_html__('Product write access is no longer available.', 'sternlabs-commerce-seo')); }
                        $permission_check = self::fetch($id); if (is_wp_error($permission_check)) { throw new \RuntimeException(esc_html__('Product access is no longer available.', 'sternlabs-commerce-seo')); }
                        if ($entry['seo'] && !current_user_can('edit_others_posts')) { throw new \RuntimeException(esc_html__('SEO access is no longer available.', 'sternlabs-commerce-seo')); }
                        $p->save();
                        if ($p->is_type('variation')) { \WC_Product_Variable::sync($p->get_parent_id()); }
                        wc_delete_product_transients($id);
                        if ($entry['seo']) { $written = SEO::persist_meta($id, $entry['seo']['changes'], $entry['seo']['before']['provider']); if (is_wp_error($written)) { throw new \RuntimeException($written->get_error_message()); } }
                        $saved = self::saved_fields($id, $entry['expected']); if (is_wp_error($saved)) { throw new \RuntimeException($saved->get_error_message()); }
                        $mismatches = array(); foreach ($entry['expected'] as $key => $value) { if ($saved[$key] !== $value) { $mismatches[] = $key; } }
                        $verified = !$mismatches;
                        $item = array('id' => $id, 'status' => $verified ? 'saved' : 'failed', 'saved' => $verified, 'verified' => $verified, 'expected_hash' => Core::digest($entry['expected']), 'saved_hash' => Core::digest($saved));
                        if (!$verified) { $item['mismatched_fields'] = $mismatches; $item['partial_write_possible'] = true; }
                        if (!empty($in['return_saved_fields'])) { $item['saved_fields'] = $saved; }
                        $result['items'][$offset] = $item;
                    } catch (\Throwable $e) { $result['items'][$offset] = array('id' => $id, 'status' => 'failed', 'saved' => false, 'verified' => false, 'partial_write_possible' => true, 'error' => 'Product could not be saved and verified. Reconcile saved values before retrying.'); }
                    self::store_receipt($token, $result);
                }
                $result['all_saved'] = !in_array(false, array_column($result['items'], 'saved'), true);
                $result['all_verified'] = !in_array(false, array_column($result['items'], 'verified'), true);
                $result['complete'] = true; self::store_receipt($token, $result);
                return $result;
            });
        });
    }
    public static function create_taxonomy($in) {
        if (is_wp_error($r = self::require_wc())) { return $r; }
        $kind = $in['kind']; $name = sanitize_text_field($in['name']); $slug = sanitize_title($in['slug'] ?? $name);
        $taxonomy = 'product_cat';
        if ($kind === 'term') { $attr = wc_get_attribute($in['attribute_id'] ?? 0); if (!$attr || !taxonomy_exists($attr->slug)) { return Core::error(__('Existing attribute_id is required.', 'sternlabs-commerce-seo')); } $taxonomy = $attr->slug; }
        if ($kind !== 'attribute' && term_exists($slug, $taxonomy)) { return Core::error(__('Term slug already exists.', 'sternlabs-commerce-seo')); }
        if ($kind === 'category' && !empty($in['parent']) && !term_exists($in['parent'], 'product_cat')) { return Core::error(__('Parent category does not exist.', 'sternlabs-commerce-seo')); }
        return Core::mutate('taxonomy-create', $in, array('kind' => $kind, 'name' => $name, 'slug' => $slug, 'taxonomy' => $taxonomy, 'parent' => $in['parent'] ?? 0), function () use ($in, $kind, $name, $slug, $taxonomy) {
            if ($kind === 'attribute') {
                $id = wc_create_attribute(array('name' => $name, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false));
                return is_wp_error($id) ? $id : array('attribute_id' => $id, 'next_step' => __('Use a new request before creating options; WooCommerce registers the new taxonomy on the next request.', 'sternlabs-commerce-seo'));
            }
            return wp_insert_term($name, $taxonomy, array('slug' => $slug, 'parent' => $kind === 'category' ? ($in['parent'] ?? 0) : 0));
        });
    }
}
