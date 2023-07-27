<?php 
/*
Plugin Name: api-data-sync
Description: A plugin to fetch, save, display and synchronize data from API.
Version: 2.0
Author: Marcin Kowalski
*/

require_once(ABSPATH . 'wp-config.php');

add_action('admin_menu', 'api_request_admin_page');
function api_request_admin_page(){
    add_menu_page('API Request Settings', 'API Request', 'administrator', 'db-request-settings', 'api_request_admin_page_callback');
}

function db_api_desc() {
    echo '<p>API general settings.</p>';
}
function refresh_desc(){
    echo '<p>Local data refreshing. <strong>Uses parameters from main options.</strong></p>';
    $options = get_option('api_data');
    if($options != ''){
        $date = $options['date'];
    }
    if(isset($date) && gettype($date) == 'string' && $date != ''){
        echo '<small>Last data refresh at: <strong>'.$date.'</strong></small>';
    }
}

function api_endpoint_input_callback() {
    $options = get_option('db_api_options');
    $options['main_endpoint'] = isset($options['main_endpoint']) ? ($options['main_endpoint']):('');
    echo "<input id='main_endpoint' name='db_api_options[main_endpoint]' size='40' type='text' value='{$options['main_endpoint']}' />";
} 
function api_endpoint_specific_input_callback() {
    $options = get_option('db_api_options');
    $options['specific_endpoint'] = isset($options['specific_endpoint']) ? ($options['specific_endpoint']):('');
    echo "<input id='specific_endpoint' name='db_api_options[specific_endpoint]' size='40' type='text' value='{$options['specific_endpoint']}' />";
} 
function api_content_type_input_callback() {
    $options = get_option('db_api_options');
    $options['content_type'] = isset($options['content_type']) && $options['content_type'] != "" ? ($options['content_type']):('application/JSON');
    echo "<input id='content_type' name='db_api_options[content_type]' size='40' type='text' value='{$options['content_type']}' />";
} 
function api_key_input_callback() {
    $options = get_option('db_api_options');
    $options['api_key'] = isset($options['api_key']) ? ($options['api_key']):('');
    echo "<input id='api_key' name='db_api_options[api_key]' size='40' type='text' value='{$options['api_key']}' />";
} 
function api_secret_input_callback() {
    $options = get_option('db_api_options');
    $options['api_secret'] = isset($options['api_secret']) ? ($options['api_secret']):('');
    echo "<input id='api_secret' name='db_api_options[api_secret]' size='40' type='text' value='{$options['api_secret']}' />";
} 

function refresh_date_input_callback() {
    echo "<input id='date' name='api_data[data]' type='hidden' />";
} 
function refresh_data_input_callback() {
    echo "<input id='data' name='api_data[date]' type='hidden' />";
} 
add_action('admin_init', 'api_request_register_settings');
function api_request_register_settings(){
    register_setting('api_request_settings', 'api_request_settings', 'api_request_settings_validate');

    register_setting( 'db_api_options', 'db_api_options', 'db_api_options_validate' );
    add_settings_section('db_api_requests', 'Main Settings', 'db_api_desc', 'db-request-settings');
    
    add_settings_field('main_endpoint', 'Main Endpoint:', 'api_endpoint_input_callback', 'db-request-settings', 'db_api_requests');
    add_settings_field('specific_endpoint', 'Specific Key Endpoint:', 'api_endpoint_specific_input_callback', 'db-request-settings', 'db_api_requests');
    add_settings_field('content_type', 'Content Type:', 'api_content_type_input_callback', 'db-request-settings', 'db_api_requests');
    add_settings_field('api_key', 'API Key:', 'api_key_input_callback', 'db-request-settings', 'db_api_requests');
    add_settings_field('secret', 'API Secret:', 'api_secret_input_callback', 'db-request-settings', 'db_api_requests');
    
    register_setting( 'api_data', 'api_data', 'refresh_data_validate' );
    add_settings_section('refresh','Refresh Data','refresh_desc', 'db-refresh');
    add_settings_field('data', 'refresh_data_input_callback', 'db-refresh', 'refresh');
    add_settings_field('date', 'refresh_date_input_callback', 'db-refresh', 'refresh');
    
    
}
add_action('init', 'handle_webhook_request');

