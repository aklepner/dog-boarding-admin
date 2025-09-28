<?php
/**
 * Reservations Database Management Class
 */

class DBAS_Reservations_Database {
    
    /**
     * Create reservation tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Daycare check-ins table
        $table_daycare = $wpdb->prefix . 'dbas_daycare_checkins';
        $sql_daycare = "CREATE TABLE IF NOT EXISTS $table_daycare (
            id int(11) NOT NULL AUTO_INCREMENT,
            dog_id int(11) NOT NULL,
            owner_id bigint(20) UNSIGNED NOT NULL,
            checkin_date date NOT NULL,
            checkin_time time,
            checkout_time time,
            is_fed tinyint(1) DEFAULT 0,
            fed_time time,
            price decimal(10,2) NOT NULL,
            notes text,
            status varchar(20) DEFAULT 'checked_in',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY dog_id (dog_id),
            KEY owner_id (owner_id),
            KEY checkin_date (checkin_date),
            KEY status (status)
        ) $charset_collate;";
        
        // Boarding reservations table
        $table_reservations = $wpdb->prefix . 'dbas_reservations';
        $sql_reservations = "CREATE TABLE IF NOT EXISTS $table_reservations (
            id int(11) NOT NULL AUTO_INCREMENT,
            owner_id bigint(20) UNSIGNED NOT NULL,
            checkin_date date NOT NULL,
            checkout_date date NOT NULL,
            pickup_time varchar(20) NOT NULL DEFAULT 'evening',
            total_dogs int(11) NOT NULL DEFAULT 1,
            boarding_price decimal(10,2) NOT NULL,
            daycare_price decimal(10,2) NOT NULL,
            total_price decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            admin_notes text,
            customer_notes text,
            payment_status varchar(20) DEFAULT 'unpaid',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY owner_id (owner_id),
            KEY checkin_date (checkin_date),
            KEY checkout_date (checkout_date),
            KEY status (status)
        ) $charset_collate;";
        
        // Reservation dogs table (links reservations to specific dogs)
        $table_reservation_dogs = $wpdb->prefix . 'dbas_reservation_dogs';
        $sql_reservation_dogs = "CREATE TABLE IF NOT EXISTS $table_reservation_dogs (
            id int(11) NOT NULL AUTO_INCREMENT,
            reservation_id int(11) NOT NULL,
            dog_id int(11) NOT NULL,
            kennel_number varchar(50),
            special_instructions text,
            PRIMARY KEY (id),
            KEY reservation_id (reservation_id),
            KEY dog_id (dog_id),
            UNIQUE KEY reservation_dog (reservation_id, dog_id)
        ) $charset_collate;";
        
        // Pricing table
        $table_pricing = $wpdb->prefix . 'dbas_pricing';
        $sql_pricing = "CREATE TABLE IF NOT EXISTS $table_pricing (
            id int(11) NOT NULL AUTO_INCREMENT,
            service_type varchar(20) NOT NULL,
            dog_count int(11) NOT NULL,
            price decimal(10,2) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_dog_count (service_type, dog_count, is_active)
        ) $charset_collate;";
        
        // Email templates table
        $table_email_templates = $wpdb->prefix . 'dbas_email_templates';
        $sql_email_templates = "CREATE TABLE IF NOT EXISTS $table_email_templates (
            id int(11) NOT NULL AUTO_INCREMENT,
            template_key varchar(50) NOT NULL,
            template_name varchar(100) NOT NULL,
            subject varchar(255) NOT NULL,
            body text NOT NULL,
            variables text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_key (template_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_daycare);
        dbDelta($sql_reservations);
        dbDelta($sql_reservation_dogs);
        dbDelta($sql_pricing);
        dbDelta($sql_email_templates);
        
        // Insert default pricing
        self::insert_default_pricing();
        
        // Insert default email templates
        self::insert_default_email_templates();
    }
    
    /**
     * Insert default pricing
     */
    public static function insert_default_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_pricing';
        
