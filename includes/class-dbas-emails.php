<?php
/**
 * Email Notifications Class
 */

class DBAS_Emails {
    
    private $admin_email;
    
    public function __construct() {
        $this->admin_email = get_option('dbas_admin_email', get_option('admin_email'));
        
        // Hook into events
        add_action('dbas_user_registered', array($this, 'send_user_registration_email'));
        add_action('dbas_user_updated', array($this, 'send_user_update_email'));
        add_action('dbas_dog_added', array($this, 'send_dog_registration_email'), 10, 2);
        add_action('dbas_dog_updated', array($this, 'send_dog_update_email'), 10, 2);
        add_action('dbas_dog_deleted', array($this, 'send_dog_deletion_email'), 10, 2);
    }
    
    /**
     * Send email when a new user registers
     */
    public function send_user_registration_email($user_id) {
        if (!get_option('dbas_notify_user_registration', 1)) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        // Email to admin
        $admin_subject = sprintf(__('[%s] New Dog Owner Registration', 'dbas'), get_bloginfo('name'));
        $admin_message = $this->get_user_registration_admin_message($user);
        
        wp_mail(
            $this->admin_email,
            $admin_subject,
            $admin_message,
            $this->get_email_headers()
        );
        
        // Email to user
        $user_subject = sprintf(__('Welcome to %s Dog Boarding', 'dbas'), get_bloginfo('name'));
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
            __("A new dog owner has registered.\n\n" .
               "User Details:\n" .
               "Username: %s\n" .
               "Name: %s %s\n" .
               "Email: %s\n" .
               "Phone: %s\n" .
               "Address: %s, %s, %s %s\n" .
               "Registration Date: %s\n\n" .
               "Emergency Contacts:\n%s\n\n" .
               "View user profile: %s", 'dbas'),
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
     * UPDATED: Clear explanation about password reset process
     */
    private function get_user_registration_user_message($user) {
        $portal_url = get_permalink(get_option('dbas_portal_page_id'));
        
        $message = sprintf(
            __("Dear %s,\n\n" .
               "Welcome to %s Dog Boarding!\n\n" .
               "Your account has been successfully created.\n\n" .
               "Your username: %s\n\n" .
               "IMPORTANT: Password Setup\n" .
               "=========================================\n" .
               "For security reasons, you will receive a SEPARATE email from WordPress with a link to set your password.\n" .
               "Please check your email for a message with the subject \"[%s] Login Details\" or \"Password Reset\".\n" .
               "This email may take a few minutes to arrive and could be in your spam folder.\n\n" .
               "Once you've set your password, you can log in here:\n%s\n\n" .
               "Getting Started:\n" .
               "1. Check your email and click the password reset link\n" .
               "2. Create your secure password\n" .
               "3. Log in to your portal\n" .
               "4. Complete your profile information\n" .
               "5. Register your dog(s)\n" .
               "6. Upload required documentation (vaccination records, etc.)\n\n" .
               "Important: All dogs must have up-to-date vaccination records and pass our review process before boarding.\n\n" .
               "If you don't receive the password setup email within 10 minutes:\n" .
               "- Check your spam/junk folder\n" .
               "- Try the \"Lost your password?\" link on the login page\n" .
               "- Contact us for assistance\n\n" .
               "If you have any questions, please don't hesitate to contact us.\n\n" .
               "Thank you for choosing %s!\n\n" .
               "Best regards,\n" .
               "The %s Team", 'dbas'),
            $user->first_name,
            get_bloginfo('name'),
            $user->user_login,
            get_bloginfo('name'),
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
            'From: ' . get_bloginfo('name') . ' <' . $this->admin_email . '>'
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
}
?>