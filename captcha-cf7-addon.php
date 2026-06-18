<?php
/**
 * Plugin Name: Animated CAPTCHA for Contact Form 7
 * Description: A clean, modern, and animated CAPTCHA addon for Contact Form 7.
 * Version: 1.0.0
 * Author: Gemini CLI
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPCF7_Animated_Captcha {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wpcf7_init', array( $this, 'add_form_tag' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'wpcf7_validate_animated_captcha', array( $this, 'validate' ), 10, 2 );
		add_filter( 'wpcf7_validate_animated_captcha*', array( $this, 'validate' ), 10, 2 );

		// AJAX Refresh
		add_action( 'wp_ajax_refresh_animated_captcha', array( $this, 'ajax_refresh' ) );
		add_action( 'wp_ajax_nopriv_refresh_animated_captcha', array( $this, 'ajax_refresh' ) );

		// Tag generator
		add_action( 'admin_init', array( $this, 'tag_generator' ), 100 );
	}

	public function add_form_tag() {
		wpcf7_add_form_tag(
			array( 'animated_captcha', 'animated_captcha*' ),
			array( $this, 'form_tag_handler' ),
			array( 'name-attr' => true )
		);
	}

	public function form_tag_handler( $tag ) {
		if ( empty( $tag->name ) ) {
			$tag->name = 'captcha-' . mt_rand( 100, 999 ); // Assign a random name if missing
		}

		$validation_error = wpcf7_get_validation_error( $tag->name );

		$class = wpcf7_form_controls_class( $tag->type );
		if ( $validation_error ) {
			$class .= ' wpcf7-not-valid';
		}

		$atts = array();
		$atts['class'] = $tag->get_class_option( $class );
		$atts['id'] = $tag->get_id_option();
		$atts['name'] = $tag->name;

		$size = $tag->get_option( 'size', 'int', true );
		if ( ! $size ) {
			$size = 7; // Default length
		}

		$width = $tag->get_option( 'width', 'int', true );
		
		// Text options - Advanced parsing to handle multi-word options without quotes
		$placeholder = $this->get_complex_option( $tag, 'placeholder' );
		$refresh_text = $this->get_complex_option( $tag, 'refresh_text' );
		$error_message = $this->get_complex_option( $tag, 'error_message' );

		// Fallback for standard CF7 placeholder flag
		if ( ! $placeholder && ( $tag->has_option( 'placeholder' ) || $tag->has_option( 'watermark' ) ) ) {
			$placeholder = $tag->values[0];
		}

		if ( ! $placeholder ) {
			$placeholder = __( 'Enter CAPTCHA', 'contact-form-7' );
		}
		if ( ! $refresh_text ) {
			$refresh_text = __( 'Click to refresh', 'contact-form-7' );
		}

		$container_style = '';
		if ( $width ) {
			$container_style = sprintf( ' style="width: %dpx; max-width: 100%%;"', $width );
		}

		// Generate CAPTCHA
		$captcha_word = $this->generate_random_word( $size );
		$session_id = uniqid();
		set_transient( 'wpcf7_animated_captcha_' . $session_id, strtolower( $captcha_word ), 10 * MINUTE_IN_SECONDS );

		// Store custom error message in a hidden field if provided
		$error_html = '';
		if ( $error_message ) {
			$error_html = sprintf( '<input type="hidden" name="_wpcf7_animated_captcha_error_%s" value="%s" />', esc_attr( $tag->name ), esc_attr( $error_message ) );
		}

		$html = sprintf(
			'<div class="wpcf7-animated-captcha-container" data-captcha-word="%1$s" data-session-id="%2$s" data-size="%7$s" data-width="%8$s"%10$s>
				<div class="canvas-wrapper" title="%11$s">
					<canvas class="animated-captcha-canvas"></canvas>
				</div>
				<span class="wpcf7-form-control-wrap %3$s">
					<input type="text" name="%3$s" value="" size="40" class="wpcf7-form-control wpcf7-text wpcf7-animated-captcha-input" aria-invalid="%4$s" aria-describedby="%5$s" placeholder="%9$s" autocomplete="off" />
					<input type="hidden" class="wpcf7-animated-captcha-session" name="_wpcf7_animated_captcha_session_%3$s" value="%2$s" />
					%12$s
					%6$s
				</span>
			</div>',
			esc_attr( $captcha_word ),
			esc_attr( $session_id ),
			esc_attr( $tag->name ),
			$validation_error ? 'true' : 'false',
			$tag->name . '-error',
			$validation_error,
			esc_attr( $size ),
			esc_attr( $width ),
			esc_attr( $placeholder ),
			$container_style,
			esc_attr( $refresh_text ),
			$error_html
		);

		return $html;
	}

	/**
	 * Extracts complex options that might contain spaces even without quotes.
	 */
	private function get_complex_option( $tag, $option_name ) {
		$value = $tag->get_option( $option_name, '', true );
		
		if ( ! $value ) {
			$value = $tag->get_option( $option_name, 'string', true );
		}

		// If we found a value, let's see if there are "orphaned" words after it that belong to it
		if ( $value && ! empty( $tag->options ) ) {
			$found = false;
			$collected = array();
			foreach ( $tag->options as $opt ) {
				if ( strpos( $opt, $option_name . ':' ) === 0 ) {
					$found = true;
					$val = substr( $opt, strlen( $option_name . ':' ) );
					$collected[] = trim( $val, '"' );
					continue;
				}
				
				if ( $found ) {
					// If this option looks like another key:value, stop collecting
					if ( strpos( $opt, ':' ) !== false ) {
						break;
					}
					$collected[] = trim( $opt, '"' );
				}
			}
			
			if ( ! empty( $collected ) ) {
				$value = implode( ' ', $collected );
			}
		}

		return $value;
	}

	private function generate_random_word( $length = 7 ) {
		$characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
		$word = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$word .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
		}
		return $word;
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'wpcf7-animated-captcha-google-font', 'https://fonts.googleapis.com/css?family=Cutive+Mono', array(), null );
		wp_enqueue_style( 'wpcf7-animated-captcha-style', plugins_url( 'assets/css/style.css', __FILE__ ), array(), '1.0.0' );
		wp_enqueue_script( 'wpcf7-animated-captcha-script', plugins_url( 'assets/js/script.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

		wp_localize_script( 'wpcf7-animated-captcha-script', 'wpcf7AnimatedCaptcha', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpcf7-animated-captcha-nonce' )
		) );
	}

	public function ajax_refresh() {
		check_ajax_referer( 'wpcf7-animated-captcha-nonce', 'nonce' );

		$size = isset( $_GET['size'] ) ? absint( $_GET['size'] ) : 7;
		$captcha_word = $this->generate_random_word( $size );
		$session_id = uniqid();
		set_transient( 'wpcf7_animated_captcha_' . $session_id, strtolower( $captcha_word ), 10 * MINUTE_IN_SECONDS );

		wp_send_json_success( array(
			'captcha_word' => $captcha_word,
			'session_id'   => $session_id
		) );
	}

	public function validate( $result, $tag ) {
		$name = $tag->name;
		$value = isset( $_POST[$name] ) ? trim( $_POST[$name] ) : '';
		$session_id = isset( $_POST['_wpcf7_animated_captcha_session_' . $name] ) ? $_POST['_wpcf7_animated_captcha_session_' . $name] : '';
		$custom_error = isset( $_POST['_wpcf7_animated_captcha_error_' . $name] ) ? $_POST['_wpcf7_animated_captcha_error_' . $name] : '';

		// CAPTCHA is always required by default
		if ( '' === $value ) {
			$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
		} else {
			$expected = get_transient( 'wpcf7_animated_captcha_' . $session_id );
			
			if ( ! $expected || strtolower( $value ) !== $expected ) {
				$msg = $custom_error ? $custom_error : 'add correct captcha';
				$result->invalidate( $tag, $msg );
			}
			
			if ( $expected ) {
				delete_transient( 'wpcf7_animated_captcha_' . $session_id );
			}
		}

		return $result;
	}

	public function tag_generator() {
		if ( ! class_exists( 'WPCF7_TagGenerator' ) ) {
			return;
		}

		$tag_generator = WPCF7_TagGenerator::get_instance();
		$tag_generator->add( 'animated_captcha', __( 'animated captcha', 'contact-form-7' ), array( $this, 'tag_generator_callback' ) );
	}

	public function tag_generator_callback( $contact_form, $args = '' ) {
		$args = wp_parse_args( $args, array() );
		$type = 'animated_captcha';
		?>
		<div class="control-box">
			<fieldset>
				<legend><?php echo esc_html( __( 'Generate a form-tag for the animated CAPTCHA.', 'contact-form-7' ) ); ?></legend>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></legend>
									<label><input type="checkbox" name="required" checked="checked" /> <?php echo esc_html( __( 'Required field', 'contact-form-7' ) ); ?></label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?></label></th>
							<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-placeholder' ); ?>"><?php echo esc_html( __( 'Placeholder', 'contact-form-7' ) ); ?></label></th>
							<td><input type="text" name="placeholder" class="oneline option" id="<?php echo esc_attr( $args['content'] . '-placeholder' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-error_message' ); ?>"><?php echo esc_html( __( 'Error message', 'contact-form-7' ) ); ?></label></th>
							<td><input type="text" name="error_message" class="oneline option" id="<?php echo esc_attr( $args['content'] . '-error_message' ); ?>" placeholder="Incorrect code!" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-refresh_text' ); ?>"><?php echo esc_html( __( 'Refresh tooltip', 'contact-form-7' ) ); ?></label></th>
							<td><input type="text" name="refresh_text" class="oneline option" id="<?php echo esc_attr( $args['content'] . '-refresh_text' ); ?>" placeholder="Click to refresh" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-size' ); ?>"><?php echo esc_html( __( 'Word length', 'contact-form-7' ) ); ?></label></th>
							<td><input type="number" name="size" class="option" id="<?php echo esc_attr( $args['content'] . '-size' ); ?>" min="3" max="10" placeholder="7" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-width' ); ?>"><?php echo esc_html( __( 'Width (px)', 'contact-form-7' ) ); ?></label></th>
							<td>
								<input type="number" name="width" class="option" id="<?php echo esc_attr( $args['content'] . '-width' ); ?>" placeholder="300" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php echo esc_html( __( 'Id attribute', 'contact-form-7' ) ); ?></label></th>
							<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php echo esc_html( __( 'Class attribute', 'contact-form-7' ) ); ?></label></th>
							<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-class' ); ?>" /></td>
						</tr>
					</tbody>
				</table>
			</fieldset>
		</div>

		<div class="insert-box">
			<input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly" onfocus="this.select();" />
			<div class="submitbox">
				<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
			</div>
		</div>
		<?php
	}
}

WPCF7_Animated_Captcha::get_instance();
