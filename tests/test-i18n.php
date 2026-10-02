<?php
/**
 * Bulgarian translations cover admin and shop labels used in the UI.
 *
 * @package Auto_Plate_Designer
 */

$plugin_dir = dirname( __DIR__ );
$map_file   = $plugin_dir . '/languages/auto-plate-designer-bg_BG.l10n.php';

if ( ! is_readable( $map_file ) ) {
	fwrite( STDERR, "L10N_FILE_MISSING\n" );
	exit( 1 );
}

$data = include $map_file;

if ( ! is_array( $data ) || empty( $data['messages'] ) || ! is_array( $data['messages'] ) ) {
	fwrite( STDERR, "L10N_MAP_INVALID\n" );
	exit( 1 );
}

$messages = $data['messages'];
$required = array(
	'Formats'            => 'Формати',
	'Type'               => 'Тип',
	'Name'               => 'Име',
	'Actions'            => 'Действия',
	'Edit'               => 'Редактирай',
	'Upload the plate graphic, then drag the text area onto the number hole. Set the character limit above.' => 'Качи графиката на табелата и завлачи текстовата област върху мястото за номера. Лимитът на символите е отгоре.',
	'Choose a palette for the text.' => 'Избери палета за текста.',
	'Choose a palette for the text. Every active palette is listed with its name and colors.' => 'Избери палета за текста. Всяка активна палета е в списъка с името и цветовете си.',
	'USA formats require a photo of the plate.' => 'USA форматът изисква снимка на табелата.',
	'Two text fields side by side' => 'Две текстови полета едно до друго',
	'The two boxes share a height and move together. Each width and the gap between them are set separately.' => 'Двете кутии са с еднаква височина и се местят заедно. Ширината на всяка и разстоянието между тях се задават отделно.',
	'Left text' => 'Текст ляво поле',
	'Right text' => 'Текст дясно поле',
	'Left maximum' => 'Максимум за лявото поле',
	'Right maximum' => 'Максимум за дясното поле',
	'Create product'     => 'Създай продукт',
	'Select a design'    => 'Избери дизайн',
	'Create product opens one WooCommerce product for this graphic. When the design belongs to more than one USA format, choose the format first. Shoppers see only that picture and type on the text area you place on it. The catalog photo is the product image you set in the product editor.' => '„Създай продукт“ отваря един WooCommerce продукт за тази картинка. Ако дизайнът е към повече от един USA формат, първо избираш формата. Клиентът вижда само тази картинка и пише в текстовата област, която си поставил върху нея. Снимката в каталога е изображението на продукта, зададено в редактора.',
	'This product shows only this graphic. Shoppers cannot switch to another state. The catalog photo is the product image. The plate on the product page is the picture uploaded with this design.' => 'Този продукт показва само тази картинка. Клиентът не може да смени щат. Снимката в каталога е изображението на продукта. Табелата на продуктовата страница е картинката, качена към този дизайн.',
	'Delete'             => 'Изтрий',
	'Duplicate'          => 'Дублирай',
	'(Copy)'             => '(Копие)',
	'Format duplicated.' => 'Форматът е дублиран.',
	'Default color'      => 'Цвят по подразбиране',
	'Without frame'      => 'Без рамка',
	'Width'              => 'Широчина',
	'Height'             => 'Височина',
	'Country band'       => 'Държава на регистрация',
	'First row'          => 'Първи ред',
	'Second row'         => 'Втори ред',
	'Characters per row' => 'Символи на ред',
	'First row maximum'  => 'Максимум за първи ред',
	'Second row maximum' => 'Максимум за втори ред',
	'Shoppers cannot type more than this on that row.' => 'Клиентът не може да въведе повече символи на този ред.',
	'Details are on the GitHub tag.' => 'Подробностите са в тага в GitHub.',
	'WooCommerce product configurator for custom vehicle plates.' => 'Конфигуратор на WooCommerce за персонални автомобилни номера.',
	'Add Format'         => 'Добави формат',
	'Select type'        => 'Избери тип',
	'Country preset'     => 'Държавен пресет',
	'Add format'         => 'Добави формат',
	'Update format'      => 'Обнови формат',
	'Maximum characters' => 'Максимален брой символи',
	'Fonts'              => 'Шрифтове',
	'Color palette'      => 'Цветна палета',
	'Add design'         => 'Добави дизайн',
	'Add palette'        => 'Добави палета',
	'Add font'           => 'Добави шрифт',
	'Edit font'          => 'Редактирай шрифт',
	'Update font'        => 'Обнови шрифт',
	'Leave this empty to keep the current file.' => 'Остави празно, за да запазиш текущия файл.',
	'Shop label'         => 'Етикет в магазина',
	'Cyrillic'           => 'кирилица',
	'For a perfect look, choose a font that matches the plate style and the language of your text (Latin or Cyrillic).' => 'За перфектна визия изберете шрифт, съответстващ на стила на табелата и езика на вашия текст (латиница или кирилица).',
	'Allowed characters are digits, letters, "-", ".", "@", "!" and "?".' => 'Позволените символи са цифри, букви, "-", ".", "@", "!" и "?"',
	'Customers see this after the font name. Leave it empty to show only the name.' => 'Клиентът го вижда след името на шрифта. Остави празно, за да се вижда само името.',
	'1. Shop buttons'    => '1. Бутони в магазина',
	'2. Color library'   => '2. Библиотека с цветове',
	'3. Named palettes'  => '3. Именувани палети',
	'Remove'             => 'Премахни',
	'Space between'      => 'С разстояние',
	'Letters'            => 'Букви',
	'Numbers'            => 'Цифри',
	'Country presets'    => 'Държавни пресети',
	'Text color'         => 'Цвят на текста',
	'Border color'       => 'Цвят на рамката',
	'Plate preview'      => 'Преглед на табелата',
	'Select image'       => 'Избери изображение',
	'Settings saved.'    => 'Настройките са запазени.',
	'Band width'         => 'Широчина на лентата',
	'Band height'        => 'Височина на лентата',
	'Center text area'   => 'Центрирай текстовата област',
	'X, Y, width, and height are percentages of the white strip.' => 'X, Y, ширината и височината са проценти от бялата лента.',
	'EU motorcycle plate 199×154' => 'EU табела за мотор 199×154 мм',
	'EU motorcycle plate 240×130' => 'EU табела за мотор 240×130 мм',
	'Motorcycle plate without country band 199×154' => 'EU табела за мотор без държавна лента 199×154 мм',
	'Motorcycle plate without country band 240×130' => 'EU табела за мотор без държавна лента 240×130 мм',
	'EU SUV plate'       => 'EU табела за SUV',
	'SUV plate without preset' => 'Табела за SUV без пресет',
	'Car plate holders'  => 'Стойки за автомобилни номера',
	'Motorcycle plate holder 199×154' => 'Стойка за мотор (199×154 мм)',
	'For a plate measuring %1$d×%2$d mm' => 'За табела с размери %1$d×%2$d мм',
	'Type D plate holder' => 'Стойка тип D',
	'Strip and letters' => 'Лента и букви',
	'The strip and the letters use the same palettes. The strip starts white and the letters start black.' => 'Лентата и буквите ползват едни и същи палети. Лентата започва бяла, а буквите — черни.',
	'Car plate without preset' => 'Авто табела без пресет',
	'Publish in the shop' => 'Публикувай в магазина',
	'Format types in this category: %s' => 'Типове формати в тази категория: %s',
	'Add frame'          => 'Добави рамка',
	'Holder color'       => 'Цвят на стойката',
	'Holder inscription' => 'Надпис на стойката',
	'Holder strip'       => 'Фон на лентата',
	'Strip color'        => 'Цвят на лентата',
	'NO TEXT!'           => 'БЕЗ ТЕКСТ!',
	'Without styling'    => 'Без стилизация',
	'I do not want personalization' => 'Не искам персонализация',
	'Color plate text'   => 'Текст на цветна табела',
	'Color plate frame'  => 'Рамка на цветна табела',
	'Color plate fill'   => 'Фон на цветна табела',
	'Select all fonts'   => 'Избери всички шрифтове',
	'Street plates keep the millimetres you type. A 34×20 cm plate is 340 × 200.' => 'Уличната табела пази въведените милиметри. Табела 34×20 см е 340 × 200.',
	'Colors for this format' => 'Цветове за този формат',
	'Pick the palettes shoppers can use. The name and colors of each palette are shown here. A product only chooses this format.' => 'Избери палетите, които клиентът може да ползва. Тук се виждат името и цветовете на всяка палета. Продуктът избира само този формат.',
	'Colors come from the palettes chosen on this format.' => 'Цветовете идват от палетите, избрани за този формат.',
	'Plate frame color'  => 'Цвят на рамка на табелата',
	'Choose a palette'   => 'Избери палета',
	'Choose a palette for the text, the plate, and the frame.' => 'Избери палета за текста, табелата и рамката.',
	'Choose one palette for the text, the plate, and the frame. Every active palette is listed with its name and colors.' => 'Избери по една палета за текста, табелата и рамката. Всяка активна палета е в списъка с името и цветовете си.',
	'Text wrap'          => 'Пренасяне на текста',
	'Wrap long text onto extra lines.' => 'Дългият текст минава на следващ ред.',
);

