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
* **Global Header & Footer:** Insert custom scripts into your site header, body open, and footer from a dedicated page with CodeMirror editors.
* **Smart Conditional Logic Builder:** A visual rule builder with AND/OR groups, 20+ condition types, and Show/Hide logic to control exactly where and when snippets run.
* **Testing Mode:** Preview snippet changes in a sandbox visible only to admins. Publish or discard when ready — visitors never see untested code.
* **Activity Log:** Track every snippet lifecycle event — enables, disables, edits, errors, imports, and settings changes — with filtering and pagination.
* **7 Plugin Importers:** Migrate from WPCode, Code Snippets, Code Snippets Pro, Woody Snippets, Simple Custom CSS and JS, Header Footer Code Manager, and Post Snippets with one click.
* **Custom Named Shortcodes:** Give your custom snippets a named shortcode (e.g., `[my-banner]`) for easy placement anywhere.
* **WooCommerce Locations:** Auto-insert snippets before/after shop loops, single products, cart, checkout form, and thank-you page.
* **After-Paragraph Insertion:** Insert snippets after a specific paragraph number in post content.
* **Compatibility Checker:** See at a glance whether your PHP version, WordPress version, and plugins meet a snippet's requirements before you enable it.
* **Conflict Detection:** Warns you when two active snippets hook into the same WordPress action or filter, with high-risk alerts for critical hooks.
* **Performance Badges:** Every snippet shows its expected performance impact — Lightweight, Moderate, or Heavy — so you can make informed decisions.
* **Email Error Alerts:** Get an email notification when a snippet is auto-disabled due to an error, with rate limiting to prevent flooding.
* **Visual Diff for Revisions:** Compare any revision to the current code with a line-by-line diff view highlighting added, removed, and unchanged lines.
* **Custom Code Editor:** Full PHP, JS, CSS, and HTML support with syntax highlighting.
* **Editor Dark Mode:** Toggle dark mode in the code editor for comfortable coding.
* **Tags:** Clickable tags on library snippets for quick filtering, plus custom tags on your own snippets.
* **Copy to My Snippets:** Copy any library snippet and customize it to fit your needs.
* **Import & Export:** Move snippets between sites with JSON export/import.
* **Code Auto-Detection:** Paste code and the editor automatically detects PHP, JS, CSS, or HTML.
* **Snippet Revisions:** Previous versions of your custom snippets are saved automatically.
* **Search & Bulk Actions:** Search My Snippets and manage multiple snippets at once.
* **Grid & List View:** Toggle between grid and list view on the library page.
* **Admin Bar Quick Access:** See active snippet count and quick links from the WordPress admin bar.
* **Keyboard Shortcut:** Save snippets with Ctrl+S / Cmd+S.
* **Developer Hooks:** Actions and filters for extending SnipDrop programmatically.

= Smart Conditional Logic =

Control exactly where and when your snippets run with a visual rule builder:

