<?php
/**
 * Rich text Customizer control retained for backward compatibility.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Customize_Control' ) && ! class_exists( 'Text_Editor_Custom_Control' ) ) {
	class Text_Editor_Custom_Control extends WP_Customize_Control {
		/** @var string */
		public $type = 'textarea';

		/**
		 * Render the control.
		 *
		 * @return void
		 */
		public function render_content() {
			$settings = array(
				'media_buttons' => false,
				'quicktags'     => false,
				'tinymce'       => true,
			);

			$this->filter_editor_setting_link();
			?>
			<label>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php wp_editor( $this->value(), $this->id, $settings ); ?>
			</label>
			<?php
		}

		/**
		 * Attach Customizer data binding to the editor textarea.
		 *
		 * @return void
		 */
		private function filter_editor_setting_link() {
			add_filter( 'the_editor', array( $this, 'filter_editor_setting_link_callback' ) );
		}

		/**
		 * Inject the data-customize-setting-link attribute.
		 *
		 * @param string $output Editor markup.
		 * @return string
		 */
		public function filter_editor_setting_link_callback( $output ) {
			return preg_replace( '/<textarea/', '<textarea ' . $this->get_link(), $output, 1 );
		}
	}
}
