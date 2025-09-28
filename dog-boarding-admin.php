<?php
/**
 * Plugin Name: Dog Boarding Administrative Software
 * Plugin URI: https://yourwebsite.com/
 * Description: Comprehensive dog boarding management system with client and pet management
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: dbas
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DBAS_VERSION', '1.0.0');
define('DBAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DBAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DBAS_PLUGIN_FILE', __FILE__);

// Include required files
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-activator.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-database.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-user-fields.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-dog-manager.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-frontend.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-admin.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-emails.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-ajax.php';

// NEW RESERVATION SYSTEM FILES
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-reservations-database.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-reservation-manager.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-daycare-manager.php';
require_once DBAS_PLUGIN_DIR . 'includes/class-dbas-reservations-admin.php';

// Main plugin class
class DBAS_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Initialize existing components
        new DBAS_User_Fields();
        new DBAS_Dog_Manager();
        new DBAS_Frontend();
        new DBAS_Admin();
        new DBAS_Emails();
        new DBAS_Ajax();
        
        // Initialize NEW reservation system components
        new DBAS_Reservation_Manager();
        new DBAS_Daycare_Manager();
        new DBAS_Reservations_Admin();
        
        // Add hooks
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('dbas', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('dbas-frontend', DBAS_PLUGIN_URL . 'assets/css/frontend.css', array(), DBAS_VERSION);
        wp_enqueue_script('dbas-frontend', DBAS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), DBAS_VERSION, true);
        
        wp_localize_script('dbas-frontend', 'dbas_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dbas_ajax_nonce')
        ));
    }
    
    public function enqueue_admin_assets() {
        wp_enqueue_style('dbas-admin', DBAS_PLUGIN_URL . 'assets/css/admin.css', array(), DBAS_VERSION);
        wp_enqueue_script('dbas-admin', DBAS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), DBAS_VERSION, true);
        wp_enqueue_media();
        
        // Localize script for admin AJAX
        wp_localize_script('dbas-admin', 'dbas_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dbas_admin_ajax_nonce')
        ));
    }
}

// Activation hook
register_activation_hook(__FILE__, array('DBAS_Activator', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('DBAS_Activator', 'deactivate'));

// Initialize plugin
add_action('plugins_loaded', array('DBAS_Plugin', 'get_instance'));