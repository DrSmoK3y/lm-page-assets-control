=== Lazy Assets Blocker ===
Contributors: lmdesigners
Tags: performance, speed, asset management, css javascript, page speed, optimization
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block specific CSS and JS files from loading on pages where they are not needed. Improve page speed by preventing unnecessary assets from loading.

== Description ==

**Lazy Asset Blocker** gives you full control over which CSS and JavaScript files load on each page of your WordPress site. Plugins often load their assets globally — on every single page — even when they are only needed on one or two. This causes unnecessary bloat, slower load times, and lower PageSpeed scores.

With Lazy Asset Blocker, you can create simple rules to block any CSS or JS file from loading on specific pages, page types, or even everywhere except where it is needed.

= How It Works =

You provide a partial URL of the asset you want to block (for example `woocommerce.css` or `elementor/assets/css`) and choose where to block it — on the homepage, a specific page, all single posts, and more. The plugin then dequeues and deregisters the matching assets on the frontend, preventing them from loading entirely.

= Key Features =

* **Partial URL matching** — You don't need the full URL. Just enter any part of the filename or path and all matching assets will be blocked.
* **14 built-in page conditions** — Block assets everywhere, on the front page, specific pages (by slug or ID), single posts, archive pages, blog index, and all major WooCommerce contexts (Shop, Product, Cart, Checkout).
* **URL-based conditions** — Block assets on any URL that contains or does not contain a specific string.
* **Enable / Disable rules** — Toggle any rule on or off without deleting it.
* **Named rules** — Give each rule a descriptive name so you can manage them easily.
* **Multiple rules** — Add as many blocking rules as you need.
* **Zero configuration** — No API keys, no external services, no bloat. Just install and use.
* **Clean admin UI** — A simple, modern interface under Plugins → Asset Blocker.
* **WooCommerce compatible** — Includes dedicated conditions for Shop, Product, Cart, and Checkout pages.

= Available Page Conditions =

* Everywhere (all pages)
* Home / Front page
* Everywhere except home
* Blog index page
* Single posts
* Specific page (by slug or ID — supports multiple, comma-separated)
* Archive pages
* WooCommerce — Shop page
* WooCommerce — Everywhere except Shop
* WooCommerce — Single product page
* WooCommerce — Cart page
* WooCommerce — Checkout page
* URL contains a specific string
* URL does NOT contain a specific string

= Common Use Cases =

* Block WooCommerce styles on non-shop pages
* Prevent a contact form plugin from loading its CSS/JS on every page
* Stop a slider plugin from loading scripts on pages that have no slider
* Remove Elementor assets from pages built with a different builder
* Block any third-party plugin asset that is causing PageSpeed issues

= ⚠️ Important Notice =

This plugin directly controls which CSS and JavaScript files are loaded on your website. Blocking the wrong asset can break your site's appearance, functionality, or checkout process. **Always test changes on a staging environment first and keep a backup ready.** If you are not confident about WordPress asset management, consult a developer before making changes.

= Who Is This For? =

This plugin is intended for developers, advanced WordPress users, and site owners who are familiar with browser developer tools and WordPress asset management. It is not a "set and forget" solution — it requires you to identify the assets you want to block and verify that blocking them does not cause issues.

== Installation ==

1. Upload the `lazy-asset-blocker` folder to the `/wp-content/plugins/` directory, or install it directly through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Plugins → ⚡ Asset Blocker** in the WordPress admin menu.
4. Click **Add New Rule** and fill in the asset URL partial and the condition.
5. Click **Save All Rules**.

== Frequently Asked Questions ==

= How do I find the URL of an asset I want to block? =

Open your browser's Developer Tools (press F12), go to the **Network** tab, filter by CSS or JS, and reload the page. You will see all assets being loaded. Copy any part of the filename or path — for example `slider-pro.min.css` or `plugins/slider-pro/css` — and paste it into the Asset URL field.

You can also use PageSpeed Insights (https://pagespeed.web.dev/) to identify render-blocking resources that are slowing down your site.

= Will this break my site? =

It can, if you block the wrong asset. Always test on a staging site first. If something breaks after saving rules, you can disable the offending rule or deactivate the plugin — your site will return to normal immediately.

= Does it work with WooCommerce? =

Yes. The plugin includes dedicated conditions for WooCommerce Shop, Product, Cart, and Checkout pages. However, be especially careful when blocking WooCommerce-related assets as this can affect the purchasing experience.

= Does it work with page builders like Elementor, Divi, or Beaver Builder? =

Yes, you can use it to block page builder assets on pages that don't use that builder. Use the Asset URL partial from the Network tab to identify the correct file paths.

= Can I block multiple assets with one rule? =

One rule targets one partial URL match. However, one partial match can block multiple files if they all share the same path string. For example, entering `elementor/assets` would block all assets whose URL contains that path. Add separate rules for different asset paths.

= Can I block an asset on all pages except one? =

Yes. Use the **URL does NOT contain** condition and enter a string that uniquely identifies the page you want to keep the asset on.

= Does this affect the admin area? =

No. The plugin only runs on the frontend (`wp_enqueue_scripts` hook). Admin assets are never affected.

= Is there a premium version? =

No. Lazy Asset Blocker is completely free with no premium upsell.

== Screenshots ==

1. The main admin panel showing the rule list with the warning notice.
2. An expanded rule card showing the asset URL field and page condition selector.
3. WooCommerce-specific conditions available in the condition picker.
4. Empty state when no rules have been created yet.

== Changelog ==

= 1.0.0 =
* Initial release.
* Support for 14 page conditions including all major WooCommerce contexts.
* Partial URL matching for CSS and JS assets.
* Enable/disable toggle per rule.
* Named rules for easy management.
* Clean, modern admin UI.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
