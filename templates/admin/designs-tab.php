<?php
/**
 * Admin settings tab: US plate designs (state / graphic library).
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 */

defined( 'ABSPATH' ) || exit;

$designs     = APD_Designs::all();
$us_formats  = APD_Formats::of_type( 'us' );
$edit_id     = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing     = '' !== $edit_id ? APD_Designs::get( $edit_id ) : null;

if ( ! is_array( $editing ) ) {
	$editing = array(
		'id'                   => '',
		'name'                 => '',
		'code'                 => '',
		'image_id'             => 0,
		'allowed_format_types' => array( 'us' ),
		'allowed_format_ids'   => array(),
		'text_box'             => APD_Designs::default_text_box(),
		'active'               => true,
	);
}

$image_id    = absint( $editing['image_id'] );
$image_thumb = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
$image_full  = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
$text_box    = APD_Designs::sanitize_text_box( isset( $editing['text_box'] ) ? $editing['text_box'] : array() );
$stage_ratio = '2 / 1';

if ( ! empty( $us_formats[0]['width'] ) && ! empty( $us_formats[0]['height'] ) ) {
	$stage_ratio = (string) (int) $us_formats[0]['width'] . ' / ' . (string) (int) $us_formats[0]['height'];
}
$allowed_ids = isset( $editing['allowed_format_ids'] ) && is_array( $editing['allowed_format_ids'] ) ? $editing['allowed_format_ids'] : array();
$all_us      = empty( $allowed_ids );

/**
 * Human-readable format scope for a design row.
 *
 * @param array<string, mixed> $design Design.
 * @return string
 */
