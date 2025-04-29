<?php
/**
 * Plugin Name: Admin Activity Tracker
 * Description: Tracks admin user activity in the WordPress backend, including menu navigation.
 * Version: 1.1
 * Author: Milo Lockhart
 */

if (!defined('ABSPATH')) exit;

// Create database table
register_activation_hook(__FILE__, 'create_admin_tracking_table');

function create_admin_tracking_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'admin_activity_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        action_type varchar(255) NOT NULL,
        details text NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Log admin actions
function log_admin_action($action, $details) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'admin_activity_log';
    $user_id = get_current_user_id();
    $timestamp = current_time('mysql');

    $wpdb->insert(
        $table_name,
        [
            'user_id' => $user_id,
            'action_type' => sanitize_text_field($action),
            'details' => sanitize_textarea_field($details),
            'timestamp' => $timestamp
        ],
        ['%d', '%s', '%s', '%s']
    );
}

// Track logins/logouts
add_action('wp_login', function($user_login, $user) {
    if (user_can($user, 'manage_options')) {
        log_admin_action('Login', "Admin user {$user->user_login} logged in.");
    }
}, 10, 2);

add_action('wp_logout', function() {
    $user = wp_get_current_user();
    if ($user && current_user_can('manage_options')) {
        log_admin_action('Logout', "Admin user {$user->user_login} logged out.");
    }
});

// Track post/page updates
add_action('save_post', function($post_id, $post, $update) {
    if (current_user_can('edit_others_posts')) {
        $action = $update ? 'Updated' : 'Created';
        log_admin_action("Post {$action}", "Post ID: {$post_id}, Title: {$post->post_title}");
    }
}, 10, 3);

add_action('before_delete_post', function($post_id) {
    if (current_user_can('delete_others_posts')) {
        log_admin_action('Deleted Post', "Deleted Post ID: {$post_id}");
    }
});

// Track plugin activations & deactivations
add_action('activated_plugin', function($plugin) {
    log_admin_action('Activated Plugin', "Activated: {$plugin}");
});

add_action('deactivated_plugin', function($plugin) {
    log_admin_action('Deactivated Plugin', "Deactivated: {$plugin}");
});

// Track setting changes
add_action('updated_option', function($option_name, $old_value, $new_value) {
    log_admin_action('Updated Setting', "Changed: {$option_name}");
}, 10, 3);

// Add admin page
add_action('admin_menu', function() {
    add_menu_page(
        'Admin Activity Log',
        'Admin Activity',
        'manage_options',
        'admin-activity-log',
        'display_admin_activity_log',
        'dashicons-visibility',
        80
    );
});

// Enqueue JavaScript for menu tracking
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('admin-tracker-js', plugin_dir_url(__FILE__) . 'admin-tracker.js', ['jquery'], null, true);
    wp_localize_script('admin-tracker-js', 'adminTrackerAjax', ['ajaxurl' => admin_url('admin-ajax.php')]);
});

// Handle AJAX request for menu tracking
add_action('wp_ajax_log_admin_navigation', function() {
    if (is_user_logged_in() && current_user_can('manage_options')) {
        $menu_item = sanitize_text_field($_POST['menu_item'] ?? '');
        log_admin_action('Navigated Menu', "Admin clicked on: {$menu_item}");
    }
    wp_die();
});

// Display logs with filters
function display_admin_activity_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'admin_activity_log';
    
    $action_filter = $_GET['action_filter'] ?? '';
    $user_filter = $_GET['user_filter'] ?? '';
    $search_query = $_GET['search_query'] ?? '';
    
    $query = "SELECT * FROM $table_name WHERE 1=1";
    if ($action_filter) $query .= $wpdb->prepare(" AND action_type = %s", $action_filter);
    if ($user_filter) $query .= $wpdb->prepare(" AND user_id = %d", $user_filter);
    if ($search_query) $query .= $wpdb->prepare(" AND (details LIKE %s OR action_type LIKE %s)", "%$search_query%", "%$search_query%");
    
    $query .= " ORDER BY timestamp DESC";
    $results = $wpdb->get_results($query);

    echo '<div class="wrap">';
    echo '<h1>Admin Activity Log</h1>';
    
    echo '<form method="GET"><input type="hidden" name="page" value="admin-activity-log">';
    echo 'Filter by Action: <select name="action_filter"><option value="">All</option>';
    $actions = $wpdb->get_col("SELECT DISTINCT action_type FROM $table_name");
    foreach ($actions as $action) {
        echo "<option value='$action' " . selected($action, $action_filter, false) . ">$action</option>";
    }
    echo '</select>';
    
    echo 'Filter by User: <select name="user_filter"><option value="">All</option>';
    $users = get_users(['role' => 'administrator']);
    foreach ($users as $user) {
        echo "<option value='{$user->ID}' " . selected($user->ID, $user_filter, false) . ">{$user->user_login}</option>";
    }
    echo '</select>';

    echo ' Search: <input type="text" name="search_query" value="' . esc_attr($search_query) . '">';
    echo ' <input type="submit" value="Filter"></form>';

    echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>User</th><th>Action</th><th>Details</th><th>Timestamp</th></tr></thead><tbody>';

    foreach ($results as $row) {
        $user = get_userdata($row->user_id);
        echo "<tr><td>{$row->id}</td><td>{$user->user_login}</td><td>{$row->action_type}</td><td>{$row->details}</td><td>{$row->timestamp}</td></tr>";
    }

    echo '</tbody></table></div>';
}
