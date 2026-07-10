<?php
/**
 * Plugin Name:       Universal News Feed
 * Description:       A configurable, resilient plugin to display a cached news feed from any RSS sources via a shortcode.
 * Version:           1.0
 * Author:            Pashalis Laoutaris
 * Text Domain:       universal-news-feed
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) exit;

class Latest_Fashion_News_Plugin {
    private $shortcode_tag = 'latest-news-feed';

    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'plugin_activation']);
    }

    public function init() {
        $options = get_option('lfn_settings', []);
        $this->shortcode_tag = isset($options['shortcode_tag']) && !empty($options['shortcode_tag']) ? $options['shortcode_tag'] : 'latest-news-feed';
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']); add_action('plugins_loaded', [$this, 'load_textdomain']); add_action('admin_menu', [$this, 'add_admin_menu']); add_action('admin_init', [$this, 'setup_settings_fields']); add_action('admin_init', [$this, 'handle_force_refresh']); add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']); add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']); add_shortcode($this->shortcode_tag, [$this, 'render_shortcode']); add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']); add_action('fetch_fashion_news_hook', [$this, 'fetch_and_cache_news_data']);
        if (!wp_next_scheduled('fetch_fashion_news_hook')) { $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'; wp_schedule_event(time(), $frequency, 'fetch_fashion_news_hook'); }
    }
    public function plugin_activation() { if (!wp_next_scheduled('fetch_fashion_news_hook')) { $options = get_option('lfn_settings', []); $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'; wp_schedule_event(time(), $frequency, 'fetch_fashion_news_hook'); } wp_schedule_single_event(time() + 10, 'fetch_fashion_news_hook'); }
    public function add_settings_link($links) { $settings_link = '<a href="options-general.php?page=fashion_news_feed">' . __('Settings', 'latest-fashion-news-feed') . '</a>'; array_unshift($links, $settings_link); return $links; }
    public function add_custom_cron_schedules($schedules) { $schedules['twice_a_day'] = ['interval' => 43200, 'display' => __('Twice a Day', 'latest-fashion-news-feed')]; return $schedules; }
    public function load_textdomain() { load_plugin_textdomain('latest-fashion-news-feed', false, dirname(plugin_basename(__FILE__)) . '/languages'); }
    public function add_admin_menu() { add_options_page(__('News Feed', 'latest-fashion-news-feed'), __('News Feed', 'latest-fashion-news-feed'), 'manage_options', 'fashion_news_feed', [$this, 'render_settings_page']); }

    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1><?php _e('News Feed Settings', 'latest-fashion-news-feed'); ?></h1>
            <div class="notice notice-info inline" style="display:flex; justify-content: space-between; align-items: center; padding: 12px; margin-top: 15px;"><div><p style="margin: 0 0 10px 0;"><strong><?php _e('Active Shortcode:', 'latest-fashion-news-feed'); ?></strong> <code>[<?php echo esc_html($this->shortcode_tag); ?>]</code></p><p style="margin: 0;"><strong><?php _e('Last Update:', 'latest-fashion-news-feed'); ?></strong> <?php $last_update_gmt = get_option('lfn_last_update_timestamp'); echo $last_update_gmt ? esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $last_update_gmt), get_option('date_format') . ' ' . get_option('time_format'))) : __('Never', 'latest-fashion-news-feed'); ?> <em style="display: block; font-size: 12px; color: #666;"><?php printf(__('Time is based on your <a href="%s">WordPress timezone setting</a>.', 'latest-fashion-news-feed'), esc_url(admin_url('options-general.php'))); ?></em> </p></div><form method="post" action=""><input type="hidden" name="lfn_force_refresh" value="1"><?php wp_nonce_field('lfn_force_refresh_action'); submit_button(__('Force Refresh Now', 'latest-fashion-news-feed'), 'secondary', 'lfn_force_refresh_submit', false); ?></form></div>
            <h2 class="nav-tab-wrapper"><a href="?page=fashion_news_feed&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'latest-fashion-news-feed'); ?></a><a href="?page=fashion_news_feed&tab=style" class="nav-tab <?php echo $active_tab == 'style' ? 'nav-tab-active' : ''; ?>"><?php _e('Style', 'latest-fashion-news-feed'); ?></a><a href="?page=fashion_news_feed&tab=feeds" class="nav-tab <?php echo $active_tab == 'feeds' ? 'nav-tab-active' : ''; ?>"><?php _e('Feeds & Images', 'latest-fashion-news-feed'); ?></a><a href="?page=fashion_news_feed&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>"><?php _e('Advanced', 'latest-fashion-news-feed'); ?></a></h2>
            <form action="options.php" method="post">
                <?php settings_fields('lfn_settings_group'); if ($active_tab == 'general') { do_settings_sections('lfn_general_section'); } if ($active_tab == 'style') { do_settings_sections('lfn_style_section'); } if ($active_tab == 'feeds') { do_settings_sections('lfn_feeds_section'); do_settings_sections('lfn_placeholders_section'); } if ($active_tab == 'advanced') { do_settings_sections('lfn_advanced_section'); } submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function handle_force_refresh() { if (isset($_POST['lfn_force_refresh_submit'])) { check_admin_referer('lfn_force_refresh_action'); $this->fetch_and_cache_news_data(); add_action('admin_notices', function() { echo '<div class="notice notice-success is-dismissible"><p>' . __('News feed cache has been successfully refreshed.', 'latest-fashion-news-feed') . '</p></div>'; }); } }
    public function setup_settings_fields() { register_setting('lfn_settings_group', 'lfn_settings', [$this, 'sanitize_settings']); add_settings_section('lfn_general_section', false, null, 'lfn_general_section'); add_settings_field('lfn_feed_title_field', __('Feed Title', 'latest-fashion-news-feed'), [$this, 'render_feed_title_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_field('lfn_load_more_field', __('Pagination', 'latest-fashion-news-feed'), [$this, 'render_load_more_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_field('lfn_items_per_page_field', __('Items Per Page', 'latest-fashion-news-feed'), [$this, 'render_items_per_page_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_section('lfn_style_section', false, null, 'lfn_style_section'); add_settings_field('lfn_layout_field', __('Feed Layout', 'latest-fashion-news-feed'), [$this, 'render_layout_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_title_color_field', __('Title Color', 'latest-fashion-news-feed'), [$this, 'render_title_color_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_source_color_field', __('Source Name Color', 'latest-fashion-news-feed'), [$this, 'render_source_color_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_custom_css_field', __('Custom CSS', 'latest-fashion-news-feed'), [$this, 'render_custom_css_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_section('lfn_feeds_section', __('RSS Feed Sources', 'latest-fashion-news-feed'), null, 'lfn_feeds_section'); add_settings_field('lfn_rss_feeds_field', __('Feeds', 'latest-fashion-news-feed'), [$this, 'render_feeds_field'], 'lfn_feeds_section', 'lfn_feeds_section'); add_settings_section('lfn_placeholders_section', __('Placeholder Images', 'latest-fashion-news-feed'), null, 'lfn_placeholders_section'); add_settings_field('lfn_placeholders_field', __('Images', 'latest-fashion-news-feed'), [$this, 'render_placeholders_field'], 'lfn_placeholders_section', 'lfn_placeholders_section'); add_settings_section('lfn_advanced_section', false, null, 'lfn_advanced_section'); add_settings_field('lfn_update_frequency_field', __('Update Frequency', 'latest-fashion-news-feed'), [$this, 'render_update_frequency_field'], 'lfn_advanced_section', 'lfn_advanced_section'); add_settings_field('lfn_fetcher_method_field', __('Fetcher Method', 'latest-fashion-news-feed'), [$this, 'render_fetcher_method_field'], 'lfn_advanced_section', 'lfn_advanced_section'); add_settings_field('lfn_shortcode_field', __('Custom Shortcode', 'latest-fashion-news-feed'), [$this, 'render_shortcode_field'], 'lfn_advanced_section', 'lfn_advanced_section'); }
    public function render_feed_title_field() { $options = get_option('lfn_settings'); $title = isset($options['feed_title']) ? $options['feed_title'] : 'Latest News'; echo '<input type="text" name="lfn_settings[feed_title]" value="' . esc_attr($title) . '" size="40">'; }
    public function render_load_more_field() { $options = get_option('lfn_settings'); $load_more = isset($options['load_more_enabled']) ? $options['load_more_enabled'] : 1; echo '<input type="checkbox" name="lfn_settings[load_more_enabled]" value="1"' . checked(1, $load_more, false) . '> ' . __('Enable "Load More" button', 'latest-fashion-news-feed'); }
    public function render_items_per_page_field() { $options = get_option('lfn_settings'); $items = isset($options['items_per_page']) ? $options['items_per_page'] : 8; echo '<input type="number" name="lfn_settings[items_per_page]" value="' . esc_attr($items) . '" min="1" max="100">'; }
    public function render_update_frequency_field() { $options = get_option('lfn_settings'); $current = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'; $schedules = ['hourly' => __('Hourly', 'latest-fashion-news-feed'), 'twicedaily' => __('Twice a Day', 'latest-fashion-news-feed'), 'daily' => __('Daily', 'latest-fashion-news-feed')]; echo '<select name="lfn_settings[update_frequency]">'; foreach ($schedules as $value => $label) { echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>'; } echo '</select>'; }
    public function render_fetcher_method_field() { $options = get_option('lfn_settings'); $method = isset($options['fetcher_method']) ? $options['fetcher_method'] : 'hybrid'; echo '<select name="lfn_settings[fetcher_method]"><option value="hybrid" ' . selected($method, 'hybrid', false) . '>' . __('Hybrid (Recommended)', 'latest-fashion-news-feed') . '</option><option value="internal" ' . selected($method, 'internal', false) . '>' . __('Internal Fetcher Only', 'latest-fashion-news-feed') . '</option></select><p class="description">' . __('"Hybrid" tries an external service first for better images, then falls back to the reliable internal parser.', 'latest-fashion-news-feed') . '</p>'; }
    public function render_shortcode_field() { $options = get_option('lfn_settings'); $tag = isset($options['shortcode_tag']) && !empty($options['shortcode_tag']) ? $options['shortcode_tag'] : 'latest-news-feed'; echo '<input type="text" name="lfn_settings[shortcode_tag]" value="' . esc_attr($tag) . '"><p class="description">' . __('Use simple, lowercase letters and dashes only.', 'latest-fashion-news-feed') . '</p>'; }
    public function render_layout_field() { $options = get_option('lfn_settings'); $layout = isset($options['layout']) ? $options['layout'] : 'list'; echo '<select name="lfn_settings[layout]"><option value="list" ' . selected($layout, 'list', false) . '>' . __('List', 'latest-fashion-news-feed') . '</option><option value="grid" ' . selected($layout, 'grid', false) . '>' . __('Grid', 'latest-fashion-news-feed') . '</option></select>'; }
    public function render_title_color_field() { $options = get_option('lfn_settings'); $color = isset($options['title_color']) ? $options['title_color'] : '#1c1e21'; echo '<input type="text" name="lfn_settings[title_color]" value="' . esc_attr($color) . '" class="lfn-color-picker">'; }
    public function render_source_color_field() { $options = get_option('lfn_settings'); $color = isset($options['source_color']) ? $options['source_color'] : '#65676b'; echo '<input type="text" name="lfn_settings[source_color]" value="' . esc_attr($color) . '" class="lfn-color-picker">'; }
    public function render_custom_css_field() { $options = get_option('lfn_settings'); $css = isset($options['custom_css']) ? $options['custom_css'] : ''; echo '<textarea name="lfn_settings[custom_css]" rows="8" cols="50" class="large-text code">' . esc_textarea($css) . '</textarea><p class="description"><strong>' . __('How to use Custom CSS:', 'latest-fashion-news-feed') . '</strong><br>' . __('Key Classes:', 'latest-fashion-news-feed') . ' <code>.lfn-container</code>, <code>.news-item</code>, <code>.news-title a</code></p>'; }
    public function render_feeds_field() { $options = get_option('lfn_settings'); ?><div id="lfn-feeds-container"><p class="description"><?php _e('Add the name and URL for each RSS feed source.', 'latest-fashion-news-feed'); ?></p><?php $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : []; if(!empty($feeds)){foreach($feeds as $index=>$feed){?><div class="lfn-feed-row"><input type="text" name="lfn_settings[rss_feeds][<?php echo $index;?>][name]" value="<?php echo esc_attr($feed['name']);?>" placeholder="<?php esc_attr_e('Source Name', 'latest-fashion-news-feed');?>" size="30"/><input type="url" name="lfn_settings[rss_feeds][<?php echo $index;?>][url]" value="<?php echo esc_attr($feed['url']);?>" placeholder="<?php esc_attr_e('RSS Feed URL', 'latest-fashion-news-feed');?>" size="50"/><button type="button" class="button lfn-remove-feed"><?php _e('Remove', 'latest-fashion-news-feed'); ?></button></div><?php }}?></div><button type="button" class="button" id="lfn-add-feed"><?php _e('Add Feed', 'latest-fashion-news-feed'); ?></button><?php }
    public function render_placeholders_field() { $options = get_option('lfn_settings'); ?><div id="lfn-placeholders-container"><p class="description"><?php _e('Select images from your Media Library.', 'latest-fashion-news-feed'); ?></p><?php $placeholders = isset($options['placeholders']) ? $options['placeholders'] : []; if(!empty($placeholders)){foreach($placeholders as $index=>$url){?><div class="lfn-placeholder-item"><img src="<?php echo esc_url($url);?>"/><input type="hidden" name="lfn_settings[placeholders][]" value="<?php echo esc_url($url);?>"><button type="button" class="button lfn-remove-placeholder"><?php _e('Remove', 'latest-fashion-news-feed');?></button></div><?php }}?></div><button type="button" class="button" id="lfn-add-placeholder"><?php _e('Add Placeholder Image', 'latest-fashion-news-feed'); ?></button><style> .lfn-feed-row{margin-bottom:10px;} #lfn-placeholders-container{display:flex;flex-wrap:wrap;gap:15px;} .lfn-placeholder-item{position:relative;} .lfn-placeholder-item img{width:100px;height:100px;object-fit:cover;border:1px solid #ddd;} .lfn-placeholder-item button{position:absolute;top:5px;right:5px;} </style><?php }
    public function enqueue_admin_assets($hook) { if ($hook !== 'settings_page_fashion_news_feed') { return; } wp_enqueue_media(); wp_enqueue_style('wp-color-picker'); wp_enqueue_script('lfn-admin-script', plugin_dir_url(__FILE__) . 'admin-scripts.js', ['jquery', 'wp-color-picker'], '12.0', true); }
    public function sanitize_settings($input) { $old_options = get_option('lfn_settings', []); $new_input = $input; $merged_input = array_merge($old_options, $new_input); $sanitized_input = []; $schedules = wp_get_schedules(); if(isset($merged_input['update_frequency']) && array_key_exists($merged_input['update_frequency'], $schedules)) { if(!isset($old_options['update_frequency']) || $old_options['update_frequency'] !== $merged_input['update_frequency']) { $this->reschedule_cron_job($merged_input['update_frequency']); } $sanitized_input['update_frequency'] = $merged_input['update_frequency']; } if(isset($merged_input['fetcher_method'])) { $sanitized_input['fetcher_method'] = in_array($merged_input['fetcher_method'], ['hybrid', 'internal']) ? $merged_input['fetcher_method'] : 'hybrid'; } if(isset($merged_input['feed_title'])) { $sanitized_input['feed_title'] = sanitize_text_field($merged_input['feed_title']); } $sanitized_input['load_more_enabled'] = isset($merged_input['load_more_enabled'])?1:0; if(isset($merged_input['items_per_page'])){$items=absint($merged_input['items_per_page']);$sanitized_input['items_per_page']=($items>0)?$items:8;} if(isset($merged_input['shortcode_tag'])){$tag=sanitize_key($merged_input['shortcode_tag']);$sanitized_input['shortcode_tag']=!empty($tag)?$tag:'latest-news-feed';} if(isset($merged_input['layout'])){$sanitized_input['layout']=in_array($merged_input['layout'],['list','grid'])?$merged_input['layout']:'list';} if(isset($merged_input['title_color'])){$sanitized_input['title_color']=sanitize_hex_color($merged_input['title_color']);} if(isset($merged_input['source_color'])){$sanitized_input['source_color']=sanitize_hex_color($merged_input['source_color']);} if(isset($merged_input['custom_css'])){$sanitized_input['custom_css']=wp_strip_all_tags($merged_input['custom_css']);} if(isset($merged_input['rss_feeds'])){$sanitized_input['rss_feeds']=[];foreach($merged_input['rss_feeds'] as $feed){if(!empty(trim($feed['name']))&&!empty(trim($feed['url']))){$sanitized_input['rss_feeds'][]=['name'=>sanitize_text_field($feed['name']),'url'=>esc_url_raw($feed['url'])];}}} if(isset($merged_input['placeholders'])){$sanitized_input['placeholders']=array_map('esc_url_raw',$merged_input['placeholders']);}elseif(array_key_exists('placeholders',$input)){$sanitized_input['placeholders']=[];} wp_cache_flush(); if(class_exists('LiteSpeed_Cache_API')){LiteSpeed_Cache_API::purge_all();} $this->fetch_and_cache_news_data($sanitized_input); return $sanitized_input; }
    public function enqueue_public_assets() { $options = get_option('lfn_settings'); $title_color = isset($options['title_color']) ? $options['title_color'] : '#1c1e21'; $source_color = isset($options['source_color']) ? $options['source_color'] : '#65676b'; $custom_css = isset($options['custom_css']) ? $options['custom_css'] : ''; $dynamic_css = ":root { --lfn-title-color: " . esc_attr($title_color) . "; --lfn-source-color: " . esc_attr($source_color) . "; }"; $dynamic_css .= $custom_css; wp_add_inline_style('universal-news-feed-style', $dynamic_css); wp_enqueue_style('universal-news-feed-style', plugin_dir_url(__FILE__) . 'universal-news-feed.css', [], '12.0'); wp_enqueue_script('universal-news-feed-script', plugin_dir_url(__FILE__) . 'universal-news-feed.js', [], '12.0', true); wp_localize_script('universal-news-feed-script', 'fashionNewsData', [ 'posts' => get_transient('latest_fashion_news_data')?:[], 'placeholders' => isset($options['placeholders']) ? $options['placeholders'] : [], 'load_more_enabled' => isset($options['load_more_enabled']) ? $options['load_more_enabled'] : 1, 'items_per_page' => isset($options['items_per_page']) ? $options['items_per_page'] : 8 ]); }
    public function render_shortcode() { $options = get_option('lfn_settings'); $layout = isset($options['layout']) ? $options['layout'] : 'list'; $title = isset($options['feed_title']) ? $options['feed_title'] : 'Latest News'; ob_start(); ?><div class="lfn-container lfn-layout-<?php echo esc_attr($layout); ?>"><h2><?php echo esc_html($title); ?></h2><div id="loading"><?php _e('Loading news...', 'latest-fashion-news-feed'); ?></div><div id="newsFeed"></div><div id="loadMoreContainer"></div></div><?php return ob_get_clean(); }
    public function reschedule_cron_job($new_frequency = null) { $timestamp = wp_next_scheduled('fetch_fashion_news_hook'); if ($timestamp) { wp_unschedule_event($timestamp, 'fetch_fashion_news_hook'); } $options = get_option('lfn_settings'); $frequency = $new_frequency ? $new_frequency : (isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'); wp_schedule_event(time(), $frequency, 'fetch_fashion_news_hook'); }
    
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

    public function fetch_and_cache_news_data($options_to_use = null) {
        include_once(ABSPATH . WPINC . '/feed.php');
        $current_options = is_array($options_to_use) ? $options_to_use : get_option('lfn_settings');
        $rssFeeds = isset($current_options['rss_feeds']) ? $current_options['rss_feeds'] : [];
        if (empty($rssFeeds)) { set_transient('latest_fashion_news_data', [], HOUR_IN_SECONDS); return; }
        $fetcher_method = isset($current_options['fetcher_method']) ? $current_options['fetcher_method'] : 'hybrid';
        $all_news = [];
        foreach ($rssFeeds as $feed) {
            $items = null;
            if ($fetcher_method === 'hybrid') {
                $response = wp_remote_get('https://api.rss2json.com/v1/api.json?rss_url=' . urlencode($feed['url']), ['timeout' => 20]);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) { $data = json_decode(wp_remote_retrieve_body($response), true); if ($data && $data['status'] === 'ok') { $items = $data['items']; foreach ($items as &$item) { $has_enclosure_image = !empty($item['enclosure']['link']) && !empty($item['enclosure']['type']) && strpos($item['enclosure']['type'], 'image') === 0; if (empty($item['thumbnail']) && !$has_enclosure_image) { $html_to_scan = !empty($item['content']) ? $item['content'] : (isset($item['description']) ? $item['description'] : ''); $found_image = $this->extract_first_image_url($html_to_scan); if (!$found_image && !empty($item['link'])) { $found_image = $this->fetch_og_image($item['link']); } if ($found_image) { $item['thumbnail'] = $found_image; } } } unset($item); } }
            }
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
        set_transient('latest_fashion_news_data', $all_news, HOUR_IN_SECONDS);
        update_option('lfn_last_update_timestamp', time());
    }
}
new Latest_Fashion_News_Plugin();