function handle_webhook_request() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['webhook'])) {
        
        $params = get_option('db_api_options');
        $headers = array(
            'Content-Type' => $params['content_type'],
            'api-key' => $params['api_key'],
            'api-secret' =>  $params['api_secret']
        );
        $response = wp_remote_get($params['main_endpoint'], array('headers' => $headers));
        
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
        }
        else{
            $json_body = wp_remote_retrieve_body($response);
            $var_body = json_decode($json_body);
            $options = get_option('api_data');

            $options['data'] = get_key_value($response);
            $options['date'] = date(DATE_ATOM);
            update_option('api_data', $options);
        }   


        
        echo "Webhook received!";
        http_response_code(200);
        exit;
    }
}
function get_key_value($response){
    $json_body = wp_remote_retrieve_body($response);
    $var_body = json_decode($json_body);
    $return = array();
    $params = get_option('db_api_options');
    $headers = array(
        'Content-Type' => $params['content_type'],
        'api-key' => $params['api_key'],
        'api-secret' =>  $params['api_secret']
    );
    foreach($var_body as $key){
        $id = $key->key;
        $response = wp_remote_get($params['specific_endpoint'] . $id, array('headers' => $headers));
        $json_value = wp_remote_retrieve_body($response);
        $body_value = json_decode($json_value);

        
        $return = array_merge($return, array($id=>$body_value->value));
    }
    return ($return);
}
function refresh_data_validate($args){
    $params = get_option('db_api_options');
    $headers = array(
        'Content-Type' => $params['content_type'],
        'api-key' => $params['api_key'],
        'api-secret' =>  $params['api_secret']
    );
    $response = wp_remote_get($params['main_endpoint'], array('headers' => $headers));
    
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();

        add_settings_error('api_request_settings', 'rest_api_error', "Nothing was updated.", $type = 'error');
        add_settings_error('api_request_settings', 'rest_api_error', $error_message, $type = 'error');
    
    }
    else{
        $json_body = wp_remote_retrieve_body($response);
        $var_body = json_decode($json_body);
        $options = get_option('api_data');

        // add_settings_error('api_request_settings', 'rest_api_error',$options, $type = 'success');
        $args['data'] = get_key_value($response);
        $args['date'] = date(DATE_ATOM);

        add_settings_error('api_request_settings', 'rest_api_error','Data updated succesfully!', $type = 'success');

    }  

    return $args;
}
function db_api_options_validate($args){
    // i don't see much reason to validate these, its really on hte user what data they put here like yeah    
    return $args;
}

add_action('admin_notices', 'api_request_admin_notices');
function api_request_admin_notices(){
    settings_errors();
}

function api_request_admin_page_callback(){ ?>
    <div class="wrap">
        <h2 >API Request Settings</h2>
        
        
        <form action="options.php" method="post">
            <?php
    settings_fields('db_api_options');
    do_settings_sections('db-request-settings'); 
    submit_button();
    ?>
    </form>
        
    <form action="options.php" method="post">
    <?php
    settings_fields('api_data');
    do_settings_sections('db-refresh'); 
    submit_button('Refresh');
    ?>
    </form>
</div>
<?php }

function get_external_content($atts = []){
    if(!isset(get_option('api_data')['data'])){
        return "";
    }
    $data = get_option('api_data')['data'];
    // print_r($data);
    $defaults = array(
        'key' => '',
    );
    $atts = shortcode_atts($defaults, $atts);
    if($atts['key'] == ""){
        return "";
    }

    return $data[$atts['key']];
}


function plugin_shortcodes_init() {
	add_shortcode( 'get_external_content', 'get_external_content' );
}

add_action( 'init', 'plugin_shortcodes_init' );
?>