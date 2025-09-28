<?php
/**
 * Fired during plugin activation
 *
 * This class defines all code necessary to run during the plugin's activation.
 */

class DBAS_Activator {
    
    /**
     * Activate the plugin.
     *
     * Create database tables, add capabilities, and create default pages.
     */
    public static function activate() {
        // Start output buffering to prevent activation warnings
        ob_start();
        
        // Create existing database tables
        self::create_tables();
        
        // CREATE NEW RESERVATION SYSTEM TABLES
        if (class_exists('DBAS_Reservations_Database')) {
            DBAS_Reservations_Database::create_tables();
        }
        
        // Add capabilities
        self::add_capabilities();
        
        // Create pages
        self::create_pages();
        
        // Add default data
        self::add_default_data();
        
        // Schedule events
        self::schedule_events();
        
        // Set version
        update_option('dbas_version', DBAS_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any output
        ob_end_clean();
    }
    
    /**
     * Deactivate the plugin.
     */
    public static function deactivate() {
        // Unschedule events
        self::unschedule_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Dogs table
        $table_dogs = $wpdb->prefix . 'dbas_dogs';
        $sql_dogs = "CREATE TABLE IF NOT EXISTS $table_dogs (
            id int(11) NOT NULL AUTO_INCREMENT,
            owner_id bigint(20) UNSIGNED NOT NULL,
            name varchar(100) NOT NULL,
            breed varchar(100),
            birthdate date,
            sex varchar(10),
            color varchar(50),
            spay_neuter varchar(10),
            vet_details text,
            vaccination_records varchar(255),
            allergies text,
            behavioral_notes text,
            status varchar(20) DEFAULT 'inactive',
            hipaa_forms varchar(255),
            vet_verified tinyint(1) DEFAULT 0,
            pet_photos text,
            yearly_paperwork varchar(50) DEFAULT 'not_good_standing',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY owner_id (owner_id),
            KEY status (status),
            KEY yearly_paperwork (yearly_paperwork)
        ) $charset_collate;";
        
        // Emergency contacts table
        $table_contacts = $wpdb->prefix . 'dbas_emergency_contacts';
        $sql_contacts = "CREATE TABLE IF NOT EXISTS $table_contacts (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            relationship varchar(50),
            is_alternative_pickup tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Terms acceptance table
        $table_terms = $wpdb->prefix . 'dbas_terms_acceptance';
        $sql_terms = "CREATE TABLE IF NOT EXISTS $table_terms (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            term_id varchar(50) NOT NULL,
            term_version varchar(20),
            accepted tinyint(1) DEFAULT 0,
            accepted_date datetime,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY term_id (term_id),
            UNIQUE KEY user_term (user_id, term_id)
        ) $charset_collate;";
        
