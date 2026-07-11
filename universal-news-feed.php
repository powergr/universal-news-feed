<?php
/**
 * Plugin Name:       Universal News Feed
 * Description:       A configurable, resilient plugin to display a cached news feed from any RSS sources via a shortcode.
 * Version:           2.1
 * Author:            Pashalis Laoutaris
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       universal-news-feed
 */

if (!defined('ABSPATH')) exit;

class Universal_News_Feed_Plugin {
    private $shortcode_tag = 'latest-news-feed';

    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'plugin_activation']);
    }

    public function init() {
        // One-time cleanup: this plugin's cron hook was previously named "fetch_fashion_news_hook".
        // If that event is still scheduled from before the rename, remove it so it doesn't sit there
        // firing into nothing every interval.
        $legacy_cron_time = wp_next_scheduled('fetch_fashion_news_hook');
        if ($legacy_cron_time) { wp_unschedule_event($legacy_cron_time, 'fetch_fashion_news_hook'); }

        $options = get_option('lfn_settings', []);
        $this->shortcode_tag = isset($options['shortcode_tag']) && !empty($options['shortcode_tag']) ? $options['shortcode_tag'] : 'latest-news-feed';
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']); add_action('admin_menu', [$this, 'add_admin_menu']); add_action('admin_init', [$this, 'setup_settings_fields']); add_action('admin_init', [$this, 'handle_force_refresh']); add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']); add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']); add_shortcode($this->shortcode_tag, [$this, 'render_shortcode']); add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']); add_action('unf_fetch_news_hook', [$this, 'fetch_and_cache_news_data']);
        if (!wp_next_scheduled('unf_fetch_news_hook')) { $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'; wp_schedule_event(time(), $frequency, 'unf_fetch_news_hook'); }
    }
    public function plugin_activation() { if (!wp_next_scheduled('unf_fetch_news_hook')) { $options = get_option('lfn_settings', []); $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'; wp_schedule_event(time(), $frequency, 'unf_fetch_news_hook'); } wp_schedule_single_event(time() + 10, 'unf_fetch_news_hook'); }
    public function add_settings_link($links) { $settings_link = '<a href="options-general.php?page=universal_news_feed_settings">' . esc_html__('Settings', 'universal-news-feed') . '</a>'; array_unshift($links, $settings_link); return $links; }
    public function add_custom_cron_schedules($schedules) { $schedules['twice_a_day'] = ['interval' => 43200, 'display' => __('Twice a Day', 'universal-news-feed')]; return $schedules; }
    public function add_admin_menu() { add_options_page(__('Universal News Feed', 'universal-news-feed'), __('Universal News Feed', 'universal-news-feed'), 'manage_options', 'universal_news_feed_settings', [$this, 'render_settings_page']); }

    public function render_settings_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which settings tab to display; no data is read or written from this value.
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('News Feed Settings', 'universal-news-feed'); ?></h1>
            <div class="notice notice-info inline" style="display:flex; justify-content: space-between; align-items: center; padding: 12px; margin-top: 15px;"><div><p style="margin: 0 0 10px 0;"><strong><?php esc_html_e('Active Shortcode:', 'universal-news-feed'); ?></strong> <code>[<?php echo esc_html($this->shortcode_tag); ?>]</code></p><p style="margin: 0 0 10px 0;"><strong><?php esc_html_e('Filter by source:', 'universal-news-feed'); ?></strong> <code>[<?php echo esc_html($this->shortcode_tag); ?> source="Source Name"]</code> <span style="color:#666;"><?php esc_html_e('— shows only that source. Use the exact Source Name from the Feeds tab below; comma-separate multiple names, e.g.', 'universal-news-feed'); ?> <code>source="Source One, Source Two"</code>. <?php esc_html_e('Add several shortcodes with different source values to build separate sections on one page.', 'universal-news-feed'); ?></span></p><p style="margin: 0;"><strong><?php esc_html_e('Last Update:', 'universal-news-feed'); ?></strong> <?php $last_update_gmt = get_option('lfn_last_update_timestamp'); echo $last_update_gmt ? esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $last_update_gmt), get_option('date_format') . ' ' . get_option('time_format'))) : esc_html__('Never', 'universal-news-feed'); ?> <em style="display: block; font-size: 12px; color: #666;"><?php
                /* translators: %s: URL of the General Settings screen, where the site timezone can be changed. */
                printf(wp_kses(__('Time is based on your <a href="%s">WordPress timezone setting</a>.', 'universal-news-feed'), ['a' => ['href' => []]]), esc_url(admin_url('options-general.php')));
            ?></em> </p></div><form method="post" action=""><input type="hidden" name="lfn_force_refresh" value="1"><?php wp_nonce_field('lfn_force_refresh_action'); submit_button(__('Force Refresh Now', 'universal-news-feed'), 'secondary', 'lfn_force_refresh_submit', false); ?></form></div>
            <h2 class="nav-tab-wrapper"><a href="?page=universal_news_feed_settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'universal-news-feed'); ?></a><a href="?page=universal_news_feed_settings&tab=style" class="nav-tab <?php echo $active_tab == 'style' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Style', 'universal-news-feed'); ?></a><a href="?page=universal_news_feed_settings&tab=feeds" class="nav-tab <?php echo $active_tab == 'feeds' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Feeds & Images', 'universal-news-feed'); ?></a><a href="?page=universal_news_feed_settings&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Advanced', 'universal-news-feed'); ?></a></h2>
            <form action="options.php" method="post">
                <?php settings_fields('lfn_settings_group'); if ($active_tab == 'general') { do_settings_sections('lfn_general_section'); } if ($active_tab == 'style') { do_settings_sections('lfn_style_section'); } if ($active_tab == 'feeds') { do_settings_sections('lfn_feeds_section'); do_settings_sections('lfn_placeholders_section'); } if ($active_tab == 'advanced') { do_settings_sections('lfn_advanced_section'); } submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function handle_force_refresh() { if (isset($_POST['lfn_force_refresh_submit']) && current_user_can('manage_options')) { check_admin_referer('lfn_force_refresh_action'); $this->fetch_and_cache_news_data(); add_action('admin_notices', function() { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('News feed cache has been successfully refreshed.', 'universal-news-feed') . '</p></div>'; }); } }
    public function setup_settings_fields() { register_setting('lfn_settings_group', 'lfn_settings', [$this, 'sanitize_settings']); add_settings_section('lfn_general_section', false, null, 'lfn_general_section'); add_settings_field('lfn_feed_title_field', __('Feed Title', 'universal-news-feed'), [$this, 'render_feed_title_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_field('lfn_load_more_field', __('Pagination', 'universal-news-feed'), [$this, 'render_load_more_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_field('lfn_items_per_page_field', __('Items Per Page', 'universal-news-feed'), [$this, 'render_items_per_page_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_section('lfn_style_section', false, null, 'lfn_style_section'); add_settings_field('lfn_layout_field', __('Feed Layout', 'universal-news-feed'), [$this, 'render_layout_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_title_color_field', __('Title Color', 'universal-news-feed'), [$this, 'render_title_color_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_source_color_field', __('Source Name Color', 'universal-news-feed'), [$this, 'render_source_color_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_custom_css_field', __('Custom CSS', 'universal-news-feed'), [$this, 'render_custom_css_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_section('lfn_feeds_section', __('RSS Feed Sources', 'universal-news-feed'), null, 'lfn_feeds_section'); add_settings_field('lfn_rss_feeds_field', __('Feeds', 'universal-news-feed'), [$this, 'render_feeds_field'], 'lfn_feeds_section', 'lfn_feeds_section'); add_settings_section('lfn_placeholders_section', __('Placeholder Images', 'universal-news-feed'), null, 'lfn_placeholders_section'); add_settings_field('lfn_placeholders_field', __('Images', 'universal-news-feed'), [$this, 'render_placeholders_field'], 'lfn_placeholders_section', 'lfn_placeholders_section'); add_settings_section('lfn_advanced_section', false, null, 'lfn_advanced_section'); add_settings_field('lfn_update_frequency_field', __('Update Frequency', 'universal-news-feed'), [$this, 'render_update_frequency_field'], 'lfn_advanced_section', 'lfn_advanced_section'); add_settings_field('lfn_shortcode_field', __('Custom Shortcode', 'universal-news-feed'), [$this, 'render_shortcode_field'], 'lfn_advanced_section', 'lfn_advanced_section'); }
    public function render_feed_title_field() { $options = get_option('lfn_settings'); $title = isset($options['feed_title']) ? $options['feed_title'] : 'Latest News'; echo '<input type="text" name="lfn_settings[feed_title]" value="' . esc_attr($title) . '" size="40">'; }
    public function render_load_more_field() { $options = get_option('lfn_settings'); $load_more = isset($options['load_more_enabled']) ? $options['load_more_enabled'] : 1; echo '<input type="checkbox" name="lfn_settings[load_more_enabled]" value="1"' . checked(1, $load_more, false) . '> ' . esc_html__('Enable "Load More" button', 'universal-news-feed'); }
    public function render_items_per_page_field() { $options = get_option('lfn_settings'); $items = isset($options['items_per_page']) ? $options['items_per_page'] : 8; echo '<input type="number" name="lfn_settings[items_per_page]" value="' . esc_attr($items) . '" min="1" max="100">'; }
    public function render_update_frequency_field() { $options = get_option('lfn_settings'); $current = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'; $schedules = ['hourly' => __('Hourly', 'universal-news-feed'), 'twicedaily' => __('Twice a Day', 'universal-news-feed'), 'daily' => __('Daily', 'universal-news-feed')]; echo '<select name="lfn_settings[update_frequency]">'; foreach ($schedules as $value => $label) { echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>'; } echo '</select>'; }
    public function render_shortcode_field() { $options = get_option('lfn_settings'); $tag = isset($options['shortcode_tag']) && !empty($options['shortcode_tag']) ? $options['shortcode_tag'] : 'latest-news-feed'; echo '<input type="text" name="lfn_settings[shortcode_tag]" value="' . esc_attr($tag) . '"><p class="description">' . esc_html__('Use simple, lowercase letters and dashes only.', 'universal-news-feed') . '</p>'; }
    public function render_layout_field() { $options = get_option('lfn_settings'); $layout = isset($options['layout']) ? $options['layout'] : 'list'; echo '<select name="lfn_settings[layout]"><option value="list" ' . selected($layout, 'list', false) . '>' . esc_html__('List', 'universal-news-feed') . '</option><option value="grid" ' . selected($layout, 'grid', false) . '>' . esc_html__('Grid', 'universal-news-feed') . '</option></select>'; }
    public function render_title_color_field() { $options = get_option('lfn_settings'); $color = isset($options['title_color']) ? $options['title_color'] : '#1c1e21'; echo '<input type="text" name="lfn_settings[title_color]" value="' . esc_attr($color) . '" class="lfn-color-picker">'; }
    public function render_source_color_field() { $options = get_option('lfn_settings'); $color = isset($options['source_color']) ? $options['source_color'] : '#65676b'; echo '<input type="text" name="lfn_settings[source_color]" value="' . esc_attr($color) . '" class="lfn-color-picker">'; }
    public function render_custom_css_field() { $options = get_option('lfn_settings'); $css = isset($options['custom_css']) ? $options['custom_css'] : ''; echo '<textarea name="lfn_settings[custom_css]" rows="8" cols="50" class="large-text code">' . esc_textarea($css) . '</textarea><p class="description"><strong>' . esc_html__('How to use Custom CSS:', 'universal-news-feed') . '</strong><br>' . esc_html__('Key Classes:', 'universal-news-feed') . ' <code>.lfn-container</code>, <code>.news-item</code>, <code>.news-title a</code></p>'; }
    public function render_feeds_field() { $options = get_option('lfn_settings'); ?><div id="lfn-feeds-container"><p class="description"><?php esc_html_e('Add the name and URL for each RSS feed source.', 'universal-news-feed'); ?></p><?php $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : []; if(!empty($feeds)){foreach($feeds as $index=>$feed){?><div class="lfn-feed-row"><input type="text" name="lfn_settings[rss_feeds][<?php echo absint($index);?>][name]" value="<?php echo esc_attr($feed['name']);?>" placeholder="<?php esc_attr_e('Source Name', 'universal-news-feed');?>" size="30"/><input type="url" name="lfn_settings[rss_feeds][<?php echo absint($index);?>][url]" value="<?php echo esc_attr($feed['url']);?>" placeholder="<?php esc_attr_e('RSS Feed URL', 'universal-news-feed');?>" size="50"/><button type="button" class="button lfn-remove-feed"><?php esc_html_e('Remove', 'universal-news-feed'); ?></button></div><?php }}?></div><button type="button" class="button" id="lfn-add-feed"><?php esc_html_e('Add Feed', 'universal-news-feed'); ?></button><?php }
    public function render_placeholders_field() { $options = get_option('lfn_settings'); ?><div id="lfn-placeholders-container"><p class="description"><?php esc_html_e('Select images from your Media Library.', 'universal-news-feed'); ?></p><?php $placeholders = isset($options['placeholders']) ? $options['placeholders'] : []; if(!empty($placeholders)){foreach($placeholders as $index=>$url){?><div class="lfn-placeholder-item"><img src="<?php echo esc_url($url);?>"/><input type="hidden" name="lfn_settings[placeholders][]" value="<?php echo esc_url($url);?>"><button type="button" class="button lfn-remove-placeholder"><?php esc_html_e('Remove', 'universal-news-feed');?></button></div><?php }}?></div><button type="button" class="button" id="lfn-add-placeholder"><?php esc_html_e('Add Placeholder Image', 'universal-news-feed'); ?></button><style> .lfn-feed-row{margin-bottom:10px;} #lfn-placeholders-container{display:flex;flex-wrap:wrap;gap:15px;} .lfn-placeholder-item{position:relative;} .lfn-placeholder-item img{width:100px;height:100px;object-fit:cover;border:1px solid #ddd;} .lfn-placeholder-item button{position:absolute;top:5px;right:5px;} </style><?php }
    public function enqueue_admin_assets($hook) { if ($hook !== 'settings_page_universal_news_feed_settings') { return; } wp_enqueue_media(); wp_enqueue_style('wp-color-picker'); wp_enqueue_script('lfn-admin-script', plugin_dir_url(__FILE__) . 'admin-scripts.js', ['jquery', 'wp-color-picker'], '2.1', true); }
    public function sanitize_settings($input) { $old_options = get_option('lfn_settings', []); $new_input = $input; $merged_input = array_merge($old_options, $new_input); $sanitized_input = []; $schedules = wp_get_schedules(); if(isset($merged_input['update_frequency']) && array_key_exists($merged_input['update_frequency'], $schedules)) { if(!isset($old_options['update_frequency']) || $old_options['update_frequency'] !== $merged_input['update_frequency']) { $this->reschedule_cron_job($merged_input['update_frequency']); } $sanitized_input['update_frequency'] = $merged_input['update_frequency']; } if(isset($merged_input['feed_title'])) { $sanitized_input['feed_title'] = sanitize_text_field($merged_input['feed_title']); } $sanitized_input['load_more_enabled'] = isset($merged_input['load_more_enabled'])?1:0; if(isset($merged_input['items_per_page'])){$items=absint($merged_input['items_per_page']);$sanitized_input['items_per_page']=($items>0)?$items:8;} if(isset($merged_input['shortcode_tag'])){$tag=sanitize_key($merged_input['shortcode_tag']);$sanitized_input['shortcode_tag']=!empty($tag)?$tag:'latest-news-feed';} if(isset($merged_input['layout'])){$sanitized_input['layout']=in_array($merged_input['layout'],['list','grid'])?$merged_input['layout']:'list';} if(isset($merged_input['title_color'])){$sanitized_input['title_color']=sanitize_hex_color($merged_input['title_color']);} if(isset($merged_input['source_color'])){$sanitized_input['source_color']=sanitize_hex_color($merged_input['source_color']);} if(isset($merged_input['custom_css'])){$sanitized_input['custom_css']=wp_strip_all_tags($merged_input['custom_css']);} if(isset($merged_input['rss_feeds'])){$sanitized_input['rss_feeds']=[];foreach($merged_input['rss_feeds'] as $feed){if(!empty(trim($feed['name']))&&!empty(trim($feed['url']))){$sanitized_input['rss_feeds'][]=['name'=>sanitize_text_field($feed['name']),'url'=>esc_url_raw($feed['url'])];}}} if(isset($merged_input['placeholders'])){$sanitized_input['placeholders']=array_map('esc_url_raw',$merged_input['placeholders']);}elseif(array_key_exists('placeholders',$input)){$sanitized_input['placeholders']=[];} wp_cache_flush(); if(class_exists('LiteSpeed_Cache_API')){LiteSpeed_Cache_API::purge_all();} $this->fetch_and_cache_news_data($sanitized_input); return $sanitized_input; }
    public function enqueue_public_assets() { $options = get_option('lfn_settings'); $title_color = isset($options['title_color']) ? $options['title_color'] : '#1c1e21'; $source_color = isset($options['source_color']) ? $options['source_color'] : '#65676b'; $custom_css = isset($options['custom_css']) ? $options['custom_css'] : ''; $dynamic_css = ":root { --lfn-title-color: " . esc_attr($title_color) . "; --lfn-source-color: " . esc_attr($source_color) . "; }"; $dynamic_css .= $custom_css; wp_add_inline_style('universal-news-feed-style', $dynamic_css); wp_enqueue_style('universal-news-feed-style', plugin_dir_url(__FILE__) . 'universal-news-feed.css', [], '2.1'); wp_enqueue_script('universal-news-feed-script', plugin_dir_url(__FILE__) . 'universal-news-feed.js', [], '2.1', true); }
    public function render_shortcode($atts = []) {
        $atts = shortcode_atts(['source' => ''], $atts, $this->shortcode_tag);
        $options = get_option('lfn_settings');
        $layout = isset($options['layout']) ? $options['layout'] : 'list';
        $title = isset($options['feed_title']) ? $options['feed_title'] : 'Latest News';
        $all_items = get_transient('universal_news_feed_data') ?: [];

        if (!empty(trim($atts['source']))) {
            $wanted_sources = array_map('strtolower', array_map('trim', explode(',', $atts['source'])));
            $all_items = array_values(array_filter($all_items, function($item) use ($wanted_sources) {
                return isset($item['sourceName']) && in_array(strtolower($item['sourceName']), $wanted_sources, true);
            }));
        }

        $instance_data = [
            'posts' => $all_items,
            'placeholders' => isset($options['placeholders']) ? $options['placeholders'] : [],
            'load_more_enabled' => isset($options['load_more_enabled']) ? $options['load_more_enabled'] : 1,
            'items_per_page' => isset($options['items_per_page']) ? $options['items_per_page'] : 8,
        ];
        $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        ob_start();
        ?><div class="lfn-container lfn-layout-<?php echo esc_attr($layout); ?>"><h2><?php echo esc_html($title); ?></h2><div class="lfn-loading"><?php esc_html_e('Loading news...', 'universal-news-feed'); ?></div><div class="lfn-feed"></div><div class="lfn-load-more-container"></div><script type="application/json" class="lfn-data"><?php echo wp_json_encode($instance_data, $json_flags); ?></script></div><?php
        return ob_get_clean();
    }
    public function reschedule_cron_job($new_frequency = null) { $timestamp = wp_next_scheduled('unf_fetch_news_hook'); if ($timestamp) { wp_unschedule_event($timestamp, 'unf_fetch_news_hook'); } $options = get_option('lfn_settings'); $frequency = $new_frequency ? $new_frequency : (isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'); wp_schedule_event(time(), $frequency, 'unf_fetch_news_hook'); }
    
    private function extract_first_image_url($html) {
        if (empty($html)) { return ''; }
        // Many WordPress sites lazy-load images: the real URL sits in data-src / data-lazy-src / data-original,
        // while src holds a tiny placeholder. Check these first.
        if (preg_match('/<img[^>]+(?:data-src|data-lazy-src|data-original)=["\']([^"\']+)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        // Next, try srcset and take the first URL listed.
        if (preg_match('/<img[^>]+srcset=["\']([^"\'\s]+)/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        // Finally, fall back to a plain src attribute.
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        return '';
    }

    private function fetch_og_image($article_url) {
        if (empty($article_url)) { return ''; }
        $response = wp_remote_get($article_url, [ 'timeout' => 10, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36' ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return ''; }
        $body = wp_remote_retrieve_body($response);
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $matches)) { return html_entity_decode($matches[1]); }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $body, $matches)) { return html_entity_decode($matches[1]); }
        return '';
    }

    // Returns how long the cached feed data should live, based on the configured update
    // frequency, with a small buffer so the cache doesn't expire just before the next
    // scheduled fetch runs (e.g. a "Daily" setting shouldn't leave the feed empty for hours).
    private function get_cache_expiration_seconds($frequency) {
        $schedules = wp_get_schedules();
        if (isset($schedules[$frequency]['interval'])) {
            return $schedules[$frequency]['interval'] + (15 * MINUTE_IN_SECONDS);
        }
        return HOUR_IN_SECONDS;
    }

    public function fetch_and_cache_news_data($options_to_use = null) {
        include_once(ABSPATH . WPINC . '/feed.php');
        $current_options = is_array($options_to_use) ? $options_to_use : get_option('lfn_settings');
        $rssFeeds = isset($current_options['rss_feeds']) ? $current_options['rss_feeds'] : [];
        $frequency = isset($current_options['update_frequency']) ? $current_options['update_frequency'] : 'hourly';
        $cache_expiration = $this->get_cache_expiration_seconds($frequency);
        if (empty($rssFeeds)) { set_transient('universal_news_feed_data', [], $cache_expiration); return; }
        $all_news = [];
        foreach ($rssFeeds as $feed) {
            $items = null;
            // Always try the external service first for richer image data; the internal
            // parser below runs automatically as a fallback if this doesn't return results.
            $response = wp_remote_get('https://api.rss2json.com/v1/api.json?rss_url=' . urlencode($feed['url']), ['timeout' => 20]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) { $data = json_decode(wp_remote_retrieve_body($response), true); if ($data && $data['status'] === 'ok') { $items = $data['items']; foreach ($items as &$item) { $has_enclosure_image = !empty($item['enclosure']['link']) && !empty($item['enclosure']['type']) && strpos($item['enclosure']['type'], 'image') === 0; if (empty($item['thumbnail']) && !$has_enclosure_image) { $html_to_scan = !empty($item['content']) ? $item['content'] : (isset($item['description']) ? $item['description'] : ''); $found_image = $this->extract_first_image_url($html_to_scan); if (!$found_image && !empty($item['link'])) { $found_image = $this->fetch_og_image($item['link']); } if ($found_image) { $item['thumbnail'] = $found_image; } } } unset($item); } }
            if (is_null($items)) {
                $rss = fetch_feed($feed['url']);
                if (!is_wp_error($rss)) {
                    $items = [];
                    foreach ($rss->get_items(20) as $item) {
                        $enclosure = $item->get_enclosure();
                        $thumbnail = '';
                        // New, smarter image finding logic
                        if ($enclosure && $enclosure->get_thumbnail()) { $thumbnail = $enclosure->get_thumbnail(); }
                        elseif ($enclosure && $enclosure->get_link()) { $thumbnail = $enclosure->get_link(); }
                        else { $media_tags = $item->get_item_tags('http://search.yahoo.com/mrss/', 'content'); if ($media_tags && isset($media_tags[0]['attribs']['']['url'])) { $thumbnail = $media_tags[0]['attribs']['']['url']; } }
                        if (empty($thumbnail)) { $full_content = $item->get_content(); $thumbnail = $this->extract_first_image_url($full_content); }
                        if (empty($thumbnail)) { $thumbnail = $this->extract_first_image_url($item->get_description()); }
                        if (empty($thumbnail)) { $thumbnail = $this->fetch_og_image($item->get_permalink()); }
                        $items[] = [ 'title' => $item->get_title(), 'pubDate' => $item->get_date('Y-m-d H:i:s'), 'link' => $item->get_permalink(), 'description' => $item->get_description(), 'thumbnail' => $thumbnail, 'enclosure' => [], ];
                    }
                }
            }
            if (!is_null($items)) { $processed_items = []; foreach($items as $item) { $item['sourceName'] = $feed['name']; $processed_items[] = $item; } $all_news = array_merge($all_news, $processed_items); }
        }
        usort($all_news, function($a, $b) { return strtotime($b['pubDate']) - strtotime($a['pubDate']); });
        set_transient('universal_news_feed_data', $all_news, $cache_expiration);
        update_option('lfn_last_update_timestamp', time());
    }
}
new Universal_News_Feed_Plugin();