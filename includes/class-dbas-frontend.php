<?php
/**
 * Frontend Management Class
 */

class DBAS_Frontend {
    
    public function __construct() {
        // Add shortcodes
        add_shortcode('dbas_portal', array($this, 'render_portal'));
        add_shortcode('dbas_register', array($this, 'render_registration_form'));
        add_shortcode('dbas_login', array($this, 'render_login_form'));
        add_shortcode('dbas_user_profile', array($this, 'render_user_profile'));
        add_shortcode('dbas_terms', array($this, 'render_terms_page'));
        
        // Handle form submissions
        add_action('init', array($this, 'handle_registration'));
        add_action('init', array($this, 'handle_profile_update'));
        add_action('wp_login', array($this, 'redirect_after_login'), 10, 2);
    }
    
    public function render_portal() {
        if (!is_user_logged_in()) {
            return $this->render_login_form();
        }
        
        ob_start();
        ?>
        <div class="dbas-portal">
            <h2><?php _e('Dog Owner Portal', 'dbas'); ?></h2>
            
            <div class="dbas-welcome">
                <?php 
                $current_user = wp_get_current_user();
                printf(__('Welcome back, %s!', 'dbas'), esc_html($current_user->display_name));
                ?>
            </div>
            
            <div class="dbas-portal-navigation">
                <ul class="dbas-nav-tabs">
                    <li><a href="#dbas-profile" class="dbas-tab-link active"><?php _e('My Profile', 'dbas'); ?></a></li>
                    <li><a href="#dbas-dogs" class="dbas-tab-link"><?php _e('My Dogs', 'dbas'); ?></a></li>
                    <li><a href="#dbas-add-dog" class="dbas-tab-link"><?php _e('Add New Dog', 'dbas'); ?></a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>"><?php _e('Logout', 'dbas'); ?></a></li>
                </ul>
            </div>
            
            <div class="dbas-portal-content">
                <!-- Profile Tab -->
                <div id="dbas-profile" class="dbas-tab-content active">
                    <?php echo $this->render_user_profile(); ?>
                </div>
                
                <!-- Dogs Tab -->
                <div id="dbas-dogs" class="dbas-tab-content">
                    <?php echo do_shortcode('[dbas_manage_dogs]'); ?>
                </div>
                
                <!-- Add Dog Tab -->
                <div id="dbas-add-dog" class="dbas-tab-content">
                    <?php echo do_shortcode('[dbas_add_dog_form]'); ?>
                </div>
            </div>
        </div>
        
        <style>
            .dbas-portal { max-width: 1200px; margin: 0 auto; padding: 20px; }
            .dbas-welcome { background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .dbas-nav-tabs { list-style: none; padding: 0; display: flex; border-bottom: 2px solid #ddd; }
            .dbas-nav-tabs li { margin-right: 20px; }
            .dbas-nav-tabs a { display: block; padding: 10px 15px; text-decoration: none; color: #333; }
            .dbas-nav-tabs a.active { background: #0073aa; color: white; }
            .dbas-tab-content { display: none; padding: 20px 0; }
            .dbas-tab-content.active { display: block; }
            .dbas-form-group { margin-bottom: 15px; }
            .dbas-form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
            .dbas-form-group input[type="text"],
            .dbas-form-group input[type="email"],
            .dbas-form-group input[type="date"],
            .dbas-form-group select,
            .dbas-form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; }
            .dbas-radio-group label { display: inline-block; margin-right: 15px; font-weight: normal; }
            .dbas-dogs-table { width: 100%; border-collapse: collapse; }
            .dbas-dogs-table th,
            .dbas-dogs-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .dbas-status-active { color: green; }
            .dbas-status-inactive { color: orange; }
            .dbas-emergency-contact-item { margin-bottom: 10px; padding: 10px; background: #f9f9f9; }
            .dbas-emergency-contact-item input { margin-right: 10px; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.dbas-tab-link').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.dbas-tab-link').removeClass('active');
                $(this).addClass('active');
                
                $('.dbas-tab-content').removeClass('active');
                $(target).addClass('active');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function render_registration_form() {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already registered and logged in.', 'dbas') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="dbas-registration-form">
            <h3><?php _e('Register for Dog Boarding', 'dbas'); ?></h3>
            
            <?php if (isset($_GET['registration']) && $_GET['registration'] == 'success'): ?>
                <div class="dbas-notice dbas-success">
                    <?php _e('Registration successful! Please check your email for your password.', 'dbas'); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registration']) && $_GET['registration'] == 'failed'): ?>
                <div class="dbas-notice dbas-error">
                    <?php _e('Registration failed. Please try again.', 'dbas'); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('dbas_register', 'dbas_register_nonce'); ?>
                <input type="hidden" name="dbas_action" value="register" />
                
                <div class="dbas-form-group">
                    <label for="username"><?php _e('Username', 'dbas'); ?> *</label>
                    <input type="text" name="username" id="username" required />
                </div>
                
                <div class="dbas-form-group">
                    <label for="email"><?php _e('Email Address', 'dbas'); ?> *</label>
                    <input type="email" name="email" id="email" required />
                </div>
                
                <div class="dbas-form-group">
                    <label for="first_name"><?php _e('First Name', 'dbas'); ?> *</label>
                    <input type="text" name="first_name" id="first_name" required />
                </div>
                
                <div class="dbas-form-group">
                    <label for="last_name"><?php _e('Last Name', 'dbas'); ?> *</label>
                    <input type="text" name="last_name" id="last_name" required />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_phone"><?php _e('Phone Number', 'dbas'); ?> *</label>
                    <input type="text" name="dbas_phone" id="dbas_phone" required />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_street"><?php _e('Street Address', 'dbas'); ?></label>
                    <input type="text" name="dbas_street" id="dbas_street" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_city"><?php _e('City', 'dbas'); ?></label>
                    <input type="text" name="dbas_city" id="dbas_city" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_state"><?php _e('State', 'dbas'); ?></label>
                    <input type="text" name="dbas_state" id="dbas_state" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_zip"><?php _e('Zip Code', 'dbas'); ?></label>
                    <input type="text" name="dbas_zip" id="dbas_zip" />
                </div>
                
                <!-- Terms and Conditions -->
                <div class="dbas-form-group">
                    <h4><?php _e('Terms and Conditions', 'dbas'); ?></h4>
                    <?php 
                    global $wpdb;
                    $terms_table = $wpdb->prefix . 'dbas_terms_content';
                    
                    // Get VALID terms only (exclude empty or zero term_ids)
                    $terms = $wpdb->get_results("SELECT * FROM $terms_table WHERE is_active = 1 AND term_id != '' AND term_id != '0' AND term_id IS NOT NULL ORDER BY display_order");
                    
                    if ($terms) {
                        foreach ($terms as $term) {
                            // Skip if term_id is somehow still invalid
                            if (empty($term->term_id) || $term->term_id == '0') {
                                continue;
                            }
                            ?>
                            <div class="dbas-term-item" style="margin-bottom: 15px;">
                                <label>
                                    <input type="checkbox" 
                                           name="terms_<?php echo esc_attr($term->term_id); ?>" 
                                           value="1" 
                                           <?php echo $term->required ? 'required' : ''; ?>>
                                    <strong><?php echo esc_html($term->term_title); ?></strong>
                                    <?php if ($term->required): ?>
                                        <span style="color: red;">*</span>
                                    <?php endif; ?>
                                </label>
                                <div style="margin-left: 25px; margin-top: 5px; color: #666; font-size: 14px;">
                                    <?php echo esc_html($term->term_content); ?>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        // Fallback to default terms if none in database
                        ?>
                        <label>
                            <input type="checkbox" name="terms_liability" value="1" required />
                            <?php _e('I accept the liability waiver', 'dbas'); ?> *
                        </label><br>
                        <label>
                            <input type="checkbox" name="terms_vaccination" value="1" required />
                            <?php _e('I confirm all vaccination records will be up to date', 'dbas'); ?> *
                        </label><br>
                        <label>
                            <input type="checkbox" name="terms_behavior" value="1" required />
                            <?php _e('I agree to the behavioral policy', 'dbas'); ?> *
                        </label><br>
                        <label>
                            <input type="checkbox" name="terms_payment" value="1" required />
                            <?php _e('I accept the payment terms and conditions', 'dbas'); ?> *
                        </label><br>
                        <label>
                            <input type="checkbox" name="terms_pickup" value="1" required />
                            <?php _e('I agree to the pickup and drop-off policies', 'dbas'); ?> *
                        </label>
                        <?php
                    }
                    ?>
                </div>
                
                <div class="dbas-form-submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Register', 'dbas'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_login_form() {
        if (is_user_logged_in()) {
            $portal_url = get_permalink(get_option('dbas_portal_page_id'));
            return '<p>' . sprintf(__('You are already logged in. <a href="%s">Go to portal</a>', 'dbas'), $portal_url) . '</p>';
        }
        
        ob_start();
        ?>
        <div class="dbas-login-form">
            <h3><?php _e('Login to Dog Portal', 'dbas'); ?></h3>
            
            <?php
            $args = array(
                'echo' => true,
                'redirect' => get_permalink(get_option('dbas_portal_page_id')),
                'form_id' => 'dbas_loginform',
                'label_username' => __('Username', 'dbas'),
                'label_password' => __('Password', 'dbas'),
                'label_remember' => __('Remember Me', 'dbas'),
                'label_log_in' => __('Log In', 'dbas'),
                'remember' => true
            );
            wp_login_form($args);
            ?>
            
            <p class="dbas-register-link">
                <?php 
                $register_url = get_permalink(get_option('dbas_register_page_id'));
                printf(__('Not registered yet? <a href="%s">Register here</a>', 'dbas'), $register_url);
                ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_user_profile() {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        ob_start();
        ?>
        <div class="dbas-user-profile">
            <h3><?php _e('My Profile', 'dbas'); ?></h3>
            
            <?php if (isset($_GET['profile']) && $_GET['profile'] == 'updated'): ?>
                <div class="dbas-notice dbas-success">
                    <?php _e('Profile updated successfully!', 'dbas'); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('dbas_update_profile', 'dbas_profile_nonce'); ?>
                <input type="hidden" name="dbas_action" value="update_profile" />
                
                <div class="dbas-form-group">
                    <label for="first_name"><?php _e('First Name', 'dbas'); ?></label>
                    <input type="text" name="first_name" id="first_name" 
                           value="<?php echo esc_attr($user->first_name); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="last_name"><?php _e('Last Name', 'dbas'); ?></label>
                    <input type="text" name="last_name" id="last_name" 
                           value="<?php echo esc_attr($user->last_name); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="email"><?php _e('Email Address', 'dbas'); ?></label>
                    <input type="email" name="email" id="email" 
                           value="<?php echo esc_attr($user->user_email); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_phone"><?php _e('Phone Number', 'dbas'); ?></label>
                    <input type="text" name="dbas_phone" id="dbas_phone" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_phone', true)); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_street"><?php _e('Street Address', 'dbas'); ?></label>
                    <input type="text" name="dbas_street" id="dbas_street" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_street', true)); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_city"><?php _e('City', 'dbas'); ?></label>
                    <input type="text" name="dbas_city" id="dbas_city" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_city', true)); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_state"><?php _e('State', 'dbas'); ?></label>
                    <input type="text" name="dbas_state" id="dbas_state" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_state', true)); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label for="dbas_zip"><?php _e('Zip Code', 'dbas'); ?></label>
                    <input type="text" name="dbas_zip" id="dbas_zip" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_zip', true)); ?>" />
                </div>
                
                <!-- Emergency Contacts -->
                <h4><?php _e('Emergency Contacts', 'dbas'); ?></h4>
                <div id="dbas-emergency-contacts-frontend">
                    <?php $this->display_emergency_contacts($user_id); ?>
                </div>
                <button type="button" class="button" id="dbas-add-emergency-contact-frontend">
                    <?php _e('Add Emergency Contact', 'dbas'); ?>
                </button>
                
                <!-- Family Photo -->
                <div class="dbas-form-group">
                    <label for="family_photo"><?php _e('Family Photo', 'dbas'); ?></label>
                    <?php 
                    $photo_id = get_user_meta($user_id, 'dbas_family_photo', true);
                    if ($photo_id && $photo_url = wp_get_attachment_url($photo_id)):
                    ?>
                        <div class="dbas-current-photo">
                            <img src="<?php echo esc_url($photo_url); ?>" style="max-width: 200px;" />
                        </div>
                    <?php endif; ?>
                    <input type="file" name="family_photo" id="family_photo" accept="image/*" />
                </div>
                
                <div class="dbas-form-submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Update Profile', 'dbas'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#dbas-add-emergency-contact-frontend').on('click', function() {
                var html = '<div class="dbas-emergency-contact-item">';
                html += '<input type="text" name="dbas_emergency_name[]" placeholder="Name" />';
                html += '<input type="text" name="dbas_emergency_phone[]" placeholder="Phone" />';
                html += '<button type="button" class="button dbas-remove-contact">Remove</button>';
                html += '</div>';
                $('#dbas-emergency-contacts-frontend').append(html);
            });
            
            $(document).on('click', '.dbas-remove-contact', function() {
                $(this).closest('.dbas-emergency-contact-item').remove();
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function display_emergency_contacts($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_emergency_contacts';
        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        foreach ($contacts as $contact) {
            ?>
            <div class="dbas-emergency-contact-item">
                <input type="hidden" name="dbas_emergency_id[]" value="<?php echo $contact->id; ?>" />
                <input type="text" name="dbas_emergency_name[]" value="<?php echo esc_attr($contact->name); ?>" placeholder="Name" />
                <input type="text" name="dbas_emergency_phone[]" value="<?php echo esc_attr($contact->phone); ?>" placeholder="Phone" />
                <button type="button" class="button dbas-remove-contact"><?php _e('Remove', 'dbas'); ?></button>
            </div>
            <?php
        }
    }
    
    public function handle_registration() {
        if (!isset($_POST['dbas_action']) || $_POST['dbas_action'] !== 'register') {
            return;
        }
        
        if (!isset($_POST['dbas_register_nonce']) || !wp_verify_nonce($_POST['dbas_register_nonce'], 'dbas_register')) {
            return;
        }
        
        // Start output buffering to prevent header issues
        ob_start();
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = wp_generate_password();
        
        $userdata = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name'])
        );
        
        $user_id = wp_insert_user($userdata);
        
        if (!is_wp_error($user_id)) {
            // Save custom fields
            update_user_meta($user_id, 'dbas_phone', sanitize_text_field($_POST['dbas_phone']));
            update_user_meta($user_id, 'dbas_street', sanitize_text_field($_POST['dbas_street']));
            update_user_meta($user_id, 'dbas_city', sanitize_text_field($_POST['dbas_city']));
            update_user_meta($user_id, 'dbas_state', sanitize_text_field($_POST['dbas_state']));
            update_user_meta($user_id, 'dbas_zip', sanitize_text_field($_POST['dbas_zip']));
            
            // Save terms acceptance
            global $wpdb;
            $terms_table = $wpdb->prefix . 'dbas_terms_content';
            $acceptance_table = $wpdb->prefix . 'dbas_terms_acceptance';
            
            // Suppress database errors temporarily
            $wpdb->suppress_errors(true);
            
            // Get VALID terms only (exclude empty or zero term_ids)
            $terms = $wpdb->get_results("SELECT * FROM $terms_table WHERE is_active = 1 AND term_id != '' AND term_id != '0' AND term_id IS NOT NULL");
            
            if ($terms) {
                foreach ($terms as $term) {
                    // Skip if term_id is invalid
                    if (empty($term->term_id) || $term->term_id == '0') {
                        continue;
                    }
                    
                    if (isset($_POST['terms_' . $term->term_id])) {
                        // Check if already exists to avoid duplicate entry
                        $existing = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM $acceptance_table WHERE user_id = %d AND term_id = %s",
                            $user_id, $term->term_id
                        ));
                        
                        if (!$existing) {
                            // Save to terms acceptance table
                            $wpdb->insert($acceptance_table, array(
                                'user_id' => $user_id,
                                'term_id' => $term->term_id,
                                'term_version' => $term->term_version ?: '1.0',
                                'accepted' => 1,
                                'accepted_date' => current_time('mysql'),
                                'ip_address' => $_SERVER['REMOTE_ADDR']
                            ));
                        }
                        
                        // Also save as user meta for backward compatibility
                        update_user_meta($user_id, 'dbas_term_' . $term->term_id, '1');
                        update_user_meta($user_id, 'dbas_term_' . $term->term_id . '_date', current_time('mysql'));
                    }
                }
            } else {
                // Fallback to old method if no valid terms in database
                $terms = array('liability', 'vaccination', 'behavior', 'payment', 'pickup');
                foreach ($terms as $term) {
                    if (isset($_POST['terms_' . $term])) {
                        update_user_meta($user_id, 'dbas_term_' . $term, '1');
                        update_user_meta($user_id, 'dbas_term_' . $term . '_date', current_time('mysql'));
                    }
                }
            }
            
            // Re-enable database error reporting
            $wpdb->suppress_errors(false);
            
            // Send notification
            do_action('dbas_user_registered', $user_id);
            
            // Send password email
            wp_new_user_notification($user_id, null, 'both');
            
            // Clear any output
            ob_end_clean();
            
            // Redirect with success message
            $register_url = add_query_arg('registration', 'success', get_permalink(get_option('dbas_register_page_id')));
            wp_redirect($register_url);
            exit;
        } else {
            // Clear any output
            ob_end_clean();
            
            // Redirect with error message
            $register_url = add_query_arg('registration', 'failed', get_permalink(get_option('dbas_register_page_id')));
            wp_redirect($register_url);
            exit;
        }
    }
    
    public function handle_profile_update() {
        if (!isset($_POST['dbas_action']) || $_POST['dbas_action'] !== 'update_profile') {
            return;
        }
        
        if (!isset($_POST['dbas_profile_nonce']) || !wp_verify_nonce($_POST['dbas_profile_nonce'], 'dbas_update_profile')) {
            return;
        }
        
        if (!is_user_logged_in()) {
            return;
        }
        
        // Start output buffering to prevent header issues
        ob_start();
        
        $user_id = get_current_user_id();
        
        // Update WordPress user data
        $userdata = array(
            'ID' => $user_id,
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'user_email' => sanitize_email($_POST['email'])
        );
        
        wp_update_user($userdata);
        
        // Update custom fields
        update_user_meta($user_id, 'dbas_phone', sanitize_text_field($_POST['dbas_phone']));
        update_user_meta($user_id, 'dbas_street', sanitize_text_field($_POST['dbas_street']));
        update_user_meta($user_id, 'dbas_city', sanitize_text_field($_POST['dbas_city']));
        update_user_meta($user_id, 'dbas_state', sanitize_text_field($_POST['dbas_state']));
        update_user_meta($user_id, 'dbas_zip', sanitize_text_field($_POST['dbas_zip']));
        
        // Handle family photo upload
        if (!empty($_FILES['family_photo']['name'])) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            
            $uploadedfile = $_FILES['family_photo'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $attachment = array(
                    'post_mime_type' => $movefile['type'],
                    'post_title' => 'Family Photo - User ' . $user_id,
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                $attach_id = wp_insert_attachment($attachment, $movefile['file']);
                update_user_meta($user_id, 'dbas_family_photo', $attach_id);
            }
        }
        
        // Save emergency contacts
        $this->save_emergency_contacts($user_id);
        
        // Send notification
        do_action('dbas_user_updated', $user_id);
        
        // Clear any output
        ob_end_clean();
        
        // Redirect with success message
        $portal_url = add_query_arg('profile', 'updated', get_permalink(get_option('dbas_portal_page_id')));
        wp_redirect($portal_url);
        exit;
    }
    
    private function save_emergency_contacts($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_emergency_contacts';
        
        // Delete existing contacts
        $wpdb->delete($table, array('user_id' => $user_id));
        
        // Save new contacts
        if (isset($_POST['dbas_emergency_name']) && is_array($_POST['dbas_emergency_name'])) {
            $names = $_POST['dbas_emergency_name'];
            $phones = $_POST['dbas_emergency_phone'];
            
            for ($i = 0; $i < count($names); $i++) {
                if (!empty($names[$i]) && !empty($phones[$i])) {
                    $wpdb->insert($table, array(
                        'user_id' => $user_id,
                        'name' => sanitize_text_field($names[$i]),
                        'phone' => sanitize_text_field($phones[$i])
                    ));
                }
            }
        }
    }
    
    public function redirect_after_login($user_login, $user) {
        if (!isset($_POST['redirect_to']) || empty($_POST['redirect_to'])) {
            $portal_url = get_permalink(get_option('dbas_portal_page_id'));
            if ($portal_url) {
                wp_redirect($portal_url);
                exit;
            }
        }
    }
    
    /**
     * Render terms and conditions page
     */
    public function render_terms_page() {
        global $wpdb;
        $terms_table = $wpdb->prefix . 'dbas_terms_content';
        
        // Get VALID terms only
        $terms = $wpdb->get_results("SELECT * FROM $terms_table WHERE is_active = 1 AND term_id != '' AND term_id != '0' AND term_id IS NOT NULL ORDER BY display_order");
        
        ob_start();
        ?>
        <div class="dbas-terms-page">
            <h2><?php _e('Terms and Conditions', 'dbas'); ?></h2>
            
            <?php if ($terms): ?>
                <?php foreach ($terms as $index => $term): ?>
                    <div class="dbas-term-section">
                        <h3><?php echo ($index + 1) . '. ' . esc_html($term->term_title); ?></h3>
                        <div class="dbas-term-content">
                            <?php echo nl2br(esc_html($term->term_content)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (is_user_logged_in()): ?>
                    <?php 
                    $user_id = get_current_user_id();
                    $acceptance_table = $wpdb->prefix . 'dbas_terms_acceptance';
                    ?>
                    <div class="dbas-terms-status">
                        <h3><?php _e('Your Acceptance Status', 'dbas'); ?></h3>
                        <table class="dbas-terms-status-table">
                            <?php foreach ($terms as $term): ?>
                                <?php
                                $acceptance = $wpdb->get_row($wpdb->prepare(
                                    "SELECT * FROM $acceptance_table WHERE user_id = %d AND term_id = %s",
                                    $user_id, $term->term_id
                                ));
                                ?>
                                <tr>
                                    <td><?php echo esc_html($term->term_title); ?></td>
                                    <td>
                                        <?php if ($acceptance && $acceptance->accepted): ?>
                                            <span style="color: green;">✓ <?php _e('Accepted', 'dbas'); ?></span>
                                            <small><?php echo date('M j, Y', strtotime($acceptance->accepted_date)); ?></small>
                                        <?php else: ?>
                                            <span style="color: red;">✗ <?php _e('Not Accepted', 'dbas'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <p>
                            <a href="<?php echo get_permalink(get_option('dbas_portal_page_id')); ?>" class="button">
                                <?php _e('Update Your Acceptance in Profile', 'dbas'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p><?php _e('No terms and conditions are currently available.', 'dbas'); ?></p>
            <?php endif; ?>
        </div>
        
        <style>
            .dbas-terms-page { max-width: 800px; margin: 0 auto; padding: 20px; }
            .dbas-term-section { 
                margin-bottom: 30px; 
                padding: 20px; 
                background: #f9f9f9; 
                border-radius: 5px; 
                border: 1px solid #e0e0e0;
            }
            .dbas-term-section h3 { 
                color: #333; 
                margin-top: 0; 
                border-bottom: 2px solid #0073aa; 
                padding-bottom: 10px;
            }
            .dbas-term-content { 
                color: #555; 
                line-height: 1.6; 
                margin-top: 15px;
            }
            .dbas-terms-status { 
                margin-top: 30px; 
                padding: 20px; 
                background: #fff; 
                border: 1px solid #ddd; 
                border-radius: 5px;
            }
            .dbas-terms-status-table { 
                width: 100%; 
                margin-top: 15px;
            }
            .dbas-terms-status-table td { 
                padding: 8px; 
                border-bottom: 1px solid #eee;
            }
        </style>
        <?php
        return ob_get_clean();
    }
}
?>