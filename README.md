# Auto Plate Designer

WooCommerce configurator for custom vehicle plates. Shoppers style a plate on the product page; the same configuration is stored on the cart line and the order.

## Shop preview

Every plate preview uses the same width as a car plate: 500 CSS pixels (`APD_Formats::CANVAS_DISPLAY_MAX_PX`). Height follows that format’s aspect ratio, so a motorcycle plate, an SUV plate, and a USA plate stay as wide as the EU plate and only grow taller or shorter with their own proportions. A holder preview is as wide as the plate it is made for, on that same scale (520 mm = 500 px). A car holder stays 500 px wide. A motorcycle holder matches a 199×154 plate. A type D holder matches a 280×200 plate. The holder photo keeps its own proportions.

The country-band button stays at most as tall as a car plate (about 106 pixels) so a tall plate does not stretch the country control.

##  Two-row plates

Motorcycle plates and SUV plates (`suv`, `suv_eu`) have two fields on the product page, **First row** and **Second row**. What the shopper types is painted on that row. The format editor sets a character limit for each row (the default is 5 on the first row and 4 on the second) and the same alignment buttons on each row: left, center, right, and space between. Those buttons stay in the format editor. The product page shows the two fields and the limit under each one, for example `3 / 5` and `2 / 4`. In the Formats studio the SUV sample is four letters on the first row with a space between the pairs (`CA AA`) and `1234` on the second row. A motorcycle sample is `CA` over `1234`.

There are four motorcycle types. `moto` is the 199×154 plate with a euroband. `moto_240` is the 240×130 plate with a euroband. `moto_plain` is the 199×154 plate without a country band. `moto_plain_240` is the 240×130 plate without a country band. The euroband on `moto_240` uses the same 40 mm formula as a car plate and runs the full height of the plate. Picking a type locks those millimetres. A street plate (`custom`) is still a street plate: the admin types its millimetres, including 240×130, and it stays one line. Old motorcycle formats stay on their current type. Adding a 240×130 motorcycle plate is a new format of `moto_240` or `moto_plain_240`.

**Duplicate** on the Formats list copies the saved format, including its size, colors, and text area. The copy is named with ` (Copy)` after the original name (Bulgarian: ` (Копие)`).

The EU SUV band covers the first row only. An active country preset is shown on every EU, motorcycle, and EU SUV product. The preset graphic sits inside the frame: the shop draws it as the original image, including SVG, on top of the plate so the frame does not cover it. The cart image uses the same crop and the same blue band fill, so the preset is not stretched and the file’s rounded corner does not show through.

SUV without a preset (`suv`) is a painted two-row plate. Saving that format does not require a photo of the whole plate.

A car plate without a preset (`eu_plain`) uses the same studio as a color plate: 520×110, plate fill, text, and frame, with no country band. It is still a car plate.

## Configured image

Adding a plate to the cart stores a PNG of the canvas, including the country graphic and the colors the shopper chose. That image is the thumbnail in the cart, the mini-cart, checkout, order emails, the customer order page, and the admin order screen.

## Format colors

Color plates, street plates, and the plates without a country preset (car, both motorcycle sizes, and SUV) each store one palette for the text, one for the plate fill, and one for the frame. The format editor lists every active palette in those three menus, with the palette name and its colors. The shop paints that part with the colors of the palette the admin picked, including a palette that was created for a color plate or a holder.

EU, EU motorcycle, EU SUV, USA, and holder formats keep the checkbox list. A product still only chooses a format.

An older format of the menu types opens with a palette already chosen when exactly one saved palette belongs to that row. When more than one belongs to the row, that menu stays empty until the admin picks one. Saving requires all three.

## Frame

Shoppers can add or hide the admin frame on painted plates. The checkbox label is “Add frame” (Bulgarian: “Добави рамка”). The text area cannot come closer than 8 mm inside that frame, or closer than 8 mm to a full-height country band. The format editor stops the box at that line, and the shop fits the letters — including both rows of a two-line plate — inside the same margin. Holders and plates with the frame turned off keep their own area.

## Fonts

The product page draws the font the shopper picks. A plate font file has to contain the letters being typed. When it has no Cyrillic glyph for a letter that looks like a Latin one (А В Е К М Н О Р С Т У Х), the preview uses that font’s Latin lookalike, so Oswald, Roboto, and a plate font still look different. The text stored on the order stays what the shopper typed.

Every plate type opens on the first offered font whose family does not start with “German” and does not contain “немски”. A German family is selected only when it is the only font on that format. USA plates still open on the first family whose name starts with “USA”, when one is offered. That choice does not reorder the font list. A note saved on a font is shown after the family name, for example `Oswald — кирилица`. Under the Font label the shop shows a hint to pick a face that matches the plate and the script of the text.