$apd_design_scope = static function ( $design ) {
	$ids = isset( $design['allowed_format_ids'] ) && is_array( $design['allowed_format_ids'] ) ? $design['allowed_format_ids'] : array();

	if ( empty( $ids ) ) {
		return __( 'All USA formats', 'auto-plate-designer' );
	}

	$names = array();

	foreach ( $ids as $format_id ) {
		$format = APD_Formats::get( $format_id );

		if ( is_array( $format ) && isset( $format['name'] ) ) {
			$names[] = $format['name'];
		}
	}

	return $names ? implode( ', ', $names ) : __( 'All USA formats', 'auto-plate-designer' );
};
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'USA designs', 'auto-plate-designer' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Arizona, California, and other state graphics belong here — they are not formats. Keep as many USA formats as you need (car, motorcycle, …) and attach a design to all of them or only some.', 'auto-plate-designer' ); ?></p>

	<div class="apd-table-scroll">
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Image', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Name', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Code', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Formats', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Active', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'auto-plate-designer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $designs ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No USA designs yet. Add Arizona, California, Oklahoma, and the rest here.', 'auto-plate-designer' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $designs as $design ) : ?>
					<?php
					$row_image_id  = isset( $design['image_id'] ) ? absint( $design['image_id'] ) : 0;
					$row_image_url = $row_image_id ? wp_get_attachment_image_url( $row_image_id, 'medium' ) : '';
					?>
					<tr>
						<td>
							<?php if ( $row_image_url ) : ?>
								<img class="apd-design-thumb" src="<?php echo esc_url( $row_image_url ); ?>" alt="<?php echo esc_attr( $design['name'] ); ?>">
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $design['name'] ); ?></td>
						<td><?php echo esc_html( isset( $design['code'] ) ? $design['code'] : '' ); ?></td>
						<td><?php echo esc_html( $apd_design_scope( $design ) ); ?></td>
						<td><?php echo ! empty( $design['active'] ) ? esc_html__( 'Yes', 'auto-plate-designer' ) : esc_html__( 'No', 'auto-plate-designer' ); ?></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'edit', $design['id'], $apd_admin->tab_url( 'designs' ) ) ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
							|
							<a class="apd-js-confirm" href="<?php echo esc_url( $apd_admin->delete_url( 'designs', $design['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'auto-plate-designer' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<h3><?php echo $editing['id'] ? esc_html__( 'Edit design', 'auto-plate-designer' ) : esc_html__( 'Add design', 'auto-plate-designer' ); ?></h3>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'designs' ) ); ?>" class="apd-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_design">
		<input type="hidden" name="apd_design[id]" value="<?php echo esc_attr( $editing['id'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="apd_design_name"><?php esc_html_e( 'Name', 'auto-plate-designer' ); ?></label></th>
				<td><input type="text" class="regular-text" id="apd_design_name" name="apd_design[name]" value="<?php echo esc_attr( $editing['name'] ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="apd_design_code"><?php esc_html_e( 'Code', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="text" id="apd_design_code" name="apd_design[code]" value="<?php echo esc_attr( isset( $editing['code'] ) ? $editing['code'] : '' ); ?>" maxlength="3" class="small-text" required>
					<p class="description"><?php esc_html_e( '2 or 3 letters shown to shoppers, for example AZ or CA.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Plate graphic', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-media-field">
						<input type="hidden" name="apd_design[image_id]" value="<?php echo esc_attr( (string) $image_id ); ?>" data-apd-media-input>
						<button type="button" class="button" data-apd-media="image"><?php esc_html_e( 'Select image', 'auto-plate-designer' ); ?></button>
						<button type="button" class="button-link" data-apd-media-clear><?php esc_html_e( 'Clear', 'auto-plate-designer' ); ?></button>
						<div class="apd-media-preview">
							<?php if ( $image_thumb ) : ?>
								<img src="<?php echo esc_url( $image_thumb ); ?>" alt="">
							<?php endif; ?>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Full plate PNG or JPEG, including the frame if the artwork already has one. Shoppers only change the text on top.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Text area', 'auto-plate-designer' ); ?></th>
				<td>
					<?php
					$apd_text_box            = $text_box;
					$apd_text_box_name       = 'apd_design[text_box]';
					$apd_text_box_ratio      = $stage_ratio;
					$apd_text_box_image      = $image_full ? $image_full : '';
					$apd_text_box_help       = __( 'Drag the box onto the number hole of this graphic. The same percentages are used on every USA format this design is attached to. Shoppers cannot move the text.', 'auto-plate-designer' );
					$apd_text_box_show_stage = (bool) $image_full;
					$apd_text_box_band       = false;
					include APD_PLUGIN_DIR . 'templates/admin/partials/text-box-editor.php';
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Available on', 'auto-plate-designer' ); ?></th>
				<td>
					<label class="apd-choice">
						<input type="checkbox" name="apd_design[all_us]" value="1" data-apd-all-us <?php checked( $all_us ); ?>>
						<?php esc_html_e( 'All USA formats', 'auto-plate-designer' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Leave this on so a later USA size (another car plate, motorcycle, …) can use the same states. Uncheck it only to hide this graphic from some USA formats.', 'auto-plate-designer' ); ?></p>
					<?php if ( ! empty( $us_formats ) ) : ?>
						<div class="apd-us-format-list apd-choice-list" style="margin-top:8px;">
							<?php foreach ( $us_formats as $us_format ) : ?>
								<label class="apd-choice">
									<input type="checkbox" name="apd_design[allowed_format_ids][]" value="<?php echo esc_attr( $us_format['id'] ); ?>" data-apd-us-format <?php checked( in_array( $us_format['id'], $allowed_ids, true ) ); ?>>
									<?php echo esc_html( $us_format['name'] ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No USA formats yet. Create one under Formats — this design will attach to it automatically.', 'auto-plate-designer' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'auto-plate-designer' ); ?></th>
				<td><label class="apd-choice"><input type="checkbox" name="apd_design[active]" value="1" <?php checked( ! empty( $editing['active'] ) ); ?>> <?php esc_html_e( 'Active', 'auto-plate-designer' ); ?></label></td>
			</tr>
		</table>

		<?php submit_button( $editing['id'] ? __( 'Update design', 'auto-plate-designer' ) : __( 'Add design', 'auto-plate-designer' ) ); ?>
	</form>
</div>
