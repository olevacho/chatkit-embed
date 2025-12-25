<?php
/**
 * Plugin Name: Tiny ChatKit Embed
 * Description: ChatKit embed with secure token issuing.
 * Version: 0.1.0
 * Author: Oleh Chorn...
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/admin/settings-widget.php';
class MyChatKit_Plugin {
    const OPT_KEY =  'mychatkit_openai_api_key';
    public $workflow = '';
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_routes']);
        //add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
        //add_shortcode('mychatkit_chat', [$this, 'shortcode_chat']);
        add_shortcode('chatkit_widget', [$this, 'shortcode_widget']);

    }
    
    
    
// shortcode: [chatkit_widget theme="dark" title="Support" min_height="640px" options='{"composer":{"placeholder":"Hi!"}}']



public function shortcode_widget($atts = [], $content = '') {

    wp_enqueue_script('chatkit-widget');
wp_enqueue_style('chatkit-widget');
    $atts = shortcode_atts([
        'mode'           => 'inline',     // inline | bubble | modal
        'position'       => 'br',         // tl | tr | bl | br (for bubble)
        'label'          => 'Chat',       // launcher text (bubble + modal)
        'icon'           => '',           // optional icon URL
        'panel_width'    => '380px',      // bubble panel size
        'panel_height'   => '560px',
        'workflow'       => null,

        // NEW host-shell tuning
        'launcher_bg'    => '',           // launcher background color
        'launcher_color' => '',           // launcher text color
        'launcher_shadow'=> '',           // "0" to disable box-shadow
        'start_open'     => '',           // "1" to auto-open bubble/modal
        'hide_on_mobile' => '',           // "1" to hide on small screens
        'z_index'        => '',           // override default z-index

        'id'             => 'chatkit-root-' . wp_generate_uuid4(),
        'class'          => 'chatkit-wrapper',
        // we no longer accept per-shortcode options; global JSON only
    ], $atts, 'chatkit_widget');

    // Load JSON from settings (already JSON or JS-like converted to JSON)
    $global_json = get_option('chatkit_widget_default_options', '');
    $options_json = null;

    if (!empty($global_json)) {
        $decoded = json_decode($global_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $options_json = wp_json_encode(
                $decoded,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }
    }

    $data = array_filter([
        'options'        => $options_json,

        'mode'           => $atts['mode'],
        'position'       => $atts['position'],
        'label'          => $atts['label'],
        'icon'           => $atts['icon'],
        'panelWidth'     => $atts['panel_width'],
        'panelHeight'    => $atts['panel_height'],
        'workflow'       => $atts['workflow'] ? trim($atts['workflow']) : null,

        // NEW host-shell controls
        'launcherBg'     => $atts['launcher_bg'],
        'launcherColor'  => $atts['launcher_color'],
        'launcherShadow' => $atts['launcher_shadow'],
        'startOpen'      => $atts['start_open'],
        'hideOnMobile'   => $atts['hide_on_mobile'],
        'zIndex'         => $atts['z_index'],
    ], function ($v) {
        return $v !== null && $v !== '';
    });

    $html_data = esc_attr(wp_json_encode($data));

  $html_data = esc_attr(wp_json_encode($data));

    return '<div id="' . esc_attr($atts['id']) . '" class="' . esc_attr($atts['class']) . '" data-chatkit-options="' . $html_data . '"></div>';
}




// Deep merge helper: base ← overrides
private function chatkit_array_deep_merge(array $base, array $overrides): array {
    foreach ($overrides as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = $this->chatkit_array_deep_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}


    
    public function enqueue_widget(){
        // ChatKit web component
	    wp_enqueue_script('openai-chatkit', 'https://cdn.platform.openai.com/deployments/chatkit/chatkit.js', [], null, true);


	    wp_register_script('chatkit-widget', plugins_url('/public/chatkit-widget.js', __FILE__), [], '1.14', true);
	    wp_localize_script('chatkit-widget', 'CHATKIT_WIDGET', [
		'tokenEndpoint' => rest_url('mychatkit/v1/token'), //
		'defaults' => get_option('chatkit_widget_default_options', ''),
	    ]);
	    wp_register_style(
		'chatkit-widget',
		plugins_url('assets/css/chatkit-widget.css', __FILE__),
		[],
		'1.0.0'
	    );
	    // Optional custom CSS from admin (ONE option, ONE handle)
	    $css = trim(get_option('chatkit_widget_custom_css', ''));
	    if ($css !== '') {
		wp_add_inline_style('chatkit-widget', $css);
	    }
    }

    public function add_menu() {
        add_options_page('My ChatKit', 'My ChatKit', 'manage_options', 'mychatkit', [$this, 'render_settings']);
    }

    public function register_settings() {
        register_setting(
	    'mychatkit',
	    self::OPT_KEY,
	    [
		'type'              => 'string',
		'sanitize_callback' => 'mychatkit_sanitize_opt_string',
	    ]
	);


        add_settings_section('mychatkit_main', 'OpenAI', function(){}, 'mychatkit');
        add_settings_field(self::OPT_KEY, 'OpenAI API Key', function(){
            $v = esc_attr(get_option(self::OPT_KEY, ''));
            echo '<input type="password" name="'.esc_html(self::OPT_KEY).'" value="'.esc_html($v).'" style="width: 420px;" autocomplete="off" />';
        }, 'mychatkit', 'mychatkit_main');
    }
    
    public function mychatkit_sanitize_opt_string( $value ) {
        if ( is_array( $value ) ) {
            // Just in case someone POSTs an array, flatten it
            $value = implode( ',', $value );
        }

        $value = (string) $value;
        $value = wp_unslash( $value );     // fix magic quotes
        $value = trim( $value );

        // If this is something like an API key / workflow ID / URL and
        // If want extra safety (no HTML), uncomment this:
        // $value = sanitize_text_field( $value );

        return $value;
    }

    
    public function render_settings() {
        echo '<div class="wrap"><h1>My ChatKit</h1><form method="post" action="options.php">';
        settings_fields('mychatkit'); do_settings_sections('mychatkit'); submit_button();
        echo '</form></div>';
    }

    public function register_routes() {
        register_rest_route('mychatkit/v1', '/token', [
            'methods'  => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'issue_client_token'],
        ]);
    }

    private function openai_chatkit_session_create($req) {
    $api_key = get_option(self::OPT_KEY, '');
   
    if (!$api_key) {
        return new WP_Error('no_api_key', 'OpenAI API key not configured', ['status' => 500]);
    }
    $params = $req->get_json_params();
    if(empty($params)){
            	$params = $req->get_body_params();
            }
    $workflow_id = isset($params['workflow_id'])
        ? sanitize_text_field($params['workflow_id'])
        : '';        

    $endpoint = 'https://api.openai.com/v1/chatkit/sessions'; // 
     $wp_user = wp_get_current_user();
     if ($wp_user && $wp_user->ID) {
	    $user_id    = 'wp:' . (int) $wp_user->ID;
	    
	  } else {
	    $token      = function_exists('wp_get_session_token') ? (wp_get_session_token() ?: wp_generate_password(20)) : wp_generate_password(20);
	    $remote_addr = '';
	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
	    $remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	$user_agent = '';
	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
	    $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	$finger = $token . '|' . $remote_addr . '|' . $user_agent;

	    $user_id    = 'anon:' . substr(hash_hmac('sha256', $finger, wp_salt()), 0, 16);

	  }
  
    $payload = [
        // shape depends on the session type creating; examples often include model / expiry / metadata
         'user'      => $user_id,
        'workflow' => ['id' => $workflow_id],
    ];

    $args = [
        'method'  => 'POST',
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'OpenAI-Beta'     => 'chatkit_beta=v1',  
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 20,
    ];

    $resp = wp_remote_post($endpoint, $args);

    if (is_wp_error($resp)) {
        return new WP_Error('http_error', $resp->get_error_message(), ['status' => 500]);
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);

    if ($code < 200 || $code >= 300) {
        return new WP_Error('openai_error', $body ?: 'Non-2xx from OpenAI', ['status' => $code]);
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return new WP_Error('json_error', 'Invalid JSON from OpenAI', ['status' => 500]);
    }

    // Expect fields like client_secret / client_token / expires_at, per the ChatKit auth guide.
    return $json;
}



    public function issue_client_token(WP_REST_Request $req) {
	    // Optional hardening: check a nonce from headers/body, throttle, restrict origin, etc.
	    $session = $this->openai_chatkit_session_create($req);
	    if (is_wp_error($session)) return $session;

	    return rest_ensure_response([
		'client_secret' => $session['client_secret'] ?? null,
		'client_token'  => $session['client_token'] ?? null,
		'expires_at'    => $session['expires_at'] ?? null,
	    ]);
	}


    public function enqueue_frontend() {
        //  vanilla JS that mounts ChatKit
        wp_register_script('mychatkit-frontend', plugins_url('public/chatkit-embed.js', __FILE__), [], '0.1.1', true);
        wp_localize_script('mychatkit-frontend', 'MYCHATKIT', [
            'tokenEndpoint' => rest_url('mychatkit/v1/token'),
        ]);
        
        // Load ChatKit’s web component bundle from OpenAI CDN.
wp_enqueue_script(
  'openai-chatkit',
  'https://cdn.platform.openai.com/deployments/chatkit/chatkit.js',
  [],
  null,
  true
);
// Optionally mark async:
if (function_exists('wp_script_add_data')) {
  wp_script_add_data('openai-chatkit', 'async', true);
}

    }

    public function shortcode_chat($atts = []) {
        // Enqueue and output a mount point
        wp_enqueue_script('mychatkit-frontend');
        return '<div id="chatkit-root" data-theme="light"></div>'
        . '<style>'
                . 'openai-chatkit {
  display: block;
  min-height: 520px;
}'
                . '</style>';
    }
}

new MyChatKit_Plugin();

