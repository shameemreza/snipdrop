=== SnipDrop ===
Contributors: shameemreza
Tags: code snippets, woocommerce, php snippets, wpcode, customization
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add tested code snippets to your WordPress and WooCommerce site with one click. No coding required.

== Description ==

= Curated Code Snippets for WordPress and WooCommerce =

SnipDrop takes a different approach to code snippets. Instead of asking you to write or paste code, it provides a library of pre-tested snippets that you simply enable with a toggle.

Every snippet in SnipDrop has been tested and verified to work. You do not need to copy code from blog posts, paste it into your `functions.php`, and hope it works. Just find the snippet you need, enable it, and you are done.

= Who is SnipDrop for? =

SnipDrop was built for store owners, site administrators, and developers who want quick solutions without the hassle of managing code manually.

If you have ever searched for "how to change WooCommerce button text" and found yourself copying code from five different websites, SnipDrop is for you.

= How it works =

1. Browse the snippet library by category.
2. Find a snippet that does what you need.
3. Toggle it on.

That is it. The snippet runs immediately. No page refresh needed, no code editing required.

= Key Features =

* **Curated Library:** Pre-tested snippets that work out of the box.
* **One-Click Toggle:** Enable and disable snippets instantly.
* **Configurable Snippets:** Change settings like button text or IDs without touching code.
* **Compatibility Checker:** See at a glance whether your PHP version, WordPress version, and plugins meet a snippet's requirements before you enable it.
* **Conflict Detection:** Warns you when two active snippets hook into the same WordPress action or filter, with high-risk alerts for critical hooks.
* **Performance Badges:** Every snippet shows its expected performance impact — Lightweight, Moderate, or Heavy — so you can make informed decisions.
* **Email Error Alerts:** Get an email notification when a snippet is auto-disabled due to an error, with rate limiting to prevent flooding.
* **Visual Diff for Revisions:** Compare any revision to the current code with a line-by-line diff view highlighting added, removed, and unchanged lines.
* **Custom Code Editor:** Full PHP, JS, CSS, and HTML support with syntax highlighting.
* **Editor Dark Mode:** Toggle dark mode in the code editor for comfortable coding.
* **Copy to My Snippets:** Copy any library snippet and customize it to fit your needs.
* **Import & Export:** Move snippets between sites with JSON export/import. Also imports from WPCode and Code Snippets.
* **Code Auto-Detection:** Paste code and the editor automatically detects PHP, JS, CSS, or HTML.
* **Snippet Revisions:** Previous versions of your custom snippets are saved automatically.
* **Search & Bulk Actions:** Search My Snippets and manage multiple snippets at once.
* **Grid & List View:** Toggle between grid and list view on the library page.
* **Admin Bar Quick Access:** See active snippet count and quick links from the WordPress admin bar.
* **Keyboard Shortcut:** Save snippets with Ctrl+S / Cmd+S.
* **Developer Hooks:** Actions and filters for extending SnipDrop programmatically.

= Conditional Execution =

Control exactly where and when your snippets run:

* **Run Location:** Everywhere, frontend only, admin only, or auto-insert into header, footer, before/after content.
* **Shortcode Support:** Place snippets anywhere with the `[snipdrop]` shortcode.
* **User Condition:** Run for all users, logged-in only, or logged-out only.
* **Schedule:** Set start and end dates to run snippets only within a specific time window.
* **Post Types:** Restrict to specific post types (posts, pages, products, etc.).
* **Specific Posts/Pages:** Target individual posts or pages by searching and selecting them.
* **URL Patterns:** Match URL paths with wildcard patterns (e.g., `/shop/*`, `/checkout`).
* **Taxonomy Terms:** Run only on posts in specific categories, tags, or custom taxonomy terms.

= Error Protection =

* **Automatic Error Detection:** Snippets that cause PHP errors are disabled automatically.
* **Email Error Alerts:** Receive email notifications when a snippet is auto-disabled. Rate-limited to one email per 15 minutes. Configurable in Settings.
* **Safe Mode:** Disable all snippets at once if something goes wrong.
* **Recovery URL:** A secret URL that enables safe mode even if you cannot access the admin.
* **Suspicious Code Detection:** Warns about potentially dangerous code patterns before saving.
* **File-Based Error Logging:** Errors are logged with timestamps and line numbers.

