<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class SEO {
    public static function register() {
        Core::ability('seo-page-audit', 'Audit a public page', 'Independent HTML audit of titles, headings, metadata, canonical, robots, images, links, JSON-LD, HTTP redirects and mixed content. Same-origin public URLs only. Does not claim Google index status or browser/Core Web Vitals measurements.', Core::schema(array('url' => Core::field('string', array('format' => 'uri'))), array('url')), array(__CLASS__, 'audit'), 'manage_options');
        Core::ability('seo-crawl-start', 'Start a bounded site SEO audit', 'Seeds a resumable crawl from published public content and product categories. Follows discovered same-origin links without query strings. Maximum 2000 pages; never a guarantee of exhaustive coverage.', Core::schema(array('max_pages' => Core::field('integer', array('minimum' => 1, 'maximum' => 2000, 'default' => 100)))), array(__CLASS__, 'start'), 'manage_options');
        Core::ability('seo-crawl-next', 'Continue site SEO audit', 'Fetches up to 3 pages per request. State is retained for 24 hours; rerun until complete. Read-only crawling. WordPress cron is not required.', Core::schema(array('crawl_id' => Core::field('string', array('pattern' => '^[a-f0-9]{32}$'))), array('crawl_id')), array(__CLASS__, 'next'), 'manage_options');
        Core::ability('seo-crawl-get', 'Read site SEO audit', 'Returns coverage, issues and duplicate-title/description/content candidates, plus paginated page details. Potential orphan detection is limited to crawled pages. Scores are not Google rankings.', Core::schema(array_merge(Core::page_fields(), array('crawl_id' => Core::field('string', array('pattern' => '^[a-f0-9]{32}$')))), array('crawl_id')), array(__CLASS__, 'get'), 'manage_options');
        Core::ability('seo-site-files', 'Inspect robots.txt and sitemap entry points', 'Fetches robots.txt and known sitemap entry points without modifying them. Reports WordPress search visibility and available SEO integration.', Core::schema(), array(__CLASS__, 'files'), 'manage_options');
        Core::ability('seo-meta', 'Read saved SEO metadata', 'Reads supported Yoast or Rank Math saved fields, not a ranking score. With no SEO plugin, reports unavailable; crawl rendered output independently.', Core::schema(array('post_id' => Core::field('integer', array('minimum' => 1))), array('post_id')), array(__CLASS__, 'meta'), 'edit_others_posts');
        Core::ability('seo-meta-update', 'Preview or edit supported SEO metadata', 'Writes saved fields only for active Yoast or Rank Math. No native frontend SEO output is added in this version. Verify rendered page afterward.', Core::schema(array_merge(Core::mutation_fields(), array(
            'post_id' => Core::field('integer', array('minimum' => 1)), 'title' => Core::field('string'), 'description' => Core::field('string'),
            'focus_keyword' => Core::field('string'), 'canonical' => Core::field('string'), 'noindex' => Core::field('boolean')
        )), array('post_id')), array(__CLASS__, 'update_meta'), 'edit_others_posts', true);
        Core::ability('image-alt-update', 'Preview or edit media alt text', 'Updates an image attachment alt field, with permission and stale-record checks.', Core::schema(array_merge(Core::mutation_fields(), array('attachment_id' => Core::field('integer', array('minimum' => 1)), 'alt' => Core::field('string', array('maxLength' => 1000)))), array('attachment_id', 'alt')), array(__CLASS__, 'image_alt'), 'upload_files', true);
    }
    public static function origin($url) {
        $p = wp_parse_url($url); if (!$p || empty($p['host']) || empty($p['scheme'])) { return ''; }
        return strtolower($p['scheme']) . '://' . strtolower($p['host']) . ':' . ($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
    }
    public static function safe_url($url) {
        $url = esc_url_raw($url); $p = wp_parse_url($url);
        if (!$p || self::origin($url) !== self::origin(home_url('/')) || isset($p['user']) || isset($p['pass']) || !in_array($p['scheme'], array('http', 'https'), true)) { return Core::error('Only the exact home_url origin is allowed.'); }
        $path = $p['path'] ?? '/';
        for ($decode = 0; $decode < 3; $decode++) { $decoded = rawurldecode($path); if ($decoded === $path) { break; } $path = $decoded; }
        if (strpos($path, '\\') !== false || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path) || preg_match('/[\x00-\x1f\x7f]/', $path)) { return Core::error('Unsafe public page path.'); }
        if (preg_match('~(?:^|/)(?:wp-admin|wp-json|wp-login\.php|xmlrpc\.php|wp-cron\.php)(?:/|$)~i', $path) || !empty($p['query'])) { return Core::error('Only public pages without query strings are crawled.'); }
        return preg_replace('/#.*$/', '', $url);
    }
    public static function absolute($href, $base) {
        $href = html_entity_decode(trim($href), ENT_QUOTES, 'UTF-8');
        if ($href === '' || $href[0] === '#' || preg_match('~^(?:mailto|tel|javascript|data):~i', $href)) { return ''; }
        return \WP_Http::make_absolute_url($href, $base);
    }
    public static function fetch($url) {
        $chain = array(); $started = microtime(true);
        for ($i = 0; $i <= 3; $i++) {
            $valid = self::safe_url($url); if (is_wp_error($valid)) { return $valid; }
            $response = wp_safe_remote_get($valid, array('timeout' => 8, 'redirection' => 0, 'limit_response_size' => 1024 * 1024, 'user-agent' => 'MCP-Capability-Extender-Audit/' . SCI_VERSION . '; ' . home_url('/')));
            if (is_wp_error($response)) { return $response; }
            $status = wp_remote_retrieve_response_code($response); $chain[] = array('url' => $valid, 'status' => $status);
            if ($status >= 300 && $status < 400 && wp_remote_retrieve_header($response, 'location')) {
                $url = self::absolute(wp_remote_retrieve_header($response, 'location'), $valid); continue;
            }
            return array('url' => $valid, 'status' => $status, 'redirects' => $chain, 'response' => $response, 'fetch_seconds' => round(microtime(true) - $started, 3));
        }
        return Core::error('More than three redirects or redirect loop.', 'sci_redirect_limit');
    }
    public static function finding(&$issues, $code, $severity, $evidence, $action) { $issues[] = array('code' => $code, 'severity' => $severity, 'evidence' => $evidence, 'recommendation' => $action); }
    public static function parse_html($html, $url, $status = 200, $robots_header = '') {
        if (!class_exists('DOMDocument')) { return Core::error('PHP DOM extension is needed for HTML SEO audits.'); }
        $previous = libxml_use_internal_errors(true); $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); libxml_clear_errors(); libxml_use_internal_errors($previous);
        if (!$loaded) { return Core::error('Page HTML could not be parsed.'); }
        $xp = new \DOMXPath($dom); $issues = array();
        $text = function ($query) use ($xp) { $nodes = $xp->query($query); return $nodes && $nodes->length ? trim(preg_replace('/\s+/u', ' ', $nodes->item(0)->textContent)) : ''; };
        $attr = function ($query, $key) use ($xp) { $nodes = $xp->query($query); return $nodes && $nodes->length ? trim($nodes->item(0)->getAttribute($key)) : ''; };
        $lower = "translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $title = $text('//title'); $description = $attr('//meta[' . $lower . '="description"]', 'content');
        $canonical_raw = $attr('//link[contains(concat(" ",normalize-space(@rel)," ")," canonical ")]', 'href'); $canonical = self::absolute($canonical_raw, $url);
        $robots = $attr('//meta[' . $lower . '="robots"]', 'content'); $googlebot = $attr('//meta[' . $lower . '="googlebot"]', 'content');
        $h1 = array(); foreach ($xp->query('//h1') as $node) { $h1[] = trim($node->textContent); }
        if ($status >= 400) { self::finding($issues, 'http_error', 'error', $status, 'Repair, redirect or intentionally remove this URL.'); }
        if ($title === '') { self::finding($issues, 'missing_title', 'error', '', 'Add a descriptive page title.'); }
        if ($description === '') { self::finding($issues, 'missing_meta_description', 'warning', '', 'Write a useful description; Google may generate its own snippet.'); }
        if (count($h1) !== 1) { self::finding($issues, 'h1_review', 'review', count($h1), 'Review the main heading structure; multiple H1s are not automatically a ranking penalty.'); }
        if ($canonical === '') { self::finding($issues, 'missing_canonical', 'review', '', 'Review whether an explicit canonical would clarify duplicate URLs.'); }
        elseif ($canonical !== $url) { self::finding($issues, 'canonical_elsewhere', 'review', $canonical, 'Confirm the intended canonical target and inspect its status/indexability.'); }
        if (preg_match('/(?:^|[\s,:;])(?:noindex|none)(?=$|[\s,;])/i', $robots . ' ' . $googlebot . ' ' . $robots_header)) { self::finding($issues, 'noindex', 'review', array($robots, $googlebot, $robots_header), 'Confirm exclusion is intentional.'); }
        if (!$attr('//meta[' . $lower . '="viewport"]', 'content')) { self::finding($issues, 'missing_viewport', 'warning', '', 'Check mobile layout and add a viewport declaration if needed.'); }
        $images = array(); $missing_alt = 0; $empty_alt = 0; $no_dimensions = 0;
        foreach ($xp->query('//img') as $node) {
            if (!$node->hasAttribute('alt')) { $missing_alt++; } elseif (trim($node->getAttribute('alt')) === '') { $empty_alt++; }
            if (!$node->getAttribute('width') || !$node->getAttribute('height')) { $no_dimensions++; }
            if (count($images) < 30) { $images[] = array('src' => self::absolute($node->getAttribute('src'), $url), 'alt' => $node->getAttribute('alt'), 'width' => $node->getAttribute('width'), 'height' => $node->getAttribute('height')); }
        }
        if ($missing_alt) { self::finding($issues, 'images_missing_alt', 'warning', $missing_alt, 'Add useful alt text to informative images; decorative images should use empty alt.'); }
        if ($no_dimensions) { self::finding($issues, 'images_missing_dimensions', 'review', $no_dimensions, 'Inspect rendered layout shifts; CSS aspect ratio may already reserve space.'); }
        $internal = array(); $external = array(); $empty_anchor = 0; $mixed = array(); $hreflang = array(); $schemas = array();
        foreach ($xp->query('//a[@href]') as $node) {
            $link = self::absolute($node->getAttribute('href'), $url); if (!$link) { continue; }
            if (trim($node->textContent) === '' && !$node->getAttribute('aria-label') && !$node->getElementsByTagName('img')->length) { $empty_anchor++; }
            if (self::origin($link) === self::origin($url)) { $internal[$link] = true; } else { $external[$link] = true; }
        }
        foreach ($xp->query('//script[@src]|//img[@src]|//iframe[@src]|//video[@src]|//audio[@src]|//source[@src]|//link[@rel="stylesheet"]|//link[@rel="preload"]') as $node) { foreach (array('src', 'href') as $a) { $v = $node->getAttribute($a); if (strpos($url, 'https:') === 0 && strpos($v, 'http:') === 0) { $mixed[$v] = true; } } }
        if ($mixed) { self::finding($issues, 'mixed_content', 'error', array_keys($mixed), 'Serve page resources over HTTPS.'); }
        if ($empty_anchor) { self::finding($issues, 'unnamed_links', 'review', $empty_anchor, 'Check accessible link names in the rendered page.'); }
        foreach ($xp->query('//link[@hreflang]') as $node) { $hreflang[] = array('language' => $node->getAttribute('hreflang'), 'url' => self::absolute($node->getAttribute('href'), $url)); }
        foreach ($xp->query('//script[@type="application/ld+json"]') as $node) {
            $json = json_decode($node->textContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) { self::finding($issues, 'invalid_json_ld', 'error', json_last_error_msg(), 'Repair JSON-LD syntax.'); }
            else { self::schema_types($json, $schemas); }
        }
        foreach (iterator_to_array($xp->query('//script|//style|//nav|//header|//footer|//noscript')) as $node) { if ($node->parentNode) { $node->parentNode->removeChild($node); } }
        $body = $text('//body'); $words = preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY); $word_count = count($words ?: array());
        if ($word_count < 100) { self::finding($issues, 'short_content_candidate', 'review', $word_count, 'Assess usefulness for this page purpose. Word count is not a Google ranking threshold.'); }
        return array('url' => $url, 'http_status' => $status, 'title' => $title, 'meta_description' => $description, 'canonical' => $canonical,
            'robots_meta' => $robots, 'googlebot_meta' => $googlebot, 'robots_header' => (string) $robots_header, 'h1' => $h1, 'word_count_estimate' => $word_count,
            'content_hash' => hash('sha256', function_exists('mb_strtolower') ? mb_strtolower($body) : strtolower($body)), 'images_sample' => $images, 'images_missing_alt' => $missing_alt, 'images_empty_alt' => $empty_alt,
            'internal_links' => array_slice(array_keys($internal), 0, 500), 'external_links_sample' => array_slice(array_keys($external), 0, 50),
            'hreflang' => $hreflang, 'schema_types' => array_values(array_unique($schemas)), 'issues' => $issues,
            'limitations' => array('Server HTML only; no JavaScript rendering.', 'JSON-LD syntax and type extraction only, not full rich-result validation.', 'Links are extracted, not all fetched. Google index status is not inferred.'));
    }
    public static function schema_types($node, &$types) {
        if (!is_array($node)) { return; }
        if (isset($node['@type'])) { foreach ((array) $node['@type'] as $type) { if (is_string($type)) { $types[] = $type; } } }
        foreach ($node as $child) { if (is_array($child)) { self::schema_types($child, $types); } }
    }
    public static function audit($in) {
        $f = self::fetch($in['url']); if (is_wp_error($f)) { return $f; }
        $response = $f['response']; $body = wp_remote_retrieve_body($response);
        $type = wp_remote_retrieve_header($response, 'content-type');
        if ($type && stripos($type, 'html') === false) { return array('url' => $f['url'], 'http_status' => $f['status'], 'content_type' => $type, 'issues' => array(), 'skipped' => 'Non-HTML resource'); }
        $audit = self::parse_html($body, $f['url'], $f['status'], wp_remote_retrieve_header($response, 'x-robots-tag'));
        if (is_wp_error($audit)) { return $audit; }
        $audit['requested_url'] = $in['url']; $audit['redirect_chain'] = $f['redirects']; $audit['total_fetch_seconds'] = $f['fetch_seconds'];
        $audit['html_bytes_received'] = strlen($body); $audit['possibly_truncated'] = strlen($body) >= 1024 * 1024;
        return $audit;
    }
    public static function start($in) {
        $max = $in['max_pages'] ?? 100; $queue = array(home_url('/'));
        $types = get_post_types(array('public' => true)); unset($types['attachment']);
        $ids = get_posts(array('post_type' => array_values($types), 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => $max, 'orderby' => 'ID', 'order' => 'ASC'));
        foreach ($ids as $id) { $queue[] = get_permalink($id); }
        if (taxonomy_exists('product_cat')) { $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => min($max, 100))); if (!is_wp_error($terms)) { foreach ($terms as $term) { $link = get_term_link($term); if (!is_wp_error($link)) { $queue[] = $link; } } } }
        $queue = array_values(array_unique(array_filter($queue, function ($url) { return !is_wp_error(SEO::safe_url($url)); })));
        $id = md5(wp_generate_uuid4()); set_transient('sci_crawl_' . $id, array('owner' => get_current_user_id(), 'max' => $max, 'queue' => array_slice($queue, 0, $max), 'results' => array(), 'complete' => false, 'started' => gmdate('c')), DAY_IN_SECONDS);
        return array('crawl_id' => $id, 'complete' => false, 'queued' => min(count($queue), $max), 'max_pages' => $max, 'next_ability' => 'sternlabs/seo-crawl-next');
    }
    public static function load($id) {
        $j = get_transient('sci_crawl_' . $id); return (!$j || (int) $j['owner'] !== get_current_user_id()) ? Core::error('Crawl expired or belongs to another user.') : $j;
    }
    public static function next($in) {
        return Core::lock('crawl-' . $in['crawl_id'], function () use ($in) {
            $j = self::load($in['crawl_id']); if (is_wp_error($j)) { return $j; }
            for ($i = 0; $i < 3 && $j['queue'] && count($j['results']) < $j['max']; $i++) {
                $url = array_shift($j['queue']); if (isset($j['results'][$url])) { $i--; continue; }
                $audit = self::audit(array('url' => $url));
                $j['results'][$url] = is_wp_error($audit) ? array('url' => $url, 'error' => $audit->get_error_message()) : $audit;
                if (!is_wp_error($audit)) { foreach ($audit['internal_links'] ?? array() as $link) {
                    if (count($j['queue']) + count($j['results']) >= $j['max']) { break; }
                    $link = preg_replace('/#.*$/', '', $link);
                    if (!is_wp_error(self::safe_url($link)) && !isset($j['results'][$link]) && !in_array($link, $j['queue'], true) && !preg_match('/\.(?:jpg|jpeg|png|webp|gif|svg|pdf|zip|css|js|mp4|woff2?)(?:$|\?)/i', $link)) { $j['queue'][] = $link; }
                } }
                set_transient('sci_crawl_' . $in['crawl_id'], $j, DAY_IN_SECONDS);
            }
            $j['complete'] = !$j['queue'] || count($j['results']) >= $j['max'];
            set_transient('sci_crawl_' . $in['crawl_id'], $j, DAY_IN_SECONDS);
            return array('crawl_id' => $in['crawl_id'], 'complete' => $j['complete'], 'processed' => count($j['results']), 'queued' => count($j['queue']), 'next_ability' => $j['complete'] ? 'sternlabs/seo-crawl-get' : 'sternlabs/seo-crawl-next');
        });
    }
    public static function get($in) {
        $j = self::load($in['crawl_id']); if (is_wp_error($j)) { return $j; }
        $groups = array(); $inbound = array(); $issues = array();
        foreach ($j['results'] as $url => $r) {
            foreach (array('title', 'meta_description', 'content_hash') as $field) { if (!empty($r[$field])) { $key = strtolower(trim($r[$field])); $groups[$field][$key][] = $url; } }
            foreach ($r['internal_links'] ?? array() as $link) { $link = preg_replace('/#.*$/', '', $link); if ($link !== $url) { $inbound[$link] = ($inbound[$link] ?? 0) + 1; } }
            foreach ($r['issues'] ?? array() as $issue) { $issues[$issue['severity']] = ($issues[$issue['severity']] ?? 0) + 1; }
        }
        $duplicates = array(); foreach ($groups as $field => $values) { $duplicates[$field] = array_values(array_filter($values, function ($urls) { return count($urls) > 1; })); }
        $candidates = array(); foreach ($j['results'] as $url => $r) { if (!isset($inbound[$url]) && $url !== home_url('/')) { $candidates[] = $url; } }
        $limit = $in['per_page'] ?? 20; $page = $in['page'] ?? 1;
        return array('crawl_id' => $in['crawl_id'], 'complete' => $j['complete'], 'processed' => count($j['results']), 'max_pages' => $j['max'], 'started_utc' => $j['started'], 'page' => $page, 'total_pages' => (int) ceil(count($j['results']) / $limit),
            'issues_by_severity' => $issues, 'duplicate_candidates' => $duplicates, 'no_inbound_link_in_crawl_candidates' => $candidates, 'pages' => array_slice(array_values($j['results']), ($page - 1) * $limit, $limit),
            'coverage_note' => 'Bounded server-HTML crawl. Orphan and duplicate candidates require review. Query-string URLs, offsite redirects, JavaScript-only pages and pages outside the cap are not audited.');
    }
    public static function files() {
        $out = array('blog_public' => (bool) get_option('blog_public'), 'files' => array());
        foreach (array('/robots.txt', '/wp-sitemap.xml', '/sitemap_index.xml') as $path) {
            $url = home_url($path); $f = self::fetch($url);
            $out['files'][] = is_wp_error($f) ? array('url' => $url, 'error' => $f->get_error_message()) : array('url' => $f['url'], 'status' => $f['status'], 'content_sample' => substr(wp_remote_retrieve_body($f['response']), 0, 12000));
        }
        return $out;
    }
    public static function provider() { return defined('WPSEO_VERSION') ? 'yoast' : (defined('RANK_MATH_VERSION') ? 'rank_math' : 'none'); }
    public static function keys() {
        return self::provider() === 'yoast' ? array('title' => '_yoast_wpseo_title', 'description' => '_yoast_wpseo_metadesc', 'focus_keyword' => '_yoast_wpseo_focuskw', 'canonical' => '_yoast_wpseo_canonical', 'noindex' => '_yoast_wpseo_meta-robots-noindex') : array('title' => 'rank_math_title', 'description' => 'rank_math_description', 'focus_keyword' => 'rank_math_focus_keyword', 'canonical' => 'rank_math_canonical_url', 'robots' => 'rank_math_robots');
    }
    public static function meta($in) {
        if (!get_post($in['post_id']) || !current_user_can('edit_post', $in['post_id'])) { return Core::error('Post not found or access denied.'); }
        $fields = array(); if (self::provider() !== 'none') { foreach (self::keys() as $field => $key) { $fields[$field] = get_post_meta($in['post_id'], $key, true); } }
        return array('post_id' => $in['post_id'], 'provider' => self::provider(), 'saved_fields' => $fields, 'note' => 'Saved metadata may contain templates. Rendered HTML is the authoritative output.');
    }
    public static function content_schema() {
        $schema = Core::schema(array('title' => Core::field('string'), 'description' => Core::field('string'), 'focus_keyword' => Core::field('string')));
        $schema['minProperties'] = 1;
        return $schema;
    }
    public static function prepare_content($post_id, $input) {
        if (!current_user_can('edit_others_posts')) { return Core::error('SEO edits require edit_others_posts.', 'sci_seo_access'); }
        if (!is_array($input) || !$input || array_diff(array_keys($input), array('title', 'description', 'focus_keyword'))) { return Core::error('SEO content accepts only title, description and focus_keyword.'); }
        $before = self::meta(array('post_id' => $post_id));
        if (is_wp_error($before)) { return $before; }
        if ($before['provider'] === 'none') { return Core::error('No supported SEO plugin active. Content-only edits remain available.', 'sci_seo_unavailable'); }
        $changes = array();
        foreach ($input as $key => $value) {
            if (!is_string($value)) { return Core::error('SEO content values must be strings.'); }
            $changes[$key] = sanitize_text_field($value);
        }
        ksort($changes);
        return array('before' => $before, 'changes' => $changes);
    }
    // Shared persistence; callers supply a validated, preview-protected plan.
    public static function persist_meta($post_id, $changes, $provider) {
        if (!current_user_can('edit_others_posts') || !current_user_can('edit_post', $post_id)) { return Core::error('SEO access denied.', 'sci_seo_access'); }
        if ($provider === 'none' || $provider !== self::provider()) { return Core::error('SEO provider changed. Generate a fresh preview.', 'sci_stale_preview'); }
        $keys = self::keys();
        foreach ($changes as $field => $value) {
            if ($field === 'noindex') {
                if ($provider === 'yoast') { update_post_meta($post_id, $keys[$field], $value ? '1' : '2'); }
                else { $robots = (array) get_post_meta($post_id, 'rank_math_robots', true); $robots = array_values(array_diff($robots, array('index', 'noindex'))); $robots[] = $value ? 'noindex' : 'index'; update_post_meta($post_id, 'rank_math_robots', $robots); }
            } else { update_post_meta($post_id, $keys[$field], wp_slash($value)); }
        }
        // Refresh once, after both WooCommerce content and SEO metadata are saved.
        if ($provider === 'yoast' && function_exists('YoastSEO')) {
            $yoast = YoastSEO();
            if (isset($yoast->classes)) { $builder = $yoast->classes->get('Yoast\\WP\\SEO\\Builders\\Indexable_Builder'); $builder->build_for_id_and_type($post_id, 'post'); }
        }
        clean_post_cache($post_id);
        return self::meta(array('post_id' => $post_id));
    }
    public static function update_meta($in) {
        $before = self::meta($in); if (is_wp_error($before)) { return $before; }
        if ($before['provider'] === 'none') { return Core::error('No supported SEO plugin active. Independent audits work, but native SEO output is not implemented in this release.'); }
        $changes = array(); foreach (array('title', 'description', 'focus_keyword', 'canonical') as $field) {
            if (isset($in[$field])) { if ($field === 'canonical' && $in[$field] !== '' && !wp_http_validate_url($in[$field])) { return Core::error('Canonical must be a public HTTP(S) URL or empty.'); } $changes[$field] = sanitize_text_field($in[$field]); }
        }
        if (isset($in['noindex'])) { $changes['noindex'] = $in['noindex']; }
        return Core::mutate('seo-meta-update', $in, array('before' => $before, 'changes' => $changes), function () use ($in, $changes) {
            return self::persist_meta($in['post_id'], $changes, self::provider());
        });
    }
    public static function image_alt($in) {
        if (get_post_type($in['attachment_id']) !== 'attachment' || !wp_attachment_is_image($in['attachment_id']) || !current_user_can('edit_post', $in['attachment_id'])) { return Core::error('Image not found or access denied.'); }
        $alt = sanitize_text_field($in['alt']);
        return Core::mutate('image-alt-update', $in, array('id' => $in['attachment_id'], 'before' => get_post_meta($in['attachment_id'], '_wp_attachment_image_alt', true), 'after' => $alt), function () use ($in, $alt) { update_post_meta($in['attachment_id'], '_wp_attachment_image_alt', $alt); return array('attachment_id' => $in['attachment_id'], 'alt' => $alt); });
    }
}
