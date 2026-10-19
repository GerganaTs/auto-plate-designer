# Auto Plate Designer

WooCommerce configurator for custom vehicle plates. Shoppers style a plate on the product page; the same configuration is stored on the cart line and the order.

## Shop preview

A car plate and a color plate preview at 500 CSS pixels wide (`APD_Formats::CANVAS_DISPLAY_MAX_PX`). Height follows that format’s aspect ratio. Motorcycle plates use the 199 mm plate as those 500 pixels, so a 240×130 plate is wider and its letters stay the same size as on the 199×154 plate. SUV plates use the 280 mm plate as 500 pixels, so a 340×200 plate is wider and its letters stay the same size as on the 280×200 plate. A USA plate uses the 305 mm plate as 500 pixels, so a wider USA plate grows and its letters stay the same size. A holder preview is as wide as the plate it is made for, on the car-plate scale (520 mm = 500 px). A car holder stays 500 px wide. A motorcycle holder matches a 199×154 plate. A type D holder matches a 280×200 plate. The holder photo keeps its own proportions.

The country-band button stays at most as tall as a car plate (about 106 pixels) so a tall plate does not stretch the country control.

##  Two-row plates

Motorcycle plates and SUV plates (`suv`, `suv_eu`) have two fields on the product page, **First row** and **Second row**. What the shopper types is painted on that row. The format editor sets a character limit for each row (the default is 5 on the first row and 4 on the second) and the same alignment buttons on each row: left, center, right, and space between. Those buttons stay in the format editor. The product page shows the two fields and the limit under each one, for example `3 / 5` and `2 / 4`. In the Formats studio the SUV sample is four letters on the first row with a space between the pairs (`CA AA`) and `1234` on the second row. A motorcycle sample is `CA` over `1234`.

There are four motorcycle types. `moto` is the 199×154 plate with a euroband. `moto_240` is the 240×130 plate with a euroband. `moto_plain` is the 199×154 plate without a country band. `moto_plain_240` is the 240×130 plate without a country band. The euroband on `moto_240` uses the same 40 mm formula as a car plate and runs the full height of the plate. Picking a type locks those millimetres. A street plate (`custom`) is still a street plate: the admin types its millimetres, including 240×130, and it stays one line. Old motorcycle formats stay on their current type. Adding a 240×130 motorcycle plate is a new format of `moto_240` or `moto_plain_240`.

**Duplicate** on the Formats list copies the saved format, including its size, colors, and text area. The copy is named with ` (Copy)` after the original name (Bulgarian: ` (Копие)`).

There are four SUV types. `suv_eu` is the 280×200 plate with a euroband. `suv_eu_340` is the 340×200 plate with a euroband. `suv` is the 280×200 plate without a country preset. `suv_340` is the 340×200 plate without a country preset. The euroband stays on the first row and keeps the same millimetre width as on the 280 plate, so on the wider plate it takes a smaller share of the width. The second row stays full width. Picking a type locks those millimetres. Old SUV formats stay on their current type.

`eu_300` is a 300×150 plate with a country preset and two text rows. The blue band runs the full height, and both rows sit beside it. `atyp_300` is a 300×150 plate without a country preset. In the format editor that type has two choices, text on 1 row or text on 2 rows, and each choice has its own character fields. A product that uses the two-row choice shows two fields in the shop. Both plates preview at 500 CSS pixels wide. The letters fill the white area and keep the same 8 mm gap between two rows. Other format types are unchanged.

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

Shoppers can add or hide the admin frame on painted plates that still use that choice. The checkbox label is “Add frame” (Bulgarian: “Добави рамка”). Registration and color plates do not hide the frame; they keep it and offer “Without frame color” instead. The text area cannot come closer than 8 mm inside that frame, or closer than 8 mm to a full-height country band. The format editor stops the box at that line, and the shop fits the letters — including both rows of a two-line plate — inside the same margin. Holders and plates with the frame turned off keep their own area.

## Fonts

The product page draws the font the shopper picks. A plate font file has to contain the letters being typed. When it has no Cyrillic glyph for a letter that looks like a Latin one (А В Е К М Н О Р С Т У Х), the preview uses that font’s Latin lookalike, so Oswald, Roboto, and a plate font still look different. The text stored on the order stays what the shopper typed.