= Custom Capabilities =

SnipDrop uses a custom `sndp_manage_snippets` capability. Administrators get it automatically, and you can grant it to other roles (e.g., Editor) using any capability manager plugin; without giving them full `manage_options` access.

= Smart Badges =

SnipDrop gives you detailed insight into every snippet before you enable it:

* **Compatibility Badges:** Each library snippet shows whether your environment meets its requirements. Incompatible snippets are clearly marked in red, and their toggles are disabled to prevent errors.
* **Requirement Pills:** See exactly what a snippet needs — "WooCommerce 10.0+", "PHP 8.0+", etc. — at a glance on every card.
* **Conflict Detection:** When two active snippets register callbacks on the same WordPress hook, SnipDrop shows an orange "Potential Conflict" badge. For critical hooks (checkout, pricing, login), the badge turns red.
* **Performance Weight:** Every snippet is classified as Lightweight, Moderate, or Heavy based on what the code does. Custom snippets are analyzed automatically when you save them.
* **Custom Code Analysis:** When you save a custom PHP snippet, SnipDrop checks for PHP version requirements, plugin dependencies, and performance patterns — and warns you before anything breaks.

= Snippet Categories =

* WooCommerce Checkout: Customize buttons, fields, and checkout flow.
* WooCommerce Cart: Modify cart behavior and appearance.
* WooCommerce Products: Change product display and pricing.
* WordPress Admin: Customize the admin dashboard.
* More categories added regularly.

= Configurable Snippets =

Some snippets include configurable options. Instead of editing code, you simply fill in a form. For example, to change the checkout button text, just type your desired text in the settings field. No coding required.

= Custom Snippets =

For developers and advanced users, SnipDrop includes a full code editor to create your own snippets. Write PHP, JavaScript, CSS, or HTML code with syntax highlighting and save it alongside the curated library.

You can also copy any library snippet to My Snippets and customize the code to fit your specific needs.

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

SnipDrop automatically detects PHP errors. If a snippet causes a fatal error, it gets disabled immediately to protect your site. You can also use the recovery URL (found in Settings) to enter safe mode and disable all snippets at once.

= Will snippets survive theme updates? =

Yes. Snippets are stored in the WordPress database, independent of your theme. Updating or changing your theme will not affect your enabled snippets.

= Can I customize library snippets? =

Yes. For configurable snippets, you can change settings like text values and IDs without touching any code. For full customization, use the Copy to My Snippets feature to create your own editable version.

= Can I add my own custom code? =

Yes. Go to SnipDrop > Add New to create custom PHP, JavaScript, CSS, or HTML snippets with a full code editor. You get syntax highlighting, dark mode, and all the conditional execution options.

= Can I schedule a snippet to run only during a specific period? =

Yes. Each snippet has a Schedule option where you can set a start date and end date. The snippet will only execute within that time window. Uses your site's configured timezone.

= Can I restrict a snippet to specific pages or URLs? =

Yes. You can target snippets by post type, specific post/page IDs, URL patterns with wildcards (e.g., `/shop/*`), or taxonomy terms (categories, tags, product categories).

= Can I import snippets from other plugins? =

Yes. SnipDrop can import from its own JSON format, as well as from WPCode and Code Snippets export files. Go to My Snippets and click Import.

= Can I export my snippets? =

Yes. Go to My Snippets and click Export to download all or selected snippets as a JSON file.

= Can I give editors access to snippets without making them administrators? =

Yes. SnipDrop uses a custom capability (`sndp_manage_snippets`). Use any capability manager plugin to grant this capability to the Editor role or any other role.

= What do the compatibility and performance badges mean? =

Each library snippet shows badges based on your environment. If a snippet requires a plugin you do not have installed or a PHP version higher than yours, it will be marked as "Incompatible" and the toggle will be disabled. Performance badges indicate expected impact: Lightweight (minimal), Moderate (some processing), or Heavy (database queries or external calls).

