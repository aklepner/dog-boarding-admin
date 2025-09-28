<?php
/**
 * Admin Management Class
 */

class DBAS_Admin {
    
    private $dog_manager;
    
    public function __construct() {
        $this->dog_manager = new DBAS_Dog_Manager();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Process forms BEFORE any output (early hook)
        add_action('admin_init', array($this, 'handle_admin_forms'));
        
        // Add user columns
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'show_user_columns'), 10, 3);
        
        // Add bulk actions
        add_filter('bulk_actions-users', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Handle all admin form submissions early
     */
    public function handle_admin_forms() {
        // Only process on our admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'dbas-') !== 0) {
            return;
        }
        
        // Handle database fix FIRST, before any other actions
        if (isset($_GET['fix_terms_db']) && $_GET['fix_terms_db'] == '1' && current_user_can('manage_dbas')) {
            $this->fix_terms_database();
            wp_redirect(admin_url('admin.php?page=dbas-terms&message=fixed'));
            exit;
        }
        
        // Handle terms page actions
        if ($_GET['page'] === 'dbas-terms') {
            $this->handle_terms_actions();
        }
        
        // Handle dog edit form
        if ($_GET['page'] === 'dbas-dogs' && isset($_POST['update_dog']) && isset($_GET['dog_id'])) {
            $this->handle_update_dog($_GET['dog_id']);
        }
        
        // Handle breeds page actions
        if ($_GET['page'] === 'dbas-breeds') {
            $this->handle_breeds_actions();
        }
    }
    
    /**
     * Handle breeds page actions
     */
    private function handle_breeds_actions() {
        // Handle breed addition
        if (isset($_POST['add_breed']) && wp_verify_nonce($_POST['dbas_breed_nonce'], 'dbas_add_breed')) {
            $breed_name = sanitize_text_field($_POST['breed_name']);
            if ($breed_name) {
                if ($this->dog_manager->add_breed($breed_name)) {
                    wp_redirect(admin_url('admin.php?page=dbas-breeds&message=added'));
                    exit;
                } else {
                    wp_redirect(admin_url('admin.php?page=dbas-breeds&message=error'));
                    exit;
                }
            }
        }
        
        // Handle breed deletion
        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['breed_id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_breed_' . $_GET['breed_id'])) {
                if ($this->dog_manager->delete_breed($_GET['breed_id'])) {
                    wp_redirect(admin_url('admin.php?page=dbas-breeds&message=deleted'));
                    exit;
                } else {
                    wp_redirect(admin_url('admin.php?page=dbas-breeds&message=error'));
                    exit;
                }
            }
        }
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Dog Boarding', 'dbas'),
            __('Dog Boarding', 'dbas'),
            'manage_dbas',
            'dbas-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-pets',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'dbas-dashboard',
            __('Dashboard', 'dbas'),
            __('Dashboard', 'dbas'),
            'manage_dbas',
            'dbas-dashboard',
            array($this, 'render_dashboard')
        );
        
        // Dogs submenu
        add_submenu_page(
            'dbas-dashboard',
            __('All Dogs', 'dbas'),
            __('All Dogs', 'dbas'),
            'manage_dbas_dogs',
            'dbas-dogs',
            array($this, 'render_dogs_page')
        );
        
        // Users submenu
        add_submenu_page(
            'dbas-dashboard',
            __('Dog Owners', 'dbas'),
            __('Dog Owners', 'dbas'),
            'manage_dbas_users',
            'dbas-users',
            array($this, 'render_users_page')
        );
        
        // Breeds submenu
        add_submenu_page(
            'dbas-dashboard',
            __('Breeds', 'dbas'),
            __('Breeds', 'dbas'),
            'manage_dbas',
            'dbas-breeds',
            array($this, 'render_breeds_page')
        );
        
        // Terms submenu
        add_submenu_page(
            'dbas-dashboard',
            __('Terms & Conditions', 'dbas'),
            __('Terms & Conditions', 'dbas'),
            'manage_dbas',
            'dbas-terms',
            array($this, 'render_terms_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'dbas-dashboard',
            __('Settings', 'dbas'),
            __('Settings', 'dbas'),
            'manage_dbas',
            'dbas-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function render_dashboard() {
        global $wpdb;
        
        // Get statistics
        $total_users = count_users();
        $total_dogs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dbas_dogs");
        $active_dogs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dbas_dogs WHERE status = 'active'");
        $pending_dogs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dbas_dogs WHERE status = 'inactive'");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Dog Boarding Dashboard', 'dbas'); ?></h1>
            
            <div class="dbas-dashboard-widgets">
                <div class="dbas-widget">
                    <h3><?php _e('Statistics', 'dbas'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <td><?php _e('Total Dog Owners', 'dbas'); ?></td>
                            <td><strong><?php echo $total_users['total_users']; ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('Total Dogs', 'dbas'); ?></td>
                            <td><strong><?php echo $total_dogs; ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('Active Dogs', 'dbas'); ?></td>
                            <td><strong><?php echo $active_dogs; ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('Pending Approval', 'dbas'); ?></td>
                            <td><strong><?php echo $pending_dogs; ?></strong></td>
                        </tr>
                    </table>
                </div>
                
                <div class="dbas-widget">
                    <h3><?php _e('Recent Dogs', 'dbas'); ?></h3>
                    <?php
                    $recent_dogs = $wpdb->get_results("
                        SELECT d.*, u.display_name as owner_name 
                        FROM {$wpdb->prefix}dbas_dogs d
                        LEFT JOIN {$wpdb->users} u ON d.owner_id = u.ID
                        ORDER BY d.created_at DESC 
                        LIMIT 5
                    ");
                    
                    if ($recent_dogs):
                    ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Dog', 'dbas'); ?></th>
                                    <th><?php _e('Owner', 'dbas'); ?></th>
                                    <th><?php _e('Status', 'dbas'); ?></th>
                                    <th><?php _e('Date', 'dbas'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_dogs as $dog): ?>
                                    <tr>
                                        <td><?php echo esc_html($dog->name); ?></td>
                                        <td><?php echo esc_html($dog->owner_name); ?></td>
                                        <td><?php echo ucfirst($dog->status); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($dog->created_at)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No dogs registered yet.', 'dbas'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="dbas-widget">
                    <h3><?php _e('Quick Actions', 'dbas'); ?></h3>
                    <ul>
                        <li><a href="<?php echo admin_url('admin.php?page=dbas-dogs'); ?>" class="button">
                            <?php _e('Manage Dogs', 'dbas'); ?>
                        </a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=dbas-users'); ?>" class="button">
                            <?php _e('Manage Owners', 'dbas'); ?>
                        </a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=dbas-breeds'); ?>" class="button">
                            <?php _e('Manage Breeds', 'dbas'); ?>
                        </a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=dbas-settings'); ?>" class="button">
                            <?php _e('Settings', 'dbas'); ?>
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
            .dbas-dashboard-widgets { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
            .dbas-widget { flex: 1; min-width: 300px; background: white; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .dbas-widget h3 { margin-top: 0; }
            .dbas-widget ul { list-style: none; padding: 0; }
            .dbas-widget ul li { margin-bottom: 10px; }
        </style>
        <?php
    }
    
    public function render_dogs_page() {
        // Handle actions
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['dog_id'])) {
            $this->render_edit_dog_form($_GET['dog_id']);
            return;
        }
        
        // List all dogs
        $dogs = $this->dog_manager->get_all_dogs();
        ?>
        <div class="wrap">
            <h1><?php _e('All Dogs', 'dbas'); ?></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Action completed successfully.', 'dbas'); ?></p>
                </div>
            <?php endif; ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'dbas'); ?></th>
                        <th><?php _e('Dog Name', 'dbas'); ?></th>
                        <th><?php _e('Breed', 'dbas'); ?></th>
                        <th><?php _e('Owner', 'dbas'); ?></th>
                        <th><?php _e('Status', 'dbas'); ?></th>
                        <th><?php _e('Yearly Paperwork', 'dbas'); ?></th>
                        <th><?php _e('Vet Verified', 'dbas'); ?></th>
                        <th><?php _e('Actions', 'dbas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dogs as $dog): 
                        $owner = get_userdata($dog->owner_id);
                    ?>
                        <tr>
                            <td><?php echo $dog->id; ?></td>
                            <td><strong><?php echo esc_html($dog->name); ?></strong></td>
                            <td><?php echo esc_html($dog->breed); ?></td>
                            <td>
                                <?php if ($owner): ?>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $owner->ID); ?>">
                                        <?php echo esc_html($owner->display_name); ?>
                                    </a>
                                <?php else: ?>
                                    <?php _e('Unknown', 'dbas'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="dbas-status-<?php echo $dog->status; ?>">
                                    <?php echo ucfirst($dog->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $dog->yearly_paperwork == 'good_standing' ? 
                                    __('Good Standing', 'dbas') : __('Not in Good Standing', 'dbas'); ?>
                            </td>
                            <td><?php echo $dog->vet_verified ? __('Yes', 'dbas') : __('No', 'dbas'); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=dbas-dogs&action=edit&dog_id=' . $dog->id); ?>" 
                                   class="button button-small"><?php _e('Edit', 'dbas'); ?></a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dbas-dogs&action=delete&dog_id=' . $dog->id), 'delete_dog_' . $dog->id); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php _e('Are you sure you want to delete this dog?', 'dbas'); ?>');">
                                    <?php _e('Delete', 'dbas'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_edit_dog_form($dog_id) {
        $dog = $this->dog_manager->get_dog($dog_id);
        if (!$dog) {
            echo '<div class="notice notice-error"><p>' . __('Dog not found.', 'dbas') . '</p></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('Edit Dog: %s', 'dbas'), esc_html($dog->name)); ?></h1>
            
            <?php if (isset($_GET['message']) && $_GET['message'] == 'updated'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Dog updated successfully.', 'dbas'); ?></p>
                </div>
            <?php elseif (isset($_GET['message']) && $_GET['message'] == 'error'): ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <?php 
                        $error = get_transient('dbas_dog_error');
                        echo $error ? esc_html($error) : __('Failed to update dog.', 'dbas');
                        delete_transient('dbas_dog_error');
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('edit_dog_' . $dog_id); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="name"><?php _e('Name', 'dbas'); ?></label></th>
                        <td><input type="text" name="name" id="name" value="<?php echo esc_attr($dog->name); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="breed"><?php _e('Breed', 'dbas'); ?></label></th>
                        <td>
                            <select name="breed" id="breed">
                                <?php foreach ($this->dog_manager->get_breeds() as $breed): ?>
                                    <option value="<?php echo esc_attr($breed->breed_name); ?>" 
                                            <?php selected($dog->breed, $breed->breed_name); ?>>
                                        <?php echo esc_html($breed->breed_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="status"><?php _e('Status', 'dbas'); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="inactive" <?php selected($dog->status, 'inactive'); ?>><?php _e('Inactive', 'dbas'); ?></option>
                                <option value="active" <?php selected($dog->status, 'active'); ?>><?php _e('Active', 'dbas'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="yearly_paperwork"><?php _e('Yearly Paperwork', 'dbas'); ?></label></th>
                        <td>
                            <select name="yearly_paperwork" id="yearly_paperwork">
                                <option value="not_good_standing" <?php selected($dog->yearly_paperwork, 'not_good_standing'); ?>>
                                    <?php _e('Not in Good Standing', 'dbas'); ?>
                                </option>
                                <option value="good_standing" <?php selected($dog->yearly_paperwork, 'good_standing'); ?>>
                                    <?php _e('Good Standing', 'dbas'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vet_verified"><?php _e('Vet Verified', 'dbas'); ?></label></th>
                        <td>
                            <input type="checkbox" name="vet_verified" id="vet_verified" value="1" 
                                   <?php checked($dog->vet_verified, 1); ?> />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="birthdate"><?php _e('Birthdate', 'dbas'); ?></label></th>
                        <td><input type="date" name="birthdate" id="birthdate" value="<?php echo esc_attr($dog->birthdate); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="sex"><?php _e('Sex', 'dbas'); ?></label></th>
                        <td>
                            <select name="sex" id="sex">
                                <option value=""><?php _e('Not specified', 'dbas'); ?></option>
                                <option value="male" <?php selected($dog->sex, 'male'); ?>><?php _e('Male', 'dbas'); ?></option>
                                <option value="female" <?php selected($dog->sex, 'female'); ?>><?php _e('Female', 'dbas'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="color"><?php _e('Color', 'dbas'); ?></label></th>
                        <td><input type="text" name="color" id="color" value="<?php echo esc_attr($dog->color); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="spay_neuter"><?php _e('Spayed/Neutered', 'dbas'); ?></label></th>
                        <td>
                            <select name="spay_neuter" id="spay_neuter">
                                <option value=""><?php _e('Not specified', 'dbas'); ?></option>
                                <option value="yes" <?php selected($dog->spay_neuter, 'yes'); ?>><?php _e('Yes', 'dbas'); ?></option>
                                <option value="no" <?php selected($dog->spay_neuter, 'no'); ?>><?php _e('No', 'dbas'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="vet_details"><?php _e('Veterinarian Details', 'dbas'); ?></label></th>
                        <td><textarea name="vet_details" id="vet_details" rows="3" cols="50"><?php echo esc_textarea($dog->vet_details); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="allergies"><?php _e('Allergies', 'dbas'); ?></label></th>
                        <td><textarea name="allergies" id="allergies" rows="3" cols="50"><?php echo esc_textarea($dog->allergies); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="behavioral_notes"><?php _e('Behavioral Notes', 'dbas'); ?></label></th>
                        <td><textarea name="behavioral_notes" id="behavioral_notes" rows="3" cols="50"><?php echo esc_textarea($dog->behavioral_notes); ?></textarea></td>
                    </tr>
                    
                    <?php if ($dog->vaccination_records): ?>
                    <tr>
                        <th><?php _e('Current Vaccination Records', 'dbas'); ?></th>
                        <td>
                            <a href="<?php echo esc_url($dog->vaccination_records); ?>" target="_blank">
                                <?php _e('View PDF', 'dbas'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($dog->hipaa_forms): ?>
                    <tr>
                        <th><?php _e('Current HIPAA Forms', 'dbas'); ?></th>
                        <td>
                            <a href="<?php echo esc_url($dog->hipaa_forms); ?>" target="_blank">
                                <?php _e('View PDF', 'dbas'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($dog->pet_photos): ?>
                    <tr>
                        <th><?php _e('Current Pet Photo', 'dbas'); ?></th>
                        <td>
                            <img src="<?php echo esc_url($dog->pet_photos); ?>" style="max-width: 200px;" />
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <p class="submit">
                    <input type="submit" name="update_dog" class="button button-primary" value="<?php _e('Update Dog', 'dbas'); ?>" />
                    <a href="<?php echo admin_url('admin.php?page=dbas-dogs'); ?>" class="button"><?php _e('Cancel', 'dbas'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function handle_update_dog($dog_id) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'edit_dog_' . $dog_id)) {
            wp_die(__('Security check failed', 'dbas'));
        }
        
        $success = $this->dog_manager->update_dog($dog_id, $_POST);
        
        if ($success) {
            wp_redirect(admin_url('admin.php?page=dbas-dogs&action=edit&dog_id=' . $dog_id . '&message=updated'));
            exit;
        } else {
            // Store error in transient
            set_transient('dbas_dog_error', __('Failed to update dog.', 'dbas'), 30);
            wp_redirect(admin_url('admin.php?page=dbas-dogs&action=edit&dog_id=' . $dog_id . '&message=error'));
            exit;
        }
    }
    
    public function render_users_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Dog Owners', 'dbas'); ?></h1>
            
            <?php
            $users = get_users(array(
                'meta_key' => 'dbas_phone',
                'meta_compare' => 'EXISTS'
            ));
            
            if ($users):
            ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'dbas'); ?></th>
                            <th><?php _e('Email', 'dbas'); ?></th>
                            <th><?php _e('Phone', 'dbas'); ?></th>
                            <th><?php _e('Dogs', 'dbas'); ?></th>
                            <th><?php _e('Not Allowed Back', 'dbas'); ?></th>
                            <th><?php _e('Actions', 'dbas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user):
                            $dogs = $this->dog_manager->get_user_dogs($user->ID);
                            $not_allowed = get_user_meta($user->ID, 'dbas_not_allowed', true);
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo esc_html(get_user_meta($user->ID, 'dbas_phone', true)); ?></td>
                                <td><?php echo count($dogs); ?></td>
                                <td>
                                    <?php echo $not_allowed ? '<span style="color: red;">Yes</span>' : 'No'; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" 
                                       class="button button-small"><?php _e('Edit', 'dbas'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No dog owners registered yet.', 'dbas'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_breeds_page() {
        $breeds = $this->dog_manager->get_breeds();
        ?>
        <div class="wrap">
            <h1><?php _e('Dog Breeds', 'dbas'); ?></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <?php if ($_GET['message'] == 'added'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php _e('Breed added successfully.', 'dbas'); ?></p>
                    </div>
                <?php elseif ($_GET['message'] == 'deleted'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php _e('Breed deleted successfully.', 'dbas'); ?></p>
                    </div>
                <?php elseif ($_GET['message'] == 'error'): ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php _e('An error occurred. Please try again.', 'dbas'); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('dbas_add_breed', 'dbas_breed_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="breed_name"><?php _e('Add New Breed', 'dbas'); ?></label></th>
                        <td>
                            <input type="text" name="breed_name" id="breed_name" class="regular-text" />
                            <input type="submit" name="add_breed" class="button button-primary" value="<?php _e('Add Breed', 'dbas'); ?>" />
                        </td>
                    </tr>
                </table>
            </form>
            
            <h2><?php _e('Existing Breeds', 'dbas'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'dbas'); ?></th>
                        <th><?php _e('Breed Name', 'dbas'); ?></th>
                        <th><?php _e('Actions', 'dbas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($breeds as $breed): ?>
                        <tr>
                            <td><?php echo $breed->id; ?></td>
                            <td><?php echo esc_html($breed->breed_name); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dbas-breeds&action=delete&breed_id=' . $breed->id), 'delete_breed_' . $breed->id); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php _e('Are you sure you want to delete this breed?', 'dbas'); ?>');">
                                    <?php _e('Delete', 'dbas'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function render_terms_page() {
        global $wpdb;
        $terms_table = $wpdb->prefix . 'dbas_terms_content';
        
        // Check for problematic entries
        $bad_entries_count = $wpdb->get_var("SELECT COUNT(*) FROM $terms_table WHERE term_id = '0' OR term_id = '' OR term_id IS NULL");
        
        if ($bad_entries_count > 0) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Database Issue Detected:', 'dbas'); ?></strong> 
                    <?php printf(__('There are %d terms with invalid IDs that need to be fixed.', 'dbas'), $bad_entries_count); ?>
                    <a href="<?php echo admin_url('admin.php?page=dbas-terms&fix_terms_db=1'); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php _e('Fix Database Now', 'dbas'); ?>
                    </a>
                </p>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; color: #0073aa;"><?php _e('Manual Fix Instructions (if automatic fix fails)', 'dbas'); ?></summary>
                    <div style="background: #f5f5f5; padding: 10px; margin-top: 10px; border-left: 3px solid #0073aa;">
                        <p><strong><?php _e('Run these SQL queries in phpMyAdmin:', 'dbas'); ?></strong></p>
                        <code style="display: block; padding: 10px; background: white; margin: 10px 0;">
                            -- Step 1: Check existing indices<br>
                            SHOW INDEX FROM <?php echo $terms_table; ?>;<br><br>
                            
                            -- Step 2: Drop problematic constraints (if they exist)<br>
                            ALTER TABLE <?php echo $terms_table; ?> DROP INDEX term_id_version;<br>
                            ALTER TABLE <?php echo $terms_table; ?> DROP INDEX term_unique;<br><br>
                            
                            -- Step 3: Fix bad entries<br>
                            DELETE FROM <?php echo $terms_table; ?> WHERE term_id = '0' OR term_id = '' OR term_id IS NULL;<br><br>
                            
                            -- Step 4: Add simple index<br>
                            ALTER TABLE <?php echo $terms_table; ?> ADD INDEX idx_term_id (term_id);
                        </code>
                        <p><em><?php _e('After running these queries, refresh this page.', 'dbas'); ?></em></p>
                    </div>
                </details>
            </div>
            <?php
        }
        
        $terms = $wpdb->get_results("SELECT * FROM $terms_table WHERE is_active = 1 AND term_id != '0' AND term_id != '' ORDER BY display_order");
        ?>
        <div class="wrap">
            <h1><?php _e('Terms & Conditions Management', 'dbas'); ?></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <?php if ($_GET['message'] == 'saved'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php _e('Term saved successfully.', 'dbas'); ?></p>
                    </div>
                <?php elseif ($_GET['message'] == 'deleted'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php _e('Term deleted successfully.', 'dbas'); ?></p>
                    </div>
                <?php elseif ($_GET['message'] == 'fixed'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php _e('Database has been fixed successfully.', 'dbas'); ?></p>
                    </div>
                <?php elseif ($_GET['message'] == 'error'): ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php _e('Error saving term.', 'dbas'); ?>
                        <?php 
                        $error = get_transient('dbas_terms_error');
                        if ($error) {
                            echo ' ' . esc_html($error);
                            delete_transient('dbas_terms_error');
                        }
                        ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Add/Edit Term Form -->
            <?php 
            $editing_term = null;
            if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['term_id'])) {
                $editing_term = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $terms_table WHERE id = %d",
                    intval($_GET['term_id'])
                ));
            }
            ?>
            
            <div class="dbas-terms-form-wrapper">
                <h2><?php echo $editing_term ? __('Edit Term', 'dbas') : __('Add New Term', 'dbas'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('dbas_save_term', 'dbas_term_nonce'); ?>
                    <?php if ($editing_term): ?>
                        <input type="hidden" name="term_id" value="<?php echo $editing_term->id; ?>">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="term_identifier"><?php _e('Term ID (Internal Use)', 'dbas'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="term_identifier" name="term_identifier" 
                                       value="<?php echo $editing_term ? esc_attr($editing_term->term_id) : ''; ?>" 
                                       class="regular-text" required 
                                       pattern="[a-z0-9_-]+" 
                                       <?php echo $editing_term ? 'readonly' : ''; ?>>
                                <p class="description">
                                    <?php _e('Unique identifier for this term (lowercase letters, numbers, underscore, dash only). Examples: liability_waiver, vaccination_policy, payment_terms', 'dbas'); ?>
                                </p>
                                <?php if (!$editing_term): ?>
                                    <p class="description" style="color: #d63638;">
                                        <?php _e('This cannot be changed after creation. Choose carefully!', 'dbas'); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="term_title"><?php _e('Term Title', 'dbas'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="term_title" name="term_title" 
                                       value="<?php echo $editing_term ? esc_attr($editing_term->term_title) : ''; ?>" 
                                       class="regular-text" required>
                                <p class="description"><?php _e('Display title for this term (shown to users)', 'dbas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="term_content"><?php _e('Term Content', 'dbas'); ?> *</label>
                            </th>
                            <td>
                                <textarea id="term_content" name="term_content" rows="5" cols="50" class="large-text" required><?php echo $editing_term ? esc_textarea($editing_term->term_content) : ''; ?></textarea>
                                <p class="description"><?php _e('The full text of the term that users must agree to', 'dbas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="required"><?php _e('Required', 'dbas'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="required" name="required" value="1" 
                                       <?php echo ($editing_term && $editing_term->required) ? 'checked' : 'checked'; ?>>
                                <label for="required"><?php _e('Users must accept this term to register', 'dbas'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="display_order"><?php _e('Display Order', 'dbas'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="display_order" name="display_order" 
                                       value="<?php echo $editing_term ? intval($editing_term->display_order) : (count($terms) + 1); ?>" 
                                       min="0" class="small-text">
                                <p class="description"><?php _e('Order in which this term appears (lower numbers appear first)', 'dbas'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="save_term" class="button button-primary" 
                               value="<?php echo $editing_term ? __('Update Term', 'dbas') : __('Add Term', 'dbas'); ?>">
                        <?php if ($editing_term): ?>
                            <a href="<?php echo admin_url('admin.php?page=dbas-terms'); ?>" class="button">
                                <?php _e('Cancel', 'dbas'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <hr>
            
            <!-- Existing Terms List -->
            <h2><?php _e('Existing Terms', 'dbas'); ?></h2>
            
            <?php if ($terms): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php _e('Order', 'dbas'); ?></th>
                            <th><?php _e('Title', 'dbas'); ?></th>
                            <th><?php _e('Term ID', 'dbas'); ?></th>
                            <th><?php _e('Content', 'dbas'); ?></th>
                            <th style="width: 80px;"><?php _e('Required', 'dbas'); ?></th>
                            <th style="width: 150px;"><?php _e('Actions', 'dbas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($terms as $term): ?>
                            <tr>
                                <td><?php echo $term->display_order; ?></td>
                                <td><strong><?php echo esc_html($term->term_title); ?></strong></td>
                                <td><code><?php echo esc_html($term->term_id); ?></code></td>
                                <td>
                                    <div style="max-height: 60px; overflow-y: auto;">
                                        <?php echo esc_html(substr($term->term_content, 0, 150)); ?>
                                        <?php echo strlen($term->term_content) > 150 ? '...' : ''; ?>
                                    </div>
                                </td>
                                <td><?php echo $term->required ? __('Yes', 'dbas') : __('No', 'dbas'); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=dbas-terms&action=edit&term_id=' . $term->id); ?>" 
                                       class="button button-small"><?php _e('Edit', 'dbas'); ?></a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=dbas-terms&action=delete&term_id=' . $term->id), 'delete_term_' . $term->id); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this term?', 'dbas'); ?>');">
                                        <?php _e('Delete', 'dbas'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No terms found. Add your first term above.', 'dbas'); ?></p>
            <?php endif; ?>
            
            <div class="dbas-terms-preview" style="margin-top: 30px;">
                <h2><?php _e('Preview (How it appears during registration)', 'dbas'); ?></h2>
                <div style="background: #f5f5f5; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h4><?php _e('Terms and Conditions', 'dbas'); ?></h4>
                    <?php foreach ($terms as $term): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" disabled <?php echo $term->required ? 'required' : ''; ?>>
                                <strong><?php echo esc_html($term->term_title); ?></strong>
                                <?php if ($term->required): ?>
                                    <span style="color: red;">*</span>
                                <?php endif; ?>
                            </label>
                            <div style="margin-left: 25px; color: #666; font-size: 13px;">
                                <?php echo esc_html($term->term_content); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <style>
            .dbas-terms-form-wrapper {
                background: white;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .dbas-terms-form-wrapper h2 {
                margin-top: 0;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-generate term ID from title
            <?php if (!$editing_term): ?>
            $('#term_title').on('blur', function() {
                var title = $(this).val();
                var identifier = $('#term_identifier');
                
                // Only auto-fill if identifier is empty
                if (title && !identifier.val()) {
                    // Convert title to valid ID format
                    var generated_id = title.toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')  // Replace non-alphanumeric with underscore
                        .replace(/^_+|_+$/g, '')       // Remove leading/trailing underscores
                        .substring(0, 50);             // Limit length
                    
                    // Make sure it's not empty after conversion
                    if (!generated_id) {
                        generated_id = 'term_' + Date.now();
                    }
                    
                    // Make sure it doesn't start with a number
                    if (/^\d/.test(generated_id)) {
                        generated_id = 'term_' + generated_id;
                    }
                    
                    identifier.val(generated_id);
                }
            });
            <?php endif; ?>
            
            // Validate term identifier on input
            $('#term_identifier').on('input', function() {
                var value = $(this).val();
                
                // Convert to lowercase automatically
                if (value !== value.toLowerCase()) {
                    $(this).val(value.toLowerCase());
                    value = value.toLowerCase();
                }
                
                var valid = /^[a-z0-9_-]+$/.test(value);
                
                if (!valid && value) {
                    $(this).css('border-color', '#d63638');
                    if (!$(this).next('.error-message').length) {
                        $(this).after('<span class="error-message" style="color: #d63638; display: block; margin-top: 5px;">Only lowercase letters, numbers, underscore, and dash allowed</span>');
                    }
                } else {
                    $(this).css('border-color', '');
                    $(this).next('.error-message').remove();
                }
            });
            
            // Prevent form submission if identifier is invalid or empty
            $('form').on('submit', function(e) {
                var identifier = $('#term_identifier').val();
                
                // Check if empty
                if (!identifier) {
                    // Auto-generate from title if empty
                    var title = $('#term_title').val();
                    if (title) {
                        var generated_id = title.toLowerCase()
                            .replace(/[^a-z0-9]+/g, '_')
                            .replace(/^_+|_+$/g, '')
                            .substring(0, 50);
                        
                        if (!generated_id) {
                            generated_id = 'term_' + Date.now();
                        }
                        
                        if (/^\d/.test(generated_id)) {
                            generated_id = 'term_' + generated_id;
                        }
                        
                        $('#term_identifier').val(generated_id);
                        identifier = generated_id;
                    } else {
                        e.preventDefault();
                        alert('Please enter a Term ID');
                        $('#term_identifier').focus();
                        return false;
                    }
                }
                
                // Validate format
                if (!/^[a-z0-9_-]+$/.test(identifier)) {
                    e.preventDefault();
                    alert('Term ID can only contain lowercase letters, numbers, underscore, and dash');
                    $('#term_identifier').focus();
                    return false;
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle terms page actions
     */
    private function handle_terms_actions() {
        // Start output buffering to prevent any notices from breaking redirects
        ob_start();
        
        global $wpdb;
        $terms_table = $wpdb->prefix . 'dbas_terms_content';
        
        // Handle save/update
        if (isset($_POST['save_term']) && isset($_POST['dbas_term_nonce']) && wp_verify_nonce($_POST['dbas_term_nonce'], 'dbas_save_term')) {
            // Validate and generate term_identifier
            $term_identifier = sanitize_text_field($_POST['term_identifier']);
            
            // If empty, generate from title
            if (empty($term_identifier)) {
                $title = sanitize_text_field($_POST['term_title']);
                $term_identifier = sanitize_title($title);
                
                // If still empty, generate a unique one
                if (empty($term_identifier)) {
                    $term_identifier = 'term_' . uniqid();
                }
            }
            
            // Ensure term_id is lowercase and valid
            $term_identifier = strtolower(preg_replace('/[^a-z0-9_-]/', '_', $term_identifier));
            
            // Make sure it's not just numbers (which could be confused with ID)
            if (is_numeric($term_identifier)) {
                $term_identifier = 'term_' . $term_identifier;
            }
            
            $data = array(
                'term_id' => $term_identifier,
                'term_title' => sanitize_text_field($_POST['term_title']),
                'term_content' => sanitize_textarea_field($_POST['term_content']),
                'required' => isset($_POST['required']) ? 1 : 0,
                'display_order' => intval($_POST['display_order']) ?: 999,
                'is_active' => 1,
                'term_version' => '1.0'
            );
            
            $success = false;
            $error_message = '';
            
            if (isset($_POST['term_id']) && !empty($_POST['term_id'])) {
                // Update existing - don't update term_id
                unset($data['term_id']); // Remove term_id from update to prevent changes
                unset($data['term_version']); // Don't change version on update
                $result = $wpdb->update($terms_table, $data, array('id' => intval($_POST['term_id'])));
                $success = ($result !== false);
                if (!$success) {
                    $error_message = $wpdb->last_error ?: 'Update failed';
                }
            } else {
                // For new entries, check if this term_id already exists
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $terms_table WHERE term_id = %s",
                    $term_identifier
                ));
                
                if ($existing) {
                    // Make term_id unique by appending number
                    $original = $term_identifier;
                    $counter = 2;
                    while ($wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $terms_table WHERE term_id = %s",
                        $term_identifier
                    ))) {
                        $term_identifier = $original . '_' . $counter;
                        $counter++;
                        if ($counter > 100) { // Prevent infinite loop
                            $term_identifier = $original . '_' . uniqid();
                            break;
                        }
                    }
                    $data['term_id'] = $term_identifier;
                }
                
                // Double-check the term_id is valid before insert
                if (empty($data['term_id']) || $data['term_id'] == '0') {
                    $data['term_id'] = 'term_' . uniqid();
                }
                
                // Insert new term
                $result = $wpdb->insert($terms_table, $data);
                $success = ($result !== false);
                if (!$success) {
                    $error_message = $wpdb->last_error ?: 'Insert failed';
                }
            }
            
            if ($success) {
                ob_end_clean(); // Clear any output before redirect
                wp_redirect(admin_url('admin.php?page=dbas-terms&message=saved'));
                exit;
            } else {
                // Store error in transient and redirect with error flag
                set_transient('dbas_terms_error', $error_message, 30);
                ob_end_clean(); // Clear any output before redirect
                wp_redirect(admin_url('admin.php?page=dbas-terms&message=error'));
                exit;
            }
        }
        
        // Handle delete
        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['term_id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_term_' . $_GET['term_id'])) {
                $wpdb->update(
                    $terms_table,
                    array('is_active' => 0),
                    array('id' => intval($_GET['term_id']))
                );
                ob_end_clean(); // Clear any output before redirect
                wp_redirect(admin_url('admin.php?page=dbas-terms&message=deleted'));
                exit;
            }
        }
        
        // Clean up buffer if no redirect happened
        ob_end_clean();
    }
    
    /**
     * Fix terms database issues
     */
    private function fix_terms_database() {
        global $wpdb;
        $terms_table = $wpdb->prefix . 'dbas_terms_content';
        
        // Start output buffering to prevent header errors
        ob_start();
        
        // Suppress database errors temporarily
        $wpdb->suppress_errors(true);
        
        // Get all problematic entries
        $bad_entries = $wpdb->get_results("SELECT * FROM $terms_table WHERE term_id = '0' OR term_id = '' OR term_id IS NULL");
        
        if ($bad_entries) {
            foreach ($bad_entries as $entry) {
                // Generate a unique term_id
                $base_id = sanitize_title($entry->term_title);
                if (empty($base_id)) {
                    $base_id = 'term_auto_' . $entry->id;
                }
                
                $new_term_id = $base_id;
                $counter = 1;
                
                // Make sure it's unique
                while ($wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $terms_table WHERE term_id = %s AND id != %d",
                    $new_term_id, $entry->id
                ))) {
                    $new_term_id = $base_id . '_' . $counter;
                    $counter++;
                    if ($counter > 100) {
                        $new_term_id = $base_id . '_' . uniqid();
                        break;
                    }
                }
                
                // Try to update
                $updated = $wpdb->update(
                    $terms_table,
                    array('term_id' => $new_term_id),
                    array('id' => $entry->id),
                    array('%s'),
                    array('%d')
                );
                
                // If update failed, delete the problematic entry
                if ($updated === false) {
                    $wpdb->delete($terms_table, array('id' => $entry->id));
                }
            }
        }
        
        // Check if indices exist before trying to drop them
        $indices = $wpdb->get_results("SHOW INDEX FROM $terms_table");
        $index_names = array();
        foreach ($indices as $index) {
            $index_names[] = $index->Key_name;
        }
        
        // Drop problematic indices if they exist
        if (in_array('term_id_version', $index_names)) {
            $wpdb->query("ALTER TABLE $terms_table DROP INDEX term_id_version");
        }
        if (in_array('term_unique', $index_names)) {
            $wpdb->query("ALTER TABLE $terms_table DROP INDEX term_unique");
        }
        
        // Add a simple non-unique index for performance
        if (!in_array('idx_term_id', $index_names)) {
            $wpdb->query("ALTER TABLE $terms_table ADD INDEX idx_term_id (term_id)");
        }
        
        // Re-enable error reporting
        $wpdb->suppress_errors(false);
        
        // Clear any output
        ob_end_clean();
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Dog Boarding Settings', 'dbas'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('dbas_settings_group');
                do_settings_sections('dbas_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dbas_admin_email"><?php _e('Admin Notification Email', 'dbas'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="dbas_admin_email" id="dbas_admin_email" 
                                   value="<?php echo esc_attr(get_option('dbas_admin_email', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Email address where admin notifications will be sent.', 'dbas'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dbas_email_notifications"><?php _e('Email Notifications', 'dbas'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="dbas_notify_user_registration" value="1" 
                                       <?php checked(get_option('dbas_notify_user_registration', 1)); ?> />
                                <?php _e('Send email when user registers', 'dbas'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="dbas_notify_dog_registration" value="1" 
                                       <?php checked(get_option('dbas_notify_dog_registration', 1)); ?> />
                                <?php _e('Send email when dog is registered', 'dbas'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="dbas_notify_updates" value="1" 
                                       <?php checked(get_option('dbas_notify_updates', 1)); ?> />
                                <?php _e('Send email on profile/dog updates', 'dbas'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dbas_portal_page"><?php _e('Portal Page', 'dbas'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name' => 'dbas_portal_page_id',
                                'selected' => get_option('dbas_portal_page_id'),
                                'show_option_none' => __('Select Page', 'dbas')
                            ));
                            ?>
                            <p class="description"><?php _e('Select the page that contains the [dbas_portal] shortcode.', 'dbas'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function register_settings() {
        register_setting('dbas_settings_group', 'dbas_admin_email');
        register_setting('dbas_settings_group', 'dbas_notify_user_registration');
        register_setting('dbas_settings_group', 'dbas_notify_dog_registration');
        register_setting('dbas_settings_group', 'dbas_notify_updates');
        register_setting('dbas_settings_group', 'dbas_portal_page_id');
    }
    
    public function add_user_columns($columns) {
        $columns['dbas_dogs'] = __('Dogs', 'dbas');
        $columns['dbas_phone'] = __('Phone', 'dbas');
        $columns['dbas_not_allowed'] = __('Not Allowed', 'dbas');
        return $columns;
    }
    
    public function show_user_columns($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'dbas_dogs':
                $dogs = $this->dog_manager->get_user_dogs($user_id);
                return count($dogs);
                
            case 'dbas_phone':
                return get_user_meta($user_id, 'dbas_phone', true);
                
            case 'dbas_not_allowed':
                $not_allowed = get_user_meta($user_id, 'dbas_not_allowed', true);
                return $not_allowed ? '<span style="color: red;">Yes</span>' : 'No';
                
            default:
                return $value;
        }
    }
    
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['dbas_mark_not_allowed'] = __('Mark as Not Allowed Back', 'dbas');
        $bulk_actions['dbas_mark_allowed'] = __('Mark as Allowed Back', 'dbas');
        return $bulk_actions;
    }
    
    public function handle_bulk_actions($redirect_to, $action, $user_ids) {
        if ($action == 'dbas_mark_not_allowed') {
            foreach ($user_ids as $user_id) {
                update_user_meta($user_id, 'dbas_not_allowed', '1');
            }
            $redirect_to = add_query_arg('dbas_bulk_action', 'not_allowed', $redirect_to);
        }
        
        if ($action == 'dbas_mark_allowed') {
            foreach ($user_ids as $user_id) {
                update_user_meta($user_id, 'dbas_not_allowed', '0');
            }
            $redirect_to = add_query_arg('dbas_bulk_action', 'allowed', $redirect_to);
        }
        
        return $redirect_to;
    }
}
?>