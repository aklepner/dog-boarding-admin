<?php
/**
 * Database operations handler
 *
 * This class handles all database operations for the plugin
 */

class DBAS_Database {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Database tables
     */
    private $tables = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        
        // Define table names
        $this->tables = array(
            'dogs' => $wpdb->prefix . 'dbas_dogs',
            'emergency_contacts' => $wpdb->prefix . 'dbas_emergency_contacts',
            'terms_acceptance' => $wpdb->prefix . 'dbas_terms_acceptance',
            'breeds' => $wpdb->prefix . 'dbas_breeds',
            'terms_content' => $wpdb->prefix . 'dbas_terms_content',
            'activity_log' => $wpdb->prefix . 'dbas_activity_log'
        );
    }
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get table name
     */
    public function get_table($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : false;
    }
    
    /**
     * Get all dogs with optional filters
     */
    public function get_dogs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'owner_id' => 0,
            'status' => '',
            'yearly_paperwork' => '',
            'vet_verified' => null,
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0,
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array('1=1');
        
        if ($args['owner_id'] > 0) {
            $where_clauses[] = $wpdb->prepare("owner_id = %d", $args['owner_id']);
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($args['yearly_paperwork'])) {
            $where_clauses[] = $wpdb->prepare("yearly_paperwork = %s", $args['yearly_paperwork']);
        }
        
        if ($args['vet_verified'] !== null) {
            $where_clauses[] = $wpdb->prepare("vet_verified = %d", $args['vet_verified']);
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = $wpdb->prepare("(name LIKE %s OR breed LIKE %s)", $search, $search);
        }
        
        $where = implode(' AND ', $where_clauses);
        $orderby = $this->sanitize_orderby($args['orderby']);
        $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
        
        $query = "SELECT * FROM {$this->tables['dogs']} WHERE $where ORDER BY $orderby $order";
        
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get single dog by ID
     */
    public function get_dog($dog_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['dogs']} WHERE id = %d",
            $dog_id
        ));
    }
    
    /**
     * Insert dog
     */
    public function insert_dog($data) {
        global $wpdb;
        
        $defaults = array(
            'owner_id' => 0,
            'name' => '',
            'breed' => '',
            'birthdate' => null,
            'sex' => '',
            'color' => '',
            'spay_neuter' => '',
            'vet_details' => '',
            'vaccination_records' => '',
            'allergies' => '',
            'behavioral_notes' => '',
            'status' => 'inactive',
            'hipaa_forms' => '',
            'vet_verified' => 0,
            'pet_photos' => '',
            'yearly_paperwork' => 'not_good_standing'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $data = $this->sanitize_dog_data($data);
        
        $result = $wpdb->insert($this->tables['dogs'], $data);
        
        if ($result) {
            $dog_id = $wpdb->insert_id;
            $this->log_activity('dog_added', 'dog', $dog_id, $data['owner_id']);
            return $dog_id;
        }
        
        return false;
    }
    
    /**
     * Update dog
     */
    public function update_dog($dog_id, $data) {
        global $wpdb;
        
        // Get current dog for comparison
        $current_dog = $this->get_dog($dog_id);
        if (!$current_dog) {
            return false;
        }
        
        // Sanitize data
        $data = $this->sanitize_dog_data($data);
        
        $result = $wpdb->update(
            $this->tables['dogs'],
            $data,
            array('id' => $dog_id)
        );
        
        if ($result !== false) {
            $this->log_activity('dog_updated', 'dog', $dog_id, $current_dog->owner_id);
            
            // Check for status change
            if (isset($data['status']) && $data['status'] != $current_dog->status) {
                $this->log_activity('dog_status_changed', 'dog', $dog_id, $current_dog->owner_id, array(
                    'old_status' => $current_dog->status,
                    'new_status' => $data['status']
                ));
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete dog
     */
    public function delete_dog($dog_id) {
        global $wpdb;
        
        $dog = $this->get_dog($dog_id);
        if (!$dog) {
            return false;
        }
        
        $result = $wpdb->delete(
            $this->tables['dogs'],
            array('id' => $dog_id)
        );
        
        if ($result) {
            $this->log_activity('dog_deleted', 'dog', $dog_id, $dog->owner_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user's emergency contacts
     */
    public function get_emergency_contacts($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tables['emergency_contacts']} WHERE user_id = %d ORDER BY id",
            $user_id
        ));
    }
    
    /**
     * Save emergency contacts
     */
    public function save_emergency_contacts($user_id, $contacts) {
        global $wpdb;
        
        // Delete existing contacts
        $wpdb->delete($this->tables['emergency_contacts'], array('user_id' => $user_id));
        
        // Insert new contacts
        foreach ($contacts as $contact) {
            if (!empty($contact['name']) && !empty($contact['phone'])) {
                $wpdb->insert($this->tables['emergency_contacts'], array(
                    'user_id' => $user_id,
                    'name' => sanitize_text_field($contact['name']),
                    'phone' => sanitize_text_field($contact['phone']),
                    'relationship' => sanitize_text_field($contact['relationship'] ?? ''),
                    'is_alternative_pickup' => isset($contact['is_alternative_pickup']) ? 1 : 0
                ));
            }
        }
        
        $this->log_activity('emergency_contacts_updated', 'user', $user_id, $user_id);
        return true;
    }
    
    /**
     * Get breeds
     */
    public function get_breeds($active_only = true) {
        global $wpdb;
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->tables['breeds']} $where ORDER BY breed_name"
        );
    }
    
    /**
     * Add breed
     */
    public function add_breed($breed_name, $breed_group = '') {
        global $wpdb;
        
        return $wpdb->insert($this->tables['breeds'], array(
            'breed_name' => sanitize_text_field($breed_name),
            'breed_group' => sanitize_text_field($breed_group)
        ));
    }
    
    /**
     * Get terms and conditions
     */
    public function get_terms($active_only = true) {
        global $wpdb;
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->tables['terms_content']} $where ORDER BY display_order"
        );
    }
    
    /**
     * Get user's term acceptances
     */
    public function get_user_term_acceptances($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->tables['terms_acceptance']} WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Save term acceptance
     */
    public function save_term_acceptance($user_id, $term_id, $accepted = true) {
        global $wpdb;
        
        $data = array(
            'user_id' => $user_id,
            'term_id' => $term_id,
            'accepted' => $accepted ? 1 : 0,
            'accepted_date' => current_time('mysql'),
            'ip_address' => $this->get_user_ip()
        );
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['terms_acceptance']} WHERE user_id = %d AND term_id = %s",
            $user_id, $term_id
        ));
        
        if ($existing) {
            return $wpdb->update(
                $this->tables['terms_acceptance'],
                $data,
                array('id' => $existing->id)
            );
        } else {
            return $wpdb->insert($this->tables['terms_acceptance'], $data);
        }
    }
    
    /**
     * Log activity
     */
    public function log_activity($action, $object_type = null, $object_id = null, $user_id = null, $details = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $data = array(
            'user_id' => $user_id,
            'action' => $action,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'details' => is_array($details) ? json_encode($details) : $details,
            'ip_address' => $this->get_user_ip()
        );
        
        return $wpdb->insert($this->tables['activity_log'], $data);
    }
    
    /**
     * Get activity log
     */
    public function get_activity_log($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => 0,
            'action' => '',
            'object_type' => '',
            'object_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'limit' => 100,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array('1=1');
        
        if ($args['user_id'] > 0) {
            $where_clauses[] = $wpdb->prepare("user_id = %d", $args['user_id']);
        }
        
        if (!empty($args['action'])) {
            $where_clauses[] = $wpdb->prepare("action = %s", $args['action']);
        }
        
        if (!empty($args['object_type'])) {
            $where_clauses[] = $wpdb->prepare("object_type = %s", $args['object_type']);
        }
        
        if ($args['object_id'] > 0) {
            $where_clauses[] = $wpdb->prepare("object_id = %d", $args['object_id']);
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = $wpdb->prepare("created_at >= %s", $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = $wpdb->prepare("created_at <= %s", $args['date_to']);
        }
        
        $where = implode(' AND ', $where_clauses);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->tables['activity_log']} WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $args['limit'], $args['offset']
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total dogs
        $stats['total_dogs'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['dogs']}");
        
        // Active dogs
        $stats['active_dogs'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['dogs']} WHERE status = 'active'");
        
        // Pending dogs
        $stats['pending_dogs'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['dogs']} WHERE status = 'inactive'");
        
        // Dogs with good standing paperwork
        $stats['good_standing'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['dogs']} WHERE yearly_paperwork = 'good_standing'");
        
        // Total owners
        $stats['total_owners'] = $wpdb->get_var("SELECT COUNT(DISTINCT owner_id) FROM {$this->tables['dogs']}");
        
        // Dogs added this month
        $stats['dogs_this_month'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['dogs']} WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        );
        
        // Most popular breeds
        $stats['popular_breeds'] = $wpdb->get_results(
            "SELECT breed, COUNT(*) as count FROM {$this->tables['dogs']} GROUP BY breed ORDER BY count DESC LIMIT 5"
        );
        
        return $stats;
    }
    
    /**
     * Sanitize dog data
     */
    private function sanitize_dog_data($data) {
        $sanitized = array();
        
        $text_fields = array('name', 'breed', 'color', 'sex', 'spay_neuter', 'status', 'yearly_paperwork');
        $textarea_fields = array('vet_details', 'allergies', 'behavioral_notes');
        $url_fields = array('vaccination_records', 'hipaa_forms', 'pet_photos');
        $int_fields = array('owner_id', 'vet_verified');
        $date_fields = array('birthdate');
        
        foreach ($data as $key => $value) {
            if (in_array($key, $text_fields)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (in_array($key, $textarea_fields)) {
                $sanitized[$key] = sanitize_textarea_field($value);
            } elseif (in_array($key, $url_fields)) {
                $sanitized[$key] = esc_url_raw($value);
            } elseif (in_array($key, $int_fields)) {
                $sanitized[$key] = intval($value);
            } elseif (in_array($key, $date_fields)) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize orderby
     */
    private function sanitize_orderby($orderby) {
        $allowed = array('id', 'name', 'breed', 'status', 'created_at', 'updated_at', 'owner_id');
        return in_array($orderby, $allowed) ? $orderby : 'name';
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = explode(',', $_SERVER[$key]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Export data to CSV
     */
    public function export_to_csv($type = 'dogs') {
        global $wpdb;
        
        $data = array();
        $filename = '';
        
        switch ($type) {
            case 'dogs':
                $data = $wpdb->get_results(
                    "SELECT d.*, u.display_name as owner_name, u.user_email as owner_email 
                     FROM {$this->tables['dogs']} d
                     LEFT JOIN {$wpdb->users} u ON d.owner_id = u.ID",
                    ARRAY_A
                );
                $filename = 'dogs_export_' . date('Y-m-d') . '.csv';
                break;
                
            case 'users':
                $data = $wpdb->get_results(
                    "SELECT u.ID, u.user_login, u.user_email, u.display_name,
                            um1.meta_value as phone,
                            um2.meta_value as street,
                            um3.meta_value as city,
                            um4.meta_value as state,
                            um5.meta_value as zip
                     FROM {$wpdb->users} u
                     LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'dbas_phone'
                     LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'dbas_street'
                     LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = 'dbas_city'
                     LEFT JOIN {$wpdb->usermeta} um4 ON u.ID = um4.user_id AND um4.meta_key = 'dbas_state'
                     LEFT JOIN {$wpdb->usermeta} um5 ON u.ID = um5.user_id AND um5.meta_key = 'dbas_zip'
                     WHERE um1.meta_value IS NOT NULL",
                    ARRAY_A
                );
                $filename = 'users_export_' . date('Y-m-d') . '.csv';
                break;
                
            case 'activity':
                $data = $this->get_activity_log(array('limit' => 10000));
                $data = array_map(function($item) {
                    return (array) $item;
                }, $data);
                $filename = 'activity_log_' . date('Y-m-d') . '.csv';
                break;
        }
        
        if (empty($data)) {
            return false;
        }
        
        // Create CSV
        $output = fopen('php://temp', 'r+');
        
        // Add headers
        fputcsv($output, array_keys(reset($data)));
        
        // Add data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return array(
            'filename' => $filename,
            'content' => $csv
        );
    }
}
?>