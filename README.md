# Auto Plate Designer

WooCommerce configurator for custom vehicle plates. Shoppers style a plate on the product page; the same configuration is stored on the cart line and the order.

## Shop preview

Every configurator preview uses the same width as a car plate: 500 CSS pixels (`APD_Formats::CANVAS_DISPLAY_MAX_PX`). Height follows that format’s aspect ratio, so a motorcycle plate, an SUV plate, a USA plate, and a holder stay as wide as the EU plate and only grow taller or shorter with their own proportions. A new format type uses the same width without a separate layout.

The country-band button stays at most as tall as a car plate (about 106 pixels) so a tall plate does not stretch the country control.

## Two-row plates

Motorcycle plates (`moto`, `moto_plain`) still split one field: letters, grouped in pairs, on the top row and digits on the bottom row (`AA 0099 AA` is shown as `AA AA` over `0099`).

SUV plates (`suv`, `suv_eu`) have two fields on the product page, **First row** and **Second row**. What the shopper types is painted on that row. The format editor sets a character limit for each row (the default is 5 on the first row and 4 on the second). The product page shows that limit under the matching field, for example `3 / 5` and `2 / 4`. In the Formats studio the SUV sample is four letters on the first row with a space between the pairs (`CA AA`) and `1234` on the second row.

The EU SUV band covers the first row only. An active country preset is shown on every EU, motorcycle, and EU SUV product. The preset graphic sits inside the frame: the shop draws it as the original image, including SVG, on top of the plate so the frame does not cover it. The cart image uses the same crop and the same blue band fill, so the preset is not stretched and the file’s rounded corner does not show through.

SUV without a preset (`suv`) is a painted two-row plate. Saving that format does not require a photo of the whole plate.

A car plate without a preset (`eu_plain`) uses the same studio as a color plate: 520×110, plate fill, text, and frame, with no country band. It is still a car plate, so its products stay in the EU category.

## Configured image

Adding a plate to the cart stores a PNG of the canvas, including the country graphic and the colors the shopper chose. That image is the thumbnail in the cart, the mini-cart, checkout, order emails, the customer order page, and the admin order screen.

## Frame

Shoppers can add or hide the admin frame on painted plates. The checkbox label is “Add frame” (Bulgarian: “Добави рамка”). Letters are fitted inside that frame, so the border does not cut them off.

## Fonts

The product page draws the font the shopper picks. A plate font file has to contain the letters being typed. When it has no Cyrillic glyph for a letter that looks like a Latin one (А В Е К М Н О Р С Т У Х), the preview uses that font’s Latin lookalike, so Oswald, Roboto, and a plate font still look different. The text stored on the order stays what the shopper typed.

USA plates (`us`, both standard and non-standard formats of that type) and car plate holders do not offer a frame. The shop hides the checkbox and the border colors, and the cart does not list a frame for those types.

## Add to cart

WooCommerce sends the product form and then loads the product page again. That reload used to paint the catalog default instead of the plate the shopper had just styled.

The configurator now posts the form without replacing the preview, so the styled plate stays on the page. The header cart count refreshes from the same request. If the product page is opened again while that styled plate is still in the cart, the preview is filled from the latest cart line for the product (text, font, country, design, colors, and frame).

## Language

Plugin strings use the `auto-plate-designer` text domain. Bulgarian translations live in `languages/auto-plate-designer-bg_BG.l10n.php`. The shop uses the site language (Settings → General). The admin can still follow the user’s profile language.

## Holders

Holders use the bundled car-holder photo at 520×260. Shoppers change the strip text and the strip color. The color follows the rounded ends of the white bar. Single-line text is centered on the ink of the glyphs.

## Catalog

The plugin creates WooCommerce categories for EU, USA, motorcycle, SUV, street, color, and holder plates, and assigns a configured product to the matching category.
