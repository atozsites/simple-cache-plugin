<?php
/**
 * Settings class
 *
 * @package  atozsites-simple-cache
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class containing settings hooks
 */
class atozsites_Settings {

	/**
	 * Setup the plugin
	 *
	 * @since 1.0
	 */
	public function setup() {
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts_styles' ) );

		add_action( 'load-settings_page_atozsites-simple-cache', array( $this, 'update' ) );
		add_action( 'load-settings_page_atozsites-simple-cache', array( $this, 'purge_cache' ) );

		if ( atozsites_IS_NETWORK ) {
			add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
		}

	}

	/**
	 * Output network setting menu option
	 *
	 * @since  1.7
	 */
	public function network_admin_menu() {
		add_submenu_page( 'settings.php', esc_html__( 'Simple Cache', 'atozsites-simple-cache' ), esc_html__( 'Simple Cache', 'atozsites-simple-cache' ), 'manage_options', 'atozsites-simple-cache', array( $this, 'screen_options' ) );
	}

	/**
	 * Add purge cache button to admin bar
	 *
	 * @since 1,3
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_menu(
			array(
				'id'     => 'sc-purge-cache',
				'parent' => 'top-secondary',
				'href'   => esc_url( admin_url( 'options-general.php?page=atozsites-simple-cache&amp;wp_http_referer=' . esc_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . '&amp;action=atozsites_purge_cache&amp;atozsites_cache_nonce=' . wp_create_nonce( 'atozsites_purge_cache' ) ) ),
				'title'  => esc_html__( 'Purge Cache', 'atozsites-simple-cache' ),
			)
		);
	}

	/**
	 * Enqueue settings screen js/css
	 *
	 * @since 1.0
	 */
	public function action_admin_enqueue_scripts_styles() {

		global $pagenow;

		if ( ( 'options-general.php' === $pagenow || 'settings.php' === $pagenow ) && ! empty( $_GET['page'] ) && 'atozsites-simple-cache' === $_GET['page'] ) {
			wp_enqueue_script( 'sc-settings', plugins_url( '/dist/js/settings.js', dirname( __FILE__ ) ), array( 'jquery' ), atozsites_VERSION, true );
			wp_enqueue_style( 'sc-settings', plugins_url( '/dist/css/settings-styles.css', dirname( __FILE__ ) ), array(), atozsites_VERSION );
		}
	}

	/**
	 * Add options page
	 *
	 * @since 1.0
	 */
	public function action_admin_menu() {
		add_submenu_page( 'options-general.php', esc_html__( 'Simple Cache', 'atozsites-simple-cache' ), esc_html__( 'Simple Cache', 'atozsites-simple-cache' ), 'manage_options', 'atozsites-simple-cache', array( $this, 'screen_options' ) );
	}

	/**
	 * Purge cache manually
	 *
	 * @since 1.0
	 */
	public function purge_cache() {

		if ( ! empty( $_REQUEST['action'] ) && 'atozsites_purge_cache' === $_REQUEST['action'] ) {
			if ( ! current_user_can( 'manage_options' ) || empty( $_REQUEST['atozsites_cache_nonce'] ) || ! wp_verify_nonce( $_REQUEST['atozsites_cache_nonce'], 'atozsites_purge_cache' ) ) {
				wp_die( esc_html__( 'You need a higher level of permission.', 'atozsites-simple-cache' ) );
			}

			if ( atozsites_IS_NETWORK ) {
				atozsites_cache_flush( true );
			} else {
				atozsites_cache_flush();
			}

			if ( ! empty( $_REQUEST['wp_http_referer'] ) ) {
				wp_safe_redirect( $_REQUEST['wp_http_referer'] );
				exit;
			}
		}
	}

