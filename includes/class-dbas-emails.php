<?php
/**
 * Email Notifications Class
 * Fixed version that ensures WordPress default emails are sent
 */

class DBAS_Emails {
    
    private $admin_email;
    
    public function __construct() {
        $this->admin_email = get_option('dbas_admin_email', get_option('admin_email'));
        
        // Hook into events with LOWER priority (higher number) to ensure WordPress emails go first
        add_action('dbas_user_registered', array($this, 'send_user_registration_email'), 50);
        add_action('dbas_user_updated', array($this, 'send_user_update_email'), 50);
        add_action('dbas_dog_added', array($this, 'send_dog_registration_email'), 50, 2);
        add_action('dbas_dog_updated', array($this, 'send_dog_update_email'), 50, 2);
        add_action('dbas_dog_deleted', array($this, 'send_dog_deletion_email'), 50, 2);
        
        // Add filter to ensure WordPress emails aren't blocked
        add_filter('wp_mail_from', array($this, 'ensure_mail_from'), 1);
        add_filter('wp_mail_from_name', array($this, 'ensure_mail_from_name'), 1);
        
        // Debug logging for email issues
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_mail_failed', array($this, 'log_mail_error'));
        }
    }
    
    /**
     * Log mail errors for debugging
     */
    public function log_mail_error($error) {
        error_log('DBAS Mail Error: ' . print_r($error, true));
    }
    
    /**
     * Ensure mail from address doesn't break WordPress emails
     */
    public function ensure_mail_from($email) {
        // Only modify if it's our plugin sending the email
        if (empty($email) || $email == 'wordpress@' . $_SERVER['HTTP_HOST']) {
            return $this->admin_email;
        }
        return $email;
    }
    
    /**
     * Ensure mail from name doesn't break WordPress emails
     */
    public function ensure_mail_from_name($name) {
        // Only modify if it's the default WordPress name
        if ($name == 'WordPress') {
            return get_bloginfo('name');
        }
        return $name;
    }
    
    /**
     * Send email when a new user registers
     * This is IN ADDITION to WordPress default emails
     */
    public function send_user_registration_email($user_id) {
        // Check if notifications are enabled
        if (!get_option('dbas_notify_user_registration', 1)) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        // Add a small delay to ensure WordPress emails go first
        sleep(1);
        
        // Email to admin with additional dog boarding info
        $admin_subject = sprintf(__('[%s] New Dog Owner Registration Details', 'dbas'), get_bloginfo('name'));
        $admin_message = $this->get_user_registration_admin_message($user);
        
        wp_mail(
            $this->admin_email,
            $admin_subject,
            $admin_message,
            $this->get_email_headers()
        );
        
        // Send a supplementary welcome email to user (not password info)
        $user_subject = sprintf(__('Welcome to %s Dog Boarding Services', 'dbas'), get_bloginfo('name'));
        $user_message = $this->get_user_registration_user_message($user);
        
        wp_mail(
            $user->user_email,
            $user_subject,
            $user_message,
            $this->get_email_headers()
        );
    }
    
    /**
     * Send email when user updates their profile
     */
    public function send_user_update_email($user_id) {
        if (!get_option('dbas_notify_updates', 1)) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        // Email to admin
        $admin_subject = sprintf(__('[%s] User Profile Updated', 'dbas'), get_bloginfo('name'));
        $admin_message = sprintf(
            __("User profile has been updated.\n\n" .
               "User: %s\n" .
               "Email: %s\n" .
               "Phone: %s\n" .
               "Date: %s\n\n" .
               "View user profile: %s", 'dbas'),
            $user->display_name,
            $user->user_email,
            get_user_meta($user_id, 'dbas_phone', true),
            current_time('mysql'),
            admin_url('user-edit.php?user_id=' . $user_id)
        );
        
        wp_mail(
            $this->admin_email,
            $admin_subject,
            $admin_message,
            $this->get_email_headers()
        );
        
        // Email to user
        $user_subject = sprintf(__('Your Profile Has Been Updated - %s', 'dbas'), get_bloginfo('name'));
        $user_message = sprintf(
            __("Dear %s,\n\n" .
               "Your profile has been successfully updated.\n\n" .
               "If you did not make these changes, please contact us immediately.\n\n" .
               "Thank you,\n%s", 'dbas'),
            $user->first_name,
            get_bloginfo('name')
        );
        
        wp_mail(
            $user->user_email,
            $user_subject,
            $user_message,
            $this->get_email_headers()
        );
    }
    
    /**
     * Send email when a new dog is registered
     */
    public function send_dog_registration_email($dog_id, $owner_id) {
        if (!get_option('dbas_notify_dog_registration', 1)) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        $dog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $dog_id));
        
        if (!$dog) return;
        
        $owner = get_userdata($owner_id);
        if (!$owner) return;
        
        // Email to admin
        $admin_subject = sprintf(__('[%s] New Dog Registration', 'dbas'), get_bloginfo('name'));
        $admin_message = sprintf(
            __("A new dog has been registered.\n\n" .
               "Owner: %s\n" .
               "Email: %s\n" .
               "Phone: %s\n\n" .
               "Dog Information:\n" .
               "Name: %s\n" .
               "Breed: %s\n" .
               "Status: %s\n" .
               "Registration Date: %s\n\n" .
               "Please review and approve the registration.\n" .
               "Edit dog: %s", 'dbas'),
            $owner->display_name,
            $owner->user_email,
            get_user_meta($owner_id, 'dbas_phone', true),
            $dog->name,
            $dog->breed,
            $dog->status,
            current_time('mysql'),
            admin_url('admin.php?page=dbas-dogs&action=edit&dog_id=' . $dog_id)
        );
        
        wp_mail(
            $this->admin_email,
            $admin_subject,
            $admin_message,
            $this->get_email_headers()
        );
        
        // Email to owner
        $user_subject = sprintf(__('Dog Registration Received - %s', 'dbas'), get_bloginfo('name'));
        $user_message = sprintf(
            __("Dear %s,\n\n" .
               "We have received the registration for %s.\n\n" .
               "Your dog's registration is currently pending approval. " .
               "We will review the submitted information and documentation, and notify you once approved.\n\n" .
               "Dog Information:\n" .
               "Name: %s\n" .
               "Breed: %s\n" .
               "Status: Pending Approval\n\n" .
               "You can view and manage your dogs by logging into your portal:\n%s\n\n" .
               "Thank you,\n%s", 'dbas'),
            $owner->first_name,
            $dog->name,
            $dog->name,
            $dog->breed,
            get_permalink(get_option('dbas_portal_page_id')),
            get_bloginfo('name')
        );
        
        wp_mail(
            $owner->user_email,
            $user_subject,
            $user_message,
            $this->get_email_headers()
        );
    }
    
    /**
     * Send email when dog information is updated
     */
    public function send_dog_update_email($dog_id, $owner_id) {
        if (!get_option('dbas_notify_updates', 1)) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        $dog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $dog_id));
        
        if (!$dog) return;
        
        $owner = get_userdata($owner_id);
        if (!$owner) return;
        
        // Email to admin
        $admin_subject = sprintf(__('[%s] Dog Information Updated', 'dbas'), get_bloginfo('name'));
        $admin_message = sprintf(
            __("Dog information has been updated.\n\n" .
               "Owner: %s\n" .
               "Dog Name: %s\n" .
               "Status: %s\n" .
               "Update Date: %s\n\n" .
               "View dog details: %s", 'dbas'),
            $owner->display_name,
            $dog->name,
            $dog->status,
            current_time('mysql'),
            admin_url('admin.php?page=dbas-dogs&action=edit&dog_id=' . $dog_id)
        );
        
        wp_mail(
            $this->admin_email,
            $admin_subject,
            $admin_message,
            $this->get_email_headers()
        );
        
        // Email to owner
        $user_subject = sprintf(__('%s\'s Information Updated - %s', 'dbas'), $dog->name, get_bloginfo('name'));
        $user_message = sprintf(
            __("Dear %s,\n\n" .
               "%s's information has been successfully updated.\n\n" .
               "Current Status: %s\n\n" .
               "You can view and manage your dogs by logging into your portal:\n%s\n\n" .
               "Thank you,\n%s", 'dbas'),
            $owner->first_name,
            $dog->name,
            ucfirst($dog->status),
            get_permalink(get_option('dbas_portal_page_id')),
            get_bloginfo('name')
        );
        
        wp_mail(
            $owner->user_email,
            $user_subject,
            $user_message,
            $this->get_email_headers()
        );
    }
    
    /**
     * Send email when a dog is deleted
     */
    public function send_dog_deletion_email($dog_id, $owner_id) {
        if (!get_option('dbas_notify_updates', 1)) {
            return;
        }
        
        $owner = get_userdata($owner_id);
        if (!$owner) return;
        
        // Email to admin
        $admin_subject = sprintf(__('[%s] Dog Removed from System', 'dbas'), get_bloginfo('name'));
        $admin_message = sprintf(
            __("A dog has been removed from the system.\n\n" .
               "Owner: %s\n" .
               "Email: %s\n" .
               "Dog ID: %s\n" .
               "Deletion Date: %s", 'dbas'),
            $owner->display_name,
            $owner->user_email,
            $dog_id,
            current_time('mysql')
        );
        
        wp_mail(
            $this->admin_email,
            $admin_subject,
            $admin_message,
            $this->get_email_headers()
        );
        
        // Email to owner
        $user_subject = sprintf(__('Dog Removed from Our Records - %s', 'dbas'), get_bloginfo('name'));
        $user_message = sprintf(
            __("Dear %s,\n\n" .
               "Your dog has been successfully removed from our records.\n\n" .
               "If this was done in error, please contact us immediately.\n\n" .
               "Thank you,\n%s", 'dbas'),
            $owner->first_name,
            get_bloginfo('name')
        );
        
        wp_mail(
            $owner->user_email,
            $user_subject,
            $user_message,
            $this->get_email_headers()
        );
    }
    
    /**
     * Get email template for user registration (admin)
     */
    private function get_user_registration_admin_message($user) {
        $message = sprintf(
            __("Additional Dog Owner Registration Details:\n\n" .
               "User Details:\n" .
               "Username: %s (Email used as login)\n" .
               "Name: %s %s\n" .
               "Email: %s\n" .
               "Phone: %s\n" .
               "Address: %s, %s, %s %s\n" .
               "Registration Date: %s\n\n" .
               "Emergency Contacts:\n%s\n\n" .
               "View user profile: %s\n\n" .
               "Note: This is a supplementary notification from the Dog Boarding plugin.\n" .
               "The standard WordPress admin notification has also been sent.", 'dbas'),
            $user->user_login,
            $user->first_name,
            $user->last_name,
            $user->user_email,
            get_user_meta($user->ID, 'dbas_phone', true),
            get_user_meta($user->ID, 'dbas_street', true),
            get_user_meta($user->ID, 'dbas_city', true),
            get_user_meta($user->ID, 'dbas_state', true),
            get_user_meta($user->ID, 'dbas_zip', true),
            current_time('mysql'),
            $this->get_emergency_contacts_text($user->ID),
            admin_url('user-edit.php?user_id=' . $user->ID)
        );
        
        return $message;
    }
    
    /**
     * Get email template for user registration (user)
     */
    private function get_user_registration_user_message($user) {
        $portal_url = get_permalink(get_option('dbas_portal_page_id'));
        $login_url = get_permalink(get_option('dbas_login_page_id'));
        
        $message = sprintf(
            __("Dear %s,\n\n" .
               "Welcome to %s Dog Boarding Services!\n\n" .
               "Your account has been successfully created. You can now log in using:\n" .
               "- Email: %s\n" .
               "- Password: The password you created during registration\n\n" .
               "Portal Access:\n" .
               "Login URL: %s\n" .
               "Portal URL: %s\n\n" .
               "Getting Started:\n" .
               "1. Log in to your portal\n" .
               "2. Complete your profile information\n" .
               "3. Register your dog(s)\n" .
               "4. Upload required documentation (vaccination records, etc.)\n\n" .
               "Important: All dogs must have up-to-date vaccination records and pass our review process before boarding.\n\n" .
               "If you have any questions, please don't hesitate to contact us.\n\n" .
               "Note: You should also receive a standard WordPress notification email.\n\n" .
               "Thank you for choosing %s!\n\n" .
               "Best regards,\n" .
               "The %s Team", 'dbas'),
            $user->first_name,
            get_bloginfo('name'),
            $user->user_email,
            $login_url,
            $portal_url,
            get_bloginfo('name'),
            get_bloginfo('name')
        );
        
        return $message;
    }
    
    /**
     * Get emergency contacts as text
     */
    private function get_emergency_contacts_text($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_emergency_contacts';
        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if (empty($contacts)) {
            return __('No emergency contacts provided', 'dbas');
        }
        
        $text = '';
        foreach ($contacts as $contact) {
            $text .= sprintf("- %s: %s\n", $contact->name, $contact->phone);
        }
        
        return $text;
    }
    
    /**
     * Get email headers
     */
    private function get_email_headers() {
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' Dog Boarding <' . $this->admin_email . '>'
        );
        
        return $headers;
    }
    
    /**
     * Send custom email
     */
    public function send_custom_email($to, $subject, $message, $headers = array()) {
        if (empty($headers)) {
            $headers = $this->get_email_headers();
        }
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send status change notification
     */
    public function send_status_change_notification($dog_id, $old_status, $new_status) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_dogs';
        $dog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $dog_id));
        
        if (!$dog) return;
        
        $owner = get_userdata($dog->owner_id);
        if (!$owner) return;
        
        // Only send if status actually changed
        if ($old_status == $new_status) return;
        
        $subject = sprintf(__('%s Status Update - %s', 'dbas'), $dog->name, get_bloginfo('name'));
        
        if ($new_status == 'active') {
            $message = sprintf(
                __("Dear %s,\n\n" .
                   "Great news! %s has been approved for boarding.\n\n" .
                   "All documentation has been verified and %s is now eligible for our boarding services.\n\n" .
                   "You can schedule boarding appointments by contacting us or through your online portal:\n%s\n\n" .
                   "Thank you for choosing %s!\n\n" .
                   "Best regards,\n" .
                   "The %s Team", 'dbas'),
                $owner->first_name,
                $dog->name,
                $dog->name,
                get_permalink(get_option('dbas_portal_page_id')),
                get_bloginfo('name'),
                get_bloginfo('name')
            );
        } else {
            $message = sprintf(
                __("Dear %s,\n\n" .
                   "%s's status has been updated to: %s\n\n" .
                   "This may affect boarding eligibility. Please log in to your portal for more details:\n%s\n\n" .
                   "If you have questions about this status change, please contact us.\n\n" .
                   "Thank you,\n%s", 'dbas'),
                $owner->first_name,
                $dog->name,
                ucfirst($new_status),
                get_permalink(get_option('dbas_portal_page_id')),
                get_bloginfo('name')
            );
        }
        
        wp_mail(
            $owner->user_email,
            $subject,
            $message,
            $this->get_email_headers()
        );
    }
    
    /**
     * Test email functionality (for debugging)
     */
    public function test_email_system($to_email = null) {
        if (!$to_email) {
            $to_email = $this->admin_email;
        }
        
        $subject = 'Test Email from Dog Boarding Plugin';
        $message = "This is a test email to verify that the email system is working properly.\n\n";
        $message .= "If you receive this email, the plugin's email functionality is working.\n\n";
        $message .= "Sent at: " . current_time('mysql');
        
        $result = wp_mail($to_email, $subject, $message, $this->get_email_headers());
        
        if ($result) {
            error_log('DBAS: Test email sent successfully to ' . $to_email);
        } else {
            error_log('DBAS: Failed to send test email to ' . $to_email);
        }
        
        return $result;
    }
}
?>