=== Universal News Feed ===
Contributors: pashalislaoutaris
Tags: rss, news feed, aggregator, shortcode
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A configurable, resilient plugin to display a cached news feed from any RSS sources via a shortcode.

== Description ==

Universal News Feed lets you pull in articles from any RSS feed and display them anywhere on your site with a simple shortcode:

`[latest-news-feed]`

Feeds are fetched on a schedule and cached, so visitors never wait on a slow external RSS source. Images are found automatically from each article, with several fallbacks (feed thumbnails, embedded images, and Open Graph preview images) so real article photos are used wherever possible instead of a placeholder.

You can also filter a specific shortcode instance down to one or more named sources, which makes it possible to build separate sections on the same page — for example, one section fed by one set of sources and another fed by a different set:

`[latest-news-feed source="Source Name"]`
`[latest-news-feed source="Source Name, Another Source"]`

= Features =
* Configurable list of RSS feed sources, each with its own display name
* Scheduled background fetching with transient caching (hourly, twice daily, or daily)
* Automatic image discovery with multiple fallbacks, including Open Graph images
* List or grid layout, with customizable colors and custom CSS
* Optional "Load More" pagination
* Upload your own placeholder images for articles with no image available
* Per-source filtering via a shortcode attribute, for multiple independent feed sections on one page
* One-click manual cache refresh from the settings screen

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/universal-news-feed` directory, or install the plugin through the WordPress Plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings > Universal News Feed to add your RSS sources and configure the layout.
4. Place the `[latest-news-feed]` shortcode on any page or post.

== Frequently Asked Questions ==

= Why do some articles show a placeholder image instead of the real photo? =

The plugin looks for an image in several places: the feed's own thumbnail/enclosure data, any image embedded in the article content, and finally the article page's Open Graph preview image. If none of these are available — often because a publisher blocks automated requests — a placeholder image is used instead.

= Can I show different sources in different places on the same page? =

Yes. Use the `source` attribute on the shortcode with the exact Source Name you gave that feed in the settings screen, e.g. `[latest-news-feed source="My Source"]`. Add multiple shortcode instances with different `source` values to build separate sections.

= How often is the feed updated? =

By default, hourly. This can be changed to twice daily or daily under Settings > Universal News Feed > Advanced. You can also trigger an immediate refresh with the "Force Refresh Now" button on the settings screen.

== Changelog ==

= 2.1 =
Version 2.1 focuses on hardening the plugin for WordPress.org's automated code review standards.

= 2.0 =
* Renamed and refactored from the original single-purpose plugin into a generic, source-agnostic news feed plugin.
* Added per-source shortcode filtering, allowing multiple independent feed sections on one page.
* Rebuilt the front-end rendering to support multiple shortcode instances on the same page without ID collisions.
* Added Open Graph image fallback for articles with no image in their RSS feed.
* Improved image discovery to handle lazy-loaded images (data-src, srcset).
* Removed the internal-only fetcher option; the plugin now always attempts the external fetch service first with an automatic fallback to the internal parser.

= 1.0 =
* Initial release.
