<?php
/**
 * Reservation Management Class
 */

class DBAS_Reservation_Manager {
    
    public function __construct() {
        // Add shortcodes
        add_shortcode('dbas_reservation_form', array($this, 'render_reservation_form'));
        add_shortcode('dbas_my_reservations', array($this, 'render_user_reservations'));
        
        // AJAX handlers
        add_action('wp_ajax_dbas_calculate_reservation', array($this, 'ajax_calculate_reservation'));
        add_action('wp_ajax_dbas_submit_reservation', array($this, 'ajax_submit_reservation'));
        add_action('wp_ajax_dbas_cancel_reservation', array($this, 'ajax_cancel_reservation'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_dbas_approve_reservation', array($this, 'ajax_approve_reservation'));
        add_action('wp_ajax_dbas_reject_reservation', array($this, 'ajax_reject_reservation'));
        add_action('wp_ajax_dbas_update_kennel', array($this, 'ajax_update_kennel'));
    }
    
    /**
     * Render reservation form
     */
    public function render_reservation_form() {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to make a reservation.', 'dbas') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $dog_manager = new DBAS_Dog_Manager();
        $dogs = $dog_manager->get_user_dogs($user_id);
        
        // Only show active dogs
        $active_dogs = array_filter($dogs, function($dog) {
            return $dog->status === 'active';
        });
        
        if (empty($active_dogs)) {
            return '<p>' . __('You need to have at least one approved dog to make a reservation.', 'dbas') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="dbas-reservation-form-container">
            <h2><?php _e('Make a Boarding Reservation', 'dbas'); ?></h2>
            
            <form id="dbas-reservation-form" class="dbas-form">
                <?php wp_nonce_field('dbas_reservation', 'dbas_reservation_nonce'); ?>
                
                <div class="dbas-form-group">
                    <label for="checkin_date"><?php _e('Check-in Date', 'dbas'); ?> *</label>
                    <input type="date" id="checkin_date" name="checkin_date" required 
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" />
                    <p class="description"><?php _e('Check-in time is between 7:00 AM - 10:00 AM', 'dbas'); ?></p>
                </div>
                
                <div class="dbas-form-group">
                    <label for="checkout_date"><?php _e('Check-out Date', 'dbas'); ?> *</label>
                    <input type="date" id="checkout_date" name="checkout_date" required 
                           min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" />
                </div>
                
                <div class="dbas-form-group">
                    <label><?php _e('Pickup Time', 'dbas'); ?> *</label>
                    <div class="dbas-radio-group">
                        <label>
                            <input type="radio" name="pickup_time" value="morning" />
                            <?php _e('Morning (7:00 AM - 10:00 AM)', 'dbas'); ?>
                            <span class="description"><?php _e('No daycare charge on checkout day', 'dbas'); ?></span>
                        </label>
                        <label>
                            <input type="radio" name="pickup_time" value="evening" checked />
                            <?php _e('Evening (3:00 PM - 6:00 PM)', 'dbas'); ?>
                            <span class="description"><?php _e('Includes daycare charge for checkout day', 'dbas'); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="dbas-form-group">
                    <label><?php _e('Select Dogs for Boarding', 'dbas'); ?> *</label>
                    <div class="dbas-dog-selection">
                        <?php foreach ($active_dogs as $dog): ?>
                            <label class="dbas-dog-checkbox">
                                <input type="checkbox" name="dogs[]" value="<?php echo $dog->id; ?>" 
                                       class="dog-selector" />
                                <span><?php echo esc_html($dog->name); ?></span>
                                <?php if ($dog->breed): ?>
                                    <small>(<?php echo esc_html($dog->breed); ?>)</small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description"><?php _e('Maximum 4 dogs per reservation', 'dbas'); ?></p>
                </div>
                
                <div class="dbas-form-group">
                    <label for="customer_notes"><?php _e('Special Instructions (Optional)', 'dbas'); ?></label>
                    <textarea id="customer_notes" name="customer_notes" rows="4"></textarea>
                </div>
                
                <!-- Price Calculation Display -->
                <div id="dbas-price-calculation" style="display: none;">
                    <h3><?php _e('Reservation Cost Breakdown', 'dbas'); ?></h3>
                    <table class="dbas-price-table">
                        <tr>
                            <td><?php _e('Number of Nights:', 'dbas'); ?></td>
                            <td><span id="calc-nights">0</span></td>
                        </tr>
                        <tr>
                            <td><?php _e('Number of Dogs:', 'dbas'); ?></td>
                            <td><span id="calc-dogs">0</span></td>
                        </tr>
                        <tr>
                            <td><?php _e('Boarding Cost:', 'dbas'); ?></td>
                            <td>$<span id="calc-boarding">0.00</span></td>
                        </tr>
                        <tr>
                            <td><?php _e('Daycare Cost:', 'dbas'); ?></td>
                            <td>$<span id="calc-daycare">0.00</span></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong><?php _e('Total Cost:', 'dbas'); ?></strong></td>
                            <td><strong>$<span id="calc-total">0.00</span></strong></td>
                        </tr>
                    </table>
                </div>
                
                <div class="dbas-form-group" id="dbas-terms-agreement" style="display: none;">
                    <label>
                        <input type="checkbox" id="agree_terms" name="agree_terms" required />
                        <?php _e('I understand the total cost and agree to the boarding terms and conditions', 'dbas'); ?>
                    </label>
                </div>
                
                <div class="dbas-form-submit">
                    <button type="button" id="calculate-price" class="button">
                        <?php _e('Calculate Price', 'dbas'); ?>
                    </button>
                    <button type="submit" id="submit-reservation" class="button button-primary" style="display: none;">
                        <?php _e('Submit Reservation Request', 'dbas'); ?>
                    </button>
                </div>
            </form>
            
            <div id="dbas-reservation-message"></div>
        </div>
        
        <style>
            .dbas-reservation-form-container { max-width: 700px; margin: 0 auto; }
            .dbas-dog-selection { border: 1px solid #ddd; padding: 10px; max-height: 200px; overflow-y: auto; }
            .dbas-dog-checkbox { display: block; padding: 5px; }
            .dbas-dog-checkbox:hover { background: #f5f5f5; }
            .dbas-price-table { width: 100%; margin: 20px 0; }
            .dbas-price-table td { padding: 8px; border-bottom: 1px solid #eee; }
            .dbas-price-table .total-row td { border-top: 2px solid #333; border-bottom: none; font-size: 1.2em; }
            #dbas-price-calculation { background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .description { display: block; color: #666; font-size: 0.9em; margin-top: 3px; }
            .dbas-notice {
                padding: 15px;
                margin: 15px 0;
                border-radius: 4px;
                border-left: 4px solid;
            }
            .dbas-success {
                background: #d4edda;
                border-color: #28a745;
                color: #155724;
            }
            .dbas-error {
                background: #f8d7da;
                border-color: #dc3545;
                color: #721c24;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var maxDogs = 4;
            
            // Limit dog selection to 4
            $('.dog-selector').on('change', function() {
                var checked = $('.dog-selector:checked').length;
                if (checked >= maxDogs) {
                    $('.dog-selector:not(:checked)').prop('disabled', true);
                } else {
                    $('.dog-selector').prop('disabled', false);
                }
            });
            
            // Date validation
            $('#checkin_date').on('change', function() {
                var checkinDate = new Date($(this).val());
                var minCheckout = new Date(checkinDate);
                minCheckout.setDate(minCheckout.getDate() + 1);
                
                var checkoutInput = $('#checkout_date');
                checkoutInput.attr('min', minCheckout.toISOString().split('T')[0]);
                
                if (checkoutInput.val() && new Date(checkoutInput.val()) <= checkinDate) {
                    checkoutInput.val('');
                }
            });
            
            // Calculate price
            $('#calculate-price').on('click', function() {
                var checkinDate = $('#checkin_date').val();
                var checkoutDate = $('#checkout_date').val();
                var selectedDogs = $('.dog-selector:checked').length;
                var pickupTime = $('input[name="pickup_time"]:checked').val();
                
                if (!checkinDate || !checkoutDate || selectedDogs === 0) {
                    alert('Please fill in all required fields');
                    return;
                }
                
                // Make sure dbas_ajax is defined
                if (typeof dbas_ajax === 'undefined') {
                    console.error('dbas_ajax is not defined');
                    alert('Configuration error. Please refresh the page and try again.');
                    return;
                }
                
                $.ajax({
                    url: dbas_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dbas_calculate_reservation',
                        checkin_date: checkinDate,
                        checkout_date: checkoutDate,
                        dog_count: selectedDogs,
                        pickup_time: pickupTime,
                        nonce: $('#dbas_reservation_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#calc-nights').text(response.data.nights);
                            $('#calc-dogs').text(selectedDogs);
                            $('#calc-boarding').text(response.data.boarding_price.toFixed(2));
                            $('#calc-daycare').text(response.data.daycare_price.toFixed(2));
                            $('#calc-total').text(response.data.total_price.toFixed(2));
                            $('#dbas-price-calculation').slideDown();
                            $('#dbas-terms-agreement').slideDown();
                            $('#submit-reservation').show();
                        } else {
                            alert(response.data.message || 'An error occurred calculating the price');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('Failed to calculate price. Please try again.');
                    }
                });
            });
            
            // Submit reservation - FIXED VERSION
            $('#dbas-reservation-form').on('submit', function(e) {
                e.preventDefault();
                
                if (!$('#agree_terms').is(':checked')) {
                    alert('Please agree to the terms and conditions');
                    return;
                }
                
                // Check if dbas_ajax is defined
                if (typeof dbas_ajax === 'undefined') {
                    console.error('dbas_ajax is not defined');
                    alert('Configuration error. Please refresh the page and try again.');
                    return;
                }
                
                // Manually build the form data to ensure arrays are handled correctly
                var formData = new FormData(this);
                formData.append('action', 'dbas_submit_reservation');
                
                // Debug: Log what we're sending
                console.log('Submitting reservation...');
                for (var pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                $.ajax({
                    url: dbas_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log('Response:', response);
                        if (response.success) {
                            $('#dbas-reservation-message').html('<div class="dbas-notice dbas-success">' + response.data.message + '</div>');
                            $('#dbas-reservation-form')[0].reset();
                            $('#dbas-price-calculation').hide();
                            $('#dbas-terms-agreement').hide();
                            $('#submit-reservation').hide();
                            
                            // Scroll to message
                            $('html, body').animate({
                                scrollTop: $('#dbas-reservation-message').offset().top - 100
                            }, 500);
                        } else {
                            $('#dbas-reservation-message').html('<div class="dbas-notice dbas-error">' + (response.data.message || 'An error occurred. Please try again.') + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);
                        $('#dbas-reservation-message').html('<div class="dbas-notice dbas-error">Failed to submit reservation. Please try again or contact support.</div>');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Calculate reservation price
     */
    public function ajax_calculate_reservation() {
        check_ajax_referer('dbas_reservation', 'nonce');
        
        $checkin_date = sanitize_text_field($_POST['checkin_date']);
        $checkout_date = sanitize_text_field($_POST['checkout_date']);
        $dog_count = intval($_POST['dog_count']);
        $pickup_time = sanitize_text_field($_POST['pickup_time']);
        
        if (!$checkin_date || !$checkout_date || !$dog_count) {
            wp_send_json_error(array('message' => __('Missing required fields', 'dbas')));
        }
        
        $calculation = DBAS_Reservations_Database::calculate_reservation_total(
            $checkin_date, 
            $checkout_date, 
            $dog_count, 
            $pickup_time
        );
        
        wp_send_json_success($calculation);
    }
    
    /**
     * AJAX: Submit reservation
     */
    public function ajax_submit_reservation() {
        check_ajax_referer('dbas_reservation', 'dbas_reservation_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'dbas')));
        }
        
        $user_id = get_current_user_id();
        $checkin_date = sanitize_text_field($_POST['checkin_date']);
        $checkout_date = sanitize_text_field($_POST['checkout_date']);
        $pickup_time = sanitize_text_field($_POST['pickup_time']);
        
        // Handle dogs array properly
        $dogs = isset($_POST['dogs']) ? array_map('intval', (array)$_POST['dogs']) : array();
        
        if (empty($dogs)) {
            wp_send_json_error(array('message' => __('Please select at least one dog', 'dbas')));
        }
        
        $customer_notes = isset($_POST['customer_notes']) ? sanitize_textarea_field($_POST['customer_notes']) : '';
        
        // Validate dogs belong to user
        $dog_manager = new DBAS_Dog_Manager();
        foreach ($dogs as $dog_id) {
            $dog = $dog_manager->get_dog($dog_id);
            if (!$dog || $dog->owner_id != $user_id || $dog->status !== 'active') {
                wp_send_json_error(array('message' => __('Invalid dog selection', 'dbas')));
            }
        }
        
        // Calculate pricing
        $calculation = DBAS_Reservations_Database::calculate_reservation_total(
            $checkin_date, 
            $checkout_date, 
            count($dogs), 
            $pickup_time
        );
        
        // Create reservation
        global $wpdb;
        $reservation_table = $wpdb->prefix . 'dbas_reservations';
        
        $reservation_data = array(
            'owner_id' => $user_id,
            'checkin_date' => $checkin_date,
            'checkout_date' => $checkout_date,
            'pickup_time' => $pickup_time,
            'total_dogs' => count($dogs),
            'boarding_price' => $calculation['boarding_price'],
            'daycare_price' => $calculation['daycare_price'],
            'total_price' => $calculation['total_price'],
            'status' => 'pending',
            'customer_notes' => $customer_notes
        );
        
        $wpdb->insert($reservation_table, $reservation_data);
        $reservation_id = $wpdb->insert_id;
        
        if ($reservation_id) {
            // Add dogs to reservation
            $reservation_dogs_table = $wpdb->prefix . 'dbas_reservation_dogs';
            foreach ($dogs as $dog_id) {
                $wpdb->insert($reservation_dogs_table, array(
                    'reservation_id' => $reservation_id,
                    'dog_id' => $dog_id
                ));
            }
            
            // Send emails
            $this->send_reservation_emails($reservation_id, 'pending');
            
            wp_send_json_success(array(
                'message' => __('Your reservation request has been submitted successfully! You will receive an email confirmation and we will notify you once it has been approved.', 'dbas'),
                'reservation_id' => $reservation_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create reservation. Please try again.', 'dbas')));
        }
    }
    
    /**
     * Send reservation emails
     */
    private function send_reservation_emails($reservation_id, $action = 'pending') {
        global $wpdb;
        
        // Get reservation details
        $reservation = $this->get_reservation($reservation_id);
        if (!$reservation) return;
        
        $owner = get_userdata($reservation->owner_id);
        if (!$owner) return;
        
        // Get dogs
        $dogs_table = $wpdb->prefix . 'dbas_reservation_dogs';
        $dog_table = $wpdb->prefix . 'dbas_dogs';
        $dogs = $wpdb->get_results($wpdb->prepare(
            "SELECT d.* FROM $dog_table d 
             INNER JOIN $dogs_table rd ON d.id = rd.dog_id 
             WHERE rd.reservation_id = %d",
            $reservation_id
        ));
        
        $dog_list = '';
        foreach ($dogs as $dog) {
            $dog_list .= "- {$dog->name} ({$dog->breed})\n";
        }
        
        // Get email template
        $template_key = '';
        if ($action === 'pending') {
            // Send to user
            $this->send_email_from_template('reservation_pending_user', $owner->user_email, $reservation, $owner, $dogs);
            // Send to admin
            $this->send_email_from_template('reservation_pending_admin', get_option('dbas_admin_email', get_option('admin_email')), $reservation, $owner, $dogs);
        } elseif ($action === 'approved') {
            $this->send_email_from_template('reservation_approved', $owner->user_email, $reservation, $owner, $dogs);
        } elseif ($action === 'rejected') {
            $this->send_email_from_template('reservation_rejected', $owner->user_email, $reservation, $owner, $dogs);
        }
    }
    
    /**
     * Send email from template
     */
    private function send_email_from_template($template_key, $to, $reservation, $owner, $dogs) {
        global $wpdb;
        $template_table = $wpdb->prefix . 'dbas_email_templates';
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $template_table WHERE template_key = %s AND is_active = 1",
            $template_key
        ));
        
        if (!$template) return;
        
        // Build dog list
        $dog_list = '';
        foreach ($dogs as $dog) {
            $dog_list .= "- {$dog->name} ({$dog->breed})\n";
        }
        
        // Determine pickup time range
        $pickup_time_range = $reservation->pickup_time === 'morning' ? '7:00 AM - 10:00 AM' : '3:00 PM - 6:00 PM';
        
        // Replace variables
        $variables = array(
            '{owner_name}' => $owner->first_name . ' ' . $owner->last_name,
            '{owner_email}' => $owner->user_email,
            '{owner_phone}' => get_user_meta($owner->ID, 'dbas_phone', true),
            '{facility_name}' => get_bloginfo('name'),
            '{checkin_date}' => date('F j, Y', strtotime($reservation->checkin_date)),
            '{checkout_date}' => date('F j, Y', strtotime($reservation->checkout_date)),
            '{dog_count}' => $reservation->total_dogs,
            '{pickup_time}' => ucfirst($reservation->pickup_time),
            '{pickup_time_range}' => $pickup_time_range,
            '{total_price}' => number_format($reservation->total_price, 2),
            '{boarding_price}' => number_format($reservation->boarding_price, 2),
            '{daycare_price}' => number_format($reservation->daycare_price, 2),
            '{dog_list}' => $dog_list,
            '{customer_notes}' => $reservation->customer_notes ?: 'None',
            '{admin_link}' => admin_url('admin.php?page=dbas-reservations&action=view&id=' . $reservation->id),
            '{rejection_reason}' => $reservation->admin_notes ?: 'Facility is at capacity for the requested dates'
        );
        
        $subject = str_replace(array_keys($variables), array_values($variables), $template->subject);
        $body = str_replace(array_keys($variables), array_values($variables), $template->body);
        
        wp_mail($to, $subject, $body);
    }
    
    /**
     * Get reservation details
     */
    public function get_reservation($reservation_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_reservations';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reservation_id));
    }
    
    /**
     * Render user reservations
     */
    public function render_user_reservations() {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your reservations.', 'dbas') . '</p>';
        }
        
        $user_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_reservations';
        
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE owner_id = %d ORDER BY checkin_date DESC",
            $user_id
        ));
        
        ob_start();
        ?>
        <div class="dbas-user-reservations">
            <h3><?php _e('My Reservations', 'dbas'); ?></h3>
            
            <?php if (empty($reservations)): ?>
                <p><?php _e('You have no reservations yet.', 'dbas'); ?></p>
                <p><a href="<?php echo get_permalink(get_option('dbas_reservation_page_id')); ?>" class="button button-primary"><?php _e('Make a Reservation', 'dbas'); ?></a></p>
            <?php else: ?>
                <table class="dbas-reservations-table">
                    <thead>
                        <tr>
                            <th><?php _e('Check-in', 'dbas'); ?></th>
                            <th><?php _e('Check-out', 'dbas'); ?></th>
                            <th><?php _e('Dogs', 'dbas'); ?></th>
                            <th><?php _e('Total', 'dbas'); ?></th>
                            <th><?php _e('Status', 'dbas'); ?></th>
                            <th><?php _e('Actions', 'dbas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($reservation->checkin_date)); ?></td>
                                <td><?php echo date('M j, Y', strtotime($reservation->checkout_date)); ?></td>
                                <td><?php echo $reservation->total_dogs; ?></td>
                                <td>$<?php echo number_format($reservation->total_price, 2); ?></td>
                                <td>
                                    <span class="dbas-status-<?php echo $reservation->status; ?>">
                                        <?php echo ucfirst($reservation->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reservation->status === 'pending' && strtotime($reservation->checkin_date) > time()): ?>
                                        <button class="button dbas-cancel-reservation" data-reservation-id="<?php echo $reservation->id; ?>">
                                            <?php _e('Cancel', 'dbas'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
            .dbas-reservations-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .dbas-reservations-table th,
            .dbas-reservations-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .dbas-reservations-table th { background: #f5f5f5; font-weight: 600; }
            .dbas-status-pending { color: #ff9800; font-weight: 600; }
            .dbas-status-approved { color: #4caf50; font-weight: 600; }
            .dbas-status-rejected { color: #f44336; font-weight: 600; }
            .dbas-status-cancelled { color: #9e9e9e; font-weight: 600; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.dbas-cancel-reservation').on('click', function() {
                if (!confirm('Are you sure you want to cancel this reservation?')) {
                    return;
                }
                
                var reservationId = $(this).data('reservation-id');
                
                $.ajax({
                    url: dbas_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dbas_cancel_reservation',
                        reservation_id: reservationId,
                        nonce: '<?php echo wp_create_nonce('dbas_cancel_reservation'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Failed to cancel reservation');
                        }
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Cancel reservation
     */
    public function ajax_cancel_reservation() {
        check_ajax_referer('dbas_cancel_reservation', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'dbas')));
        }
        
        $reservation_id = intval($_POST['reservation_id']);
        $user_id = get_current_user_id();
        
        // Get reservation
        $reservation = $this->get_reservation($reservation_id);
        
        if (!$reservation) {
            wp_send_json_error(array('message' => __('Reservation not found', 'dbas')));
        }
        
        // Check ownership
        if ($reservation->owner_id != $user_id && !current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('You do not have permission to cancel this reservation', 'dbas')));
        }
        
        // Update status
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_reservations';
        
        $result = $wpdb->update(
            $table,
            array('status' => 'cancelled'),
            array('id' => $reservation_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Reservation cancelled successfully', 'dbas')));
        } else {
            wp_send_json_error(array('message' => __('Failed to cancel reservation', 'dbas')));
        }
    }
}
?>