$missing = array();

foreach ( $required as $source => $expected ) {
	if ( ! isset( $messages[ $source ] ) ) {
		$missing[] = $source . ' (absent)';
		continue;
	}
	if ( $messages[ $source ] !== $expected ) {
		$missing[] = $source . ' => ' . $messages[ $source ];
	}
}

if ( $missing ) {
	fwrite( STDERR, 'L10N_MISSING ' . implode( ' | ', $missing ) . PHP_EOL );
	exit( 1 );
}

$candidates = array(
	dirname( __DIR__, 3 ) . '/wp-load.php',
	dirname( __DIR__, 2 ) . '/plate-designer/wp-load.php',
);

$wp_load = '';

foreach ( $candidates as $candidate ) {
	if ( is_readable( $candidate ) ) {
		$wp_load = $candidate;
		break;
	}
}

if ( '' === $wp_load ) {
	fwrite( STDERR, "WordPress sandbox not found\n" );
	exit( 1 );
}

if ( empty( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST']   = 'localhost';
	$_SERVER['REQUEST_URI'] = '/plate-designer/';
}

require_once $wp_load;

if ( function_exists( 'switch_to_locale' ) ) {
	switch_to_locale( 'bg_BG' );
}

if ( function_exists( 'unload_textdomain' ) ) {
	unload_textdomain( 'auto-plate-designer', true );
}

load_plugin_textdomain(
	'auto-plate-designer',
	false,
	dirname( APD_PLUGIN_BASENAME ) . '/languages'
);

$translated = __( 'Formats', 'auto-plate-designer' );
$without    = __( 'Without frame', 'auto-plate-designer' );
$default    = __( 'Default color', 'auto-plate-designer' );
$country    = __( 'Country band', 'auto-plate-designer' );
$row_label  = __( 'First row', 'auto-plate-designer' );
$moto_199   = APD_Formats::type_label( 'moto' );
$moto_240   = APD_Formats::type_label( 'moto_240' );
$plain_199  = APD_Formats::type_label( 'moto_plain' );
$plain_240  = APD_Formats::type_label( 'moto_plain_240' );
$holder_199 = APD_Formats::type_label( 'holder_moto' );
$left_text  = __( 'Left text', 'auto-plate-designer' );
$right_text = __( 'Right text', 'auto-plate-designer' );

if ( 'Формати' !== $translated || 'Без рамка' !== $without || 'Цвят по подразбиране' !== $default || 'Държава на регистрация' !== $country || 'Първи ред' !== $row_label || 'Текст ляво поле' !== $left_text || 'Текст дясно поле' !== $right_text || 'EU табела за мотор 199×154 мм' !== $moto_199 || 'EU табела за мотор 240×130 мм' !== $moto_240 || 'EU табела за мотор без държавна лента 199×154 мм' !== $plain_199 || 'EU табела за мотор без държавна лента 240×130 мм' !== $plain_240 || 'Стойка за мотор (199×154 мм)' !== $holder_199 ) {
	fwrite( STDERR, "WP_TRANSLATE_FAIL Formats={$translated} Without={$without} Default={$default} Country={$country} Row={$row_label} Left={$left_text} Right={$right_text} Moto={$moto_199} Wide={$moto_240} Plain={$plain_199} PlainWide={$plain_240} Holder={$holder_199}\n" );
	exit( 1 );
}

echo 'L10N_MAP_OK' . PHP_EOL;
echo 'WP_TRANSLATE_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
