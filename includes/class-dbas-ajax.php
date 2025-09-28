<?php
/**
 * AJAX Handlers Class
 */

class DBAS_Ajax {
    
    public function __construct() {
        // Public AJAX handlers (for non-logged in users)
        add_action('wp_ajax_nopriv_dbas_check_username', array($this, 'check_username_availability'));
        add_action('wp_ajax_nopriv_dbas_check_email', array($this, 'check_email_availability'));
        
        // Logged-in user AJAX handlers
        add_action('wp_ajax_dbas_check_username', array($this, 'check_username_availability'));
        add_action('wp_ajax_dbas_check_email', array($this, 'check_email_availability'));
        add_action('wp_ajax_dbas_upload_file', array($this, 'handle_ajax_file_upload'));
        add_action('wp_ajax_dbas_delete_file', array($this, 'handle_ajax_file_delete'));
        add_action('wp_ajax_dbas_get_dog_details', array($this, 'get_dog_details'));
        add_action('wp_ajax_dbas_quick_update_dog', array($this, 'quick_update_dog'));
        add_action('wp_ajax_dbas_validate_form', array($this, 'validate_form_field'));
        add_action('wp_ajax_dbas_search_breeds', array($this, 'search_breeds'));
        add_action('wp_ajax_dbas_get_user_dogs', array($this, 'get_user_dogs'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_dbas_admin_approve_dog', array($this, 'admin_approve_dog'));
        add_action('wp_ajax_dbas_admin_verify_vet', array($this, 'admin_verify_vet_records'));
        add_action('wp_ajax_dbas_admin_update_paperwork', array($this, 'admin_update_paperwork_status'));
        add_action('wp_ajax_dbas_admin_toggle_user_status', array($this, 'admin_toggle_user_status'));
        add_action('wp_ajax_dbas_admin_export_data', array($this, 'admin_export_data'));
    }
    
    /**
     * Check username availability
     */
    public function check_username_availability() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        
        if (empty($username)) {
            wp_send_json_error(array('message' => __('Username cannot be empty', 'dbas')));
        }
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => __('Username already taken', 'dbas')));
        }
        
        wp_send_json_success(array('message' => __('Username available', 'dbas')));
    }
    
    /**
     * Check email availability
     */
    public function check_email_availability() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'dbas')));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('Email already registered', 'dbas')));
        }
        
        wp_send_json_success(array('message' => __('Email available', 'dbas')));
    }
    
    /**
     * Handle AJAX file upload
     */
    public function handle_ajax_file_upload() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to upload files', 'dbas')));
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file provided', 'dbas')));
        }
        
        $file = $_FILES['file'];
        $file_type = isset($_POST['file_type']) ? sanitize_text_field($_POST['file_type']) : 'general';
        
        // Validate file type
        $allowed_types = array();
        if ($file_type == 'photo') {
            $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        } elseif (in_array($file_type, array('vaccination', 'hipaa', 'document'))) {
            $allowed_types = array('application/pdf');
        } else {
            $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => __('Invalid file type', 'dbas')));
        }
        
        // Upload file
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = array(
                'post_mime_type' => $movefile['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $movefile['file']);
            
            // Generate metadata
            if (wp_attachment_is_image($attach_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
            }
            
            wp_send_json_success(array(
                'attachment_id' => $attach_id,
                'url' => $movefile['url'],
                'message' => __('File uploaded successfully', 'dbas')
            ));
        } else {
            wp_send_json_error(array('message' => $movefile['error']));
        }
    }
    
    /**
     * Handle AJAX file deletion
     */
    public function handle_ajax_file_delete() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'dbas')));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        
        // Verify ownership or admin privileges
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            wp_send_json_error(array('message' => __('File not found', 'dbas')));
        }
        
        $current_user_id = get_current_user_id();
        if ($attachment->post_author != $current_user_id && !current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('You do not have permission to delete this file', 'dbas')));
        }
        
        if (wp_delete_attachment($attachment_id, true)) {
            wp_send_json_success(array('message' => __('File deleted successfully', 'dbas')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete file', 'dbas')));
        }
    }
    
    /**
     * Get dog details via AJAX
     */
    public function get_dog_details() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'dbas')));
        }
        
        $dog_id = intval($_POST['dog_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        $dog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $dog_id));
        
        if (!$dog) {
            wp_send_json_error(array('message' => __('Dog not found', 'dbas')));
        }
        
        // Check ownership or admin
        $current_user_id = get_current_user_id();
        if ($dog->owner_id != $current_user_id && !current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('You do not have permission to view this dog', 'dbas')));
        }
        
        // Get owner info
        $owner = get_userdata($dog->owner_id);
        $dog->owner_name = $owner ? $owner->display_name : __('Unknown', 'dbas');
        
        wp_send_json_success($dog);
    }
    
    /**
     * Quick update dog field
     */
    public function quick_update_dog() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'dbas')));
        }
        
        $dog_id = intval($_POST['dog_id']);
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);
        
        // Validate field
        $allowed_fields = array('name', 'breed', 'color', 'vet_details', 'allergies', 'behavioral_notes');
        if (!current_user_can('manage_dbas')) {
            // Users can only update certain fields
            $allowed_fields = array('vet_details', 'allergies', 'behavioral_notes');
        }
        
        if (!in_array($field, $allowed_fields)) {
            wp_send_json_error(array('message' => __('Invalid field', 'dbas')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        
        // Check ownership
        $dog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $dog_id));
        if (!$dog) {
            wp_send_json_error(array('message' => __('Dog not found', 'dbas')));
        }
        
        $current_user_id = get_current_user_id();
        if ($dog->owner_id != $current_user_id && !current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this dog', 'dbas')));
        }
        
        // Update field
        $result = $wpdb->update(
            $table,
            array($field => $value),
            array('id' => $dog_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Updated successfully', 'dbas')));
        } else {
            wp_send_json_error(array('message' => __('Update failed', 'dbas')));
        }
    }
    
    /**
     * Validate form field
     */
    public function validate_form_field() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        $field_name = sanitize_text_field($_POST['field_name']);
        $field_value = sanitize_text_field($_POST['field_value']);
        
        $is_valid = true;
        $message = '';
        
        switch ($field_name) {
            case 'email':
                if (!is_email($field_value)) {
                    $is_valid = false;
                    $message = __('Invalid email address', 'dbas');
                }
                break;
                
            case 'phone':
                // Basic phone validation
                if (!preg_match('/^[\d\s\-\+\(\)]+$/', $field_value)) {
                    $is_valid = false;
                    $message = __('Invalid phone number', 'dbas');
                }
                break;
                
            case 'zip':
                // US zip code validation
                if (!preg_match('/^\d{5}(-\d{4})?$/', $field_value)) {
                    $is_valid = false;
                    $message = __('Invalid zip code', 'dbas');
                }
                break;
                
            case 'birthdate':
                // Date validation
                $date = DateTime::createFromFormat('Y-m-d', $field_value);
                if (!$date || $date->format('Y-m-d') !== $field_value) {
                    $is_valid = false;
                    $message = __('Invalid date format', 'dbas');
                }
                break;
        }
        
        if ($is_valid) {
            wp_send_json_success(array('message' => __('Valid', 'dbas')));
        } else {
            wp_send_json_error(array('message' => $message));
        }
    }
    
    /**
     * Search breeds
     */
    public function search_breeds() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_breeds';
        
        $breeds = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE breed_name LIKE %s ORDER BY breed_name LIMIT 10",
            '%' . $wpdb->esc_like($search) . '%'
        ));
        
        $results = array();
        foreach ($breeds as $breed) {
            $results[] = array(
                'id' => $breed->id,
                'text' => $breed->breed_name
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Get user's dogs
     */
    public function get_user_dogs() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'dbas')));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        
        // Check permissions
        if ($user_id != get_current_user_id() && !current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        
        $dogs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE owner_id = %d ORDER BY name",
            $user_id
        ));
        
        wp_send_json_success($dogs);
    }
    
    /**
     * Admin: Approve dog
     */
    public function admin_approve_dog() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $dog_id = intval($_POST['dog_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        
        // Get current status
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE id = %d",
            $dog_id
        ));
        
        $result = $wpdb->update(
            $table,
            array('status' => 'active'),
            array('id' => $dog_id)
        );
        
        if ($result !== false) {
            // Send notification
            $dog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $dog_id));
            if ($dog && class_exists('DBAS_Emails')) {
                $emails = new DBAS_Emails();
                $emails->send_status_change_notification($dog_id, $current_status, 'active');
            }
            
            wp_send_json_success(array('message' => __('Dog approved successfully', 'dbas')));
        } else {
            wp_send_json_error(array('message' => __('Failed to approve dog', 'dbas')));
        }
    }
    
    /**
     * Admin: Verify vet records
     */
    public function admin_verify_vet_records() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $dog_id = intval($_POST['dog_id']);
        $verified = $_POST['verified'] === 'true' ? 1 : 0;
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        
        $result = $wpdb->update(
            $table,
            array('vet_verified' => $verified),
            array('id' => $dog_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Vet records verification updated', 'dbas')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update verification', 'dbas')));
        }
    }
    
    /**
     * Admin: Update paperwork status
     */
    public function admin_update_paperwork_status() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $dog_id = intval($_POST['dog_id']);
        $status = sanitize_text_field($_POST['status']);
        
        if (!in_array($status, array('good_standing', 'not_good_standing'))) {
            wp_send_json_error(array('message' => __('Invalid status', 'dbas')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        
        $result = $wpdb->update(
            $table,
            array('yearly_paperwork' => $status),
            array('id' => $dog_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Paperwork status updated', 'dbas')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update status', 'dbas')));
        }
    }
    
    /**
     * Admin: Toggle user allowed status
     */
    public function admin_toggle_user_status() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $user_id = intval($_POST['user_id']);
        $current_status = get_user_meta($user_id, 'dbas_not_allowed', true);
        $new_status = $current_status ? '0' : '1';
        
        update_user_meta($user_id, 'dbas_not_allowed', $new_status);
        
        wp_send_json_success(array(
            'message' => __('User status updated', 'dbas'),
            'new_status' => $new_status
        ));
    }
    
    /**
     * Admin: Export data
     */
    public function admin_export_data() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $export_type = sanitize_text_field($_POST['export_type']);
        
        global $wpdb;
        
        $data = array();
        $filename = '';
        
        switch ($export_type) {
            case 'dogs':
                $table = $wpdb->prefix . 'dbas_dogs';
                $data = $wpdb->get_results("
                    SELECT d.*, u.display_name as owner_name, u.user_email as owner_email 
                    FROM $table d
                    LEFT JOIN {$wpdb->users} u ON d.owner_id = u.ID
                ", ARRAY_A);
                $filename = 'dogs_export_' . date('Y-m-d') . '.csv';
                break;
                
            case 'users':
                $users = get_users(array(
                    'meta_key' => 'dbas_phone',
                    'meta_compare' => 'EXISTS'
                ));
                
                foreach ($users as $user) {
                    $data[] = array(
                        'ID' => $user->ID,
                        'username' => $user->user_login,
                        'email' => $user->user_email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'phone' => get_user_meta($user->ID, 'dbas_phone', true),
                        'street' => get_user_meta($user->ID, 'dbas_street', true),
                        'city' => get_user_meta($user->ID, 'dbas_city', true),
                        'state' => get_user_meta($user->ID, 'dbas_state', true),
                        'zip' => get_user_meta($user->ID, 'dbas_zip', true),
                        'not_allowed' => get_user_meta($user->ID, 'dbas_not_allowed', true) ? 'Yes' : 'No'
                    );
                }
                $filename = 'users_export_' . date('Y-m-d') . '.csv';
                break;
                
            default:
                wp_send_json_error(array('message' => __('Invalid export type', 'dbas')));
        }
        
        // Convert to CSV
        $csv = $this->array_to_csv($data);
        
        wp_send_json_success(array(
            'csv' => $csv,
            'filename' => $filename
        ));
    }
    
    /**
     * Convert array to CSV
     */
    private function array_to_csv($array) {
        if (empty($array)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Add headers
        fputcsv($output, array_keys(reset($array)));
        
        // Add data
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
?>