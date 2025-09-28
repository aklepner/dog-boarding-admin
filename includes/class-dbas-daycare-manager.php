<?php
/**
 * Daycare Management Class
 */

class DBAS_Daycare_Manager {
    
    public function __construct() {
        // Add shortcodes
        add_shortcode('dbas_daycare_checkin', array($this, 'render_checkin_form'));
        
        // AJAX handlers
        add_action('wp_ajax_dbas_daycare_checkin', array($this, 'ajax_checkin_dog'));
        add_action('wp_ajax_dbas_daycare_checkout', array($this, 'ajax_checkout_dog'));
        add_action('wp_ajax_dbas_mark_fed', array($this, 'ajax_mark_fed'));
        add_action('wp_ajax_dbas_get_daycare_list', array($this, 'ajax_get_daycare_list'));
    }
    
    /**
     * Render check-in form (admins only)
     */
    public function render_checkin_form() {
        if (!current_user_can('manage_dbas')) {
            return '<p>' . __('You do not have permission to access this page.', 'dbas') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="dbas-daycare-management">
            <h2><?php _e('Daycare Check-In/Check-Out', 'dbas'); ?></h2>
            
            <div class="dbas-daycare-date">
                <label for="daycare_date"><?php _e('Date:', 'dbas'); ?></label>
                <input type="date" id="daycare_date" value="<?php echo date('Y-m-d'); ?>" />
                <button id="refresh-daycare-list" class="button"><?php _e('Refresh', 'dbas'); ?></button>
            </div>
            
            <!-- Quick Check-in Form -->
            <div class="dbas-quick-checkin">
                <h3><?php _e('Quick Check-In', 'dbas'); ?></h3>
                <form id="dbas-quick-checkin-form">
                    <?php wp_nonce_field('dbas_daycare', 'dbas_daycare_nonce'); ?>
                    
                    <div class="dbas-form-group">
                        <label for="owner_search"><?php _e('Search Owner', 'dbas'); ?></label>
                        <input type="text" id="owner_search" placeholder="<?php _e('Type owner name...', 'dbas'); ?>" />
                        <div id="owner_results"></div>
                    </div>
                    
                    <div id="owner_dogs" style="display: none;">
                        <h4><?php _e('Select Dogs for Check-In', 'dbas'); ?></h4>
                        <div id="dogs_list"></div>
                        
                        <div class="dbas-form-group">
                            <label for="checkin_notes"><?php _e('Notes (Optional)', 'dbas'); ?></label>
                            <textarea id="checkin_notes" name="notes"></textarea>
                        </div>
                        
                        <button type="submit" class="button button-primary">
                            <?php _e('Check In Selected Dogs', 'dbas'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Today's Daycare List -->
            <div class="dbas-daycare-list">
                <h3><?php _e("Today's Daycare Attendance", 'dbas'); ?></h3>
                
                <div class="dbas-stats">
                    <div class="stat-box">
                        <span class="stat-label"><?php _e('Total Dogs:', 'dbas'); ?></span>
                        <span class="stat-value" id="total-dogs">0</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label"><?php _e('Checked In:', 'dbas'); ?></span>
                        <span class="stat-value" id="checked-in">0</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label"><?php _e('Checked Out:', 'dbas'); ?></span>
                        <span class="stat-value" id="checked-out">0</span>
                    </div>
                </div>
                
                <table id="daycare-attendance-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Dog Name', 'dbas'); ?></th>
                            <th><?php _e('Owner', 'dbas'); ?></th>
                            <th><?php _e('Check-In', 'dbas'); ?></th>
                            <th><?php _e('Check-Out', 'dbas'); ?></th>
                            <th><?php _e('Fed', 'dbas'); ?></th>
                            <th><?php _e('Price', 'dbas'); ?></th>
                            <th><?php _e('Notes', 'dbas'); ?></th>
                            <th><?php _e('Actions', 'dbas'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="daycare-list-body">
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Overnight Dogs List (for boarding) -->
            <div class="dbas-overnight-list">
                <h3><?php _e('Dogs Staying Overnight', 'dbas'); ?></h3>
                <table id="overnight-dogs-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Dog Name', 'dbas'); ?></th>
                            <th><?php _e('Owner', 'dbas'); ?></th>
                            <th><?php _e('Check-In Date', 'dbas'); ?></th>
                            <th><?php _e('Check-Out Date', 'dbas'); ?></th>
                            <th><?php _e('Kennel #', 'dbas'); ?></th>
                            <th><?php _e('Actions', 'dbas'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="overnight-list-body">
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
            .dbas-daycare-management { padding: 20px; }
            .dbas-daycare-date { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
            .dbas-daycare-date input { margin: 0 10px; }
            .dbas-quick-checkin { background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 30px; }
            .dbas-stats { display: flex; gap: 20px; margin: 20px 0; }
            .stat-box { flex: 1; background: #f9f9f9; padding: 15px; text-align: center; border-radius: 5px; }
            .stat-label { display: block; color: #666; font-size: 14px; }
            .stat-value { display: block; font-size: 24px; font-weight: bold; color: #0073aa; }
            #owner_results { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; display: none; }
            .owner-result { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
            .owner-result:hover { background: #f5f5f5; }
            .dog-checkbox { display: block; padding: 8px; margin: 5px 0; background: #f9f9f9; border-radius: 3px; }
            .fed-checkbox { cursor: pointer; }
            .kennel-input { width: 80px; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var selectedOwner = null;
            
            // Load today's attendance
            loadDaycareList();
            loadOvernightDogs();
            
            // Owner search
            $('#owner_search').on('input', function() {
                var search = $(this).val();
                if (search.length < 2) {
                    $('#owner_results').hide();
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_search_owners',
                        search: search,
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var html = '';
                            response.data.forEach(function(owner) {
                                html += '<div class="owner-result" data-owner-id="' + owner.id + '">';
                                html += owner.name + ' - ' + owner.email;
                                html += '</div>';
                            });
                            $('#owner_results').html(html).show();
                        }
                    }
                });
            });
            
            // Select owner
            $(document).on('click', '.owner-result', function() {
                selectedOwner = $(this).data('owner-id');
                var ownerName = $(this).text();
                $('#owner_search').val(ownerName);
                $('#owner_results').hide();
                loadOwnerDogs(selectedOwner);
            });
            
            // Load owner's dogs
            function loadOwnerDogs(ownerId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_get_owner_dogs',
                        owner_id: ownerId,
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '';
                            response.data.forEach(function(dog) {
                                html += '<label class="dog-checkbox">';
                                html += '<input type="checkbox" name="dogs[]" value="' + dog.id + '" />';
                                html += ' ' + dog.name + ' (' + dog.breed + ')';
                                if (dog.is_boarding) {
                                    html += ' <span style="color: green;">[Boarding Guest]</span>';
                                }
                                html += '</label>';
                            });
                            $('#dogs_list').html(html);
                            $('#owner_dogs').show();
                        }
                    }
                });
            }
            
            // Quick check-in form submission
            $('#dbas-quick-checkin-form').on('submit', function(e) {
                e.preventDefault();
                
                var selectedDogs = [];
                $('input[name="dogs[]"]:checked').each(function() {
                    selectedDogs.push($(this).val());
                });
                
                if (selectedDogs.length === 0) {
                    alert('Please select at least one dog');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_daycare_checkin',
                        dogs: selectedDogs,
                        date: $('#daycare_date').val(),
                        notes: $('#checkin_notes').val(),
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            $('#dbas-quick-checkin-form')[0].reset();
                            $('#owner_dogs').hide();
                            loadDaycareList();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });
            
            // Refresh daycare list
            $('#refresh-daycare-list').on('click', function() {
                loadDaycareList();
                loadOvernightDogs();
            });
            
            // Load daycare list
            function loadDaycareList() {
                var date = $('#daycare_date').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_get_daycare_list',
                        date: date,
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            updateDaycareTable(response.data.checkins);
                            updateStats(response.data.stats);
                        }
                    }
                });
            }
            
            // Load overnight dogs
            function loadOvernightDogs() {
                var date = $('#daycare_date').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_get_overnight_dogs',
                        date: date,
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            updateOvernightTable(response.data);
                        }
                    }
                });
            }
            
            // Update daycare table
            function updateDaycareTable(checkins) {
                var html = '';
                checkins.forEach(function(checkin) {
                    html += '<tr>';
                    html += '<td>' + checkin.dog_name + '</td>';
                    html += '<td>' + checkin.owner_name + '</td>';
                    html += '<td>' + (checkin.checkin_time || '-') + '</td>';
                    html += '<td>' + (checkin.checkout_time || '-') + '</td>';
                    html += '<td>';
                    html += '<input type="checkbox" class="fed-checkbox" data-checkin-id="' + checkin.id + '" ';
                    if (checkin.is_fed == 1) html += 'checked';
                    html += ' />';
                    if (checkin.fed_time) html += ' ' + checkin.fed_time;
                    html += '</td>';
                    html += '<td>$' + checkin.price + '</td>';
                    html += '<td>' + (checkin.notes || '') + '</td>';
                    html += '<td>';
                    if (!checkin.checkout_time) {
                        html += '<button class="button checkout-btn" data-checkin-id="' + checkin.id + '">Check Out</button>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
                $('#daycare-list-body').html(html);
            }
            
            // Update overnight table
            function updateOvernightTable(reservations) {
                var html = '';
                reservations.forEach(function(res) {
                    res.dogs.forEach(function(dog) {
                        html += '<tr>';
                        html += '<td>' + dog.name + '</td>';
                        html += '<td>' + res.owner_name + '</td>';
                        html += '<td>' + res.checkin_date + '</td>';
                        html += '<td>' + res.checkout_date + '</td>';
                        html += '<td>';
                        html += '<input type="text" class="kennel-input" data-dog-id="' + dog.id + '" ';
                        html += 'data-reservation-id="' + res.id + '" value="' + (dog.kennel || '') + '" />';
                        html += '</td>';
                        html += '<td>';
                        html += '<button class="button save-kennel" data-dog-id="' + dog.id + '" ';
                        html += 'data-reservation-id="' + res.id + '">Save</button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                });
                $('#overnight-list-body').html(html);
            }
            
            // Update stats
            function updateStats(stats) {
                $('#total-dogs').text(stats.total);
                $('#checked-in').text(stats.checked_in);
                $('#checked-out').text(stats.checked_out);
            }
            
            // Check out dog
            $(document).on('click', '.checkout-btn', function() {
                var checkinId = $(this).data('checkin-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_daycare_checkout',
                        checkin_id: checkinId,
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            loadDaycareList();
                        }
                    }
                });
            });
            
            // Mark dog as fed
            $(document).on('change', '.fed-checkbox', function() {
                var checkinId = $(this).data('checkin-id');
                var isFed = $(this).is(':checked');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_mark_fed',
                        checkin_id: checkinId,
                        is_fed: isFed ? 1 : 0,
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            loadDaycareList();
                        }
                    }
                });
            });
            
            // Save kennel number
            $(document).on('click', '.save-kennel', function() {
                var dogId = $(this).data('dog-id');
                var reservationId = $(this).data('reservation-id');
                var kennel = $('input[data-dog-id="' + dogId + '"][data-reservation-id="' + reservationId + '"]').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dbas_update_kennel',
                        reservation_id: reservationId,
                        dog_id: dogId,
                        kennel: kennel,
                        nonce: $('#dbas_daycare_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Kennel number saved');
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
     * AJAX: Check in dog for daycare
     */
    public function ajax_checkin_dog() {
        check_ajax_referer('dbas_daycare', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $dogs = array_map('intval', $_POST['dogs']);
        $date = sanitize_text_field($_POST['date']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        global $wpdb;
        $checkin_table = $wpdb->prefix . 'dbas_daycare_checkins';
        $dog_table = $wpdb->prefix . 'dbas_dogs';
        
        $checked_in = 0;
        foreach ($dogs as $dog_id) {
            // Get dog details
            $dog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $dog_table WHERE id = %d", $dog_id));
            if (!$dog) continue;
            
            // Check if already checked in today
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $checkin_table WHERE dog_id = %d AND checkin_date = %s AND status = 'checked_in'",
                $dog_id, $date
            ));
            
            if ($existing) continue;
            
            // Get pricing (count dogs from same owner for pricing)
            $owner_dogs_today = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $checkin_table WHERE owner_id = %d AND checkin_date = %s",
                $dog->owner_id, $date
            ));
            
            $price = DBAS_Reservations_Database::get_pricing('daycare', $owner_dogs_today + 1);
            
            // Create check-in record
            $wpdb->insert($checkin_table, array(
                'dog_id' => $dog_id,
                'owner_id' => $dog->owner_id,
                'checkin_date' => $date,
                'checkin_time' => current_time('H:i:s'),
                'price' => $price,
                'notes' => $notes,
                'status' => 'checked_in'
            ));
            
            $checked_in++;
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d dog(s) checked in successfully', 'dbas'), $checked_in)
        ));
    }
    
    /**
     * AJAX: Check out dog from daycare
     */
    public function ajax_checkout_dog() {
        check_ajax_referer('dbas_daycare', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $checkin_id = intval($_POST['checkin_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_daycare_checkins';
        
        $result = $wpdb->update(
            $table,
            array(
                'checkout_time' => current_time('H:i:s'),
                'status' => 'checked_out'
            ),
            array('id' => $checkin_id)
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => __('Failed to check out', 'dbas')));
        }
    }
    
    /**
     * AJAX: Mark dog as fed
     */
    public function ajax_mark_fed() {
        check_ajax_referer('dbas_daycare', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $checkin_id = intval($_POST['checkin_id']);
        $is_fed = intval($_POST['is_fed']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_daycare_checkins';
        
        $data = array('is_fed' => $is_fed);
        if ($is_fed) {
            $data['fed_time'] = current_time('H:i:s');
        } else {
            $data['fed_time'] = null;
        }
        
        $result = $wpdb->update($table, $data, array('id' => $checkin_id));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => __('Failed to update feeding status', 'dbas')));
        }
    }
    
    /**
     * AJAX: Get daycare list for date
     */
    public function ajax_get_daycare_list() {
        check_ajax_referer('dbas_daycare', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        global $wpdb;
        $checkin_table = $wpdb->prefix . 'dbas_daycare_checkins';
        $dog_table = $wpdb->prefix . 'dbas_dogs';
        $users_table = $wpdb->users;
        
        $checkins = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, d.name as dog_name, d.breed, u.display_name as owner_name 
             FROM $checkin_table c
             LEFT JOIN $dog_table d ON c.dog_id = d.id
             LEFT JOIN $users_table u ON c.owner_id = u.ID
             WHERE c.checkin_date = %s
             ORDER BY c.checkin_time DESC",
            $date
        ));
        
        // Calculate stats
        $stats = array(
            'total' => count($checkins),
            'checked_in' => 0,
            'checked_out' => 0
        );
        
        foreach ($checkins as $checkin) {
            if ($checkin->status === 'checked_in') {
                $stats['checked_in']++;
            } else {
                $stats['checked_out']++;
            }
        }
        
        wp_send_json_success(array(
            'checkins' => $checkins,
            'stats' => $stats
        ));
    }
}
?>