	/**
	 * Handle setting changes
	 *
	 * @since 1.0
	 */
	public function update() {

		if ( ! empty( $_REQUEST['action'] ) && 'atozsites_update' === $_REQUEST['action'] ) {

			if ( ! current_user_can( 'manage_options' ) || empty( $_REQUEST['atozsites_settings_nonce'] ) || ! wp_verify_nonce( $_REQUEST['atozsites_settings_nonce'], 'atozsites_update_settings' ) ) {
				wp_die( esc_html__( 'You need a higher level of permission.', 'atozsites-simple-cache' ) );
			}

			$verify_file_access = atozsites_verify_file_access();

			if ( is_array( $verify_file_access ) ) {
				if ( atozsites_IS_NETWORK ) {
					update_site_option( 'atozsites_cant_write', array_map( 'sanitize_text_field', $verify_file_access ) );
				} else {
					update_option( 'atozsites_cant_write', array_map( 'sanitize_text_field', $verify_file_access ) );
				}

				if ( in_array( 'cache', $verify_file_access, true ) ) {
					wp_safe_redirect( $_REQUEST['wp_http_referer'] );
					exit;
				}
			} else {
				if ( atozsites_IS_NETWORK ) {
					delete_site_option( 'atozsites_cant_write' );
				} else {
					delete_option( 'atozsites_cant_write' );
				}
			}

			$defaults       = atozsites_Config::factory()->defaults;
			$current_config = atozsites_Config::factory()->get();

			foreach ( $defaults as $key => $default ) {
				$clean_config[ $key ] = $current_config[ $key ];

				if ( isset( $_REQUEST['atozsites_simple_cache'][ $key ] ) ) {
					$clean_config[ $key ] = call_user_func( $default['sanitizer'], $_REQUEST['atozsites_simple_cache'][ $key ] );
				}
			}

			// Back up configration in options.
			if ( atozsites_IS_NETWORK ) {
				update_site_option( 'atozsites_simple_cache', $clean_config );
			} else {
				update_option( 'atozsites_simple_cache', $clean_config );
			}

			atozsites_Config::factory()->write( $clean_config );

			if ( ! apply_filters( 'atozsites_disable_auto_edits', false ) ) {
				atozsites_Advanced_Cache::factory()->write();
				atozsites_Object_Cache::factory()->write();

				if ( $clean_config['enable_page_caching'] ) {
					atozsites_Advanced_Cache::factory()->toggle_caching( true );
				} else {
					atozsites_Advanced_Cache::factory()->toggle_caching( false );
				}
			}

			// Reschedule cron events.
			atozsites_Cron::factory()->unschedule_events();
			atozsites_Cron::factory()->schedule_events();

			if ( ! empty( $_REQUEST['wp_http_referer'] ) ) {
				wp_safe_redirect( $_REQUEST['wp_http_referer'] );
				exit;
			}
		}
	}

