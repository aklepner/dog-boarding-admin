<?php
/**
 * User Fields Management Class
 */

class DBAS_User_Fields {
    
    public function __construct() {
        // Add custom user fields
        add_action('show_user_profile', array($this, 'add_custom_user_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_user_fields'));
        add_action('user_new_form', array($this, 'add_custom_user_fields'));
        
        // Save custom user fields
        add_action('personal_options_update', array($this, 'save_custom_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_user_fields'));
        add_action('user_register', array($this, 'save_custom_user_fields'));
        
        // Registration form
        add_action('register_form', array($this, 'registration_form_fields'));
        add_filter('registration_errors', array($this, 'registration_errors'), 10, 3);
    }
    
    public function add_custom_user_fields($user) {
        $user_id = is_object($user) ? $user->ID : 0;
        ?>
        <h3><?php _e('Dog Boarding Information', 'dbas'); ?></h3>
        <table class="form-table">
            <!-- Address Fields -->
            <tr>
                <th><label for="dbas_street"><?php _e('Street Address', 'dbas'); ?></label></th>
                <td>
                    <input type="text" name="dbas_street" id="dbas_street" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_street', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="dbas_city"><?php _e('City', 'dbas'); ?></label></th>
                <td>
                    <input type="text" name="dbas_city" id="dbas_city" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_city', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="dbas_state"><?php _e('State', 'dbas'); ?></label></th>
                <td>
                    <input type="text" name="dbas_state" id="dbas_state" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_state', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="dbas_zip"><?php _e('Zip Code', 'dbas'); ?></label></th>
                <td>
                    <input type="text" name="dbas_zip" id="dbas_zip" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_zip', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="dbas_phone"><?php _e('Phone Number', 'dbas'); ?></label></th>
                <td>
                    <input type="text" name="dbas_phone" id="dbas_phone" 
                           value="<?php echo esc_attr(get_user_meta($user_id, 'dbas_phone', true)); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <!-- Not Allowed Back -->
            <tr>
                <th><label for="dbas_not_allowed"><?php _e('Not Allowed Back', 'dbas'); ?></label></th>
                <td>
                    <input type="checkbox" name="dbas_not_allowed" id="dbas_not_allowed" value="1" 
                           <?php checked(get_user_meta($user_id, 'dbas_not_allowed', true), '1'); ?> />
                    <span class="description"><?php _e('Check if this user\'s pets are not allowed back', 'dbas'); ?></span>
                </td>
            </tr>
            
            <!-- Family Photo -->
            <tr>
                <th><label for="dbas_family_photo"><?php _e('Family Photo', 'dbas'); ?></label></th>
                <td>
                    <?php 
                    $photo_id = get_user_meta($user_id, 'dbas_family_photo', true);
                    $photo_url = $photo_id ? wp_get_attachment_url($photo_id) : '';
                    ?>
                    <input type="hidden" name="dbas_family_photo" id="dbas_family_photo" value="<?php echo $photo_id; ?>" />
                    <button type="button" class="button dbas-upload-photo"><?php _e('Upload Photo', 'dbas'); ?></button>
                    <button type="button" class="button dbas-remove-photo" <?php echo !$photo_url ? 'style="display:none;"' : ''; ?>>
                        <?php _e('Remove Photo', 'dbas'); ?>
                    </button>
                    <div class="dbas-photo-preview">
                        <?php if ($photo_url): ?>
                            <img src="<?php echo esc_url($photo_url); ?>" style="max-width: 200px; margin-top: 10px;" />
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Emergency Contacts -->
        <h3><?php _e('Emergency Contacts', 'dbas'); ?></h3>
        <div id="dbas-emergency-contacts">
            <?php $this->display_emergency_contacts($user_id); ?>
        </div>
        <button type="button" class="button" id="dbas-add-emergency-contact">
            <?php _e('Add Emergency Contact', 'dbas'); ?>
        </button>
        
        <!-- Terms and Conditions -->
        <h3><?php _e('Terms and Conditions', 'dbas'); ?></h3>
        <table class="form-table">
            <?php $this->display_terms_checkboxes($user_id); ?>
        </table>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Photo upload
            $('.dbas-upload-photo').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var media_frame = wp.media({
                    title: '<?php _e("Select Family Photo", "dbas"); ?>',
                    button: { text: '<?php _e("Use this photo", "dbas"); ?>' },
                    multiple: false
                }).on('select', function() {
                    var attachment = media_frame.state().get('selection').first().toJSON();
                    $('#dbas_family_photo').val(attachment.id);
                    $('.dbas-photo-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; margin-top: 10px;" />');
                    $('.dbas-remove-photo').show();
                }).open();
            });
            
            $('.dbas-remove-photo').on('click', function(e) {
                e.preventDefault();
                $('#dbas_family_photo').val('');
                $('.dbas-photo-preview').html('');
                $(this).hide();
            });
            
            // Emergency contacts
            $('#dbas-add-emergency-contact').on('click', function() {
                var html = '<div class="dbas-emergency-contact-item">';
                html += '<input type="text" name="dbas_emergency_name[]" placeholder="Name" />';
                html += '<input type="text" name="dbas_emergency_phone[]" placeholder="Phone" />';
                html += '<button type="button" class="button dbas-remove-contact">Remove</button>';
                html += '</div>';
                $('#dbas-emergency-contacts').append(html);
            });
            
            $(document).on('click', '.dbas-remove-contact', function() {
                $(this).closest('.dbas-emergency-contact-item').remove();
            });
        });
        </script>
        <?php
    }
    
    public function save_custom_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Save address fields
        update_user_meta($user_id, 'dbas_street', sanitize_text_field($_POST['dbas_street'] ?? ''));
        update_user_meta($user_id, 'dbas_city', sanitize_text_field($_POST['dbas_city'] ?? ''));
        update_user_meta($user_id, 'dbas_state', sanitize_text_field($_POST['dbas_state'] ?? ''));
        update_user_meta($user_id, 'dbas_zip', sanitize_text_field($_POST['dbas_zip'] ?? ''));
        update_user_meta($user_id, 'dbas_phone', sanitize_text_field($_POST['dbas_phone'] ?? ''));
        
        // Save not allowed status
        $not_allowed = isset($_POST['dbas_not_allowed']) ? '1' : '0';
        update_user_meta($user_id, 'dbas_not_allowed', $not_allowed);
        
        // Save family photo
        if (isset($_POST['dbas_family_photo'])) {
            update_user_meta($user_id, 'dbas_family_photo', intval($_POST['dbas_family_photo']));
        }
        
        // Save emergency contacts
        $this->save_emergency_contacts($user_id);
        
        // Save terms acceptance
        $this->save_terms_acceptance($user_id);
        
        // Send notification email
        do_action('dbas_user_updated', $user_id);
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
    
    private function display_terms_checkboxes($user_id) {
        global $wpdb;
        $terms_table = $wpdb->prefix . 'dbas_terms_content';
        $acceptance_table = $wpdb->prefix . 'dbas_terms_acceptance';
        
        // Get active terms from database
        $terms = $wpdb->get_results("SELECT * FROM $terms_table WHERE is_active = 1 ORDER BY display_order");
        
        if ($terms) {
            foreach ($terms as $term) {
                // Check if user has accepted this term
                $accepted = $wpdb->get_var($wpdb->prepare(
                    "SELECT accepted FROM $acceptance_table WHERE user_id = %d AND term_id = %s",
                    $user_id, $term->term_id
                ));
                
                // Fallback to user meta if not in acceptance table
                if ($accepted === null) {
                    $accepted = get_user_meta($user_id, 'dbas_term_' . $term->term_id, true);
                }
                ?>
                <tr>
                    <th>
                        <label for="dbas_term_<?php echo esc_attr($term->term_id); ?>">
                            <?php echo esc_html($term->term_title); ?>
                            <?php if ($term->required): ?>
                                <span style="color: red;">*</span>
                            <?php endif; ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               name="dbas_term_<?php echo esc_attr($term->term_id); ?>" 
                               id="dbas_term_<?php echo esc_attr($term->term_id); ?>" 
                               value="1"
                               <?php checked($accepted, '1'); ?> />
                        <p class="description"><?php echo esc_html($term->term_content); ?></p>
                        <?php 
                        $accepted_date = $wpdb->get_var($wpdb->prepare(
                            "SELECT accepted_date FROM $acceptance_table WHERE user_id = %d AND term_id = %s",
                            $user_id, $term->term_id
                        ));
                        if ($accepted_date) {
                            echo '<small>' . sprintf(__('Accepted on: %s', 'dbas'), $accepted_date) . '</small>';
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
        } else {
            // Fallback to hardcoded terms
            $default_terms = $this->get_terms_and_conditions();
            foreach ($default_terms as $term_id => $term_text) {
                $accepted = get_user_meta($user_id, 'dbas_term_' . $term_id, true);
                ?>
                <tr>
                    <th>
                        <label for="dbas_term_<?php echo $term_id; ?>">
                            <?php echo esc_html($term_text); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" name="dbas_term_<?php echo $term_id; ?>" 
                               id="dbas_term_<?php echo $term_id; ?>" value="1"
                               <?php checked($accepted, '1'); ?> />
                    </td>
                </tr>
                <?php
            }
        }
    }
    
    private function save_terms_acceptance($user_id) {
        global $wpdb;
        $terms_table = $wpdb->prefix . 'dbas_terms_content';
        $acceptance_table = $wpdb->prefix . 'dbas_terms_acceptance';
        
        // Get all active terms
        $terms = $wpdb->get_results("SELECT * FROM $terms_table WHERE is_active = 1");
        
        if ($terms) {
            foreach ($terms as $term) {
                $accepted = isset($_POST['dbas_term_' . $term->term_id]) ? '1' : '0';
                
                // Check if record exists
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $acceptance_table WHERE user_id = %d AND term_id = %s",
                    $user_id, $term->term_id
                ));
                
                if ($existing) {
                    // Update existing record
                    $wpdb->update(
                        $acceptance_table,
                        array(
                            'accepted' => $accepted,
                            'accepted_date' => $accepted === '1' ? current_time('mysql') : $existing->accepted_date,
                            'term_version' => $term->term_version
                        ),
                        array('id' => $existing->id)
                    );
                } else if ($accepted === '1') {
                    // Insert new record only if accepted
                    $wpdb->insert(
                        $acceptance_table,
                        array(
                            'user_id' => $user_id,
                            'term_id' => $term->term_id,
                            'term_version' => $term->term_version,
                            'accepted' => 1,
                            'accepted_date' => current_time('mysql'),
                            'ip_address' => $_SERVER['REMOTE_ADDR']
                        )
                    );
                }
                
                // Also update user meta for backward compatibility
                update_user_meta($user_id, 'dbas_term_' . $term->term_id, $accepted);
                if ($accepted === '1') {
                    update_user_meta($user_id, 'dbas_term_' . $term->term_id . '_date', current_time('mysql'));
                }
            }
        } else {
            // Fallback to old method
            $default_terms = $this->get_terms_and_conditions();
            foreach ($default_terms as $term_id => $term_text) {
                $accepted = isset($_POST['dbas_term_' . $term_id]) ? '1' : '0';
                update_user_meta($user_id, 'dbas_term_' . $term_id, $accepted);
                
                if ($accepted === '1') {
                    update_user_meta($user_id, 'dbas_term_' . $term_id . '_date', current_time('mysql'));
                }
            }
        }
    }
    
    private function get_terms_and_conditions() {
        return array(
            'liability' => __('I accept the liability waiver', 'dbas'),
            'vaccination' => __('I confirm all vaccination records are up to date', 'dbas'),
            'behavior' => __('I agree to the behavioral policy', 'dbas'),
            'payment' => __('I accept the payment terms and conditions', 'dbas'),
            'pickup' => __('I agree to the pickup and drop-off policies', 'dbas')
        );
    }
    
    public function registration_form_fields() {
        ?>
        <p>
            <label for="dbas_phone"><?php _e('Phone Number', 'dbas'); ?><br />
                <input type="text" name="dbas_phone" id="dbas_phone" class="input" />
            </label>
        </p>
        <p>
            <label for="dbas_street"><?php _e('Street Address', 'dbas'); ?><br />
                <input type="text" name="dbas_street" id="dbas_street" class="input" />
            </label>
        </p>
        <p>
            <label for="dbas_city"><?php _e('City', 'dbas'); ?><br />
                <input type="text" name="dbas_city" id="dbas_city" class="input" />
            </label>
        </p>
        <p>
            <label for="dbas_state"><?php _e('State', 'dbas'); ?><br />
                <input type="text" name="dbas_state" id="dbas_state" class="input" />
            </label>
        </p>
        <p>
            <label for="dbas_zip"><?php _e('Zip Code', 'dbas'); ?><br />
                <input type="text" name="dbas_zip" id="dbas_zip" class="input" />
            </label>
        </p>
        <?php
    }
    
    public function registration_errors($errors, $sanitized_user_login, $user_email) {
        if (empty($_POST['dbas_phone'])) {
            $errors->add('dbas_phone_error', __('Please enter your phone number.', 'dbas'));
        }
        return $errors;
    }
}
?>