* **AND/OR Groups:** Combine conditions with AND logic within groups and OR logic between groups for precise targeting.
* **Show/Hide Logic:** Choose to show or hide snippets when conditions are met.
* **User Conditions:** Logged-in state, user role (multi-select from your site's roles).
* **Page Conditions:** Post type, specific pages/posts (AJAX search picker), page type (front page, home, single, archive, search, 404).
* **URL Patterns:** Match URL paths with wildcard support (e.g., `/shop/*`, `/checkout`).
* **Taxonomy Terms:** Categories, tags, product categories, or any custom taxonomy.
* **Schedule:** Start and end dates, before/after specific times, day of week.
* **Device Targeting:** Desktop or mobile (uses `wp_is_mobile()`).
* **WooCommerce Conditions:** Cart total thresholds, product in cart, customer role, and more (when WooCommerce is active).
* **Run Location:** Everywhere, frontend only, admin only, or specific auto-insert locations.

= Testing Mode =

Make changes to your snippets without affecting live visitors. When Testing Mode is enabled, your edits are saved to a staging area. Only administrators see the staged version. When you are ready, publish all changes at once or discard them. Visitors continue seeing the live, stable version throughout.

= Activity Log =

A dedicated page tracks every important event in your snippet workflow:

* Snippet enabled, disabled, created, updated, or deleted.
* Failed activation attempts with error details.
* Import events from JSON files or other plugins.
* Settings changes and testing mode publish/discard events.
* Filter by event type, paginate through history, and clear when needed.

= Error Protection =

* **Automatic Error Detection:** Snippets that cause PHP errors are disabled automatically.
* **PHP Syntax Validation:** Code is checked for syntax errors before activation — works reliably across all hosting environments including shared hosting, managed WordPress, and local development tools.
* **Email Error Alerts:** Receive email notifications when a snippet is auto-disabled. Rate-limited to one email per 15 minutes. Configurable in Settings.
* **Safe Mode:** Disable all snippets at once if something goes wrong — including global header/footer scripts.
* **Recovery URL:** A secret URL that enables safe mode even if you cannot access the admin.
* **Suspicious Code Detection:** Warns about potentially dangerous code patterns before saving.
* **File-Based Error Logging:** Errors are logged with timestamps and line numbers.

= Import from Other Plugins =

Switching to SnipDrop? Import your existing snippets from any of these plugins:

* **WPCode** (free and premium)
* **Code Snippets** (free)
* **Code Snippets Pro**
* **Woody Code Snippets**
* **Simple Custom CSS and JS**
* **Header Footer Code Manager**
* **Post Snippets**

All imported snippets are set to inactive for safety. Original code, settings, and metadata are preserved. An `imported-{source}` tag is automatically added for easy identification.

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
* WooCommerce Subscriptions: Customize subscription behavior.
* WooCommerce Product Add-Ons: Modify add-on display and logic.
* WooCommerce Product Bundles: Customize bundle behavior.
* WooCommerce Bookings: Modify booking display and flow.
* WooCommerce Tools: Utility snippets for store management.
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

= Can I use a custom shortcode name? =

Yes. When creating a custom snippet, you can set the Run Location to Shortcode and define a custom name like `my-banner`. Then use `[my-banner]` anywhere in your content. The default `[snipdrop id="..."]` shortcode also works.

= Can I add scripts to my site header and footer? =

Yes. Go to SnipDrop > Header & Footer to add custom code to your site's `<head>`, after `<body>`, and before `</body>`. This is ideal for analytics tracking codes, custom CSS, chat widgets, and verification tags.

= Can I schedule a snippet to run only during a specific period? =

Yes. Each snippet has scheduling options where you can set a start date, end date, time constraints, and even specific days of the week. The snippet will only execute within that window.

= Can I restrict a snippet to specific pages or URLs? =

Yes. The conditional logic builder lets you target snippets by post type, specific post/page IDs, page type (front page, archive, search, 404), URL patterns with wildcards, taxonomy terms, device type, user role, and more — all combinable with AND/OR logic.

= Can I test snippet changes before going live? =

Yes. Enable Testing Mode from the Settings page or admin bar. Your changes are saved to a staging area that only administrators can see. When satisfied, publish all changes at once. Visitors always see the stable live version.

= Can I import snippets from other plugins? =

Yes. SnipDrop can import from WPCode, Code Snippets, Code Snippets Pro, Woody Snippets, Simple Custom CSS and JS, Header Footer Code Manager, and Post Snippets. Go to My Snippets and look for the Import from Plugin option. You can also import from SnipDrop's own JSON export format.

= Can I export my snippets? =

Yes. Go to My Snippets and click Export to download all or selected snippets as a JSON file.

= Can I give editors access to snippets without making them administrators? =

Yes. SnipDrop uses a custom capability (`sndp_manage_snippets`). Use any capability manager plugin to grant this capability to the Editor role or any other role.

= What is the Activity Log? =

The Activity Log tracks every snippet lifecycle event — enables, disables, edits, errors, imports, and settings changes. It is available under SnipDrop > Activity Log. You can filter by event type and clear the log when needed. Up to 200 events are stored.

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

= Does SnipDrop work on managed WordPress hosting? =

Yes. SnipDrop is designed to work on all hosting environments including shared hosting, managed platforms (Pressable, WP Engine, Kinsta, Cloudways, WordPress VIP), VPS, and local development tools like Laravel Herd, Local, and MAMP.

= Does SnipDrop slow down my site? =

No. SnipDrop only loads active snippets and uses WordPress's built-in hook system. Instance-level caching minimizes database reads. Each snippet also shows a performance badge (Lightweight, Moderate, Heavy) so you can see the expected impact before enabling.

== Screenshots ==

1. Snippet library with compatibility badges, performance weight, and conflict indicators.
2. Configurable snippet settings modal.
3. My Snippets page with search, bulk actions, and grid/list view toggle.
4. Add New snippet page with code editor, dark mode, and conditional logic builder.
5. Conditional logic builder with AND/OR groups and 20+ condition types.
6. Global Header & Footer scripts page with CodeMirror editors.
7. Testing Mode with staging banner and publish/discard controls.
8. Activity Log page with event filtering and pagination.
9. Import from 7 popular snippet plugins.
10. Settings page with safe mode, admin bypass, error handling, email alerts, and recovery URL.
11. Visual diff view comparing a revision to the current code.

== Changelog ==

= 1.0.0 =
* Initial release.
* Curated snippet library with categories, search, tags, and pagination.
* One-click enable and disable for each snippet.
* Grid and list view toggle for the library.
* Configurable snippets with custom settings (no code editing needed).
* My Snippets for custom PHP, JS, CSS, and HTML code management.
* Full code editor with syntax highlighting and dark mode toggle.
* Code type auto-detection on paste.
* Copy library snippets to My Snippets for customization.
* Import and export snippets in JSON format.
* Import from 7 plugins: WPCode, Code Snippets, Code Snippets Pro, Woody Snippets, Simple Custom CSS and JS, Header Footer Code Manager, and Post Snippets.
* Global Header & Footer page with CodeMirror editors for header, body open, and footer scripts.
* Custom named shortcodes for snippet placement anywhere in content.
* Auto-insert locations: header, footer, body open, before/after content, after specific paragraph.
* 8 WooCommerce auto-insert locations: before/after shop loop, single product, cart, checkout form, and thank-you page.
* Smart Conditional Logic Builder with AND/OR groups and Show/Hide logic.
* 20+ condition types: user login state, user role, post type, specific pages, page type, URL patterns, taxonomy terms, device type, schedule, day of week, and WooCommerce conditions.
* Shortcode support with default `[snipdrop]` and custom named shortcodes.
* Date/time scheduling with start and end dates, time ranges, and day-of-week targeting.
* Testing Mode: stage changes visible only to admins, publish or discard when ready.
* Activity Log: track snippet enables, disables, edits, errors, imports, and settings changes with filtering.
* Custom `sndp_manage_snippets` capability for role-based access.
* Admin bypass setting to disable frontend snippets for administrators.
* Environment compatibility checker with dependency badges on every snippet card.
* Snippet conflict detection with high-risk alerts for critical hooks.
* Performance impact badges (Lightweight, Moderate, Heavy) for library and custom snippets.
* Custom code static analysis for PHP version requirements and plugin dependencies.
* PHP syntax validation before snippet activation — works across all hosting environments.
* Email error notifications when snippets are auto-disabled (rate-limited, configurable).
* Visual diff for revisions with line-by-line comparison.
* Automatic error detection with configurable auto-disable.
* Safe mode with recovery URL — disables all snippets including global scripts.
* Suspicious code pattern detection with warnings.
* File-based error logging with error history.
* Snippet revisions with one-click restore.
* Functional tags on library snippets (click to filter) and custom tags on user snippets.
* Search, bulk actions, and duplicate for My Snippets.
* Admin bar quick access with active snippet count.
* Keyboard shortcut (Ctrl+S / Cmd+S) to save snippets.
* Unsaved changes warning when navigating away from the editor.
* New snippet notification badges.
* Developer hooks (actions and filters) for extensibility.
* Toast notifications for save/delete/toggle feedback.
* Inline help tooltips throughout the interface.
* GitHub-based library sync.
* Full data cleanup on uninstall (optional, controlled via Settings).

== Third-Party Services ==

This plugin connects to the following external service to fetch the snippet library.

**GitHub**

* Service: raw.githubusercontent.com
* Purpose: Fetching the snippet library data (JSON files only).
* Privacy Policy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
* Terms of Service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service

Data transmitted: Standard HTTP requests to fetch JSON files. No personal information or site data is sent.
