=== SnipDrop ===
Contributors: shameemreza
Tags: code snippets, woocommerce, php snippets, functions, customization
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add tested code snippets to your WordPress and WooCommerce site with one click. No coding required.

== Description ==

= Curated Code Snippets for WordPress and WooCommerce =

SnipDrop takes a different approach to code snippets. Instead of asking you to write or paste code, it provides a library of pre-tested snippets that you simply enable with a toggle.

Every snippet in SnipDrop has been tested and verified to work. You do not need to copy code from blog posts, paste it into your functions.php, and hope it works. Just find the snippet you need, enable it, and you are done.

= Who is SnipDrop for? =

SnipDrop was built for store owners, site administrators, and developers who want quick solutions without the hassle of managing code manually.

If you have ever searched for "how to change WooCommerce button text" and found yourself copying code from five different websites, SnipDrop is for you.

= How it works =

1. Browse the snippet library by category.
2. Find a snippet that does what you need.
3. Toggle it on.

That is it. The snippet runs immediately. No page refresh needed, no code editing required.

= Key Features =

* Pre-tested snippets that work out of the box.
* One-click enable and disable for each snippet.
* Configurable snippets with custom settings for things like button text, IDs, and more.
* Custom code snippets with full PHP, JS, CSS, and HTML support.
* Copy library snippets to My Snippets for customization.
* Automatic error detection that disables problematic snippets.
* Safe mode with recovery URL if something goes wrong.
* Organized by category for easy browsing.
* Regular library updates with new snippets.

= Snippet Categories =

* WooCommerce Checkout - Customize buttons, fields, and checkout flow.
* WooCommerce Cart - Modify cart behavior and appearance.
* WooCommerce Products - Change product display and pricing.
* WordPress Admin - Customize the admin dashboard.
* More categories added regularly.

= Configurable Snippets =

Some snippets include configurable options. Instead of editing code, you simply fill in a form. For example, to change the checkout button text, just type your desired text in the settings field. No coding required.

= Custom Snippets =

For developers and advanced users, SnipDrop includes a full code editor to create your own snippets. Write PHP, JavaScript, CSS, or HTML code with syntax highlighting and save it alongside the curated library.

You can also copy any library snippet to My Snippets and customize the code to fit your specific needs.

= Error Protection =

SnipDrop includes automatic error protection. If a snippet causes a PHP error, it gets disabled automatically to prevent your site from breaking. You will see a notice explaining what happened and can investigate or re-enable the snippet after fixing the issue.

If your site becomes inaccessible, use the recovery URL (found in Settings) to enter safe mode and disable all snippets.

= Source Attribution =

Many snippets in the library come from official documentation, community contributions, and trusted sources. Each snippet includes attribution where the original source is known, giving proper credit to the developers who shared their solutions.

== Installation ==

1. Upload the `snipdrop` folder to `/wp-content/plugins/` or install through the WordPress plugin screen.
2. Activate the plugin through the Plugins screen.
3. Go to SnipDrop in your admin menu.
4. Click Sync Library to fetch available snippets.
5. Enable the snippets you want to use.

== Frequently Asked Questions ==

= Do I need coding knowledge to use SnipDrop? =

No. SnipDrop is designed for non-developers. You browse, enable, and the snippet works. However, you can view the code for any snippet if you want to understand what it does.

For advanced users, the My Snippets feature provides a full code editor to create custom snippets.

= What happens if a snippet breaks my site? =

SnipDrop automatically detects PHP errors. If a snippet causes a fatal error, it gets disabled immediately to protect your site. You can also use the recovery URL to enter safe mode and disable all snippets at once.

= Will snippets survive theme updates? =

Yes. Snippets are stored in the WordPress database, independent of your theme. Updating or changing your theme will not affect your enabled snippets.

= Can I customize library snippets? =

Yes. For configurable snippets, you can change settings like text values and IDs without touching any code. For full customization, use the Copy to My Snippets feature to create your own editable version.

= Can I add my own custom code? =

Yes. Go to SnipDrop > Add New to create custom PHP, JavaScript, CSS, or HTML snippets with a full code editor.

= How often are new snippets added? =

The snippet library is updated regularly. Click Sync Library in the plugin to check for new additions.

= Does SnipDrop work with page builders? =

Yes. SnipDrop works at the WordPress level, so it is compatible with Elementor, Divi, Beaver Builder, and other page builders.

= Is SnipDrop compatible with caching plugins? =

Yes. SnipDrop works with WP Super Cache, W3 Total Cache, LiteSpeed Cache, WP Rocket, FlyingPress and other caching solutions. You may need to clear your cache after enabling or disabling snippets.

== Screenshots ==

1. Snippet library with category filtering and toggle controls.
2. Configurable snippet settings modal.
3. My Snippets page with custom code management.
4. Add New snippet page with code editor.
5. Settings page with safe mode and recovery URL.

== Changelog ==

= 1.1.0 =
* Added configurable snippets with custom settings.
* Added My Snippets for custom code management.
* Added code editor for creating PHP, JS, CSS, and HTML snippets.
* Added Copy to My Snippets feature for library items.
* Improved error handling for custom snippets.

= 1.0.0 =
* Initial release.
* Curated snippet library with categories.
* One-click enable and disable.
* Automatic error detection and snippet disabling.
* Safe mode with recovery URL.
* GitHub-based library sync.

== Third-Party Services ==

This plugin connects to the following external service to fetch the snippet library.

**GitHub**

* Service: raw.githubusercontent.com
* Purpose: Fetching the snippet library data (JSON files only).
* Privacy Policy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
* Terms of Service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service

Data transmitted: Standard HTTP requests to fetch JSON files. No personal information or site data is sent.
