<?php
/** 
* Plugin Name: Daily Inspiration
* Description: A simple plugin to display daily inspiration
* Version: 1.0
*/

add_action('admin_menu', function(){

    add_menu_page(
        'Daily Inspiration',
        'Daily Inspiration',
        'manage_options',
        'daily-inspiration',
        'daily_inspiration_page',
        'dashicons-admin-generic',
        6
    );

});

function daily_inspiration_page() {
    $response = wp_remote_get('https://api.quotable.io/random', array(
        'sslverify' => false
    ));
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "<p>Something went wrong: $error_message</p>";
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $quote = $data['content'];
        $author = $data['author'];
        
        echo '<h1>Daily Inspiration</h1>';
        echo '<div id="quote-container"><p>' . $quote . ' - ' . $author . '</p></div>';
        echo '<button id="new-quote-button">Get New Quote</button>';
    }
}

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('daily-inspiration-js', plugin_dir_url(__FILE__) . 'daily-inspiration.js', array('jquery'), null, true);
    wp_localize_script('daily-inspiration-js', 'dailyInspiration', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
});

add_action('wp_ajax_get_daily_inspiration', 'get_daily_inspiration');
function get_daily_inspiration() {
    $response = wp_remote_get('https://api.quotable.io/random', array(
        'sslverify' => false
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        wp_send_json_success($data);
    }
}
?>
