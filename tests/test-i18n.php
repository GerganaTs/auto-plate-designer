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
	'Create product'     => 'Създай продукт',
	'Delete'             => 'Изтрий',
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
	'Add Format'         => 'Добави формат',
	'Select type'        => 'Избери тип',
	'Country preset'     => 'Държавен пресет',
	'Add format'         => 'Добави формат',
	'Update format'      => 'Обнови формат',
	'Maximum characters' => 'Максимален брой символи',
	'Fonts'              => 'Шрифтове',
	'Catalog'            => 'Каталог',
	'Color palette'      => 'Цветна палета',
	'Add design'         => 'Добави дизайн',
	'Add palette'        => 'Добави палета',
	'Add font'           => 'Добави шрифт',
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
	'EU motorcycle plate' => 'EU табела за мотор',
	'Motorcycle plate without preset' => 'Табела за мотор без пресет',
	'EU SUV plate'       => 'EU табела за SUV',
	'SUV plate without preset' => 'Табела за SUV без пресет',
	'Car plate holders'  => 'Стойки за автомобилни номера',
	'Car plate without preset' => 'Авто табела без пресет',
	'Publish in the shop' => 'Публикувай в магазина',
	'Format types in this category: %s' => 'Типове формати в тази категория: %s',
	'Add frame'          => 'Добави рамка',
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

if ( 'Формати' !== $translated || 'Без рамка' !== $without || 'Цвят по подразбиране' !== $default || 'Държава на регистрация' !== $country || 'Първи ред' !== $row_label ) {
	fwrite( STDERR, "WP_TRANSLATE_FAIL Formats={$translated} Without={$without} Default={$default} Country={$country} Row={$row_label}\n" );
	exit( 1 );
}

echo 'L10N_MAP_OK' . PHP_EOL;
echo 'WP_TRANSLATE_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