        // Check if pricing already exists
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }
        
        // Daycare pricing
        $daycare_prices = array(
            array('service_type' => 'daycare', 'dog_count' => 1, 'price' => 33.76),
            array('service_type' => 'daycare', 'dog_count' => 2, 'price' => 26.73),
            array('service_type' => 'daycare', 'dog_count' => 3, 'price' => 25.32),
            array('service_type' => 'daycare', 'dog_count' => 4, 'price' => 24.39),
        );
        
        // Boarding pricing
        $boarding_prices = array(
            array('service_type' => 'boarding', 'dog_count' => 1, 'price' => 37.05),
            array('service_type' => 'boarding', 'dog_count' => 2, 'price' => 29.94),
            array('service_type' => 'boarding', 'dog_count' => 3, 'price' => 28.76),
            array('service_type' => 'boarding', 'dog_count' => 4, 'price' => 28.14),
        );
        
        foreach ($daycare_prices as $price) {
            $wpdb->insert($table, $price);
        }
        
        foreach ($boarding_prices as $price) {
            $wpdb->insert($table, $price);
        }
    }
    
    /**
     * Insert default email templates
     */
    public static function insert_default_email_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_email_templates';
        
        // Check if templates already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }
        
        $templates = array(
            array(
                'template_key' => 'reservation_pending_user',
                'template_name' => 'Reservation Pending - User Notification',
                'subject' => 'Your Boarding Reservation Request - {facility_name}',
                'body' => "Dear {owner_name},\n\nWe have received your boarding reservation request for the following dates:\n\nCheck-in: {checkin_date}\nCheck-out: {checkout_date}\nNumber of Dogs: {dog_count}\nPickup Time: {pickup_time}\n\nTotal Estimated Cost: ${total_price}\n(Boarding: ${boarding_price} + Daycare: ${daycare_price})\n\nYour reservation is currently pending approval. We will review your request and notify you once it has been processed.\n\nReservation Details:\n{dog_list}\n\nCustomer Notes: {customer_notes}\n\nThank you for choosing {facility_name}!\n\nBest regards,\nThe {facility_name} Team",
                'variables' => '{owner_name}, {facility_name}, {checkin_date}, {checkout_date}, {dog_count}, {pickup_time}, {total_price}, {boarding_price}, {daycare_price}, {dog_list}, {customer_notes}'
            ),
            array(
                'template_key' => 'reservation_pending_admin',
                'template_name' => 'Reservation Pending - Admin Notification',
                'subject' => 'New Boarding Reservation Request',
                'body' => "A new boarding reservation request has been submitted.\n\nOwner: {owner_name}\nEmail: {owner_email}\nPhone: {owner_phone}\n\nReservation Details:\nCheck-in: {checkin_date}\nCheck-out: {checkout_date}\nNumber of Dogs: {dog_count}\nPickup Time: {pickup_time}\n\nDogs:\n{dog_list}\n\nTotal Estimated Cost: ${total_price}\n(Boarding: ${boarding_price} + Daycare: ${daycare_price})\n\nCustomer Notes: {customer_notes}\n\nPlease review and approve/reject this reservation:\n{admin_link}",
                'variables' => '{owner_name}, {owner_email}, {owner_phone}, {checkin_date}, {checkout_date}, {dog_count}, {pickup_time}, {total_price}, {boarding_price}, {daycare_price}, {dog_list}, {customer_notes}, {admin_link}'
            ),
            array(
                'template_key' => 'reservation_approved',
                'template_name' => 'Reservation Approved',
                'subject' => 'Your Boarding Reservation is Confirmed - {facility_name}',
                'body' => "Dear {owner_name},\n\nGreat news! Your boarding reservation has been approved.\n\nConfirmed Reservation Details:\nCheck-in: {checkin_date}\nCheck-out: {checkout_date}\nNumber of Dogs: {dog_count}\nPickup Time: {pickup_time}\n\nTotal Cost: ${total_price}\n(Boarding: ${boarding_price} + Daycare: ${daycare_price})\n\nDogs:\n{dog_list}\n\nPlease ensure all vaccination records are up to date before check-in.\n\nCheck-in Time: 7:00 AM - 10:00 AM\nCheck-out Time: {pickup_time_range}\n\nIf you need to make any changes to your reservation, please contact us immediately.\n\nThank you for choosing {facility_name}!\n\nBest regards,\nThe {facility_name} Team",
                'variables' => '{owner_name}, {facility_name}, {checkin_date}, {checkout_date}, {dog_count}, {pickup_time}, {pickup_time_range}, {total_price}, {boarding_price}, {daycare_price}, {dog_list}'
            ),
            array(
                'template_key' => 'reservation_rejected',
                'template_name' => 'Reservation Rejected',
                'subject' => 'Boarding Reservation Update - {facility_name}',
                'body' => "Dear {owner_name},\n\nWe regret to inform you that we are unable to accommodate your boarding reservation request for the following dates:\n\nCheck-in: {checkin_date}\nCheck-out: {checkout_date}\n\nReason: {rejection_reason}\n\nWe apologize for any inconvenience this may cause. Please contact us to discuss alternative dates or if you have any questions.\n\nThank you for your understanding.\n\nBest regards,\nThe {facility_name} Team",
                'variables' => '{owner_name}, {facility_name}, {checkin_date}, {checkout_date}, {rejection_reason}'
            )
        );
        
        foreach ($templates as $template) {
            $wpdb->insert($table, $template);
        }
    }
    
    /**
     * Get pricing for service
     */
    public static function get_pricing($service_type, $dog_count) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_pricing';
        
        $price = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $table WHERE service_type = %s AND dog_count = %d AND is_active = 1",
            $service_type,
            min($dog_count, 4) // Cap at 4 dogs
        ));
        
        return $price ?: 0;
    }
    
    /**
     * Calculate reservation total
     */
    public static function calculate_reservation_total($checkin_date, $checkout_date, $dog_count, $pickup_time = 'evening') {
        $checkin = new DateTime($checkin_date);
        $checkout = new DateTime($checkout_date);
        $interval = $checkin->diff($checkout);
        $nights = $interval->days;
        
        // Get pricing
        $boarding_price_per_night = self::get_pricing('boarding', $dog_count);
        $daycare_price_per_day = self::get_pricing('daycare', $dog_count);
        
        // Calculate boarding cost (per dog per night)
        $boarding_total = $boarding_price_per_night * $dog_count * $nights;
        
        // Calculate daycare cost
        // Dogs get charged daycare for each day they're at the facility
        // If pickup is in the evening of checkout day, they get charged daycare for that day too
        $daycare_days = $nights; // They're there during the day for each night they stay
        if ($pickup_time == 'evening') {
            $daycare_days++; // Add the checkout day if picking up in evening
        }
        $daycare_total = $daycare_price_per_day * $dog_count * $daycare_days;
        
        return array(
            'nights' => $nights,
            'daycare_days' => $daycare_days,
            'boarding_price' => $boarding_total,
            'daycare_price' => $daycare_total,
            'total_price' => $boarding_total + $daycare_total,
            'price_per_dog_per_night' => $boarding_price_per_night,
            'price_per_dog_per_day' => $daycare_price_per_day
        );
    }
}
?>