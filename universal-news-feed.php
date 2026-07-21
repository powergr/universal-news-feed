<?php
/**
 * Plugin Name:       Universal News Feed
 * Description:       A configurable, resilient plugin to display a cached news feed from any RSS sources via a shortcode.
 * Version:           2.7
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
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']); add_action('admin_menu', [$this, 'add_admin_menu']); add_action('admin_init', [$this, 'setup_settings_fields']); add_action('admin_init', [$this, 'handle_force_refresh']); add_action('admin_init', [$this, 'handle_clear_debug_log']); add_action('admin_init', [$this, 'handle_clear_image_cache']); add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']); add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']); add_shortcode($this->shortcode_tag, [$this, 'render_shortcode']); add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']); add_action('unf_fetch_news_hook', [$this, 'fetch_and_cache_news_data']);
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
            <h2 class="nav-tab-wrapper"><a href="?page=universal_news_feed_settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'universal-news-feed'); ?></a><a href="?page=universal_news_feed_settings&tab=style" class="nav-tab <?php echo $active_tab == 'style' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Style', 'universal-news-feed'); ?></a><a href="?page=universal_news_feed_settings&tab=feeds" class="nav-tab <?php echo $active_tab == 'feeds' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Feeds & Images', 'universal-news-feed'); ?></a><a href="?page=universal_news_feed_settings&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Advanced', 'universal-news-feed'); ?></a><a href="?page=universal_news_feed_settings&tab=debug_log" class="nav-tab <?php echo $active_tab == 'debug_log' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Debug Log', 'universal-news-feed'); ?></a></h2>
            <?php if ($active_tab === 'debug_log') { ?>
                <div style="margin-top: 15px;">
                    <p class="description"><?php esc_html_e('Enable "Debug Logging" under the Advanced tab, then click Force Refresh Now above to populate this log with details about each image fetch attempt (useful for figuring out why a source is showing placeholder images).', 'universal-news-feed'); ?></p>
                    <?php $log_content = $this->read_debug_log_tail(); ?>
                    <textarea readonly rows="25" class="large-text code" style="font-family: monospace; white-space: pre; background:#1e1e1e; color:#ddd;"><?php echo esc_textarea($log_content !== '' ? $log_content : __('(Log is empty.)', 'universal-news-feed')); ?></textarea>
                    <div style="display:flex; gap:10px; margin-top: 10px;">
                        <form method="post" action=""><input type="hidden" name="lfn_clear_debug_log_submit" value="1"><?php wp_nonce_field('lfn_clear_debug_log_action'); submit_button(__('Clear Log', 'universal-news-feed'), 'delete', 'lfn_clear_debug_log_submit', false); ?></form>
                        <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('This will delete all locally cached images. They will be re-downloaded on the next fetch. Continue?', 'universal-news-feed')); ?>');"><input type="hidden" name="lfn_clear_image_cache_submit" value="1"><?php wp_nonce_field('lfn_clear_image_cache_action'); submit_button(__('Clear Image Cache', 'universal-news-feed'), 'delete', 'lfn_clear_image_cache_submit', false); ?></form>
                    </div>
                </div>
            <?php } else { ?>
            <form action="options.php" method="post">
                <?php settings_fields('lfn_settings_group'); if ($active_tab == 'general') { do_settings_sections('lfn_general_section'); } if ($active_tab == 'style') { do_settings_sections('lfn_style_section'); } if ($active_tab == 'feeds') { do_settings_sections('lfn_feeds_section'); do_settings_sections('lfn_placeholders_section'); } if ($active_tab == 'advanced') { do_settings_sections('lfn_advanced_section'); } submit_button(); ?>
            </form>
            <?php } ?>
        </div>
        <?php
    }

    public function handle_force_refresh() { if (isset($_POST['lfn_force_refresh_submit']) && current_user_can('manage_options')) { check_admin_referer('lfn_force_refresh_action'); wp_schedule_single_event(time(), 'unf_fetch_news_hook'); add_action('admin_notices', function() { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Refresh triggered. The feed will update in the background shortly.', 'universal-news-feed') . '</p></div>'; }); } }
    public function setup_settings_fields() { register_setting('lfn_settings_group', 'lfn_settings', [$this, 'sanitize_settings']); add_settings_section('lfn_general_section', false, null, 'lfn_general_section'); add_settings_field('lfn_feed_title_field', __('Feed Title', 'universal-news-feed'), [$this, 'render_feed_title_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_field('lfn_load_more_field', __('Pagination', 'universal-news-feed'), [$this, 'render_load_more_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_field('lfn_items_per_page_field', __('Items Per Page', 'universal-news-feed'), [$this, 'render_items_per_page_field'], 'lfn_general_section', 'lfn_general_section'); add_settings_section('lfn_style_section', false, null, 'lfn_style_section'); add_settings_field('lfn_layout_field', __('Feed Layout', 'universal-news-feed'), [$this, 'render_layout_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_title_color_field', __('Title Color', 'universal-news-feed'), [$this, 'render_title_color_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_source_color_field', __('Source Name Color', 'universal-news-feed'), [$this, 'render_source_color_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_field('lfn_custom_css_field', __('Custom CSS', 'universal-news-feed'), [$this, 'render_custom_css_field'], 'lfn_style_section', 'lfn_style_section'); add_settings_section('lfn_feeds_section', __('RSS Feed Sources', 'universal-news-feed'), null, 'lfn_feeds_section'); add_settings_field('lfn_rss_feeds_field', __('Feeds', 'universal-news-feed'), [$this, 'render_feeds_field'], 'lfn_feeds_section', 'lfn_feeds_section'); add_settings_section('lfn_placeholders_section', __('Placeholder Images', 'universal-news-feed'), null, 'lfn_placeholders_section'); add_settings_field('lfn_placeholders_field', __('Images', 'universal-news-feed'), [$this, 'render_placeholders_field'], 'lfn_placeholders_section', 'lfn_placeholders_section'); add_settings_section('lfn_advanced_section', false, null, 'lfn_advanced_section'); add_settings_field('lfn_update_frequency_field', __('Update Frequency', 'universal-news-feed'), [$this, 'render_update_frequency_field'], 'lfn_advanced_section', 'lfn_advanced_section'); add_settings_field('lfn_shortcode_field', __('Custom Shortcode', 'universal-news-feed'), [$this, 'render_shortcode_field'], 'lfn_advanced_section', 'lfn_advanced_section'); add_settings_field('lfn_debug_logging_field', __('Debug Logging', 'universal-news-feed'), [$this, 'render_debug_logging_field'], 'lfn_advanced_section', 'lfn_advanced_section'); }
    public function render_feed_title_field() { $options = get_option('lfn_settings'); $title = isset($options['feed_title']) ? $options['feed_title'] : 'Latest News'; echo '<input type="text" name="lfn_settings[feed_title]" value="' . esc_attr($title) . '" size="40">'; }
    public function render_load_more_field() { $options = get_option('lfn_settings'); $load_more = isset($options['load_more_enabled']) ? $options['load_more_enabled'] : 1; echo '<input type="checkbox" name="lfn_settings[load_more_enabled]" value="1"' . checked(1, $load_more, false) . '> ' . esc_html__('Enable "Load More" button', 'universal-news-feed'); }
    public function render_items_per_page_field() { $options = get_option('lfn_settings'); $items = isset($options['items_per_page']) ? $options['items_per_page'] : 8; echo '<input type="number" name="lfn_settings[items_per_page]" value="' . esc_attr($items) . '" min="1" max="100">'; }
    public function render_update_frequency_field() { $options = get_option('lfn_settings'); $current = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly'; $schedules = ['hourly' => __('Hourly', 'universal-news-feed'), 'twicedaily' => __('Twice a Day', 'universal-news-feed'), 'daily' => __('Daily', 'universal-news-feed')]; echo '<select name="lfn_settings[update_frequency]">'; foreach ($schedules as $value => $label) { echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>'; } echo '</select>'; }
    public function render_debug_logging_field() { $options = get_option('lfn_settings'); $enabled = isset($options['debug_logging_enabled']) ? $options['debug_logging_enabled'] : 0; echo '<input type="checkbox" name="lfn_settings[debug_logging_enabled]" value="1"' . checked(1, $enabled, false) . '> ' . esc_html__('Log image-fetching activity for troubleshooting. View it under the Debug Log tab above.', 'universal-news-feed'); }
    public function render_shortcode_field() { $options = get_option('lfn_settings'); $tag = isset($options['shortcode_tag']) && !empty($options['shortcode_tag']) ? $options['shortcode_tag'] : 'latest-news-feed'; echo '<input type="text" name="lfn_settings[shortcode_tag]" value="' . esc_attr($tag) . '"><p class="description">' . esc_html__('Use simple, lowercase letters and dashes only.', 'universal-news-feed') . '</p>'; }
    public function render_layout_field() { $options = get_option('lfn_settings'); $layout = isset($options['layout']) ? $options['layout'] : 'list'; echo '<select name="lfn_settings[layout]"><option value="list" ' . selected($layout, 'list', false) . '>' . esc_html__('List', 'universal-news-feed') . '</option><option value="grid" ' . selected($layout, 'grid', false) . '>' . esc_html__('Grid', 'universal-news-feed') . '</option></select>'; }
    public function render_title_color_field() { $options = get_option('lfn_settings'); $color = isset($options['title_color']) ? $options['title_color'] : '#1c1e21'; echo '<input type="text" name="lfn_settings[title_color]" value="' . esc_attr($color) . '" class="lfn-color-picker">'; }
    public function render_source_color_field() { $options = get_option('lfn_settings'); $color = isset($options['source_color']) ? $options['source_color'] : '#65676b'; echo '<input type="text" name="lfn_settings[source_color]" value="' . esc_attr($color) . '" class="lfn-color-picker">'; }
    public function render_custom_css_field() { $options = get_option('lfn_settings'); $css = isset($options['custom_css']) ? $options['custom_css'] : ''; echo '<textarea name="lfn_settings[custom_css]" rows="8" cols="50" class="large-text code">' . esc_textarea($css) . '</textarea><p class="description"><strong>' . esc_html__('How to use Custom CSS:', 'universal-news-feed') . '</strong><br>' . esc_html__('Key Classes:', 'universal-news-feed') . ' <code>.lfn-container</code>, <code>.news-item</code>, <code>.news-title a</code></p>'; }
    public function render_feeds_field() { $options = get_option('lfn_settings'); ?><div id="lfn-feeds-container"><p class="description"><?php esc_html_e('Add the name and URL for each RSS feed source. "Keywords" is optional: if set, only articles whose title or description contain at least one of the comma-separated keywords are kept — useful for broad feeds (e.g. a general "Style" section) that mix in off-topic content.', 'universal-news-feed'); ?></p><?php $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : []; if(!empty($feeds)){foreach($feeds as $index=>$feed){?><div class="lfn-feed-row"><input type="text" name="lfn_settings[rss_feeds][<?php echo absint($index);?>][name]" value="<?php echo esc_attr($feed['name']);?>" placeholder="<?php esc_attr_e('Source Name', 'universal-news-feed');?>" size="30"/><input type="url" name="lfn_settings[rss_feeds][<?php echo absint($index);?>][url]" value="<?php echo esc_attr($feed['url']);?>" placeholder="<?php esc_attr_e('RSS Feed URL', 'universal-news-feed');?>" size="50"/><input type="text" name="lfn_settings[rss_feeds][<?php echo absint($index);?>][keywords]" value="<?php echo esc_attr(isset($feed['keywords']) ? $feed['keywords'] : '');?>" placeholder="<?php esc_attr_e('Keywords (optional), e.g. fashion, style, designer', 'universal-news-feed');?>" size="35"/><button type="button" class="button lfn-remove-feed"><?php esc_html_e('Remove', 'universal-news-feed'); ?></button></div><?php }}?></div><button type="button" class="button" id="lfn-add-feed"><?php esc_html_e('Add Feed', 'universal-news-feed'); ?></button><?php }
    public function render_placeholders_field() { $options = get_option('lfn_settings'); ?><div id="lfn-placeholders-container"><p class="description"><?php esc_html_e('Select images from your Media Library.', 'universal-news-feed'); ?></p><?php $placeholders = isset($options['placeholders']) ? $options['placeholders'] : []; if(!empty($placeholders)){foreach($placeholders as $index=>$url){?><div class="lfn-placeholder-item"><img src="<?php echo esc_url($url);?>"/><input type="hidden" name="lfn_settings[placeholders][]" value="<?php echo esc_url($url);?>"><button type="button" class="button lfn-remove-placeholder"><?php esc_html_e('Remove', 'universal-news-feed');?></button></div><?php }}?></div><button type="button" class="button" id="lfn-add-placeholder"><?php esc_html_e('Add Placeholder Image', 'universal-news-feed'); ?></button><style> .lfn-feed-row{margin-bottom:10px;} #lfn-placeholders-container{display:flex;flex-wrap:wrap;gap:15px;} .lfn-placeholder-item{position:relative;} .lfn-placeholder-item img{width:100px;height:100px;object-fit:cover;border:1px solid #ddd;} .lfn-placeholder-item button{position:absolute;top:5px;right:5px;} </style><?php }
    public function enqueue_admin_assets($hook) { if ($hook !== 'settings_page_universal_news_feed_settings') { return; } wp_enqueue_media(); wp_enqueue_style('wp-color-picker'); wp_enqueue_script('lfn-admin-script', plugin_dir_url(__FILE__) . 'admin-scripts.js', ['jquery', 'wp-color-picker'], '2.2', true); }
    public function sanitize_settings($input) { $old_options = get_option('lfn_settings', []); $new_input = $input; $merged_input = array_merge($old_options, $new_input); $sanitized_input = []; $schedules = wp_get_schedules(); if(isset($merged_input['update_frequency']) && array_key_exists($merged_input['update_frequency'], $schedules)) { if(!isset($old_options['update_frequency']) || $old_options['update_frequency'] !== $merged_input['update_frequency']) { $this->reschedule_cron_job($merged_input['update_frequency']); } $sanitized_input['update_frequency'] = $merged_input['update_frequency']; } if(isset($merged_input['feed_title'])) { $sanitized_input['feed_title'] = sanitize_text_field($merged_input['feed_title']); } $sanitized_input['load_more_enabled'] = isset($merged_input['load_more_enabled'])?1:0; $sanitized_input['debug_logging_enabled'] = isset($merged_input['debug_logging_enabled'])?1:0; if(isset($merged_input['items_per_page'])){$items=absint($merged_input['items_per_page']);$sanitized_input['items_per_page']=($items>0)?$items:8;} if(isset($merged_input['shortcode_tag'])){$tag=sanitize_key($merged_input['shortcode_tag']);$sanitized_input['shortcode_tag']=!empty($tag)?$tag:'latest-news-feed';} if(isset($merged_input['layout'])){$sanitized_input['layout']=in_array($merged_input['layout'],['list','grid'])?$merged_input['layout']:'list';} if(isset($merged_input['title_color'])){$sanitized_input['title_color']=sanitize_hex_color($merged_input['title_color']);} if(isset($merged_input['source_color'])){$sanitized_input['source_color']=sanitize_hex_color($merged_input['source_color']);} if(isset($merged_input['custom_css'])){$sanitized_input['custom_css']=sanitize_textarea_field($merged_input['custom_css']);} if(isset($merged_input['rss_feeds'])){$sanitized_input['rss_feeds']=[];foreach($merged_input['rss_feeds'] as $feed){if(!empty(trim($feed['name']))&&!empty(trim($feed['url']))){$sanitized_input['rss_feeds'][]=['name'=>sanitize_text_field($feed['name']),'url'=>esc_url_raw($feed['url']),'keywords'=>isset($feed['keywords'])?sanitize_text_field($feed['keywords']):''];}}} if(isset($merged_input['placeholders'])){$sanitized_input['placeholders']=array_map('esc_url_raw',$merged_input['placeholders']);}elseif(array_key_exists('placeholders',$input)){$sanitized_input['placeholders']=[];} wp_cache_flush(); if(class_exists('LiteSpeed_Cache_API')){LiteSpeed_Cache_API::purge_all();} wp_schedule_single_event(time(), 'unf_fetch_news_hook'); return $sanitized_input; }
    public function enqueue_public_assets() { $options = get_option('lfn_settings'); $title_color = isset($options['title_color']) ? $options['title_color'] : '#1c1e21'; $source_color = isset($options['source_color']) ? $options['source_color'] : '#65676b'; $custom_css = isset($options['custom_css']) ? $options['custom_css'] : ''; $dynamic_css = ":root { --lfn-title-color: " . esc_attr($title_color) . "; --lfn-source-color: " . esc_attr($source_color) . "; } .news-item.lfn-hidden { display: none !important; }"; $dynamic_css .= $custom_css; wp_enqueue_style('universal-news-feed-style', plugin_dir_url(__FILE__) . 'universal-news-feed.css', [], '2.7'); wp_add_inline_style('universal-news-feed-style', $dynamic_css); wp_enqueue_script('universal-news-feed-script', plugin_dir_url(__FILE__) . 'universal-news-feed.js', [], '2.7', true); }
    // Renders one news item as real, crawlable HTML (mirrors the markup the old
    // client-side JS used to build in the browser). $hidden marks items beyond the
    // first page when "Load More" is enabled; they stay in the HTML (so search
    // engines and other non-JS clients still see them) but are CSS-hidden until
    // the visitor clicks "Load More".
    private function render_news_item_html($news, $placeholder_urls, $hidden = false) {
        $title = !empty($news['title']) ? $news['title'] : __('No Title', 'universal-news-feed');
        $link = !empty($news['link']) ? $news['link'] : '#';
        $link = ($link === '#' || wp_http_validate_url($link)) ? $link : '#';
        $source_name = !empty($news['sourceName']) ? $news['sourceName'] : __('Unknown', 'universal-news-feed');

        $image_url = '';
        $is_placeholder = false;
        if (!empty($news['thumbnail'])) {
            $image_url = $news['thumbnail'];
        } elseif (!empty($news['enclosure']['link']) && !empty($news['enclosure']['type']) && strpos($news['enclosure']['type'], 'image') === 0) {
            $image_url = $news['enclosure']['link'];
        }
        if ($image_url && !wp_http_validate_url($image_url)) { $image_url = ''; }
        if (empty($image_url) && !empty($placeholder_urls)) {
            $image_url = $placeholder_urls[array_rand($placeholder_urls)];
            $is_placeholder = true;
        }

        $description = wp_strip_all_tags(isset($news['description']) ? $news['description'] : '');
        $description = mb_strlen($description) > 140 ? mb_substr($description, 0, 140) . '...' : $description;

        $date_string = __('Date not available', 'universal-news-feed');
        if (!empty($news['pubDate'])) {
            $timestamp = strtotime($news['pubDate']);
            if ($timestamp) { $date_string = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp); }
        }

        $item_class = 'news-item visible' . ($hidden ? ' lfn-hidden' : '');
        ob_start();
        ?><div class="<?php echo esc_attr($item_class); ?>">
            <?php if ($image_url) : ?>
            <div class="news-image-container">
                <a href="<?php echo esc_url($link); ?>" target="_blank" rel="nofollow noopener noreferrer" tabindex="-1" aria-hidden="true">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(mb_substr($title, 0, 50)); ?>" class="news-image<?php echo $is_placeholder ? ' is-placeholder' : ''; ?>" loading="lazy">
                </a>
            </div>
            <?php endif; ?>
            <div class="news-content">
                <div>
                    <div class="news-source"><?php echo esc_html__('From:', 'universal-news-feed'); ?> <?php echo esc_html($source_name); ?></div>
                    <div class="news-title"><a href="<?php echo esc_url($link); ?>" target="_blank" rel="nofollow noopener noreferrer"><?php echo esc_html($title); ?></a></div>
                    <p class="news-description"><?php echo esc_html($description); ?></p>
                </div>
                <div class="news-footer">
                    <span class="news-date"><?php echo esc_html($date_string); ?></span>
                </div>
            </div>
        </div><?php
        return ob_get_clean();
    }

    public function render_shortcode($atts = []) {
        $atts = shortcode_atts(['source' => ''], $atts, $this->shortcode_tag);
        $options = get_option('lfn_settings');
        $layout = isset($options['layout']) ? $options['layout'] : 'list';
        $title = isset($options['feed_title']) ? $options['feed_title'] : 'Latest News';
        $all_items = get_transient('universal_news_feed_data') ?: [];

        if (!empty(trim($atts['source']))) {
            $source_names = array_map('trim', explode(',', $atts['source']));
            $wanted_sources = array_map('strtolower', $source_names);
            $all_items = array_values(array_filter($all_items, function($item) use ($wanted_sources) {
                return isset($item['sourceName']) && in_array(strtolower($item['sourceName']), $wanted_sources, true);
            }));
            /* translators: 1: base feed title (e.g. "Latest News"), 2: comma-separated source name(s) this shortcode instance is filtered to. */
            $title = sprintf(__('%1$s from %2$s', 'universal-news-feed'), $title, implode(', ', $source_names));
        }

        $placeholders = isset($options['placeholders']) ? $options['placeholders'] : [];
        $load_more_enabled = isset($options['load_more_enabled']) ? (bool) $options['load_more_enabled'] : true;
        $items_per_page = isset($options['items_per_page']) ? absint($options['items_per_page']) : 8;
        if ($items_per_page < 1) { $items_per_page = 8; }
        $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $instance_id = 'lfn-' . substr(md5(wp_json_encode($atts) . microtime()), 0, 10);

        ob_start();
        ?><div id="<?php echo esc_attr($instance_id); ?>" class="lfn-container lfn-layout-<?php echo esc_attr($layout); ?>" data-placeholders="<?php echo esc_attr(wp_json_encode($placeholders, $json_flags)); ?>">
            <h2><?php echo esc_html($title); ?></h2>
            <?php if (empty($all_items)) : ?>
                <div class="lfn-loading"><?php esc_html_e('No news available.', 'universal-news-feed'); ?></div>
            <?php else : ?>
                <div class="lfn-feed">
                    <?php foreach ($all_items as $i => $news) :
                        $hidden = $load_more_enabled && $i >= $items_per_page;
                        echo $this->render_news_item_html($news, $placeholders, $hidden); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped inside render_news_item_html
                    endforeach; ?>
                </div>
                <?php if ($load_more_enabled && count($all_items) > $items_per_page) : ?>
                <div class="lfn-load-more-container">
                    <button type="button" class="load-more-btn" data-items-per-page="<?php echo esc_attr($items_per_page); ?>"><?php esc_html_e('Load More News', 'universal-news-feed'); ?></button>
                </div>
                <script>
                (function(){
                    var root = document.getElementById(<?php echo wp_json_encode($instance_id); ?>);
                    if (!root) { return; }
                    var btn = root.querySelector('.load-more-btn');
                    if (!btn) { return; }
                    btn.addEventListener('click', function(){
                        var perPage = parseInt(btn.getAttribute('data-items-per-page'), 10) || 8;
                        var hidden = root.querySelectorAll('.news-item.lfn-hidden');
                        for (var i = 0; i < perPage && i < hidden.length; i++) {
                            hidden[i].classList.remove('lfn-hidden');
                            hidden[i].classList.add('visible');
                        }
                        if (root.querySelectorAll('.news-item.lfn-hidden').length === 0) {
                            btn.parentNode.innerHTML = '';
                        }
                    });
                })();
                </script>
                <?php endif; ?>
            <?php endif; ?>
        </div><?php
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
        $debug = $this->is_debug_logging_enabled();
        $response = wp_remote_get($article_url, [ 'timeout' => 10, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36' ]);
        if (is_wp_error($response)) { if ($debug) { $this->log_debug('og:image fetch failed for ' . $article_url . ': ' . $response->get_error_message()); } return ''; }
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) { if ($debug) { $this->log_debug('og:image fetch got HTTP ' . $status_code . ' for ' . $article_url); } return ''; }
        $body = wp_remote_retrieve_body($response);
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $matches)) { if ($debug) { $this->log_debug('og:image found for ' . $article_url . ': ' . $matches[1]); } return html_entity_decode($matches[1]); }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $body, $matches)) { if ($debug) { $this->log_debug('og:image found for ' . $article_url . ': ' . $matches[1]); } return html_entity_decode($matches[1]); }
        if ($debug) { $this->log_debug('No og:image tag found on ' . $article_url); }
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

    private function get_image_cache_dir() {
        $upload_dir = wp_upload_dir();
        $cache_dir = trailingslashit($upload_dir['basedir']) . 'universal-news-feed-cache';
        if (!file_exists($cache_dir)) { wp_mkdir_p($cache_dir); }
        return $cache_dir;
    }

    private function get_image_cache_url() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['baseurl']) . 'universal-news-feed-cache';
    }

    // Downloads a remote image once and saves it locally, so the browser loads it from our
    // own domain instead of hotlinking the publisher's server directly (some publishers block
    // cross-origin image requests based on the Referer header). Returns the local URL on
    // success, or an empty string on any failure — callers should keep using the original
    // remote URL as a fallback when this returns nothing.
    // Writes to the plugin's own log file instead of relying on WordPress's debug.log,
    // since some hosts lock the PHP error_log path at the server level and silently
    // ignore WordPress's attempt to redirect it.
    private function read_debug_log_tail() {
        $path = $this->get_debug_log_path();
        if (!file_exists($path)) { return ''; }
        global $wp_filesystem;
        if (empty($wp_filesystem)) { require_once(ABSPATH . '/wp-admin/includes/file.php'); WP_Filesystem(); }
        $content = $wp_filesystem->get_contents($path);
        if ($content === false) { return ''; }
        $max_bytes = 200 * 1024; // Cap what we display to the most recent 200KB.
        if (strlen($content) > $max_bytes) {
            $content = substr($content, -$max_bytes);
            $first_newline = strpos($content, "\n");
            if ($first_newline !== false) { $content = substr($content, $first_newline + 1); } // drop partial first line
        }
        return $content;
    }

    public function handle_clear_debug_log() {
        if (isset($_POST['lfn_clear_debug_log_submit']) && current_user_can('manage_options')) {
            check_admin_referer('lfn_clear_debug_log_action');
            $path = $this->get_debug_log_path();
            if (file_exists($path)) { wp_delete_file($path); }
            add_action('admin_notices', function() { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Debug log cleared.', 'universal-news-feed') . '</p></div>'; });
        }
    }

    public function handle_clear_image_cache() {
        if (isset($_POST['lfn_clear_image_cache_submit']) && current_user_can('manage_options')) {
            check_admin_referer('lfn_clear_image_cache_action');
            $cache_dir = $this->get_image_cache_dir();
            $files = glob(trailingslashit($cache_dir) . '*');
            $deleted_count = 0;
            if ($files) { foreach ($files as $file) { if (is_file($file)) { wp_delete_file($file); $deleted_count++; } } }
            if ($this->is_debug_logging_enabled()) { $this->log_debug('Manually cleared image cache: ' . $deleted_count . ' file(s) deleted.'); }
            add_action('admin_notices', function() use ($deleted_count) {
                /* translators: %d: number of cached image files that were deleted. */
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('Image cache cleared: %d file(s) deleted.', 'universal-news-feed'), $deleted_count)) . '</p></div>';
            });
        }
    }

    private function get_debug_log_suffix() {
        $options = get_option('lfn_settings', []);
        if (!empty($options['debug_log_suffix'])) { return $options['debug_log_suffix']; }
        $suffix = wp_generate_password(12, false, false);
        $options['debug_log_suffix'] = $suffix;
        update_option('lfn_settings', $options);
        return $suffix;
    }

    private function get_debug_log_path() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'universal-news-feed-debug-' . $this->get_debug_log_suffix() . '.log';
    }

    private function is_debug_logging_enabled() {
        $options = get_option('lfn_settings');
        return !empty($options['debug_logging_enabled']);
    }

    private function log_debug($message) {
        if (!$this->is_debug_logging_enabled()) { return; }
        $log_path = $this->get_debug_log_path();
        if (!file_exists($log_path)) {
            $htaccess_path = dirname($log_path) . '/.htaccess';
            if (!file_exists($htaccess_path)) { @file_put_contents($htaccess_path, "Require all denied\n"); }
        }
        $timestamp = gmdate('Y-m-d H:i:s');
        @file_put_contents($log_path, "[{$timestamp} UTC] {$message}" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function cache_image_locally($image_url) {
        if (empty($image_url)) { return ''; }
        $debug = $this->is_debug_logging_enabled();
        $cache_dir = $this->get_image_cache_dir();
        $path_info = pathinfo(wp_parse_url($image_url, PHP_URL_PATH));
        $ext = (isset($path_info['extension']) && preg_match('/^[a-zA-Z0-9]{2,4}$/', $path_info['extension'])) ? strtolower($path_info['extension']) : 'jpg';
        $hash = md5($image_url);

        // Cache-hit check: an *animated* GIF is stored under its original .gif extension;
        // everything else - including PNGs and static GIFs, which are lossless formats that
        // are frequently much larger than a JPEG of the same photo needs to be - is
        // re-encoded and stored as .jpg. We don't know which case applies until we've
        // downloaded and inspected the file, so check both possible cache locations first.
        foreach (array_unique([$ext, 'jpg']) as $candidate_ext) {
            $candidate_path = trailingslashit($cache_dir) . $hash . '.' . $candidate_ext;
            if (file_exists($candidate_path)) {
                if ($debug) { $this->log_debug('Already cached, skipping download: ' . $image_url); }
                return trailingslashit($this->get_image_cache_url()) . $hash . '.' . $candidate_ext;
            }
        }

        if ($debug && !wp_is_writable($cache_dir)) { $this->log_debug('Cache directory is not writable: ' . $cache_dir); }

        $response = wp_remote_get($image_url, [ 'timeout' => 10, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36' ]);
        if (is_wp_error($response)) { if ($debug) { $this->log_debug('Image download failed for ' . $image_url . ': ' . $response->get_error_message()); } return ''; }
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) { if ($debug) { $this->log_debug('Image download got HTTP ' . $status_code . ' for ' . $image_url); } return ''; }
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if ($content_type && strpos($content_type, 'image/') !== 0) { if ($debug) { $this->log_debug('Rejected non-image content-type "' . $content_type . '" for ' . $image_url); } return ''; }
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) { if ($debug) { $this->log_debug('Empty response body for ' . $image_url); } return ''; }

        global $wp_filesystem;
        if (empty($wp_filesystem)) { require_once(ABSPATH . '/wp-admin/includes/file.php'); WP_Filesystem(); }

        $tmp_file = wp_tempnam($image_url);
        if (!$tmp_file || !$wp_filesystem->put_contents($tmp_file, $body, FS_CHMOD_FILE)) {
            if ($debug) { $this->log_debug('Could not write temp file for ' . $image_url); }
            return '';
        }

        // Animated GIFs are saved as-is so re-encoding doesn't destroy the animation.
        if ($ext === 'gif' && $this->is_animated_gif($tmp_file)) {
            wp_delete_file($tmp_file);
            $local_path = trailingslashit($cache_dir) . $hash . '.gif';
            $local_url = trailingslashit($this->get_image_cache_url()) . $hash . '.gif';
            $written = $wp_filesystem->put_contents($local_path, $body, FS_CHMOD_FILE);
            if ($debug) { $this->log_debug($written ? 'Cached animated GIF as-is: ' . $local_path : 'Failed to write cached image: ' . $local_path); }
            return $written ? $local_url : '';
        }

        // Everything else - JPG, PNG, static GIF, etc. - gets resized and re-encoded as a
        // compressed JPEG.
        $local_path = trailingslashit($cache_dir) . $hash . '.jpg';
        $local_url = trailingslashit($this->get_image_cache_url()) . $hash . '.jpg';
        $editor = wp_get_image_editor($tmp_file);
        if (!is_wp_error($editor)) {
            $original_size = $editor->get_size();
            $max_dim = 480; // comfortably covers the largest on-page display size (280px) at 2x pixel density
            if ($original_size && ($original_size['width'] > $max_dim || $original_size['height'] > $max_dim)) {
                $editor->resize($max_dim, $max_dim, false);
            }
            $editor->set_quality(78);
            $saved = $editor->save($local_path, 'image/jpeg');
            wp_delete_file($tmp_file);
            if (!is_wp_error($saved)) {
                if ($debug) { $this->log_debug('Converted/resized/compressed to JPEG' . ($original_size ? ' (from ' . $original_size['width'] . 'x' . $original_size['height'] . ', ' . $ext . ')' : '') . ' for ' . $image_url); }
                return $local_url;
            }
            if ($debug) { $this->log_debug('Image editor save failed for ' . $image_url . ', falling back to unconverted original.'); }
        } else {
            wp_delete_file($tmp_file);
            if ($debug) { $this->log_debug('No image editor available for ' . $image_url . ', falling back to unconverted original.'); }
        }

        // Fallback: keep the original bytes/extension untouched if conversion failed for any reason.
        $fallback_path = trailingslashit($cache_dir) . $hash . '.' . $ext;
        $fallback_url = trailingslashit($this->get_image_cache_url()) . $hash . '.' . $ext;
        $written = $wp_filesystem->put_contents($fallback_path, $body, FS_CHMOD_FILE);
        if ($debug) { $this->log_debug($written ? 'Cached image saved (unresized fallback): ' . $fallback_path : 'Failed to write cached image: ' . $fallback_path); }
        return $written ? $fallback_url : '';
    }

    // Animated GIFs contain multiple "Graphic Control Extension" blocks (one per frame); a
    // static GIF has at most one. Used to decide whether a .gif can be safely re-encoded.
    private function is_animated_gif($file_path) {
        global $wp_filesystem;
        if (empty($wp_filesystem)) { require_once(ABSPATH . '/wp-admin/includes/file.php'); WP_Filesystem(); }
        $contents = $wp_filesystem->get_contents($file_path);
        if (empty($contents)) { return false; }
        preg_match_all('/\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)/s', $contents, $matches);
        return isset($matches[0]) && count($matches[0]) > 1;
    }

    // Removes cached image files that are no longer referenced by the current feed data,
    // so the cache folder stays bounded to roughly the number of articles currently live
    // across all feeds, rather than growing forever.
    private function garbage_collect_image_cache($all_news) {
        $cache_dir = $this->get_image_cache_dir();
        $cache_url = $this->get_image_cache_url();
        $used_filenames = [];
        foreach ($all_news as $item) {
            if (!empty($item['thumbnail']) && strpos($item['thumbnail'], $cache_url) === 0) {
                $used_filenames[basename($item['thumbnail'])] = true;
            }
        }
        $existing_files = glob(trailingslashit($cache_dir) . '*');
        $deleted_count = 0;
        if ($existing_files) {
            foreach ($existing_files as $file) {
                if (is_file($file) && !isset($used_filenames[basename($file)])) { wp_delete_file($file); $deleted_count++; }
            }
        }
        if ($this->is_debug_logging_enabled() && $deleted_count > 0) { $this->log_debug('Garbage collection removed ' . $deleted_count . ' stale cached image(s).'); }
    }

    public function fetch_and_cache_news_data($options_to_use = null) {
        include_once(ABSPATH . WPINC . '/feed.php');
        $debug = $this->is_debug_logging_enabled();
        $current_options = is_array($options_to_use) ? $options_to_use : get_option('lfn_settings');
        $rssFeeds = isset($current_options['rss_feeds']) ? $current_options['rss_feeds'] : [];
        $frequency = isset($current_options['update_frequency']) ? $current_options['update_frequency'] : 'hourly';
        $cache_expiration = $this->get_cache_expiration_seconds($frequency);
        if ($debug) { $this->log_debug('--- Fetch cycle started: ' . count($rssFeeds) . ' feed(s) configured ---'); }
        if (empty($rssFeeds)) { if ($debug) { $this->log_debug('No feeds configured, nothing to fetch.'); } set_transient('universal_news_feed_data', [], $cache_expiration); return; }
        $all_news = [];
        foreach ($rssFeeds as $feed) {
            $items = null;
            if ($debug) { $this->log_debug('Fetching "' . $feed['name'] . '" via hybrid (rss2json): ' . $feed['url']); }
            // Always try the external service first for richer image data; the internal
            // parser below runs automatically as a fallback if this doesn't return results.
            $response = wp_remote_get('https://api.rss2json.com/v1/api.json?rss_url=' . urlencode($feed['url']), ['timeout' => 20]);
            if (is_wp_error($response)) { if ($debug) { $this->log_debug('rss2json request failed for "' . $feed['name'] . '": ' . $response->get_error_message()); } }
            elseif (wp_remote_retrieve_response_code($response) === 429) { if ($debug) { $this->log_debug('rss2json rate limit exceeded (HTTP 429) for "' . $feed['name'] . '" — falling back to internal fetcher.'); } }
            elseif (wp_remote_retrieve_response_code($response) !== 200) { if ($debug) { $this->log_debug('rss2json returned HTTP ' . wp_remote_retrieve_response_code($response) . ' for "' . $feed['name'] . '"'); } }
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) { $data = json_decode(wp_remote_retrieve_body($response), true); if ($data && $data['status'] === 'ok') { $items = $data['items']; if ($debug) { $this->log_debug('rss2json returned ' . count($items) . ' item(s) for "' . $feed['name'] . '"'); } foreach ($items as &$item) { $has_enclosure_image = !empty($item['enclosure']['link']) && !empty($item['enclosure']['type']) && strpos($item['enclosure']['type'], 'image') === 0; if (empty($item['thumbnail']) && !$has_enclosure_image) { $html_to_scan = !empty($item['content']) ? $item['content'] : (isset($item['description']) ? $item['description'] : ''); $found_image = $this->extract_first_image_url($html_to_scan); if (!$found_image && !empty($item['link'])) { if ($debug) { $this->log_debug('No image in feed data for "' . $item['title'] . '", trying og:image fallback'); } $found_image = $this->fetch_og_image($item['link']); } if ($found_image) { $item['thumbnail'] = $found_image; } elseif ($debug) { $this->log_debug('No image found anywhere for "' . $item['title'] . '" — will use placeholder.'); } } } unset($item); } elseif ($debug) { $this->log_debug('rss2json status was not "ok" for "' . $feed['name'] . '"'); } }
            if (is_null($items)) {
                if ($debug) { $this->log_debug('Falling back to internal fetcher for "' . $feed['name'] . '"'); }
                $rss = fetch_feed($feed['url']);
                if (is_wp_error($rss)) { if ($debug) { $this->log_debug('Internal fetcher failed for "' . $feed['name'] . '": ' . $rss->get_error_message()); } }
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
                        if (empty($thumbnail)) { if ($debug) { $this->log_debug('No image in internal feed data for "' . $item->get_title() . '", trying og:image fallback'); } $thumbnail = $this->fetch_og_image($item->get_permalink()); }
                        if (empty($thumbnail) && $debug) { $this->log_debug('No image found anywhere for "' . $item->get_title() . '" — will use placeholder.'); }
                        $items[] = [ 'title' => $item->get_title(), 'pubDate' => $item->get_date('Y-m-d H:i:s'), 'link' => $item->get_permalink(), 'description' => $item->get_description(), 'thumbnail' => $thumbnail, 'enclosure' => [], ];
                    }
                    if ($debug) { $this->log_debug('Internal fetcher returned ' . count($items) . ' item(s) for "' . $feed['name'] . '"'); }
                }
            }
            if (is_null($items) && $debug) { $this->log_debug('Both hybrid and internal fetch failed for "' . $feed['name'] . '" — no items retrieved.'); }
            $keywords = !empty($feed['keywords']) ? array_filter(array_map('trim', explode(',', strtolower($feed['keywords'])))) : [];
            if (!is_null($items)) {
                $processed_items = [];
                $skipped_by_keyword = 0;
                foreach($items as $item) {
                    if (!empty($keywords)) {
                        $haystack = strtolower(wp_strip_all_tags((isset($item['title']) ? $item['title'] : '') . ' ' . (isset($item['description']) ? $item['description'] : '')));
                        $matched = false;
                        foreach ($keywords as $keyword) { if ($keyword !== '' && strpos($haystack, $keyword) !== false) { $matched = true; break; } }
                        if (!$matched) { $skipped_by_keyword++; continue; }
                    }
                    $item['sourceName'] = $feed['name'];
                    $image_to_cache = !empty($item['thumbnail']) ? $item['thumbnail'] : (!empty($item['enclosure']['link']) ? html_entity_decode($item['enclosure']['link']) : '');
                    if (!empty($image_to_cache)) {
                        if ($debug && empty($item['thumbnail'])) { $this->log_debug('Image found only in enclosure field for "' . $item['title'] . '", caching that instead: ' . $image_to_cache); }
                        $cached_url = $this->cache_image_locally($image_to_cache);
                        if ($cached_url) { $item['thumbnail'] = $cached_url; }
                    }
                    $processed_items[] = $item;
                }
                if ($debug && !empty($keywords)) { $this->log_debug('Keyword filter for "' . $feed['name'] . '" (' . implode(', ', $keywords) . ') kept ' . count($processed_items) . ' item(s), skipped ' . $skipped_by_keyword . '.'); }
                $all_news = array_merge($all_news, $processed_items);
            }
        }
        usort($all_news, function($a, $b) { return strtotime($b['pubDate']) - strtotime($a['pubDate']); });
        if ($debug) { $this->log_debug('--- Fetch cycle finished: ' . count($all_news) . ' total item(s) across all feeds ---'); }
        $this->garbage_collect_image_cache($all_news);
        set_transient('universal_news_feed_data', $all_news, $cache_expiration);
        update_option('lfn_last_update_timestamp', time());
    }
}
new Universal_News_Feed_Plugin();