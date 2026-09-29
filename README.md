# Auto Plate Designer

WooCommerce configurator for custom vehicle plates. Shoppers style a plate on the product page; the same configuration is stored on the cart line and the order.

## Shop preview

Every configurator preview uses the same width as a car plate: 500 CSS pixels (`APD_Formats::CANVAS_DISPLAY_MAX_PX`). Height follows that format’s aspect ratio, so a motorcycle plate, an SUV plate, a USA plate, and a holder stay as wide as the EU plate and only grow taller or shorter with their own proportions. A new format type uses the same width without a separate layout.

The country-band button stays at most as tall as a car plate (about 106 pixels) so a tall plate does not stretch the country control.

##  Two-row plates

Motorcycle plates (`moto`, `moto_plain`) and SUV plates (`suv`, `suv_eu`) have two fields on the product page, **First row** and **Second row**. What the shopper types is painted on that row. The format editor sets a character limit for each row (the default is 5 on the first row and 4 on the second) and the same alignment buttons on each row: left, center, right, and space between. The product page shows that limit under the matching field, for example `3 / 5` and `2 / 4`. In the Formats studio the SUV sample is four letters on the first row with a space between the pairs (`CA AA`) and `1234` on the second row. A motorcycle sample is `CA` over `1234`.

The EU SUV band covers the first row only. An active country preset is shown on every EU, motorcycle, and EU SUV product. The preset graphic sits inside the frame: the shop draws it as the original image, including SVG, on top of the plate so the frame does not cover it. The cart image uses the same crop and the same blue band fill, so the preset is not stretched and the file’s rounded corner does not show through.

SUV without a preset (`suv`) is a painted two-row plate. Saving that format does not require a photo of the whole plate.

A car plate without a preset (`eu_plain`) uses the same studio as a color plate: 520×110, plate fill, text, and frame, with no country band. It is still a car plate, so its products stay in the EU category.

## Configured image

Adding a plate to the cart stores a PNG of the canvas, including the country graphic and the colors the shopper chose. That image is the thumbnail in the cart, the mini-cart, checkout, order emails, the customer order page, and the admin order screen.

## Format colors

Color plates, street plates, and the three plates without a country preset (car, motorcycle, and SUV) each store one palette for the text, one for the plate fill, and one for the frame. The format editor lists every active palette in those three menus, with the palette name and its colors. The shop paints that part with the colors of the palette the admin picked, including a palette that was created for a color plate or a holder.

EU, EU motorcycle, EU SUV, USA, and holder formats keep the checkbox list. A product still only chooses a format.

An older format of the menu types opens with a palette already chosen when exactly one saved palette belongs to that row. When more than one belongs to the row, that menu stays empty until the admin picks one. Saving requires all three.

## Frame

Shoppers can add or hide the admin frame on painted plates. The checkbox label is “Add frame” (Bulgarian: “Добави рамка”). Letters are fitted inside that frame, so the border does not cut them off.

## Fonts

The product page draws the font the shopper picks. A plate font file has to contain the letters being typed. When it has no Cyrillic glyph for a letter that looks like a Latin one (А В Е К М Н О Р С Т У Х), the preview uses that font’s Latin lookalike, so Oswald, Roboto, and a plate font still look different. The text stored on the order stays what the shopper typed.

USA plates (`us`, both standard and non-standard formats of that type) and holders do not offer a frame. The shop hides the checkbox and the border colors, and the cart does not list a frame for those types.

Each USA format stores its own plate graphic. Upload that picture on the format, drag the text area onto the number hole, and set the character limit there. A checkbox on the format, and the same checkbox on a USA design, splits that graphic into two text fields side by side. The two boxes stay on one line and move together. Each has its own width, and the gap between them is the space you leave for a center graphic. The shop then shows two inputs, each with its own character limit, and both use the same letter size. The text palette is chosen on the same form: one menu lists every active palette with its name and colors. On the product page the plate is that uploaded graphic and the text sits in the box you placed. The catalog thumbnail stays the product image set in the product editor.

A separate USA design can still be locked to a product when the format has no graphic of its own. On **USA designs**, **Create product** opens a WooCommerce product for that graphic and one USA format. When the design belongs to more than one format, choose the format first.

## Add to cart

WooCommerce sends the product form and then loads the product page again. That reload used to paint the catalog default instead of the plate the shopper had just styled.

The configurator now posts the form without replacing the preview, so the styled plate stays on the page. The header cart count refreshes from the same request. If the product page is opened again while that styled plate is still in the cart, the preview is filled from the latest cart line for the product (text, font, country, design, colors, and frame).

## Language

Plugin strings use the `auto-plate-designer` text domain. Bulgarian translations live in `languages/auto-plate-designer-bg_BG.l10n.php`. The shop uses the site language (Settings → General). The admin can still follow the user’s profile language.

## Holders

Car plate holders use the bundled gray mask at 520×260 (`gray-plate-holder-hole.png`). Opaque plastic takes the holder-color palette; the plate windows and the strip slot stay empty. Shoppers then set the strip color and the strip text. The strip color follows the rounded ends of that slot. Single-line text is centered on the ink of the glyphs.

Motorcycle holders (`holder_moto`, 199×199) and type D holders (`holder_d`, 280×159) use the bundled product photos (`holder-moto.png`, `holder-type-d.png`). 199 mm is the Bulgarian motorcycle plate width, and the holder is square like its photo. Type D uses the two-line plate width (280 mm); the height keeps the photo's proportion so the slogan strip stays on the white bar. Their previews scale with those millimetres, so they are not drawn as wide as a 520 mm car plate. The plastic and the empty windows stay as in the photo. Shoppers color the bottom strip and the letters from one shared list: the holder-strip palettes and the holder-inscription palettes. The strip starts white and the letters start black. Both types share the holder character limit and the Holders category. An upload can replace the bundled photo.

## Updates

The plugin is not on WordPress.org. wp-admin compares the installed `Version` with tags on [github.com/GerganaTs/auto-plate-designer](https://github.com/GerganaTs/auto-plate-designer). A tag such as `v1.0.1` shows an Update button when it is newer than the copy on the site. Before tagging, set the same number in the plugin header and in `APD_VERSION`. Replacing the plugin this way keeps the saved formats in the database. Deleting the plugin from the Plugins screen removes them.

## Catalog

The plugin creates WooCommerce categories for EU, USA, motorcycle, SUV, street, color, and holder plates, and assigns a configured product to the matching category.