        // Breeds table
        $table_breeds = $wpdb->prefix . 'dbas_breeds';
        $sql_breeds = "CREATE TABLE IF NOT EXISTS $table_breeds (
            id int(11) NOT NULL AUTO_INCREMENT,
            breed_name varchar(100) NOT NULL,
            breed_group varchar(50),
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY breed_name (breed_name)
        ) $charset_collate;";
        
        // Terms and conditions table
        $table_terms_content = $wpdb->prefix . 'dbas_terms_content';
        $sql_terms_content = "CREATE TABLE IF NOT EXISTS $table_terms_content (
            id int(11) NOT NULL AUTO_INCREMENT,
            term_id varchar(50) NOT NULL DEFAULT 'default',
            term_title varchar(200) NOT NULL,
            term_content text,
            term_version varchar(20) DEFAULT '1.0',
            is_active tinyint(1) DEFAULT 1,
            required tinyint(1) DEFAULT 1,
            display_order int(11) DEFAULT 999,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_term_id (term_id),
            KEY idx_active (is_active)
        ) $charset_collate;";
        
        // Activity log table (for tracking important actions)
        $table_activity_log = $wpdb->prefix . 'dbas_activity_log';
        $sql_activity = "CREATE TABLE IF NOT EXISTS $table_activity_log (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED,
            action varchar(100) NOT NULL,
            object_type varchar(50),
            object_id int(11),
            details text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_dogs);
        dbDelta($sql_contacts);
        dbDelta($sql_terms);
        dbDelta($sql_breeds);
        dbDelta($sql_terms_content);
        dbDelta($sql_activity);
        
        // Store table version for future updates
        update_option('dbas_db_version', '1.0.0');
    }
    
    /**
     * Add default data
     */
    private static function add_default_data() {
        // Insert default breeds
        self::insert_default_breeds();
        
        // Insert default terms and conditions
        self::insert_default_terms();
    }
    
    /**
     * Insert default dog breeds
     */
    private static function insert_default_breeds() {
        global $wpdb;
        $table_breeds = $wpdb->prefix . 'dbas_breeds';
        
        // Check if breeds already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_breeds");
        if ($count > 0) {
            return; // Breeds already exist - exit silently
        }
        
        $default_breeds = array(
            // Popular breeds
            array('breed_name' => 'Labrador Retriever', 'breed_group' => 'Sporting'),
            array('breed_name' => 'German Shepherd', 'breed_group' => 'Herding'),
            array('breed_name' => 'Golden Retriever', 'breed_group' => 'Sporting'),
            array('breed_name' => 'French Bulldog', 'breed_group' => 'Non-Sporting'),
            array('breed_name' => 'Bulldog', 'breed_group' => 'Non-Sporting'),
            array('breed_name' => 'Poodle', 'breed_group' => 'Non-Sporting'),
            array('breed_name' => 'Beagle', 'breed_group' => 'Hound'),
            array('breed_name' => 'Rottweiler', 'breed_group' => 'Working'),
            array('breed_name' => 'German Shorthaired Pointer', 'breed_group' => 'Sporting'),
            array('breed_name' => 'Yorkshire Terrier', 'breed_group' => 'Toy'),
            array('breed_name' => 'Dachshund', 'breed_group' => 'Hound'),
            array('breed_name' => 'Boxer', 'breed_group' => 'Working'),
            array('breed_name' => 'Siberian Husky', 'breed_group' => 'Working'),
            array('breed_name' => 'Great Dane', 'breed_group' => 'Working'),
            array('breed_name' => 'Pug', 'breed_group' => 'Toy'),
            array('breed_name' => 'Boston Terrier', 'breed_group' => 'Non-Sporting'),
            array('breed_name' => 'Shih Tzu', 'breed_group' => 'Toy'),
            array('breed_name' => 'Pomeranian', 'breed_group' => 'Toy'),
            array('breed_name' => 'Havanese', 'breed_group' => 'Toy'),
            array('breed_name' => 'Cocker Spaniel', 'breed_group' => 'Sporting'),
            array('breed_name' => 'Border Collie', 'breed_group' => 'Herding'),
            array('breed_name' => 'Mastiff', 'breed_group' => 'Working'),
            array('breed_name' => 'Cavalier King Charles Spaniel', 'breed_group' => 'Toy'),
            array('breed_name' => 'Maltese', 'breed_group' => 'Toy'),
            array('breed_name' => 'Australian Shepherd', 'breed_group' => 'Herding'),
            array('breed_name' => 'Chihuahua', 'breed_group' => 'Toy'),
            array('breed_name' => 'Corgi', 'breed_group' => 'Herding'),
            array('breed_name' => 'Bernese Mountain Dog', 'breed_group' => 'Working'),
            array('breed_name' => 'Shetland Sheepdog', 'breed_group' => 'Herding'),
            array('breed_name' => 'Miniature Schnauzer', 'breed_group' => 'Terrier'),
            array('breed_name' => 'Mixed Breed', 'breed_group' => 'Mixed'),
            array('breed_name' => 'Other', 'breed_group' => 'Other')
        );
        
        foreach ($default_breeds as $breed) {
            $wpdb->insert($table_breeds, $breed);
        }
    }
    
    /**
     * Insert default terms and conditions
     */
    private static function insert_default_terms() {
        global $wpdb;
        $table_terms = $wpdb->prefix . 'dbas_terms_content';
        
        // Check if terms already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_terms");
        if ($count > 0) {
            return; // Terms already exist
        }
        
        $default_terms = array(
            array(
                'term_id' => 'liability',
                'term_title' => 'Liability Waiver',
                'term_content' => 'I understand that there are inherent risks in pet boarding and release the facility from liability for injury, illness, or escape of my pet.',
                'term_version' => '1.0',
                'required' => 1,
                'display_order' => 1
            ),
            array(
                'term_id' => 'vaccination',
                'term_title' => 'Vaccination Requirements',
                'term_content' => 'I confirm that my pet\'s vaccinations are current and will provide documentation. I understand my pet may be refused if vaccinations are not up to date.',
                'term_version' => '1.0',
                'required' => 1,
                'display_order' => 2
            ),
            array(
                'term_id' => 'behavior',
                'term_title' => 'Behavioral Policy',
                'term_content' => 'I agree to disclose any behavioral issues or aggression. The facility reserves the right to refuse service for aggressive animals.',
                'term_version' => '1.0',
                'required' => 1,
                'display_order' => 3
            ),
            array(
                'term_id' => 'payment',
                'term_title' => 'Payment Terms',
                'term_content' => 'I agree to pay all boarding fees at the time of pickup. Late pickup may incur additional charges.',
                'term_version' => '1.0',
                'required' => 1,
                'display_order' => 4
            ),
            array(
                'term_id' => 'pickup',
                'term_title' => 'Pickup and Drop-off Policy',
                'term_content' => 'I understand the facility hours and agree to drop off and pick up my pet during business hours. Special arrangements must be made in advance.',
                'term_version' => '1.0',
                'required' => 1,
                'display_order' => 5
            ),
            array(
                'term_id' => 'medical',
                'term_title' => 'Medical Treatment Authorization',
                'term_content' => 'In case of medical emergency, I authorize the facility to seek veterinary treatment for my pet. I agree to pay all associated costs.',
                'term_version' => '1.0',
                'required' => 0,
                'display_order' => 6
            ),
            array(
                'term_id' => 'abandonment',
                'term_title' => 'Abandonment Policy',
                'term_content' => 'Pets not picked up within 7 days after the scheduled pickup date without communication will be considered abandoned.',
                'term_version' => '1.0',
                'required' => 0,
                'display_order' => 7
            )
        );
        
        foreach ($default_terms as $term) {
            // Ensure term_id is never empty
            if (empty($term['term_id'])) {
                $term['term_id'] = 'term_' . uniqid();
            }
            
            // Check if term already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_terms WHERE term_id = %s",
                $term['term_id']
            ));
            
            if (!$existing) {
                $wpdb->insert($table_terms, $term);
            }
        }
    }
    
    /**
     * Add plugin capabilities
     */
    private static function add_capabilities() {
        // Get administrator role
        $role = get_role('administrator');
        
        if ($role) {
            // Main capability
            $role->add_cap('manage_dbas');
            
            // Specific capabilities
            $role->add_cap('manage_dbas_dogs');
            $role->add_cap('manage_dbas_users');
            $role->add_cap('manage_dbas_settings');
            $role->add_cap('export_dbas_data');
            $role->add_cap('view_dbas_reports');
            
            // Dog-specific capabilities
            $role->add_cap('approve_dogs');
            $role->add_cap('verify_vet_records');
            $role->add_cap('update_paperwork_status');
            
            // NEW CAPABILITIES FOR RESERVATION SYSTEM
            $role->add_cap('manage_dbas_reservations');
            $role->add_cap('manage_dbas_daycare');
            $role->add_cap('approve_reservations');
            $role->add_cap('manage_pricing');
        }
        
        // Create a custom role for dog boarding staff
        add_role(
            'dbas_staff',
            __('Dog Boarding Staff', 'dbas'),
            array(
                'read' => true,
                'manage_dbas' => true,
                'manage_dbas_dogs' => true,
                'manage_dbas_users' => true,
                'approve_dogs' => true,
                'verify_vet_records' => true,
                'update_paperwork_status' => true,
                'view_dbas_reports' => true,
                // NEW PERMISSIONS
                'manage_dbas_daycare' => true,
                'approve_reservations' => true
            )
        );
        
        // Create a custom role for dog boarding manager
        add_role(
            'dbas_manager',
            __('Dog Boarding Manager', 'dbas'),
            array(
                'read' => true,
                'manage_dbas' => true,
                'manage_dbas_dogs' => true,
                'manage_dbas_users' => true,
                'manage_dbas_settings' => true,
                'export_dbas_data' => true,
                'approve_dogs' => true,
                'verify_vet_records' => true,
                'update_paperwork_status' => true,
                'view_dbas_reports' => true,
                // NEW PERMISSIONS
                'manage_dbas_reservations' => true,
                'manage_dbas_daycare' => true,
                'approve_reservations' => true,
                'manage_pricing' => true
            )
        );
    }
    
    /**
     * Create default pages
     */
    private static function create_pages() {
        $pages = array(
            'portal' => array(
                'title' => 'Dog Portal',
                'content' => '[dbas_portal]',
                'option' => 'dbas_portal_page_id'
            ),
            'register' => array(
                'title' => 'Register',
                'content' => '[dbas_register]',
                'option' => 'dbas_register_page_id'
            ),
            'login' => array(
                'title' => 'Dog Owner Login',
                'content' => '[dbas_login]',
                'option' => 'dbas_login_page_id'
            ),
            'terms' => array(
                'title' => 'Terms and Conditions',
                'content' => '[dbas_terms]',
                'option' => 'dbas_terms_page_id'
            ),
            'profile' => array(
                'title' => 'My Profile',
                'content' => '[dbas_user_profile]',
                'option' => 'dbas_profile_page_id'
            ),
            // NEW PAGES FOR RESERVATION SYSTEM
            'reservations' => array(
                'title' => 'Make a Reservation',
                'content' => '[dbas_reservation_form]',
                'option' => 'dbas_reservation_page_id'
            ),
            'my_reservations' => array(
                'title' => 'My Reservations',
                'content' => '[dbas_my_reservations]',
                'option' => 'dbas_my_reservations_page_id'
            )
        );
        
        foreach ($pages as $page) {
            // Check if page already exists
            $page_id = get_option($page['option']);
            $page_obj = $page_id ? get_post($page_id) : null;
            
            if (!$page_obj || $page_obj->post_status != 'publish') {
                // Create the page
                $new_page = array(
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => 1,
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                );
                
                $new_page_id = wp_insert_post($new_page);
                
                if (!is_wp_error($new_page_id)) {
                    update_option($page['option'], $new_page_id);
                }
            }
        }
    }
    
    /**
     * Schedule events for automated tasks
     */
    private static function schedule_events() {
        // Schedule daily paperwork check
        if (!wp_next_scheduled('dbas_daily_paperwork_check')) {
            wp_schedule_event(time(), 'daily', 'dbas_daily_paperwork_check');
        }
        
        // Schedule weekly report generation
        if (!wp_next_scheduled('dbas_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'dbas_weekly_report');
        }
        
        // NEW SCHEDULED EVENTS FOR RESERVATION SYSTEM
        // Schedule daily reservation reminder emails
        if (!wp_next_scheduled('dbas_daily_reservation_reminders')) {
            wp_schedule_event(time(), 'daily', 'dbas_daily_reservation_reminders');
        }
        
        // Schedule daily daycare report
        if (!wp_next_scheduled('dbas_daily_daycare_report')) {
            wp_schedule_event(time(), 'daily', 'dbas_daily_daycare_report');
        }
    }
    
    /**
     * Unschedule events
     */
    private static function unschedule_events() {
        wp_clear_scheduled_hook('dbas_daily_paperwork_check');
        wp_clear_scheduled_hook('dbas_weekly_report');
        wp_clear_scheduled_hook('dbas_daily_reservation_reminders');
        wp_clear_scheduled_hook('dbas_daily_daycare_report');
    }
    
    /**
     * Run database updates if needed
     */
    public static function check_version() {
        $current_version = get_option('dbas_version', '0');
        
        if (version_compare($current_version, DBAS_VERSION, '<')) {
            self::activate();
        }
    }
    
    /**
     * Uninstall the plugin (complete cleanup)
     */
    public static function uninstall() {
        global $wpdb;
        
        // Only run if explicitly uninstalling
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Get option to check if data should be deleted
        $delete_data = get_option('dbas_delete_data_on_uninstall', false);
        
        if ($delete_data) {
            // Drop all existing tables
            $tables = array(
                'dbas_dogs',
                'dbas_emergency_contacts',
                'dbas_terms_acceptance',
                'dbas_breeds',
                'dbas_terms_content',
                'dbas_activity_log',
                // NEW TABLES
                'dbas_daycare_checkins',
                'dbas_reservations',
                'dbas_reservation_dogs',
                'dbas_pricing',
                'dbas_email_templates'
            );
            
            foreach ($tables as $table) {
                $table_name = $wpdb->prefix . $table;
                $wpdb->query("DROP TABLE IF EXISTS $table_name");
            }
            
            // Delete all options
            $options = array(
                'dbas_version',
                'dbas_db_version',
                'dbas_portal_page_id',
                'dbas_register_page_id',
                'dbas_login_page_id',
                'dbas_terms_page_id',
                'dbas_profile_page_id',
                'dbas_reservation_page_id',
                'dbas_my_reservations_page_id',
                'dbas_admin_email',
                'dbas_notify_user_registration',
                'dbas_notify_dog_registration',
                'dbas_notify_updates',
                'dbas_delete_data_on_uninstall'
            );
            
            foreach ($options as $option) {
                delete_option($option);
            }
            
            // Delete all user meta
            $wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE 'dbas_%'");
            
            // Remove custom roles
            remove_role('dbas_staff');
            remove_role('dbas_manager');
            
            // Remove capabilities from administrator
            $role = get_role('administrator');
            if ($role) {
                $role->remove_cap('manage_dbas');
                $role->remove_cap('manage_dbas_dogs');
                $role->remove_cap('manage_dbas_users');
                $role->remove_cap('manage_dbas_settings');
                $role->remove_cap('export_dbas_data');
                $role->remove_cap('view_dbas_reports');
                $role->remove_cap('approve_dogs');
                $role->remove_cap('verify_vet_records');
                $role->remove_cap('update_paperwork_status');
                $role->remove_cap('manage_dbas_reservations');
                $role->remove_cap('manage_dbas_daycare');
                $role->remove_cap('approve_reservations');
                $role->remove_cap('manage_pricing');
            }
        }
    }
}
?>