Every plate type opens on the first offered font whose family does not start with “German” and does not contain “немски”. A German family is selected only when it is the only font on that format. USA plates still open on the first family whose name starts with “USA”, when one is offered. That choice does not reorder the font list. A note saved on a font is shown after the family name, for example `Oswald — кирилица`. Under the Font label the shop shows a hint to pick a face that matches the plate and the script of the text.

## French and Austrian plates

A French plate (`fr`) and a French temporary plate (`fr_ww`) are 520×110, white with black letters, with the country locked to F. The shop shows the groups of `AA-000-AA`. The temporary plate prints `WW` and a month and a year. Those two numbers line up with the ink of the main letters: the month with the top, the year with the bottom. The blue country band is drawn inside the same rounded corner as the plate, on a blue fill, so the transparent corners of the preset stay blue.

`fr_region` is the same 520×110 French plate with a second band on the right. That band is the same blue strip, with the plate’s rounded corner on the outer right, and it does not repeat the stars or the letter from the left preset. It has no regional logo. The shop adds a fourth field, **Region** (Bulgarian: **Регион**), for two digits. On the Romanian reference plate those digits are 21.9% of the plate height and stop 17.7% above the bottom edge. The three number groups stay between the two bands. Other plate types are unchanged.

An Austrian plate (`austria`) stays locked to A, with the district and the registration around the gray region box.

## English and mini plates

A standard English plate is 520×110 mm, the same size as the 52×11 cm plastic replica. `uk` has an open country preset (every active preset, or only the ones ticked on the format). `uk_plain` is the same plate with no country band. Neither is locked to GB.

Both draw a 5 mm inner frame. The registration stays at least 10 mm inside that frame. One slogan field sits on the bottom of the plate, at least 7 mm under the registration. Leave the slogan empty and the frame stays with no small text. The format sets the slogan’s character limit (40 until you change it). The shopper picks the plate fill, the registration color, the frame color, and the slogan color from the palettes chosen on the format.

A mini plate is 340×90 mm. `mini` has the same open country preset. `mini_plain` has no band. Both are one line of black text on white, with no slogan and no inner frame. Other plate types are unchanged.

## Country presets on a format

EU, motorcycle, EU SUV, the 300×150 EU plate, the English plate with a preset, and the mini plate with a preset can offer every active country preset, or only the presets ticked on that format. A format with no such setting keeps every active preset. French and Austrian plates stay locked to their own country. The product editor no longer has the allowed-layout checkboxes. The Limits tab keeps the minimum font size, the street-plate wrap line cap, and the character class. Character limits, including a holder’s own maximum, live on the format. A product that already stored layouts still sends them with the offer.

Registration plates (EU, motorcycle, SUV, 300×150, and the atypical 300) store a typing maximum and a separate embossed count. Latin text within that count is raised. A longer line, and every Cyrillic line, is printed flat. The frame stays embossed. The notice sits under the text field in the same red style as a field error. When Latin passes the format’s embossed count the notice is “Past the standard character count the letters are printed digitally. The frame stays embossed.” (Bulgarian: “Над стандартния 10 броя символи, буквите се печатат дигитално. Рамката остава релефна.”). The word “10” is the sentence the shop shows; the notice still appears from the format’s own embossed count, which can be a different number. A Cyrillic registration or color plate always shows “The plate letters are printed digitally and the frame stays embossed.” (Bulgarian: “Буквите на табелата се печатат дигитално, а рамката остава релефна.”). Mini plates, which have no frame, say only “The letters are printed digitally.” (Bulgarian: “Буквите се печатат дигитално.”). While those registration letters are flat, the text-color palette is labeled “Text and frame color” (Bulgarian: “Цвят на текст и рамка”) and that one color paints the letters and the frame. Until a palette is chosen, the frame uses the letter color. “Without frame color” (Bulgarian: “Без цвят на рамката”) keeps the frame width and paints it with the plate color, including while Cyrillic is selected. Color plates always print flat letters and keep separate plate, letter, and frame colors. Mini plates stay white with black letters and no frame: Latin is raised until 9 characters (the space counts) and flat after that, and Cyrillic is always flat. The product offers Latin, Cyrillic, or both from the format’s fonts. An empty Latin list uses every font on the format. Letters of the other alphabet are removed from the field; the letters that belong to the selected alphabet stay, and the error remains until the next edit that does not remove anything. Every configurable product has an optional comment of up to 400 characters. Street, English, French, Austrian, USA, and holder plates are unchanged apart from that comment.