## Plate text

The text field is optional on every plate, including two-row motorcycle and SUV plates and the two USA side fields. The shopper can add the product with the text left empty. Letters they type are stored in uppercase. The field hint lists the allowed characters: digits, letters, “-”, “.”, “@”, “!”, and “?”.

When the text is empty and the format has a text color, the cart and the order replace that color with `NO TEXT!` (Bulgarian: `БЕЗ ТЕКСТ!`). The frame, plate, holder, and strip colors stay.

USA plates (`us`, both standard and non-standard formats of that type) and holders do not offer a frame. The shop hides the checkbox and the border colors, and the cart does not list a frame for those types.

Each USA format stores its own plate graphic. Upload that picture on the format, drag the text area onto the number hole, and set the character limit there. A checkbox on the format, and the same checkbox on a USA design, splits that graphic into two text fields side by side. The two boxes stay on one line and move together. Each has its own width, and the gap between them is the space you leave for a center graphic. The shop then shows two inputs, each with its own character limit, and both use the same letter size. The text palette is chosen on the same form: one menu lists every active palette with its name and colors. On the product page the plate is that uploaded graphic and the text sits in the box you placed. The catalog thumbnail stays the product image set in the product editor.

A separate USA design can still be locked to a product when the format has no graphic of its own. On **USA designs**, **Create product** opens a WooCommerce product for that graphic and one USA format. When the design belongs to more than one format, choose the format first.

## Add to cart

WooCommerce sends the product form and then loads the product page again. That reload used to paint the catalog default instead of the plate the shopper had just styled.

The configurator posts the form without loading the product page again. After a successful add, the form returns to the catalog defaults (text, font, colors, and country) and the cart drawer opens. The styled plate is the cart thumbnail. Opening the product page again does not copy that cart line back into the form.

When a saved plate no longer matches the product, that line leaves the cart. The shop shows a white notice on the right side of the screen. Closing it removes the notice.

## Language

Plugin strings use the `auto-plate-designer` text domain. Bulgarian translations live in `languages/auto-plate-designer-bg_BG.l10n.php`. The shop uses the site language (Settings → General). The admin can still follow the user’s profile language.

## Holders

Car plate holders use the bundled gray mask at 520×260 (`gray-plate-holder-hole.png`). Opaque plastic takes the holder-color palette; the plate windows and the strip slot stay empty. Shoppers then set the strip color and the strip text. The strip color follows the rounded ends of that slot. The strip color label is “Strip color” (Bulgarian: “Цвят на лентата”). Single-line text is centered on the ink of the glyphs.

In the format editor, X, Y, width, and height for every holder are percentages of the white strip. The values stored on the format stay percentages of the whole canvas.

Each holder format has a maximum-character field. It stays empty until the admin types a number and saves. Until then the shop uses the global holder limit (100). The formats list shows an em dash while the field is unset.

Holder products start with an unchecked box, “I do not want personalization” (Bulgarian: “Не искам персонализация”). Checking it hides the text, the font, and the color palettes. The strip is painted gray `#7D7D7D` on a car holder and black on a motorcycle or type D holder. The cart lists the strip as “Without styling” (Bulgarian: “Без стилизация”) and does not record a chosen strip color. The holder color is still listed when one was selected. Empty holder text uses the same `NO TEXT!` line as every other plate.

The formats list column **Size (W×H)** names the plate a holder is for: a car holder is for 520×110, a motorcycle holder is for 199×154, and a type D holder is for 280×200. The stored canvas stays the drawing size (520×260, 199×199, and 280×159).

Motorcycle holders (`holder_moto`, canvas 199×199) and type D holders (`holder_d`, canvas 280×159) use the bundled product photos (`holder-moto.png`, `holder-type-d.png`). The motorcycle holder type is named for the 199×154 plate. The body stays square like its photo. Type D uses the two-line plate width (280 mm); the height keeps the photo's proportion so the slogan strip stays on the white bar. The plastic and the empty windows stay as in the photo. Shoppers color the bottom strip and the letters from one shared list: the holder-strip palettes and the holder-inscription palettes. The strip starts white and the letters start black. Both types share the holder character limit. An upload can replace the bundled photo.

## Updates

The plugin is not on WordPress.org. wp-admin compares the installed `Version` with tags on [github.com/GerganaTs/auto-plate-designer](https://github.com/GerganaTs/auto-plate-designer). A tag such as `v1.0.1` shows an Update button when it is newer than the copy on the site. Before tagging, set the same number in the plugin header and in `APD_VERSION`. Replacing the plugin this way keeps the saved formats in the database. Deleting the plugin from the Plugins screen removes them.

## Shop categories

Product categories belong to WooCommerce. The plugin does not create them and does not put a product into one. Choose the category on the product.
