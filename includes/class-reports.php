<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class Reports {
    public static function register() {
        Core::ability('sales-report-start', 'Start a sales report', 'Creates a resumable WooCommerce report. Does not send customer information to the AI. Period is order creation date in the store timezone; refunds are current lifetime refunds of that order cohort. Continue until complete before using totals.', Core::schema(Core::date_fields()), array(__CLASS__, 'start'), 'view_woocommerce_reports');
        Core::ability('sales-report-next', 'Continue a sales report', 'Processes up to 50 orders. Repeated calls accumulate totals without double counting committed batches. Reports expire in 24 hours. Current assignments determine categories; multi-category totals are non-additive.', Core::schema(array('report_id' => Core::field('string', array('pattern' => '^[a-f0-9]{32}$'))), array('report_id')), array(__CLASS__, 'next'), 'view_woocommerce_reports');
        Core::ability('sales-report-get', 'Read a sales report', 'Returns accumulated totals, top products, categories, variations and coupons. complete=false means totals are partial. No profit or traffic conversion claims.', Core::schema(array('report_id' => Core::field('string', array('pattern' => '^[a-f0-9]{32}$'))), array('report_id')), array(__CLASS__, 'get'), 'view_woocommerce_reports');
        Core::ability('sales-report-rows', 'Read paginated sales report details', 'Fetch every product, variation, category or coupon row for exports. Currency groups remain separate. Report must be complete for final totals.', Core::schema(array_merge(Core::page_fields(), array('report_id' => Core::field('string', array('pattern' => '^[a-f0-9]{32}$')), 'section' => Core::field('string', array('enum' => array('products', 'variations', 'categories', 'coupons'))))), array('report_id', 'section')), array(__CLASS__, 'rows'), 'view_woocommerce_reports');
        Core::ability('inventory-report', 'Read inventory and data quality', 'Paginated parent products and variations with stock, missing SKU, missing prices and low-stock flags. Variable parent prices are excluded from missing-price checks.', Core::schema(Core::page_fields()), array(__CLASS__, 'inventory'), 'manage_woocommerce');
    }
    public static function start($in) {
        if (is_wp_error($r = Catalog::require_wc())) { return $r; }
        $range = Core::date_range($in); if (is_wp_error($range)) { return $range; }
        $id = md5(wp_generate_uuid4());
        $job = array('owner' => get_current_user_id(), 'range' => $range, 'started' => gmdate('c'), 'page' => 1, 'complete' => false,
            'orders_processed' => 0, 'currencies' => array(), 'products' => array(), 'variations' => array(), 'categories' => array(), 'coupons' => array(), 'customers' => array());
        set_transient('sci_report_' . $id, $job, DAY_IN_SECONDS);
        return array('report_id' => $id, 'complete' => false, 'next_ability' => 'sternlabs/sales-report-next', 'date_range' => $range);
    }
    public static function load($id) {
        $j = get_transient('sci_report_' . $id);
        return (!$j || (int) $j['owner'] !== get_current_user_id()) ? Core::error('Report expired or belongs to another user.', 'sci_report_access') : $j;
    }
    public static function empty_totals() {
        return array('orders' => 0, 'order_total_including_shipping_tax' => 0.0, 'order_refunds_including_shipping_tax' => 0.0,
            'order_total_after_refunds' => 0.0, 'merchandise_before_discounts_ex_tax' => 0.0, 'merchandise_after_discounts_ex_tax' => 0.0,
            'merchandise_refunds_ex_tax' => 0.0, 'net_merchandise_ex_tax' => 0.0, 'discounts_ex_tax' => 0.0,
            'shipping_before_refunds_ex_tax' => 0.0, 'tax_before_refunds' => 0.0, 'units_ordered' => 0.0, 'units_refunded' => 0.0, 'net_units' => 0.0);
    }
    public static function bucket(&$target, $key, $identity) {
        if (!isset($target[$key])) { $target[$key] = array_merge($identity, array('orders' => 0, 'units_ordered' => 0.0, 'units_refunded' => 0.0, 'net_units' => 0.0, 'net_merchandise_ex_tax' => 0.0)); }
    }
    public static function line(&$row, $qty, $ref_qty, $net) {
        $row['units_ordered'] += $qty; $row['units_refunded'] += $ref_qty; $row['net_units'] += $qty - $ref_qty; $row['net_merchandise_ex_tax'] += $net;
    }
    public static function accumulate(&$j, $order) {
        $currency = $order->get_currency();
        if (!isset($j['currencies'][$currency])) { $j['currencies'][$currency] = self::empty_totals(); }
        $s =& $j['currencies'][$currency]; $s['orders']++;
        $s['order_total_including_shipping_tax'] += (float) $order->get_total();
        $s['order_refunds_including_shipping_tax'] += (float) $order->get_total_refunded();
        $s['order_total_after_refunds'] += (float) $order->get_total() - (float) $order->get_total_refunded();
        $s['discounts_ex_tax'] += (float) $order->get_discount_total(); $s['shipping_before_refunds_ex_tax'] += (float) $order->get_shipping_total(); $s['tax_before_refunds'] += (float) $order->get_total_tax();
        $seen = array('products' => array(), 'variations' => array(), 'categories' => array());
        foreach ($order->get_items('line_item') as $item_id => $item) {
            $pid = $item->get_product_id(); $vid = $item->get_variation_id();
            $qty = (float) $item->get_quantity(); $rq = abs((float) $order->get_qty_refunded_for_item($item_id));
            $refund = abs((float) $order->get_total_refunded_for_item($item_id)); $net = (float) $item->get_total() - $refund;
            $s['units_ordered'] += $qty; $s['units_refunded'] += $rq; $s['net_units'] += $qty - $rq;
            $s['merchandise_before_discounts_ex_tax'] += (float) $item->get_subtotal(); $s['merchandise_after_discounts_ex_tax'] += (float) $item->get_total();
            $s['merchandise_refunds_ex_tax'] += $refund; $s['net_merchandise_ex_tax'] += $net;
            $key = $currency . ':' . $pid; self::bucket($j['products'], $key, array('id' => $pid, 'name' => $item->get_name(), 'currency' => $currency));
            self::line($j['products'][$key], $qty, $rq, $net); $seen['products'][$key] = true;
            if ($vid) {
                $key = $currency . ':' . $vid; $variation = wc_get_product($vid);
                self::bucket($j['variations'], $key, array('id' => $vid, 'parent_id' => $pid, 'name' => $item->get_name(), 'currency' => $currency, 'sku_current' => $variation ? $variation->get_sku() : null,
                    'attributes_current' => $variation ? $variation->get_attributes() : array(), 'attributes_at_order' => self::item_attributes($item)));
                self::line($j['variations'][$key], $qty, $rq, $net); $seen['variations'][$key] = true;
            }
            $terms = wp_get_post_terms($pid, 'product_cat');
            if (!is_wp_error($terms)) { foreach ($terms as $term) {
                $key = $currency . ':' . $term->term_id; self::bucket($j['categories'], $key, array('id' => $term->term_id, 'name' => $term->name, 'currency' => $currency));
                self::line($j['categories'][$key], $qty, $rq, $net); $seen['categories'][$key] = true;
            } }
        }
        foreach ($seen as $type => $keys) { foreach ($keys as $key => $unused) { $j[$type][$key]['orders']++; } }
        foreach ($order->get_items('coupon') as $coupon) {
            $key = $currency . ':' . $coupon->get_code();
            if (!isset($j['coupons'][$key])) { $j['coupons'][$key] = array('code' => $coupon->get_code(), 'currency' => $currency, 'orders' => 0, 'discount_ex_tax' => 0.0); }
            $j['coupons'][$key]['orders']++; $j['coupons'][$key]['discount_ex_tax'] += (float) $coupon->get_discount();
        }
        $identity = $order->get_customer_id() ? 'id:' . $order->get_customer_id() : 'guest:' . strtolower(trim($order->get_billing_email()));
        if ($identity !== 'guest:') { $hash = hash_hmac('sha256', $identity, wp_salt('auth')); $j['customers'][$hash] = ($j['customers'][$hash] ?? 0) + 1; }
        $j['orders_processed']++;
    }
    public static function item_attributes($item) {
        $rows = array(); $allowed = array(); $parent = wc_get_product($item->get_product_id());
        if ($parent) { foreach ($parent->get_attributes() as $name => $attribute) { if ($attribute instanceof \WC_Product_Attribute && $attribute->get_variation()) { $allowed[] = $name; $allowed[] = $attribute->get_name(); } } }
        foreach ($item->get_meta_data() as $meta) { $d = $meta->get_data(); if (isset($d['key'], $d['value']) && is_scalar($d['value']) && in_array($d['key'], $allowed, true)) { $rows[sanitize_text_field($d['key'])] = sanitize_text_field((string) $d['value']); } }
        return $rows;
    }
    public static function next($in) {
        if (is_wp_error($r = Catalog::require_wc())) { return $r; }
        return Core::lock('report-' . $in['report_id'], function () use ($in) {
            $j = self::load($in['report_id']); if (is_wp_error($j)) { return $j; }
            if (!$j['complete']) {
                $result = wc_get_orders(array('type' => 'shop_order', 'status' => array('wc-processing', 'wc-completed', 'wc-refunded'),
                    'date_created' => (new \DateTimeImmutable($j['range'][0] . ' 00:00:00', wp_timezone()))->getTimestamp() . '...' . (new \DateTimeImmutable($j['range'][1] . ' 23:59:59', wp_timezone()))->getTimestamp(),
                    'limit' => 50, 'page' => $j['page'], 'paginate' => true, 'orderby' => 'ID', 'order' => 'ASC'));
                foreach ($result->orders as $order) { self::accumulate($j, $order); }
                $j['complete'] = $j['page'] >= $result->max_num_pages; $j['page']++;
                set_transient('sci_report_' . $in['report_id'], $j, DAY_IN_SECONDS);
            }
            return self::public_report($j, $in['report_id']);
        });
    }
    public static function get($in) { $j = self::load($in['report_id']); return is_wp_error($j) ? $j : self::public_report($j, $in['report_id']); }
    public static function public_report($j, $id) {
        foreach ($j['currencies'] as &$s) { $s['average_order_total_before_refunds'] = $s['orders'] ? $s['order_total_including_shipping_tax'] / $s['orders'] : 0; foreach ($s as &$value) { if (is_float($value)) { $value = round($value, 6); } } unset($value); } unset($s);
        $out = array('report_id' => $id, 'complete' => $j['complete'], 'orders_processed' => $j['orders_processed'], 'date_range' => $j['range'], 'store_timezone' => wp_timezone_string(), 'started_utc' => $j['started'], 'currencies' => $j['currencies']);
        foreach (array('products', 'variations', 'categories') as $type) {
            $rows = array_values($j[$type]); usort($rows, function ($a, $b) { return strcmp($a['currency'], $b['currency']) ?: ($b['net_merchandise_ex_tax'] <=> $a['net_merchandise_ex_tax']); });
            $out[$type] = array_slice($rows, 0, 50); $out[$type . '_total_rows'] = count($rows);
        }
        $out['coupons'] = array_slice(array_values($j['coupons']), 0, 50); $out['coupons_total_rows'] = count($j['coupons']);
        $out['detail_pagination'] = 'Summary shows first 50 rows per section. Use sales-report-rows to retrieve all detail rows.';
        $out['customers_with_orders_in_period'] = count($j['customers']);
        $out['customers_with_multiple_orders_in_period'] = count(array_filter($j['customers'], function ($n) { return $n > 1; }));
        $out['definitions'] = array('date_basis' => 'Order creation date, not payment date. Statuses: processing, completed, refunded.',
            'refund_basis' => 'Lifetime refunds of selected orders at processing time, not refunds occurring during the date range. Unallocated order refunds are included only in order-level totals.',
            'category_basis' => 'Current directly assigned categories. Full attribution to every assigned category; category totals are not additive. No historical category reconstruction.',
            'customer_basis' => 'Repeat within selected cohort only, not lifetime new-versus-returning. No personal identifiers returned.',
            'consistency' => 'Live paginated read, not database snapshot. Avoid running across active bulk order changes. Empty/no-sale products are absent; join with inventory for zero-seller analysis.',
            'currency' => 'Currencies kept separate. No FX conversion. Product/variation names may reflect order-line names.');
        return $out;
    }
    public static function rows($in) {
        $j = self::load($in['report_id']); if (is_wp_error($j)) { return $j; }
        $rows = array_values($j[$in['section']]);
        usort($rows, function ($a, $b) { return strcmp($a['currency'], $b['currency']) ?: (($b['net_merchandise_ex_tax'] ?? $b['discount_ex_tax'] ?? 0) <=> ($a['net_merchandise_ex_tax'] ?? $a['discount_ex_tax'] ?? 0)); });
        $limit = $in['per_page'] ?? 50; $page = $in['page'] ?? 1;
        return array('report_id' => $in['report_id'], 'complete' => $j['complete'], 'section' => $in['section'], 'rows' => array_slice($rows, ($page - 1) * $limit, $limit), 'total' => count($rows), 'total_pages' => (int) ceil(count($rows) / $limit), 'page' => $page);
    }
    public static function inventory($in) {
        if (is_wp_error($r = Catalog::require_wc())) { return $r; }
        $q = new \WP_Query(array('post_type' => array('product', 'product_variation'), 'post_status' => array('publish', 'private', 'draft'), 'posts_per_page' => $in['per_page'] ?? 50, 'paged' => $in['page'] ?? 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC'));
        $rows = array(); foreach ($q->posts as $id) {
            $p = wc_get_product($id); if (!$p) { continue; }
            $qty = $p->get_stock_quantity(); $threshold = (int) wc_get_low_stock_amount($p);
            $rows[] = array('id' => $id, 'parent_id' => $p->get_parent_id(), 'type' => $p->get_type(), 'name' => $p->get_name(), 'sku' => $p->get_sku('edit'),
                'manage_stock' => $p->get_manage_stock('edit'), 'stock_quantity' => $qty, 'stock_status' => $p->get_stock_status(),
                'missing_sku' => $p->get_sku('edit') === '', 'missing_price' => !$p->is_type(array('variable', 'grouped')) && $p->get_price('edit') === '',
                'low_stock' => $p->managing_stock() && $qty !== null && $qty <= $threshold, 'low_stock_threshold' => $threshold);
        }
        return array('items' => $rows, 'total' => $q->found_posts, 'total_pages' => $q->max_num_pages, 'page' => $in['page'] ?? 1);
    }
}