	/**
	 * Output settings
	 *
	 * @since 1.0
	 */
	public function screen_options() {

		$config = atozsites_Config::factory()->get();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Simple Cache Settings', 'atozsites-simple-cache' ); ?></h1>

			<form action="" method="post">
				<?php wp_nonce_field( 'atozsites_update_settings', 'atozsites_settings_nonce' ); ?>
				<input type="hidden" name="action" value="atozsites_update">
				<input type="hidden" name="wp_http_referer" value="<?php echo esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ); ?>'" />

				<div class="advanced-mode-wrapper">
					<label for="atozsites_advanced_mode"><?php esc_html_e( 'Enable Advanced Mode', 'atozsites-simple-cache' ); ?></label>
					<select name="atozsites_simple_cache[advanced_mode]" id="atozsites_advanced_mode">
						<option value="0"><?php esc_html_e( 'No', 'atozsites-simple-cache' ); ?></option>
						<option <?php selected( $config['advanced_mode'], true ); ?> value="1"><?php esc_html_e( 'Yes', 'atozsites-simple-cache' ); ?></option>
					</select>
				</div>

				<table class="form-table sc-simple-mode-table <?php if ( empty( $config['advanced_mode'] ) ) : ?>show<?php endif; ?>">
					<tbody>
						<tr>
							<th scope="row"><label for="atozsites_enable_page_caching_simple"><span class="setting-highlight">*</span><?php esc_html_e( 'Enable Caching', 'atozsites-simple-cache' ); ?></label></th>
							<td>
								<select <?php if ( ! empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> name="atozsites_simple_cache[enable_page_caching]" id="atozsites_enable_page_caching_simple">
									<option value="0"><?php esc_html_e( 'No', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['enable_page_caching'], true ); ?> value="1"><?php esc_html_e( 'Yes', 'atozsites-simple-cache' ); ?></option>
								</select>

								<p class="description"><?php esc_html_e( 'Turn this on to get started. This setting turns on caching and is really all you need.', 'atozsites-simple-cache' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="atozsites_page_cache_length_simple"><?php esc_html_e( 'Expire the cache after', 'atozsites-simple-cache' ); ?></label></th>
							<td>
								<input <?php if ( ! empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> size="5" id="atozsites_page_cache_length_simple" type="text" value="<?php echo (float) $config['page_cache_length']; ?>" name="atozsites_simple_cache[page_cache_length]">
								<select <?php if ( ! empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> name="atozsites_simple_cache[page_cache_length_unit]" id="atozsites_page_cache_length_unit_simple">
									<option <?php selected( $config['page_cache_length_unit'], 'minutes' ); ?> value="minutes"><?php esc_html_e( 'minutes', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['page_cache_length_unit'], 'hours' ); ?> value="hours"><?php esc_html_e( 'hours', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['page_cache_length_unit'], 'days' ); ?> value="days"><?php esc_html_e( 'days', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['page_cache_length_unit'], 'weeks' ); ?> value="weeks"><?php esc_html_e( 'weeks', 'atozsites-simple-cache' ); ?></option>
								</select>
							</td>
						</tr>

						<?php if ( function_exists( 'gzencode' ) ) : ?>
							<tr>
								<th scope="row"><label for="atozsites_enable_gzip_compression_simple"><?php esc_html_e( 'Enable Compression', 'atozsites-simple-cache' ); ?></label></th>
								<td>
									<select <?php if ( ! empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> name="atozsites_simple_cache[enable_gzip_compression]" id="atozsites_enable_gzip_compression_simple">
										<option value="0"><?php esc_html_e( 'No', 'atozsites-simple-cache' ); ?></option>
										<option <?php selected( $config['enable_gzip_compression'], true ); ?> value="1"><?php esc_html_e( 'Yes', 'atozsites-simple-cache' ); ?></option>
									</select>

									<p class="description"><?php esc_html_e( 'When enabled, pages will be compressed. This is a good thing! This should always be enabled unless it causes issues.', 'atozsites-simple-cache' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<table class="form-table sc-advanced-mode-table <?php if ( ! empty( $config['advanced_mode'] ) ) : ?>show<?php endif; ?>">
					<tbody>
						<tr>
							<th scope="row" colspan="2">
								<h2 class="cache-title"><?php esc_html_e( 'Page Cache', 'atozsites-simple-cache' ); ?></h2>
							</th>
						</tr>

						<tr>
							<th scope="row"><label for="atozsites_enable_page_caching_advanced"><?php esc_html_e( 'Enable Page Caching', 'atozsites-simple-cache' ); ?></label></th>
							<td>
								<select <?php if ( empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> name="atozsites_simple_cache[enable_page_caching]" id="atozsites_enable_page_caching_advanced">
									<option value="0"><?php esc_html_e( 'No', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['enable_page_caching'], true ); ?> value="1"><?php esc_html_e( 'Yes', 'atozsites-simple-cache' ); ?></option>
								</select>

								<p class="description"><?php esc_html_e( 'When enabled, entire front end pages will be cached.', 'atozsites-simple-cache' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="atozsites_cache_exception_urls"><?php esc_html_e( 'Exception URL(s)', 'atozsites-simple-cache' ); ?></label></th>
							<td>
								<textarea name="atozsites_simple_cache[cache_exception_urls]" class="widefat" id="atozsites_cache_exception_urls"><?php echo eatozsites_html( $config['cache_exception_urls'] ); ?></textarea>

								<p class="description"><?php esc_html_e( 'Allows you to add URL(s) to be exempt from page caching. One URL per line. URL(s) can be full URLs (http://google.com) or absolute paths (/my/url/). You can also use wildcards like so /url/* (matches any url starting with /url/).', 'atozsites-simple-cache' ); ?></p>

								<p>
									<select name="atozsites_simple_cache[enable_url_exemption_regex]" id="atozsites_enable_url_exemption_regex">
										<option value="0"><?php esc_html_e( 'No', 'atozsites-simple-cache' ); ?></option>
										<option <?php selected( $config['enable_url_exemption_regex'], true ); ?> value="1"><?php esc_html_e( 'Yes', 'atozsites-simple-cache' ); ?></option>
									</select>
									<?php esc_html_e( 'Enable Regex', 'atozsites-simple-cache' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="atozsites_page_cache_length_advanced"><?php esc_html_e( 'Expire page cache after', 'atozsites-simple-cache' ); ?></label></th>
							<td>
								<input <?php if ( empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> size="5" id="atozsites_page_cache_length_advanced" type="text" value="<?php echo (float) $config['page_cache_length']; ?>" name="atozsites_simple_cache[page_cache_length]">
								<select
								<?php if ( empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> name="atozsites_simple_cache[page_cache_length_unit]" id="atozsites_page_cache_length_unit_advanced">
									<option <?php selected( $config['page_cache_length_unit'], 'minutes' ); ?> value="minutes"><?php esc_html_e( 'minutes', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['page_cache_length_unit'], 'hours' ); ?> value="hours"><?php esc_html_e( 'hours', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['page_cache_length_unit'], 'days' ); ?> value="days"><?php esc_html_e( 'days', 'atozsites-simple-cache' ); ?></option>
									<option <?php selected( $config['page_cache_length_unit'], 'weeks' ); ?> value="weeks"><?php esc_html_e( 'weeks', 'atozsites-simple-cache' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row" colspan="2">
								<h2 class="cache-title"><?php esc_html_e( 'Object Cache (Redis or Memcached)', 'atozsites-simple-cache' ); ?></h2>
							</th>
						</tr>

						<?php if ( class_exists( 'Memcache' ) || class_exists( 'Memcached' ) || class_exists( 'Redis' ) ) : ?>
							<tr>
								<th scope="row"><label for="atozsites_enable_in_memory_object_caching"><?php esc_html_e( 'Enable In-Memory Object Caching', 'atozsites-simple-cache' ); ?></label></th>
								<td>
									<select name="atozsites_simple_cache[enable_in_memory_object_caching]" id="atozsites_enable_in_memory_object_caching">
										<option value="0"><?php esc_html_e( 'No', 'atozsites-simple-cache' ); ?></option>
										<option <?php selected( $config['enable_in_memory_object_caching'], true ); ?> value="1"><?php esc_html_e( 'Yes', 'atozsites-simple-cache' ); ?></option>
									</select>

									<p class="description"><?php echo wp_kses_post( __( "When enabled, things like database query results will be stored in memory. Memcached and Redis are suppported. Note that if the proper <a href='http://pecl.php.net/package/memcached'>Memcached</a>, <a href='http://pecl.php.net/package/memcache'>Memcache</a>, or <a href='https://pecl.php.net/package/redis'>Redis</a> PHP extensions aren't loaded, they won't show as options below.", 'atozsites-simple-cache' ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th class="in-memory-cache <?php if ( ! empty( $config['enable_in_memory_object_caching'] ) ) : ?>show<?php endif; ?>" scope="row"><label for="atozsites_in_memory_cache"><?php esc_html_e( 'In Memory Cache', 'atozsites-simple-cache' ); ?></label></th>
								<td class="in-memory-cache <?php if ( ! empty( $config['enable_in_memory_object_caching'] ) ) : ?>show<?php endif; ?>">
									<select name="atozsites_simple_cache[in_memory_cache]" id="atozsites_in_memory_cache">
										<?php if ( class_exists( 'Redis' ) ) : ?>
											<option <?php selected( $config['in_memory_cache'], 'redis' ); ?> value="redis">Redis</option>
										<?php endif; ?>
										<?php if ( class_exists( 'Memcached' ) ) : ?>
											<option <?php selected( $config['in_memory_cache'], 'memcachedd' ); ?> value="memcachedd">Memcached</option>
										<?php endif; ?>
										<?php if ( class_exists( 'Memcache' ) ) : ?>
											<option <?php selected( $config['in_memory_cache'], 'memcached' ); ?> value="memcached">Memcache (Legacy)</option>
										<?php endif; ?>
									</select>
								</td>
							</tr>
						<?php else : ?>
							<tr>
								<td colspan="2">
									<?php echo wp_kses_post( __( 'Neither <a href="https://pecl.php.net/package/memcached">Memcached</a>, <a href="https://pecl.php.net/package/memcache">Memcache</a>, nor <a href="https://pecl.php.net/package/redis">Redis</a> PHP extensions are set up on your server.', 'atozsites-simple-cache' ) ); ?>
								</td>
							</tr>
						<?php endif; ?>

						<tr>
							<th scope="row" colspan="2">
								<h2 class="cache-title"><?php esc_html_e( 'Compression', 'atozsites-simple-cache' ); ?></h2>
							</th>
						</tr>

						<?php if ( function_exists( 'gzencode' ) ) : ?>
							<tr>
								<th scope="row"><label for="atozsites_enable_gzip_compression_advanced"><?php esc_html_e( 'Enable gzip Compression', 'atozsites-simple-cache' ); ?></label></th>
								<td>
									<select <?php if ( empty( $config['advanced_mode'] ) ) : ?>disabled<?php endif; ?> name="atozsites_simple_cache[enable_gzip_compression]" id="atozsites_enable_gzip_compression_advanced">
										<option value="0"><?php esc_html_e( 'No', 'atozsites-simple-cache' ); ?></option>
										<option <?php selected( $config['enable_gzip_compression'], true ); ?> value="1"><?php esc_html_e( 'Yes', 'atozsites-simple-cache' ); ?></option>
									</select>

									<p class="description"><?php esc_html_e( 'When enabled pages will be gzip compressed at the PHP level. Note many hosts set up gzip compression in Apache or nginx.', 'atozsites-simple-cache' ); ?></p>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'atozsites-simple-cache' ); ?>">
					<a class="button" style="margin-left: 10px;" href="?page=atozsites-simple-cache&amp;wp_http_referer=<?php echo esc_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ); ?>&amp;action=atozsites_purge_cache&amp;atozsites_cache_nonce=<?php echo esc_attr( wp_create_nonce( 'atozsites_purge_cache' ) ); ?>"><?php esc_html_e( 'Purge Cache', 'atozsites-simple-cache' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  1.0
	 * @return object
	 */
	public static function factory() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
