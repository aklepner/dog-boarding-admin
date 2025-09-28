<?php
/**
 * Dog Management Class
 */

class DBAS_Dog_Manager {
    
    private $table_name;
    private $breeds_table;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dbas_dogs';
        $this->breeds_table = $wpdb->prefix . 'dbas_breeds';
        
        // Add shortcodes
        add_shortcode('dbas_add_dog_form', array($this, 'render_add_dog_form'));
        add_shortcode('dbas_manage_dogs', array($this, 'render_manage_dogs'));
        
        // Add AJAX handlers
        add_action('wp_ajax_dbas_add_dog', array($this, 'ajax_add_dog'));
        add_action('wp_ajax_dbas_update_dog', array($this, 'ajax_update_dog'));
        add_action('wp_ajax_dbas_delete_dog', array($this, 'ajax_delete_dog'));
        add_action('wp_ajax_dbas_get_dog', array($this, 'ajax_get_dog'));
    }
    
    public function add_dog($data) {
        global $wpdb;
        
        $dog_data = array(
            'owner_id' => intval($data['owner_id']),
            'name' => sanitize_text_field($data['name']),
            'breed' => sanitize_text_field($data['breed']),
            'birthdate' => sanitize_text_field($data['birthdate']),
            'sex' => sanitize_text_field($data['sex']),
            'color' => sanitize_text_field($data['color']),
            'spay_neuter' => sanitize_text_field($data['spay_neuter']),
            'vet_details' => sanitize_textarea_field($data['vet_details']),
            'allergies' => sanitize_textarea_field($data['allergies']),
            'behavioral_notes' => sanitize_textarea_field($data['behavioral_notes']),
            'status' => 'inactive'
        );
        
        // Handle file uploads
        if (!empty($_FILES['vaccination_records']['name'])) {
            $dog_data['vaccination_records'] = $this->handle_file_upload($_FILES['vaccination_records'], 'vaccination');
        }
        
        if (!empty($_FILES['hipaa_forms']['name'])) {
            $dog_data['hipaa_forms'] = $this->handle_file_upload($_FILES['hipaa_forms'], 'hipaa');
        }
        
        if (!empty($_FILES['pet_photos']['name'])) {
            $dog_data['pet_photos'] = $this->handle_file_upload($_FILES['pet_photos'], 'photo');
        }
        
        $result = $wpdb->insert($this->table_name, $dog_data);
        
        if ($result) {
            do_action('dbas_dog_added', $wpdb->insert_id, $data['owner_id']);
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public function update_dog($dog_id, $data) {
        global $wpdb;
        
        $dog_data = array(
            'name' => sanitize_text_field($data['name']),
            'breed' => sanitize_text_field($data['breed']),
            'birthdate' => sanitize_text_field($data['birthdate']),
            'sex' => sanitize_text_field($data['sex']),
            'color' => sanitize_text_field($data['color']),
            'spay_neuter' => sanitize_text_field($data['spay_neuter']),
            'vet_details' => sanitize_textarea_field($data['vet_details']),
            'allergies' => sanitize_textarea_field($data['allergies']),
            'behavioral_notes' => sanitize_textarea_field($data['behavioral_notes'])
        );
        
        // Admin can update status
        if (current_user_can('manage_dbas')) {
            $dog_data['status'] = sanitize_text_field($data['status']);
            $dog_data['yearly_paperwork'] = sanitize_text_field($data['yearly_paperwork']);
            $dog_data['vet_verified'] = isset($data['vet_verified']) ? 1 : 0;
        }
        
        // Handle file uploads if new files are provided
        if (!empty($_FILES['vaccination_records']['name'])) {
            $dog_data['vaccination_records'] = $this->handle_file_upload($_FILES['vaccination_records'], 'vaccination');
        }
        
        if (!empty($_FILES['hipaa_forms']['name'])) {
            $dog_data['hipaa_forms'] = $this->handle_file_upload($_FILES['hipaa_forms'], 'hipaa');
        }
        
        if (!empty($_FILES['pet_photos']['name'])) {
            $dog_data['pet_photos'] = $this->handle_file_upload($_FILES['pet_photos'], 'photo');
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $dog_data,
            array('id' => $dog_id)
        );
        
        if ($result !== false) {
            do_action('dbas_dog_updated', $dog_id, $data['owner_id']);
            return true;
        }
        
        return false;
    }
    
    public function delete_dog($dog_id, $owner_id) {
        global $wpdb;
        
        // Verify ownership
        $dog = $this->get_dog($dog_id);
        if ($dog && $dog->owner_id == $owner_id) {
            $result = $wpdb->delete(
                $this->table_name,
                array('id' => $dog_id)
            );
            
            if ($result) {
                do_action('dbas_dog_deleted', $dog_id, $owner_id);
                return true;
            }
        }
        
        return false;
    }
    
    public function get_dog($dog_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $dog_id
        ));
    }
    
    public function get_user_dogs($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE owner_id = %d ORDER BY name",
            $user_id
        ));
    }
    
    public function get_all_dogs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'owner_id' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if ($args['owner_id'] > 0) {
            $where[] = $wpdb->prepare("owner_id = %d", $args['owner_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf("ORDER BY %s %s", $args['orderby'], $args['order']);
        $limit_clause = $args['limit'] > 0 ? $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']) : '';
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} {$order_clause} {$limit_clause}";
        
        return $wpdb->get_results($query);
    }
    
    public function get_breeds() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->breeds_table} ORDER BY breed_name");
    }
    
    public function add_breed($breed_name) {
        global $wpdb;
        return $wpdb->insert(
            $this->breeds_table,
            array('breed_name' => sanitize_text_field($breed_name))
        );
    }
    
    public function delete_breed($breed_id) {
        global $wpdb;
        return $wpdb->delete(
            $this->breeds_table,
            array('id' => $breed_id)
        );
    }
    
    private function handle_file_upload($file, $type) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload_overrides = array('test_form' => false);
        
        // Check file type for PDFs
        if ($type !== 'photo' && $file['type'] !== 'application/pdf') {
            return '';
        }
        
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            return $movefile['url'];
        }
        
        return '';
    }
    
    public function render_add_dog_form() {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to add a dog.', 'dbas') . '</p>';
        }
        
        ob_start();
        ?>
        <form id="dbas-add-dog-form" class="dbas-form" enctype="multipart/form-data">
            <?php wp_nonce_field('dbas_add_dog', 'dbas_nonce'); ?>
            <input type="hidden" name="action" value="dbas_add_dog" />
            <input type="hidden" name="owner_id" value="<?php echo get_current_user_id(); ?>" />
            
            <div class="dbas-form-group">
                <label for="dog_name"><?php _e('Dog Name', 'dbas'); ?> *</label>
                <input type="text" id="dog_name" name="name" required />
            </div>
            
            <div class="dbas-form-group">
                <label for="dog_breed"><?php _e('Breed', 'dbas'); ?></label>
                <select id="dog_breed" name="breed">
                    <option value=""><?php _e('Select Breed', 'dbas'); ?></option>
                    <?php foreach ($this->get_breeds() as $breed): ?>
                        <option value="<?php echo esc_attr($breed->breed_name); ?>">
                            <?php echo esc_html($breed->breed_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="dbas-form-group">
                <label for="dog_birthdate"><?php _e('Birthdate', 'dbas'); ?></label>
                <input type="date" id="dog_birthdate" name="birthdate" />
            </div>
            
            <div class="dbas-form-group">
                <label><?php _e('Sex', 'dbas'); ?></label>
                <div class="dbas-radio-group">
                    <label>
                        <input type="radio" name="sex" value="male" />
                        <?php _e('Male', 'dbas'); ?>
                    </label>
                    <label>
                        <input type="radio" name="sex" value="female" />
                        <?php _e('Female', 'dbas'); ?>
                    </label>
                </div>
            </div>
            
            <div class="dbas-form-group">
                <label for="dog_color"><?php _e('Color', 'dbas'); ?></label>
                <input type="text" id="dog_color" name="color" />
            </div>
            
            <div class="dbas-form-group">
                <label><?php _e('Spayed/Neutered', 'dbas'); ?></label>
                <div class="dbas-radio-group">
                    <label>
                        <input type="radio" name="spay_neuter" value="yes" />
                        <?php _e('Yes', 'dbas'); ?>
                    </label>
                    <label>
                        <input type="radio" name="spay_neuter" value="no" />
                        <?php _e('No', 'dbas'); ?>
                    </label>
                </div>
            </div>
            
            <div class="dbas-form-group">
                <label for="vet_details"><?php _e('Veterinarian Details', 'dbas'); ?></label>
                <textarea id="vet_details" name="vet_details" rows="3"></textarea>
            </div>
            
            <div class="dbas-form-group">
                <label for="vaccination_records"><?php _e('Vaccination Records (PDF)', 'dbas'); ?></label>
                <input type="file" id="vaccination_records" name="vaccination_records" accept="application/pdf" />
            </div>
            
            <div class="dbas-form-group">
                <label for="allergies"><?php _e('Allergies', 'dbas'); ?></label>
                <textarea id="allergies" name="allergies" rows="3"></textarea>
            </div>
            
            <div class="dbas-form-group">
                <label for="behavioral_notes"><?php _e('Behavioral Notes & Restrictions', 'dbas'); ?></label>
                <textarea id="behavioral_notes" name="behavioral_notes" rows="3"></textarea>
            </div>
            
            <div class="dbas-form-group">
                <label for="hipaa_forms"><?php _e('HIPAA Forms (PDF)', 'dbas'); ?></label>
                <input type="file" id="hipaa_forms" name="hipaa_forms" accept="application/pdf" />
            </div>
            
            <div class="dbas-form-group">
                <label for="pet_photos"><?php _e('Pet Photos', 'dbas'); ?></label>
                <input type="file" id="pet_photos" name="pet_photos" accept="image/*" />
            </div>
            
            <div class="dbas-form-submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Add Dog', 'dbas'); ?>
                </button>
            </div>
        </form>
        
        <div id="dbas-message"></div>
        <?php
        return ob_get_clean();
    }
    
    public function render_manage_dogs() {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to manage your dogs.', 'dbas') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $dogs = $this->get_user_dogs($user_id);
        
        ob_start();
        ?>
        <div class="dbas-dogs-list">
            <h3><?php _e('Your Dogs', 'dbas'); ?></h3>
            
            <?php if (empty($dogs)): ?>
                <p><?php _e('You have not registered any dogs yet.', 'dbas'); ?></p>
            <?php else: ?>
                <table class="dbas-dogs-table">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'dbas'); ?></th>
                            <th><?php _e('Breed', 'dbas'); ?></th>
                            <th><?php _e('Status', 'dbas'); ?></th>
                            <th><?php _e('Actions', 'dbas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dogs as $dog): ?>
                            <tr>
                                <td><?php echo esc_html($dog->name); ?></td>
                                <td><?php echo esc_html($dog->breed); ?></td>
                                <td>
                                    <span class="dbas-status-<?php echo esc_attr($dog->status); ?>">
                                        <?php echo ucfirst($dog->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="button dbas-edit-dog" data-dog-id="<?php echo $dog->id; ?>">
                                        <?php _e('Edit', 'dbas'); ?>
                                    </button>
                                    <button class="button dbas-delete-dog" data-dog-id="<?php echo $dog->id; ?>">
                                        <?php _e('Delete', 'dbas'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="dbas-add-dog-button">
                <a href="#" class="button button-primary" id="dbas-show-add-dog-form">
                    <?php _e('Add New Dog', 'dbas'); ?>
                </a>
            </div>
        </div>
        
        <!-- Edit Dog Modal -->
        <div id="dbas-edit-dog-modal" style="display:none;">
            <div class="dbas-modal-content">
                <span class="dbas-close">&times;</span>
                <h3><?php _e('Edit Dog', 'dbas'); ?></h3>
                <form id="dbas-edit-dog-form" enctype="multipart/form-data">
                    <!-- Form fields will be populated via JavaScript -->
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // AJAX Handlers
    public function ajax_add_dog() {
        check_ajax_referer('dbas_add_dog', 'dbas_nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to add a dog.', 'dbas'));
        }
        
        $dog_id = $this->add_dog($_POST);
        
        if ($dog_id) {
            wp_send_json_success(array(
                'message' => __('Dog added successfully!', 'dbas'),
                'dog_id' => $dog_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to add dog. Please try again.', 'dbas')
            ));
        }
    }
    
    public function ajax_update_dog() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to update a dog.', 'dbas'));
        }
        
        $dog_id = intval($_POST['dog_id']);
        $success = $this->update_dog($dog_id, $_POST);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Dog updated successfully!', 'dbas')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to update dog. Please try again.', 'dbas')
            ));
        }
    }
    
    public function ajax_delete_dog() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to delete a dog.', 'dbas'));
        }
        
        $dog_id = intval($_POST['dog_id']);
        $owner_id = get_current_user_id();
        $success = $this->delete_dog($dog_id, $owner_id);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Dog deleted successfully!', 'dbas')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete dog. Please try again.', 'dbas')
            ));
        }
    }
    
    public function ajax_get_dog() {
        check_ajax_referer('dbas_ajax_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in.', 'dbas'));
        }
        
        $dog_id = intval($_POST['dog_id']);
        $dog = $this->get_dog($dog_id);
        
        if ($dog) {
            wp_send_json_success($dog);
        } else {
            wp_send_json_error(array(
                'message' => __('Dog not found.', 'dbas')
            ));
        }
    }
}
?>