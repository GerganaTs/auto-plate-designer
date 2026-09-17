=== Auto Plate Designer ===
Contributors: gergana
Tags: woocommerce, vehicle-plate, product-configurator, canvas
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to: 10.0

WooCommerce product configurator for custom vehicle plates with a live canvas preview, cart snapshot, and Bulgarian admin/shop labels.

== Description ==

Auto Plate Designer lets shoppers design a custom vehicle plate on the product page. Choices are stored with the cart item, the order, and the invoice, including a PNG preview snapshot.

Formats (WooCommerce → Auto Plate Designer → Formats):

* EU — parametric 520×110 mm plate, country band, optional millimetre frame
* Motorcycle — 240×130 mm painted plate with a euroband (same country presets as EU)
* US — 305×152 mm, state graphic from the USA designs library, text only
* SUV / crossover — 340×200 mm type C; upload the full plate photo, shoppers change only the number text
* Color — EU-sized painted plate without a country band; shoppers pick the fill
* Custom — street / name plates with multiline text
* Holder — 520×110 mm photo of a plate holder; shoppers change only the bottom inscription strip

Admin Formats studio and shop preview:

* Canvas display is capped at 500 CSS pixels (internal draw resolution stays sharp)
* Numeric admin fields (text area, band, canvas size) sit in a two-column grid so they stay readable on a folded wp-admin sidebar
* **Center text area** button places the dashed box in the middle of its region (plate minus euroband/frame; holder strip for holders)
* Hidden country-band number fields are disabled on USA / SUV / holder types so the browser does not block Save (`not focusable`)

EU / motorcycle format editor (Formats tab):

* White studio, proportional plate, sample text `CA 0909 BX`
* **Without frame** checkbox: when checked, Width and Default color are disabled and the shop draws no border
* When the frame is on, drag the inner corners to change `border_width` (mm)
* Country-band image is movable and resizable; the same `band_box` percentages are used in the shop (default 40 mm euroband)

Shop product page:

* Live canvas on the left, form on the right
* No “Without frame” control for shoppers — that is a format setting
* Border color swatches are hidden when the format has no frame

This plugin requires WooCommerce 8.0 or higher and declares compatibility with High-Performance Order Storage (HPOS).

== Installation ==

= Development sandbox =

This repository is the plugin source of truth. Keep it separate from `avtonomera` until the plugin is ready to ship.

Local test site (sibling folder, not inside this repo):

* WordPress root: `C:\Users\Geri\Documents\Emo\plate-designer`
* URL: `http://localhost/plate-designer`
* wp-admin: `http://localhost/plate-designer/wp-admin/`
* Junction (Cursor edits appear immediately):

        mklink /J "C:\Users\Geri\Documents\Emo\plate-designer\wp-content\plugins\auto-plate-designer" "C:\Users\Geri\Documents\Emo\auto-plate-designer"

WooCommerce and Auto Plate Designer are activated on this sandbox only.

Optional pretty URL: after clicking Reload in Laragon, add `127.0.0.1 plate-designer.test` to the Windows hosts file and switch the site URL to `http://plate-designer.test`.

When the plugin is proven, install the ZIP into `avtonomera` (do not develop against avtonomera).

= Production ZIP =

Build a clean ZIP from tracked files only (no `.git`, no ignored dev files):

    git archive --format=zip --output=auto-plate-designer.zip HEAD

Test the ZIP on a staging WordPress site: Plugins → Add New → Upload Plugin. Only then upload it to production.

== Frequently Asked Questions ==

= Does this plugin load scripts on every page? =

No. Configurator CSS/JS load only on product pages where the configurator is enabled.

= Are order records compatible with HPOS? =

Yes. Order data goes through WooCommerce CRUD methods, never direct post meta on an order ID. Compatibility is declared in the main plugin file.

= How do I turn the plate frame off? =

In **Formats**, check **Without frame**. Width and Default color become unused. Shoppers will not see a border or a border-color palette. Uncheck it to draw the millimetre frame again.

= Why is the admin EU preview larger than the plate? =

The Formats studio is a white mat around a 520×110 plate so the number, euroband, and frame match the shop canvas instead of a dark schematic.

= The site is in Bulgarian. Why were some labels still English? =

Admin and shop strings use the `auto-plate-designer` text domain. Bulgarian maps live in `languages/auto-plate-designer-bg_BG.l10n.php`. After adding strings, reload the settings screen; if a label is still English, the source string is missing from that file.

== Changelog ==

= 1.0.0 =
* Plugin skeleton, activation checks, and file layout.
* Centralized whitelist validation, upload MIME checks, SVG sanitization, nonce and rate-limit helpers.
* Admin settings tabs for formats, country presets, palettes, fonts, and limits, plus a product meta box.
* EU Formats studio: white padded canvas, proportional plate, Without frame checkbox, draggable/resizable country band and frame.
* Shop canvas uses saved `band_box` and format-level `no_frame`.
* Motorcycle (240×130) and SUV / crossover (340×200, full-plate photo) format types; holder remains a full photo with bottom-strip text.
* Admin and shop canvases display at most 500px. Center text area control after resize.
* Saving a USA format no longer trips HTML5 validation on hidden euroband fields.
* Bulgarian translations for remaining admin and shop labels.
