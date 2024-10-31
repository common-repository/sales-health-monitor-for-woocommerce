<?php
/**
 * Plugin Name: Sales Health Monitor for WooCommerce
 * Description: Effortlessly monitor your WooCommerce store's performance and receive timely email alerts when your sales fall below defined thresholds.
 * Author: IT goldman
 * Author URI: https://itgoldman.com
 * Version: 0.9.1
 * License: GPLv2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

class ITG_SalesHealthMonitorForWooCommerce
{

	private $sales_health_monitor_server = 'https://sales-health-monitor.itgoldman.com';
	private $option_name = 'itg-sales_health_monitor_settings';
	private $secret_token_option = 'itg-sales_health_monitor_secret_token';
	private $last_accessed_option = 'itg-sales_health_monitor_last_accessed';
	private $activation_notice_option = 'itg-sales_health_monitor_activation_notice';
	private $endpoint = 'itg-sales-health-monitor-endpoint';
	private $slug = 'sales-health-monitor';
	private $expected_access_interval_minutes = 60;
	private $sanitized_once = false;


	public function __construct()
	{
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_notices', array($this, 'admin_notices'));
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
		add_action('admin_init', array($this, 'check_activation_notice'));
		add_action('admin_notices', array($this, 'display_activation_notice'));
		add_action('init', array($this, 'add_endpoint'));
		add_action('template_redirect', array($this, 'handle_api_request'));

		register_activation_hook(__FILE__, array($this, 'plugin_activation'));
	}

	public function add_endpoint()
	{
		add_rewrite_endpoint($this->endpoint, EP_ROOT);
	}

	function is_woocommerce_active()
	{
		return in_array(
			'woocommerce/woocommerce.php',
			apply_filters('active_plugins', get_option('active_plugins'))
		);
	}

	public function handle_api_request()
	{
		try {

			global $wp_query;

			if (!isset($wp_query->query_vars[$this->endpoint])) {
				return;
			}

			// check if this is a POST
			if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
				throw new ErrorException('Invalid request method');
			}

			// Verify API key
			if (!$this->endpoint_authentication()) {
				throw new ErrorException('Bad credentials');
			}

			$this->set_last_accessed();

			if (!$this->is_woocommerce_active()) {
				throw new ErrorException('WooCommerce not installed');
			}

			$count = $this->get_orders_count($this->get_hours());

			// Prepare and send response
			$status_data = array(
				'count' => $count,
			);

			wp_send_json($status_data);
			exit;


		} catch (ErrorException $e) {
			wp_send_json(array(
				'error' => $e->getMessage()
			));
			exit;
		}
	}

	public function plugin_activation()
	{
		if (!$this->get_secret_token()) {
			update_option($this->secret_token_option, $this->generate_secret_token());
		}
		update_option($this->activation_notice_option, true);


		$this->add_endpoint();
		flush_rewrite_rules();
	}

	public function check_activation_notice()
	{
		if (get_option($this->activation_notice_option, false)) {
			add_action('admin_notices', array($this, 'display_activation_notice'));
		}
	}

	public function display_activation_notice()
	{
		if (get_option($this->activation_notice_option, false)) {
			?>
			<div class="notice notice-info is-dismissible">
				<p>Thank you for installing Sales Health Monitor for WooCommerce! Please visit the <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->slug)); ?>">settings page</a> to configure and <strong>enable</strong> the plugin.</p>
			</div>
			<?php
			delete_option($this->activation_notice_option);
		}
	}

	private function generate_secret_token()
	{
		return bin2hex(random_bytes(32));
	}

	public function add_admin_menu()
	{
		add_menu_page(
			'Sales Health Monitor for WooCommerce Settings',
			'Sales Health Monitor',
			'manage_options',
			$this->slug,
			array($this, 'render_settings_page'),
			'dashicons-heart'
		);
	}

	public function register_settings()
	{
		register_setting('sales_health_monitor_settings_group', $this->option_name, array($this, 'sanitize_settings'));

		add_settings_section(
			'sales_health_monitor_main_section',
			'Main Settings',
			null,
			$this->slug
		);

		$this->add_settings_field('field_email', 'Notification Email', 'render_email_field', get_option('admin_email'));
		$this->add_settings_field('field_threshold', 'Minimum Sales Allowed', 'render_number_field', 1, [], 'Anything less in the allowed time frame will raise a notification');
		$this->add_settings_field('field_hours', 'Time Frame', 'render_select_field', '24', [
			'6' => '6 hours',
			'12' => '12 hours',
			'24' => '24 hours',
			'48' => '48 hours',
			'168' => '7 days',
		]);
		$this->add_settings_field('field_active', 'Monitor Enabled?', 'render_checkbox_field', false, [], 'By enabling the service you agree to securely share aggregated sale data with our monitoring service');
	}

	private function add_settings_field($id, $title, $callback = 'render_text_field', $default = '', $options = [], $hint = '')
	{
		add_settings_field(
			'sales_health_monitor_' . $id,
			$title,
			array($this, $callback),
			$this->slug,
			'sales_health_monitor_main_section',
			array('id' => $id, 'default' => $default, 'label_for' => $this->option_name . '[' . $id . ']', 'options' => $options, 'hint' => $hint)
		);
	}

	public function sanitize_settings($input)
	{
		$new_input = array();
		$new_input['field_email'] = sanitize_email($input['field_email']);
		$new_input['field_threshold'] = absint($input['field_threshold']);
		$new_input['field_hours'] = absint($input['field_hours']);
		$new_input['field_active'] = isset($input['field_active']) ? 1 : 0;

		if (!$this->sanitized_once) {

			// prevent accidental 
			$this->sanitized_once = true;
			$old_input = get_option($this->option_name);
			if ($new_input !== $old_input) {
				try {
					$response = $this->activate_plugin_on_sales_health_monitor($new_input);
				} catch (ErrorException $ex) {
					add_settings_error('sales_health_monitor_messages', 'sales_health_monitor_message', "Error! Monitor response: " . $ex->getMessage(), 'error');
					return $old_input;
				}

				$response_code = wp_remote_retrieve_response_code($response);
				$body = wp_remote_retrieve_body($response);
				$result = json_decode($body, true);
				$message = $result['message'];
				$error = $result['error'];
				if ($error || $response_code != 200) {
					if (!$error) {
						$error = 'Unknown error ' . ' (' . $response_code . ')';
					}
					add_settings_error('sales_health_monitor_messages', 'sales_health_monitor_message', "Error! Monitor response: $error", 'error');
					return $old_input;
				} else {
					add_settings_error('sales_health_monitor_messages', 'sales_health_monitor_message', "Settings saved successfully! Monitor response: $message", 'updated');
				}
			}
		}

		return $new_input;
	}

	public function render_input_field($args, $type)
	{
		$options = get_option($this->option_name);
		$value = isset($options[$args['id']]) ? $options[$args['id']] : $args['default'];
		echo '<input style="width: 100%; max-width: 200px" min="0" type="' . esc_attr($type) . '" name="' . esc_attr($this->option_name) . '[' . esc_attr($args['id']) . ']" id="' . esc_attr($this->option_name) . '[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" />';
		echo "\n";
		echo '<p class="description">' . esc_attr($args['hint']) . '</p>';
	}

	public function render_text_field($args)
	{
		$this->render_input_field($args, 'text');
	}

	public function render_number_field($args)
	{
		$this->render_input_field($args, 'number');
	}

	public function render_email_field($args)
	{
		$this->render_input_field($args, 'email');
	}

	public function render_checkbox_field($args)
	{

		$options = get_option($this->option_name);
		$value = isset($options[$args['id']]) ? $options[$args['id']] : $args['default'];
		$checked = $value ? 'checked' : '';

		echo '<input type="checkbox" name="' . esc_attr($this->option_name) . '[' . esc_attr($args['id']) . ']" id="' . esc_attr($this->option_name) . '[' . esc_attr($args['id']) . ']" value="1" ' . esc_attr($checked) . '/>';
		echo "\n";
		echo '<p class="description">' . esc_attr($args['hint']) . '</p>';

	}

	public function render_select_field($args)
	{
		$options = get_option($this->option_name);
		$current_value = isset($options[$args['id']]) ? $options[$args['id']] : $args['default'];

		echo '<select style="width: 100%; max-width: 200px" name="' . esc_attr($this->option_name . '[' . $args['id'] . ']') . '" id="' . esc_attr($this->option_name . '[' . $args['id'] . ']') . '">';

		$select_options = $args['options'];
		foreach ($select_options as $value => $label) {
			$selected = selected($current_value, $value, false);
			echo '<option value="' . esc_attr($value) . '" ' . esc_attr($selected) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo "\n";
		echo '<p class="description">' . esc_html($args['hint']) . '</p>';
	}

	public function render_settings_page()
	{
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<br>
			<form method="post" action="options.php">
				<?php
				settings_fields('sales_health_monitor_settings_group');
				do_settings_sections($this->slug);
				submit_button();
				?>
			</form>
			<br>

			<h2>Extra Information</h2>
			<p><strong>Last Accessed:</strong> <?php echo wp_kses_post($this->get_last_accessed_formatted()); ?></p>
			<?php
			$hours = $this->get_hours();
			$str_time = $hours <= 48 ? $hours . " Hours" : ($hours / 24) . " Days";
			?>
			<p><strong>Number of Orders in recent <?php echo esc_html($str_time); ?>:</strong> <?php echo wp_kses_post($this->get_orders_count_formatted($hours)); ?></p>

		</div>
		<?php
	}

	public function admin_notices()
	{
		settings_errors('sales_health_monitor_messages');
	}

	private function activate_plugin_on_sales_health_monitor($data)
	{
		$data['site_domain'] = $this->get_site_domain();
		$data['script_url'] = $this->get_script_url();
		$data['site_url'] = $this->get_site_url();
		$data['secret_token'] = $this->get_secret_token();
		$data['plugin_version'] = $this->get_plugin_version();

		return $this->send_api_request('api/activate', $data);
	}

	private function send_api_request($endpoint, $data)
	{
		$api_url = rtrim($this->sales_health_monitor_server, '/') . '/' . ltrim($endpoint, '/');
		$response = wp_remote_post(
			$api_url,
			array(
				'body' => wp_json_encode($data),
				'headers' => array('Content-Type' => 'application/json'),
			)
		);

		if (is_wp_error($response)) {
			$msg = $response->get_error_message();
			throw new ErrorException(esc_html($msg));
		}

		return $response;
	}

	public function add_settings_link($links)
	{
		$settings_link = '<a href="' . admin_url('admin.php?page=' . $this->slug) . '">' . __('Settings') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	public function get_site_domain()
	{
		return wp_parse_url(get_site_url(), PHP_URL_HOST);
	}

	public function get_script_url()
	{
		return trailingslashit(get_home_url()) . $this->endpoint;
	}

	public function get_site_url()
	{
		return get_site_url();
	}

	public function get_secret_token()
	{
		return get_option($this->secret_token_option);
	}

	public function get_last_accessed()
	{
		return get_option($this->last_accessed_option);
	}

	public function get_last_accessed_formatted()
	{

		$last_accessed = $this->get_last_accessed();
		if (!$last_accessed) {
			return "<span style='color: red'>Never</span>";
		}

		$current_time = current_time('mysql', true);
		$last_accessed_timestamp = strtotime($last_accessed);
		$current_timestamp = strtotime($current_time);
		$diff_minutes = ($current_timestamp - $last_accessed_timestamp) / 60;

		$wp_timezone = wp_timezone();
		$date_object = new DateTime($last_accessed, new DateTimeZone('UTC'));
		$date_object->setTimezone($wp_timezone);

		$color = $diff_minutes <= $this->expected_access_interval_minutes ? "green" : "red";

		return "<span style='color: $color'>" . $date_object->format(get_option('date_format') . ' ' . get_option('time_format')) . "</span>";
	}

	public function get_orders_count_formatted($recent_hours)
	{
		$threshold = $this->get_threshold();
		$count = $this->get_orders_count($recent_hours);
		return "<span style='color: " . ($count < $threshold ? "red" : "green") . "'>" . $count . "</span>";
	}

	public function set_last_accessed()
	{
		$date = current_time('mysql', true);
		update_option($this->last_accessed_option, $date);
	}

	public function get_hours()
	{
		$options = get_option($this->option_name);
		return isset($options['field_hours']) ? $options['field_hours'] : 0;
	}

	public function get_threshold()
	{
		$options = get_option($this->option_name);
		return isset($options['field_threshold']) ? $options['field_threshold'] : 24;
	}

	public function get_plugin_version()
	{
		$version = get_plugin_data(__FILE__)['Version'];
		return $version;
	}

	public function get_orders_count($recent_hours)
	{
		$count = 0;

		if (function_exists('wc_get_orders')) {
			$args = array(
				'date_created' => '>' . (time() - ($recent_hours * HOUR_IN_SECONDS)),
				'status' => array('wc-completed', 'wc-processing'),
				'limit' => 100,
			);

			$arr = wc_get_orders($args);
			$count = $arr ? count($arr) : 0;
		}
		return $count;
	}

	public function endpoint_authentication()
	{
		// Nonce verification is not applicable here as this POST comes from a trusted external service
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pwd = isset($_POST['pwd']) ? sanitize_text_field(wp_unslash($_POST['pwd'])) : '';
		$secret_token = $this->get_secret_token();
		if ($secret_token != $pwd || !$pwd) {
			return false;
		}
		return true;
	}

}


new ITG_SalesHealthMonitorForWooCommerce();