A product with both alphabets shows **Inscription language** (Bulgarian: **Език на надписа**) above two choices, Latin and Cyrillic. Directly under that label, and outside the radio group, the same small hint used for allowed characters says “For Cyrillic text, or Latin text longer than 10 characters, the letters are made digitally while the plate frame stays classic and embossed.” (Bulgarian: “При текст на кирилица или при текст на латиница с повече от 10 символа, буквите се изработват като дигитални, докато рамката на табелата остава класическа и релефна.”). It is a `span` with the class `apd-font-hint`. A product with only one alphabet does not show that hint. It shows one sentence: “This plate is written in Latin letters.” or “This plate is written in Cyrillic letters.”

Changing the alphabet on a mixed product fills the plate with the starting text saved for that alphabet. Latin uses the product’s Latin text. Cyrillic uses the product’s Cyrillic text. A two-row plate splits that text on the line break. An empty saved text leaves the field empty. The same change puts the color choices and “Without frame color” back to the values from when the page opened. The comment is not cleared.

## Plate text

The text field is optional on every plate, including two-row motorcycle and SUV plates and the two USA side fields. The shopper can add the product with the text left empty. Letters they type are stored in uppercase. The field hint lists the allowed characters: digits, letters, “-”, “.”, “@”, “!”, and “?”.

When the text is empty and the format has a text color, the cart and the order replace that color with `NO TEXT!` (Bulgarian: `БЕЗ ТЕКСТ!`). The frame, plate, holder, and strip colors stay.

USA plates (`us`, both standard and non-standard formats of that type) and holders do not offer a frame. The shop hides the checkbox and the border colors, and the cart does not list a frame for those types.

Each USA format stores its own plate graphic. Upload that picture on the format, drag the text area onto the number hole, and set the character limit there. A checkbox on the format, and the same checkbox on a USA design, splits that graphic into two text fields side by side. The two boxes stay on one line and move together. Each has its own width, and the gap between them is the space you leave for a center graphic. The shop then shows two inputs, each with its own character limit, and both use the same letter size. The text palette is chosen on the same form: one menu lists every active palette with its name and colors. On the product page the plate is that uploaded graphic and the text sits in the box you placed. The catalog thumbnail stays the product image set in the product editor.

A separate USA design can still be locked to a product when the format has no graphic of its own. On **USA designs**, **Create product** opens a WooCommerce product for that graphic and one USA format. When the design belongs to more than one format, choose the format first.

## Add to cart

WooCommerce sends the product form and then loads the product page again. That reload used to paint the catalog default instead of the plate the shopper had just styled.

The configurator posts the form without loading the product page again. After a successful add, the fields stay as the shopper left them: text, alphabet, font, colors, country, and comment. The cart drawer opens, and the styled plate is the cart thumbnail. Opening the product page again does not copy that cart line back into the form.

The scrolling part of the drawer is the mini-cart list. If the line just added is not fully inside that list, the list moves to it. A line taller than the list is aligned to the top of the visible area, so the product name and the configuration are what shows. The move eases out and takes between about a quarter of a second and seven tenths, depending on the distance. When the system asks for reduced motion, the list jumps to that place. A line that was already fully visible, and was not jumped there from somewhere else, stays put.

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

The shop theme, not this plugin, chooses the related products on the product page (`avtonomera_same_category_related_ids` in the Kadence child theme). It skips the default product category. It first lists other published catalog products in the same subcategory, without products that are only in a parent category. A short subcategory list is not filled from the parent. Only when that list is empty does it list products from the parent category, including that category’s children. Only when that list is also empty does it list the shop’s best sellers. WooCommerce may still shuffle the list and cut it to the number of cards on the page.
