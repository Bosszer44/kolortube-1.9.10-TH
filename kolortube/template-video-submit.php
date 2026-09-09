<?php
/**
 * Template Name: Submit a Video
 *
 * Front-end submission form. Every submission is created as a pending post, so
 * nothing reaches the site until it is approved from the admin.
 *
 * @package kolortube
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$wpst_error   = '';
$wpst_success = '';

// An account is required to submit. An open form is a spam funnel: the
// moderation queue fills with junk faster than anyone reads it, and the queue
// stops being read at all. Checked here rather than only where the form is
// drawn, so posting straight to this URL does not get around it.
if ( is_user_logged_in()
	&& isset( $_POST['wpst-submitted'], $_POST['wpst-post_nonce_field'] )
	&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wpst-post_nonce_field'] ) ), 'wpst_submit_video' ) ) {

	$wpst_title       = isset( $_POST['wpst-video_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wpst-video_title'] ) ) : '';
	$wpst_description = isset( $_POST['wpst-video_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wpst-video_description'] ) ) : '';
	$wpst_video_url   = isset( $_POST['wpst-video_url'] ) ? esc_url_raw( wp_unslash( $_POST['wpst-video_url'] ) ) : '';
	$wpst_thumb       = isset( $_POST['wpst-thumb'] ) ? esc_url_raw( wp_unslash( $_POST['wpst-thumb'] ) ) : '';

	// The embed code is markup by nature, so it cannot be stripped the way the
	// other fields are. Only an iframe survives, which is what every tube
	// provider hands out, and it keeps the field from becoming a way to inject
	// scripts into the site.
	$wpst_embed = '';
	if ( isset( $_POST['wpst-embed'] ) ) {
		$wpst_embed = wp_kses(
			wp_unslash( $_POST['wpst-embed'] ),
			array(
				'iframe' => array(
					'src'             => true,
					'width'           => true,
					'height'          => true,
					'frameborder'     => true,
					'allow'           => true,
					'allowfullscreen' => true,
					'scrolling'       => true,
					'title'           => true,
				),
			)
		);
	}

	if ( '' === $wpst_title ) {
		$wpst_error = esc_html__( 'Please enter a title for your video.', 'wpst' );
	} elseif ( '' === $wpst_video_url && '' === $wpst_embed ) {
		$wpst_error = esc_html__( 'Please enter either an MP4 video URL or an embed code.', 'wpst' );
	} else {
		// Only a category that actually exists is accepted, so a tampered form
		// cannot attach a submission to an arbitrary term id.
		$wpst_category = isset( $_POST['wpst-category_selected'] ) ? absint( $_POST['wpst-category_selected'] ) : 0;
		if ( $wpst_category && ! term_exists( $wpst_category, 'category' ) ) {
			$wpst_category = 0;
		}

		$wpst_post = array(
			'post_title'   => $wpst_title,
			'post_content' => $wpst_description,
			'post_type'    => 'post',
			// Pending on purpose: a submission is a suggestion, not a
			// publication. Nothing a visitor sends appears on the site until
			// someone approves it.
			'post_status'  => 'pending',
		);
		if ( $wpst_category ) {
			$wpst_post['post_category'] = array( $wpst_category );
		}

		$wpst_post_id = wp_insert_post( $wpst_post, true );

		if ( is_wp_error( $wpst_post_id ) ) {
			$wpst_error = esc_html__( 'Your video could not be submitted, please try again.', 'wpst' );
		} else {
			// Tags and actors are set after the insert rather than through
			// tax_input, which wp_insert_post only honours for a user allowed to
			// assign terms. A subscriber is not, so tax_input would silently
			// drop them.
			$wpst_tags = isset( $_POST['wpst-tags'] ) ? sanitize_text_field( wp_unslash( $_POST['wpst-tags'] ) ) : '';
			if ( '' !== $wpst_tags ) {
				wp_set_post_terms( $wpst_post_id, $wpst_tags, 'post_tag' );
			}
			$wpst_actors = isset( $_POST['wpst-actors'] ) ? sanitize_text_field( wp_unslash( $_POST['wpst-actors'] ) ) : '';
			if ( '' !== $wpst_actors ) {
				wp_set_post_terms( $wpst_post_id, $wpst_actors, 'actors' );
			}

			if ( '' !== $wpst_video_url ) {
				update_post_meta( $wpst_post_id, 'video_url', $wpst_video_url );
			}
			if ( '' !== $wpst_embed ) {
				update_post_meta( $wpst_post_id, 'embed', $wpst_embed );
			}
			if ( '' !== $wpst_thumb ) {
				update_post_meta( $wpst_post_id, 'thumb', $wpst_thumb );
			}

			$wpst_hh = isset( $_POST['wpst-duration_hh'] ) ? absint( $_POST['wpst-duration_hh'] ) : 0;
			$wpst_mm = isset( $_POST['wpst-duration_mm'] ) ? absint( $_POST['wpst-duration_mm'] ) : 0;
			$wpst_ss = isset( $_POST['wpst-duration_ss'] ) ? absint( $_POST['wpst-duration_ss'] ) : 0;
			if ( $wpst_hh || $wpst_mm || $wpst_ss ) {
				update_post_meta( $wpst_post_id, 'duration', $wpst_hh * 3600 + $wpst_mm * 60 + $wpst_ss );
			}

			set_post_format( $wpst_post_id, 'video' );

			$wpst_success = esc_html__( 'Thanks for submitting a video! Your submission is being moderated.', 'wpst' );
		}
	}
}

get_header();
?>
<div class="wrapper" id="page-wrapper">
	<div class="container" id="content" tabindex="-1">
		<div class="row">
			<div class="col-12 col-md-10 col-lg-8 mx-auto">
				<main class="site-main" id="main">
					<h1 class="entry-title"><?php echo esc_html( get_the_title( get_queried_object_id() ) ); ?></h1>

					<?php if ( '' !== $wpst_success ) : ?>
						<div class="alert alert-success" role="alert"><?php echo esc_html( $wpst_success ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== $wpst_error ) : ?>
						<div class="alert alert-danger" role="alert"><?php echo esc_html( $wpst_error ); ?></div>
					<?php endif; ?>

					<?php if ( ! is_user_logged_in() ) : ?>
						<div class="alert alert-info" role="alert">
							<?php
							printf(
								/* translators: %1$s is the login link, %2$s the registration link. */
								esc_html__( 'You must be logged in to submit a video. Please %1$s or %2$s.', 'wpst' ),
								'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'log in', 'wpst' ) . '</a>',
								'<a href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'register a new account', 'wpst' ) . '</a>'
							);
							?>
						</div>
					<?php else : ?>
					<form action="" id="SubmitVideo" method="post">
						<?php wp_nonce_field( 'wpst_submit_video', 'wpst-post_nonce_field' ); ?>

						<div class="form-group">
							<label for="wpst-video_title"><?php esc_html_e( 'Video title', 'wpst' ); ?></label>
							<input type="text" class="form-control" name="wpst-video_title" id="wpst-video_title" required
								placeholder="<?php esc_attr_e( 'Enter video title', 'wpst' ); ?>">
						</div>

						<div class="form-group">
							<label for="wpst-video_description"><?php esc_html_e( 'Video description', 'wpst' ); ?></label>
							<textarea class="form-control" name="wpst-video_description" id="wpst-video_description" rows="6"
								placeholder="<?php esc_attr_e( 'Enter video description', 'wpst' ); ?>"></textarea>
						</div>

						<div class="form-group">
							<label for="wpst-video_url"><?php esc_html_e( 'MP4 video URL', 'wpst' ); ?></label>
							<input type="url" class="form-control" name="wpst-video_url" id="wpst-video_url"
								placeholder="<?php esc_attr_e( 'https://www.example.com/yourvideo.mp4', 'wpst' ); ?>">
							<small class="form-text text-muted"><?php esc_html_e( 'Fill in either this field or the embed code below.', 'wpst' ); ?></small>
						</div>

						<div class="form-group">
							<label for="wpst-embed"><?php esc_html_e( 'Embed code', 'wpst' ); ?></label>
							<textarea class="form-control" name="wpst-embed" id="wpst-embed" rows="4"
								placeholder="<?php esc_attr_e( 'Enter iframe / embed code', 'wpst' ); ?>"></textarea>
						</div>

						<div class="form-group">
							<label for="wpst-thumb"><?php esc_html_e( 'Thumbnail URL', 'wpst' ); ?></label>
							<input type="url" class="form-control" name="wpst-thumb" id="wpst-thumb"
								placeholder="<?php esc_attr_e( 'https://www.example.com/yourimage.jpg', 'wpst' ); ?>">
						</div>

						<div class="form-group">
							<label for="wpst-category_selected"><?php esc_html_e( 'Category', 'wpst' ); ?></label>
							<?php
							wp_dropdown_categories(
								array(
									'name'             => 'wpst-category_selected',
									'id'               => 'wpst-category_selected',
									'class'            => 'form-control',
									'show_option_none' => esc_html__( 'Select a category', 'wpst' ),
									'hide_empty'       => false,
									'orderby'          => 'name',
								)
							);
							?>
						</div>

						<div class="form-group">
							<label for="wpst-tags"><?php esc_html_e( 'Tags', 'wpst' ); ?></label>
							<input type="text" class="form-control" name="wpst-tags" id="wpst-tags"
								placeholder="<?php esc_attr_e( 'Separate tags with commas', 'wpst' ); ?>">
						</div>

						<div class="form-group">
							<label for="wpst-actors"><?php esc_html_e( 'Actors', 'wpst' ); ?></label>
							<input type="text" class="form-control" name="wpst-actors" id="wpst-actors"
								placeholder="<?php esc_attr_e( 'Separate actors with commas', 'wpst' ); ?>">
						</div>

						<div class="form-group">
							<label><?php esc_html_e( 'Duration', 'wpst' ); ?></label>
							<div class="form-row" id="video-duration-select">
								<?php
								$wpst_duration_parts = array(
									'wpst-duration_hh' => array( 'max' => 23, 'label' => esc_html__( 'Hours', 'wpst' ) ),
									'wpst-duration_mm' => array( 'max' => 59, 'label' => esc_html__( 'Minutes', 'wpst' ) ),
									'wpst-duration_ss' => array( 'max' => 59, 'label' => esc_html__( 'Seconds', 'wpst' ) ),
								);
								foreach ( $wpst_duration_parts as $wpst_field => $wpst_part ) :
									?>
									<div class="col">
										<label class="sr-only" for="<?php echo esc_attr( $wpst_field ); ?>"><?php echo esc_html( $wpst_part['label'] ); ?></label>
										<select class="form-control" name="<?php echo esc_attr( $wpst_field ); ?>" id="<?php echo esc_attr( $wpst_field ); ?>">
											<?php for ( $wpst_i = 0; $wpst_i <= $wpst_part['max']; $wpst_i++ ) : ?>
												<option value="<?php echo esc_attr( $wpst_i ); ?>"><?php echo esc_html( sprintf( '%02d', $wpst_i ) ); ?></option>
											<?php endfor; ?>
										</select>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<button type="submit" class="btn btn-primary" name="wpst-submitted" value="1">
							<?php esc_html_e( 'Submit video', 'wpst' ); ?>
						</button>
					</form>
					<?php endif; ?>
				</main><!-- #main -->
			</div>
		</div><!-- .row -->
	</div><!-- #content -->
</div><!-- #page-wrapper -->
<?php
get_footer();
