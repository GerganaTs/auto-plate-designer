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
	'Country band'       => 'Държавна лента',
	'Add format'         => 'Добави формат',
	'Update format'      => 'Обнови формат',
	'Maximum characters' => 'Максимален брой символи',
	'Fonts'              => 'Шрифтове',
	'Catalog'            => 'Каталог',
	'Color palette'      => 'Цветна палета',
	'Country presets'    => 'Държавни пресети',
	'Text color'         => 'Цвят на текста',
	'Border color'       => 'Цвят на рамката',
	'Plate preview'      => 'Преглед на табелата',
	'Select image'       => 'Избери изображение',
	'Settings saved.'    => 'Настройките са запазени.',
	'Band width'         => 'Широчина на лентата',
	'Band height'        => 'Височина на лентата',
	'Center text area'   => 'Центрирай текстовата област',
	'Motorcycle plate'   => 'Номер за мотор',
	'SUV / crossover plate' => 'SUV / кросоувър номер',
	'Plate holder'       => 'Стойка за номер',
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

if ( 'Формати' !== $translated || 'Без рамка' !== $without || 'Цвят по подразбиране' !== $default ) {
	fwrite( STDERR, "WP_TRANSLATE_FAIL Formats={$translated} Without={$without} Default={$default}\n" );
	exit( 1 );
}

echo 'L10N_MAP_OK' . PHP_EOL;
echo 'WP_TRANSLATE_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