= What happens if two snippets conflict? =

SnipDrop automatically detects when two active snippets hook into the same WordPress action or filter. You will see an orange "Potential Conflict" badge on the affected snippets. For critical hooks like checkout fields or pricing, the badge turns red. The snippets still work — the badges are warnings to help you test your site.

= Will I get notified if a snippet is auto-disabled? =

Yes. By default, SnipDrop sends you an email when a snippet causes an error and is automatically disabled. You can configure the notification email or turn this off in Settings. Emails are rate-limited to one per 15 minutes.

= How often are new snippets added? =

The snippet library is updated regularly. Click Sync Library in the plugin to check for new additions. You will also see a notification badge when new snippets are available.

= Does SnipDrop work with page builders? =

Yes. SnipDrop works at the WordPress level, so it is compatible with Elementor, Divi, Beaver Builder, and other page builders.

= Is SnipDrop compatible with caching plugins? =

Yes. SnipDrop works with WP Super Cache, W3 Total Cache, LiteSpeed Cache, WP Rocket, FlyingPress and other caching solutions. You may need to clear your cache after enabling or disabling snippets.

= Does SnipDrop slow down my site? =

No. SnipDrop only loads active snippets and uses WordPress's built-in hook system. There are no additional database queries on the frontend beyond reading the stored options. Each snippet also shows a performance badge (Lightweight, Moderate, Heavy) so you can see the expected impact before enabling.

== Screenshots ==

1. Snippet library with compatibility badges, performance weight, and conflict indicators.
2. Configurable snippet settings modal.
3. My Snippets page with search, bulk actions, and grid/list view toggle.
4. Add New snippet page with code editor, dark mode, and conditional options.
5. Settings page with safe mode, admin bypass, error handling, email alerts, and recovery URL.
6. Visual diff view comparing a revision to the current code.

== Changelog ==

= 1.0.0 =
* Initial release.
* Curated snippet library with categories, search, and pagination.
* One-click enable and disable for each snippet.
* Grid and list view toggle for the library.
* Configurable snippets with custom settings (no code editing needed).
* My Snippets for custom PHP, JS, CSS, and HTML code management.
* Full code editor with syntax highlighting and dark mode toggle.
* Code type auto-detection on paste.
* Copy library snippets to My Snippets for customization.
* Import and export snippets (JSON format, plus WPCode and Code Snippets import).
* Auto-insert locations: header, footer, before/after content, frontend, admin.
* Shortcode support for custom snippets.
* Conditional execution by user state, post type, page ID, URL patterns, and taxonomy terms.
* Date/time scheduling with start and end dates.
* Custom `sndp_manage_snippets` capability for role-based access.
* Admin bypass setting to disable frontend snippets for administrators.
* Environment compatibility checker with dependency badges on every snippet card.
* Snippet conflict detection with high-risk alerts for critical hooks.
* Performance impact badges (Lightweight, Moderate, Heavy) for library and custom snippets.
* Custom code static analysis for PHP version requirements and plugin dependencies.
* Email error notifications when snippets are auto-disabled (rate-limited, configurable).
* Visual diff for revisions with line-by-line comparison.
* Automatic error detection with configurable auto-disable.
* Safe mode with recovery URL.
* Suspicious code pattern detection with warnings.
* File-based error logging with error history.
* Snippet revisions with one-click restore.
* Search, bulk actions, and duplicate for My Snippets.
* Admin bar quick access with active snippet count.
* Keyboard shortcut (Ctrl+S / Cmd+S) to save snippets.
* New snippet notification badges.
* Developer hooks (actions and filters) for extensibility.
* Toast notifications for save/delete/toggle feedback.
* Inline help tooltips throughout the interface.
* GitHub-based library sync.

== Third-Party Services ==

This plugin connects to the following external service to fetch the snippet library.

**GitHub**

* Service: raw.githubusercontent.com
* Purpose: Fetching the snippet library data (JSON files only).
* Privacy Policy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
* Terms of Service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service

Data transmitted: Standard HTTP requests to fetch JSON files. No personal information or site data is sent.
