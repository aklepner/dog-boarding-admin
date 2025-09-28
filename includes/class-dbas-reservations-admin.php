<?php
/**
 * Reservations Admin Class
 */

class DBAS_Reservations_Admin {
    
    public function __construct() {
        // Add admin menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle form submissions EARLY before any output
        add_action('admin_init', array($this, 'handle_admin_actions'));
        
        // Add AJAX handlers for search
        add_action('wp_ajax_dbas_search_owners', array($this, 'ajax_search_owners'));
        add_action('wp_ajax_dbas_get_owner_dogs', array($this, 'ajax_get_owner_dogs'));
        add_action('wp_ajax_dbas_get_overnight_dogs', array($this, 'ajax_get_overnight_dogs'));
    }
    
    /**
     * Handle admin actions before any output
     */
    public function handle_admin_actions() {
        // Only process on our admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'dbas-') !== 0) {
            return;
        }
        
        // Handle reservation update
        if (isset($_POST['action']) && $_POST['action'] === 'update_reservation') {
            $this->handle_reservation_update();
        }
        
        // Handle pricing update
        if (isset($_POST['update_pricing'])) {
            $this->handle_pricing_update();
        }
        
        // Handle email template update
        if (isset($_POST['update_template'])) {
            $this->handle_email_template_update();
        }
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add under existing Dog Boarding menu
        add_submenu_page(
            'dbas-dashboard',
            __('Reservations', 'dbas'),
            __('Reservations', 'dbas'),
            'manage_dbas',
            'dbas-reservations',
            array($this, 'render_reservations_page')
        );
        
        add_submenu_page(
            'dbas-dashboard',
            __('Daycare Check-In', 'dbas'),
            __('Daycare Check-In', 'dbas'),
            'manage_dbas',
            'dbas-daycare',
            array($this, 'render_daycare_page')
        );
        
        add_submenu_page(
            'dbas-dashboard',
            __('Pricing Settings', 'dbas'),
            __('Pricing Settings', 'dbas'),
            'manage_dbas',
            'dbas-pricing',
            array($this, 'render_pricing_page')
        );
        
        add_submenu_page(
            'dbas-dashboard',
            __('Email Templates', 'dbas'),
            __('Email Templates', 'dbas'),
            'manage_dbas',
            'dbas-email-templates',
            array($this, 'render_email_templates_page')
        );
        
        add_submenu_page(
            'dbas-dashboard',
            __('Reports', 'dbas'),
            __('Reports', 'dbas'),
            'manage_dbas',
            'dbas-reports',
            array($this, 'render_reports_page')
        );
    }
    
    /**
     * Render reservations page
     */
    public function render_reservations_page() {
        // Check if we're viewing a single reservation
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $this->render_single_reservation(intval($_GET['id']));
            return;
        }
        
        // Get reservations
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_reservations';
        
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $where = $status_filter !== 'all' ? $wpdb->prepare("WHERE status = %s", $status_filter) : "";
        
        $reservations = $wpdb->get_results(
            "SELECT r.*, u.display_name as owner_name, u.user_email as owner_email 
             FROM $table r
             LEFT JOIN {$wpdb->users} u ON r.owner_id = u.ID
             $where
             ORDER BY r.checkin_date DESC"
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Boarding Reservations', 'dbas'); ?></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Reservation updated successfully.', 'dbas'); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <ul class="subsubsub">
                <li><a href="?page=dbas-reservations&status=all" <?php echo $status_filter === 'all' ? 'class="current"' : ''; ?>>
                    <?php _e('All', 'dbas'); ?></a> |</li>
                <li><a href="?page=dbas-reservations&status=pending" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>>
                    <?php _e('Pending', 'dbas'); ?></a> |</li>
                <li><a href="?page=dbas-reservations&status=approved" <?php echo $status_filter === 'approved' ? 'class="current"' : ''; ?>>
                    <?php _e('Approved', 'dbas'); ?></a> |</li>
                <li><a href="?page=dbas-reservations&status=rejected" <?php echo $status_filter === 'rejected' ? 'class="current"' : ''; ?>>
                    <?php _e('Rejected', 'dbas'); ?></a> |</li>
                <li><a href="?page=dbas-reservations&status=cancelled" <?php echo $status_filter === 'cancelled' ? 'class="current"' : ''; ?>>
                    <?php _e('Cancelled', 'dbas'); ?></a></li>
            </ul>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'dbas'); ?></th>
                        <th><?php _e('Owner', 'dbas'); ?></th>
                        <th><?php _e('Check-in', 'dbas'); ?></th>
                        <th><?php _e('Check-out', 'dbas'); ?></th>
                        <th><?php _e('Dogs', 'dbas'); ?></th>
                        <th><?php _e('Total', 'dbas'); ?></th>
                        <th><?php _e('Status', 'dbas'); ?></th>
                        <th><?php _e('Payment', 'dbas'); ?></th>
                        <th><?php _e('Actions', 'dbas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?php echo $reservation->id; ?></td>
                            <td>
                                <strong><?php echo esc_html($reservation->owner_name); ?></strong><br>
                                <small><?php echo esc_html($reservation->owner_email); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($reservation->checkin_date)); ?></td>
                            <td>
                                <?php echo date('M j, Y', strtotime($reservation->checkout_date)); ?><br>
                                <small><?php echo ucfirst($reservation->pickup_time); ?> pickup</small>
                            </td>
                            <td><?php echo $reservation->total_dogs; ?></td>
                            <td>
                                $<?php echo number_format($reservation->total_price, 2); ?><br>
                                <small>B: $<?php echo number_format($reservation->boarding_price, 2); ?> | D: $<?php echo number_format($reservation->daycare_price, 2); ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $reservation->status; ?>">
                                    <?php echo ucfirst($reservation->status); ?>
                                </span>
                            </td>
                            <td>
                                <span class="payment-badge payment-<?php echo $reservation->payment_status; ?>">
                                    <?php echo ucfirst($reservation->payment_status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?page=dbas-reservations&action=view&id=<?php echo $reservation->id; ?>" 
                                   class="button button-small"><?php _e('View', 'dbas'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
            .status-pending { background: #ff9800; color: white; }
            .status-approved { background: #4caf50; color: white; }
            .status-rejected { background: #f44336; color: white; }
            .status-cancelled { background: #9e9e9e; color: white; }
            .payment-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; }
            .payment-paid { background: #4caf50; color: white; }
            .payment-unpaid { background: #f44336; color: white; }
            .payment-partial { background: #ff9800; color: white; }
        </style>
        <?php
    }
    
    /**
     * Render single reservation view
     */
    private function render_single_reservation($reservation_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_reservations';
        $dogs_table = $wpdb->prefix . 'dbas_reservation_dogs';
        $dog_table = $wpdb->prefix . 'dbas_dogs';
        
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, u.display_name as owner_name, u.user_email as owner_email 
             FROM $table r
             LEFT JOIN {$wpdb->users} u ON r.owner_id = u.ID
             WHERE r.id = %d",
            $reservation_id
        ));
        
        if (!$reservation) {
            echo '<div class="notice notice-error"><p>' . __('Reservation not found.', 'dbas') . '</p></div>';
            return;
        }
        
        // Get dogs
        $dogs = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, rd.kennel_number, rd.special_instructions 
             FROM $dog_table d
             INNER JOIN $dogs_table rd ON d.id = rd.dog_id
             WHERE rd.reservation_id = %d",
            $reservation_id
        ));
        
        ?>
        <div class="wrap">
            <h2><?php printf(__('Reservation #%d', 'dbas'), $reservation_id); ?></h2>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'updated'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Reservation updated successfully.', 'dbas'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="dbas-reservation-details">
                <div class="reservation-box">
                    <h3><?php _e('Reservation Information', 'dbas'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Owner:', 'dbas'); ?></th>
                            <td><?php echo esc_html($reservation->owner_name); ?> (<?php echo esc_html($reservation->owner_email); ?>)</td>
                        </tr>
                        <tr>
                            <th><?php _e('Check-in:', 'dbas'); ?></th>
                            <td><?php echo date('F j, Y', strtotime($reservation->checkin_date)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Check-out:', 'dbas'); ?></th>
                            <td><?php echo date('F j, Y', strtotime($reservation->checkout_date)); ?> (<?php echo ucfirst($reservation->pickup_time); ?> pickup)</td>
                        </tr>
                        <tr>
                            <th><?php _e('Total Cost:', 'dbas'); ?></th>
                            <td>
                                $<?php echo number_format($reservation->total_price, 2); ?><br>
                                <small>Boarding: $<?php echo number_format($reservation->boarding_price, 2); ?> | Daycare: $<?php echo number_format($reservation->daycare_price, 2); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Customer Notes:', 'dbas'); ?></th>
                            <td><?php echo esc_html($reservation->customer_notes ?: 'None'); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="reservation-box">
                    <h3><?php _e('Dogs', 'dbas'); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'dbas'); ?></th>
                                <th><?php _e('Breed', 'dbas'); ?></th>
                                <th><?php _e('Kennel #', 'dbas'); ?></th>
                                <th><?php _e('Special Instructions', 'dbas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dogs as $dog): ?>
                                <tr>
                                    <td><?php echo esc_html($dog->name); ?></td>
                                    <td><?php echo esc_html($dog->breed); ?></td>
                                    <td>
                                        <input type="text" name="kennel_<?php echo $dog->id; ?>" 
                                               value="<?php echo esc_attr($dog->kennel_number); ?>" 
                                               style="width: 80px;" />
                                    </td>
                                    <td>
                                        <input type="text" name="instructions_<?php echo $dog->id; ?>" 
                                               value="<?php echo esc_attr($dog->special_instructions); ?>" 
                                               style="width: 100%;" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="reservation-box">
                    <h3><?php _e('Update Reservation', 'dbas'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('update_reservation_' . $reservation_id); ?>
                        <input type="hidden" name="action" value="update_reservation" />
                        <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>" />
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="status"><?php _e('Status', 'dbas'); ?></label></th>
                                <td>
                                    <select name="status" id="status">
                                        <option value="pending" <?php selected($reservation->status, 'pending'); ?>><?php _e('Pending', 'dbas'); ?></option>
                                        <option value="approved" <?php selected($reservation->status, 'approved'); ?>><?php _e('Approved', 'dbas'); ?></option>
                                        <option value="rejected" <?php selected($reservation->status, 'rejected'); ?>><?php _e('Rejected', 'dbas'); ?></option>
                                        <option value="cancelled" <?php selected($reservation->status, 'cancelled'); ?>><?php _e('Cancelled', 'dbas'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="payment_status"><?php _e('Payment Status', 'dbas'); ?></label></th>
                                <td>
                                    <select name="payment_status" id="payment_status">
                                        <option value="unpaid" <?php selected($reservation->payment_status, 'unpaid'); ?>><?php _e('Unpaid', 'dbas'); ?></option>
                                        <option value="paid" <?php selected($reservation->payment_status, 'paid'); ?>><?php _e('Paid', 'dbas'); ?></option>
                                        <option value="partial" <?php selected($reservation->payment_status, 'partial'); ?>><?php _e('Partial', 'dbas'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="admin_notes"><?php _e('Admin Notes', 'dbas'); ?></label></th>
                                <td>
                                    <textarea name="admin_notes" id="admin_notes" rows="4" cols="50"><?php echo esc_textarea($reservation->admin_notes); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="update_reservation" class="button button-primary" value="<?php _e('Update Reservation', 'dbas'); ?>" />
                            <a href="?page=dbas-reservations" class="button"><?php _e('Back to List', 'dbas'); ?></a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
            .dbas-reservation-details { margin-top: 20px; }
            .reservation-box { background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; }
            .reservation-box h3 { margin-top: 0; }
        </style>
        <?php
    }
    
    /**
     * Handle reservation update
     */
    private function handle_reservation_update() {
        if (!isset($_POST['reservation_id'])) return;
        
        $reservation_id = intval($_POST['reservation_id']);
        if (!wp_verify_nonce($_POST['_wpnonce'], 'update_reservation_' . $reservation_id)) {
            wp_die(__('Security check failed', 'dbas'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_reservations';
        
        // Get current reservation
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reservation_id));
        if (!$current) {
            wp_die(__('Reservation not found', 'dbas'));
        }
        
        $old_status = $current->status;
        $new_status = sanitize_text_field($_POST['status']);
        
        // Update reservation
        $wpdb->update(
            $table,
            array(
                'status' => $new_status,
                'payment_status' => sanitize_text_field($_POST['payment_status']),
                'admin_notes' => sanitize_textarea_field($_POST['admin_notes'])
            ),
            array('id' => $reservation_id)
        );
        
        // Send email if status changed
        if ($old_status !== $new_status && ($new_status === 'approved' || $new_status === 'rejected')) {
            $reservation_manager = new DBAS_Reservation_Manager();
            $reservation_manager->send_reservation_emails($reservation_id, $new_status);
        }
        
        // Redirect with success message
        wp_redirect(admin_url('admin.php?page=dbas-reservations&action=view&id=' . $reservation_id . '&message=updated'));
        exit;
    }
    
    /**
     * Render daycare page
     */
    public function render_daycare_page() {
        echo do_shortcode('[dbas_daycare_checkin]');
    }
    
    /**
     * Render pricing page
     */
    public function render_pricing_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_pricing';
        $prices = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY service_type, dog_count");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Pricing Settings', 'dbas'); ?></h1>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'updated'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Pricing updated successfully.', 'dbas'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('update_pricing'); ?>
                
                <h2><?php _e('Daycare Pricing (Per Dog Per Day)', 'dbas'); ?></h2>
                <table class="form-table">
                    <?php foreach ($prices as $price): ?>
                        <?php if ($price->service_type === 'daycare'): ?>
                            <tr>
                                <th><label><?php printf(__('%d Dog(s)', 'dbas'), $price->dog_count); ?></label></th>
                                <td>
                                    $<input type="number" name="price_daycare_<?php echo $price->dog_count; ?>" 
                                           value="<?php echo $price->price; ?>" step="0.01" style="width: 100px;" />
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                
                <h2><?php _e('Boarding Pricing (Per Dog Per Night)', 'dbas'); ?></h2>
                <table class="form-table">
                    <?php foreach ($prices as $price): ?>
                        <?php if ($price->service_type === 'boarding'): ?>
                            <tr>
                                <th><label><?php printf(__('%d Dog(s)', 'dbas'), $price->dog_count); ?></label></th>
                                <td>
                                    $<input type="number" name="price_boarding_<?php echo $price->dog_count; ?>" 
                                           value="<?php echo $price->price; ?>" step="0.01" style="width: 100px;" />
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                
                <p class="submit">
                    <input type="submit" name="update_pricing" class="button button-primary" value="<?php _e('Update Pricing', 'dbas'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle pricing update
     */
    private function handle_pricing_update() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'update_pricing')) {
            wp_die(__('Security check failed', 'dbas'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_pricing';
        
        // Update daycare pricing
        for ($i = 1; $i <= 4; $i++) {
            if (isset($_POST['price_daycare_' . $i])) {
                $wpdb->update(
                    $table,
                    array('price' => floatval($_POST['price_daycare_' . $i])),
                    array('service_type' => 'daycare', 'dog_count' => $i)
                );
            }
        }
        
        // Update boarding pricing
        for ($i = 1; $i <= 4; $i++) {
            if (isset($_POST['price_boarding_' . $i])) {
                $wpdb->update(
                    $table,
                    array('price' => floatval($_POST['price_boarding_' . $i])),
                    array('service_type' => 'boarding', 'dog_count' => $i)
                );
            }
        }
        
        wp_redirect(admin_url('admin.php?page=dbas-pricing&message=updated'));
        exit;
    }
    
    /**
     * Render email templates page
     */
    public function render_email_templates_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_email_templates';
        
        $template_id = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'reservation_pending_user';
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE template_key = %s",
            $template_id
        ));
        
        $templates = $wpdb->get_results("SELECT template_key, template_name FROM $table WHERE is_active = 1");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Email Templates', 'dbas'); ?></h1>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'updated'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Email template updated successfully.', 'dbas'); ?></p>
                </div>
            <?php endif; ?>
            
            <ul class="subsubsub">
                <?php foreach ($templates as $t): ?>
                    <li>
                        <a href="?page=dbas-email-templates&template=<?php echo $t->template_key; ?>" 
                           <?php echo $template_id === $t->template_key ? 'class="current"' : ''; ?>>
                            <?php echo esc_html($t->template_name); ?>
                        </a> <?php echo $t !== end($templates) ? '|' : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if ($template): ?>
                <form method="post" action="" style="margin-top: 20px;">
                    <?php wp_nonce_field('update_email_template'); ?>
                    <input type="hidden" name="template_key" value="<?php echo esc_attr($template->template_key); ?>" />
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="subject"><?php _e('Subject', 'dbas'); ?></label></th>
                            <td>
                                <input type="text" name="subject" id="subject" value="<?php echo esc_attr($template->subject); ?>" 
                                       class="large-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="body"><?php _e('Body', 'dbas'); ?></label></th>
                            <td>
                                <textarea name="body" id="body" rows="15" class="large-text code"><?php echo esc_textarea($template->body); ?></textarea>
                                <p class="description">
                                    <?php _e('Available variables:', 'dbas'); ?><br>
                                    <code><?php echo esc_html($template->variables); ?></code>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="update_template" class="button button-primary" value="<?php _e('Update Template', 'dbas'); ?>" />
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle email template update
     */
    private function handle_email_template_update() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'update_email_template')) {
            wp_die(__('Security check failed', 'dbas'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_email_templates';
        
        $wpdb->update(
            $table,
            array(
                'subject' => sanitize_text_field($_POST['subject']),
                'body' => sanitize_textarea_field($_POST['body'])
            ),
            array('template_key' => sanitize_text_field($_POST['template_key']))
        );
        
        wp_redirect(admin_url('admin.php?page=dbas-email-templates&template=' . $_POST['template_key'] . '&message=updated'));
        exit;
    }
    
    /**
     * Render reports page
     */
    public function render_reports_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Reports', 'dbas'); ?></h1>
            
            <div class="dbas-reports">
                <h2><?php _e('Select Report Type', 'dbas'); ?></h2>
                
                <form method="get" action="">
                    <input type="hidden" name="page" value="dbas-reports" />
                    
                    <select name="report_type">
                        <option value="daycare_inventory"><?php _e('Daycare Inventory Report', 'dbas'); ?></option>
                        <option value="checkin_checkout"><?php _e('Check-In/Check-Out Report', 'dbas'); ?></option>
                        <option value="overnight_dogs"><?php _e('Overnight Dogs Report', 'dbas'); ?></option>
                        <option value="revenue"><?php _e('Revenue Report', 'dbas'); ?></option>
                    </select>
                    
                    <label><?php _e('Date Range:', 'dbas'); ?></label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-01'); ?>" />
                    <input type="date" name="end_date" value="<?php echo date('Y-m-d'); ?>" />
                    
                    <input type="submit" value="<?php _e('Generate Report', 'dbas'); ?>" class="button button-primary" />
                </form>
                
                <?php
                if (isset($_GET['report_type'])) {
                    $this->generate_report($_GET['report_type'], $_GET['start_date'], $_GET['end_date']);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Generate report
     */
    private function generate_report($type, $start_date, $end_date) {
        global $wpdb;
        
        switch ($type) {
            case 'daycare_inventory':
                $this->generate_daycare_inventory_report($start_date, $end_date);
                break;
            case 'checkin_checkout':
                $this->generate_checkin_checkout_report($start_date, $end_date);
                break;
            case 'overnight_dogs':
                $this->generate_overnight_report($start_date, $end_date);
                break;
            case 'revenue':
                $this->generate_revenue_report($start_date, $end_date);
                break;
        }
    }
    
    /**
     * Generate daycare inventory report
     */
    private function generate_daycare_inventory_report($start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'dbas_daycare_checkins';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT checkin_date, COUNT(DISTINCT dog_id) as total_dogs, 
                    SUM(price) as total_revenue, AVG(price) as avg_price
             FROM $table
             WHERE checkin_date BETWEEN %s AND %s
             GROUP BY checkin_date
             ORDER BY checkin_date DESC",
            $start_date, $end_date
        ));
        
        ?>
        <h3><?php _e('Daycare Inventory Report', 'dbas'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'dbas'); ?></th>
                    <th><?php _e('Total Dogs', 'dbas'); ?></th>
                    <th><?php _e('Total Revenue', 'dbas'); ?></th>
                    <th><?php _e('Average Price', 'dbas'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($row->checkin_date)); ?></td>
                        <td><?php echo $row->total_dogs; ?></td>
                        <td>$<?php echo number_format($row->total_revenue, 2); ?></td>
                        <td>$<?php echo number_format($row->avg_price, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Generate checkout report
     */
    private function generate_checkin_checkout_report($start_date, $end_date) {
        // Implementation for check-in/check-out report
        ?>
        <h3><?php _e('Check-In/Check-Out Report', 'dbas'); ?></h3>
        <p><?php _e('Report implementation coming soon.', 'dbas'); ?></p>
        <?php
    }
    
    /**
     * Generate overnight report
     */
    private function generate_overnight_report($start_date, $end_date) {
        // Implementation for overnight dogs report
        ?>
        <h3><?php _e('Overnight Dogs Report', 'dbas'); ?></h3>
        <p><?php _e('Report implementation coming soon.', 'dbas'); ?></p>
        <?php
    }
    
    /**
     * Generate revenue report
     */
    private function generate_revenue_report($start_date, $end_date) {
        global $wpdb;
        $daycare_table = $wpdb->prefix . 'dbas_daycare_checkins';
        $reservations_table = $wpdb->prefix . 'dbas_reservations';
        
        // Daycare revenue
        $daycare_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(price) FROM $daycare_table WHERE checkin_date BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        // Boarding revenue
        $boarding_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM $reservations_table 
             WHERE checkin_date <= %s AND checkout_date >= %s AND status = 'approved'",
            $end_date, $start_date
        ));
        
        ?>
        <h3><?php _e('Revenue Report', 'dbas'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <tr>
                <th><?php _e('Revenue Type', 'dbas'); ?></th>
                <th><?php _e('Amount', 'dbas'); ?></th>
            </tr>
            <tr>
                <td><?php _e('Daycare Revenue', 'dbas'); ?></td>
                <td>$<?php echo number_format($daycare_revenue ?: 0, 2); ?></td>
            </tr>
            <tr>
                <td><?php _e('Boarding Revenue', 'dbas'); ?></td>
                <td>$<?php echo number_format($boarding_revenue ?: 0, 2); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Total Revenue', 'dbas'); ?></strong></td>
                <td><strong>$<?php echo number_format(($daycare_revenue ?: 0) + ($boarding_revenue ?: 0), 2); ?></strong></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * AJAX: Search owners
     */
    public function ajax_search_owners() {
        check_ajax_referer('dbas_daycare', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $search = sanitize_text_field($_POST['search']);
        
        global $wpdb;
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID as id, u.display_name as name, u.user_email as email 
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = 'dbas_phone' 
             AND (u.display_name LIKE %s OR u.user_email LIKE %s)
             LIMIT 10",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        ));
        
        wp_send_json_success($users);
    }
    
    /**
     * AJAX: Get owner's dogs
     */
    public function ajax_get_owner_dogs() {
        check_ajax_referer('dbas_daycare', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $owner_id = intval($_POST['owner_id']);
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        
        global $wpdb;
        $dogs_table = $wpdb->prefix . 'dbas_dogs';
        $reservations_table = $wpdb->prefix . 'dbas_reservations';
        $reservation_dogs_table = $wpdb->prefix . 'dbas_reservation_dogs';
        
        // Get owner's dogs
        $dogs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $dogs_table WHERE owner_id = %d AND status = 'active'",
            $owner_id
        ));
        
        // Check which dogs are boarding today
        foreach ($dogs as &$dog) {
            $is_boarding = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $reservations_table r
                 INNER JOIN $reservation_dogs_table rd ON r.id = rd.reservation_id
                 WHERE rd.dog_id = %d 
                 AND r.status = 'approved'
                 AND %s BETWEEN r.checkin_date AND r.checkout_date",
                $dog->id, $date
            ));
            $dog->is_boarding = $is_boarding > 0;
        }
        
        wp_send_json_success($dogs);
    }
    
    /**
     * AJAX: Get overnight dogs
     */
    public function ajax_get_overnight_dogs() {
        check_ajax_referer('dbas_daycare', 'nonce');
        
        if (!current_user_can('manage_dbas')) {
            wp_send_json_error(array('message' => __('Permission denied', 'dbas')));
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        global $wpdb;
        $reservations_table = $wpdb->prefix . 'dbas_reservations';
        $reservation_dogs_table = $wpdb->prefix . 'dbas_reservation_dogs';
        $dogs_table = $wpdb->prefix . 'dbas_dogs';
        
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name as owner_name 
             FROM $reservations_table r
             LEFT JOIN {$wpdb->users} u ON r.owner_id = u.ID
             WHERE r.status = 'approved'
             AND %s BETWEEN r.checkin_date AND r.checkout_date",
            $date
        ));
        
        foreach ($reservations as &$reservation) {
            $reservation->dogs = $wpdb->get_results($wpdb->prepare(
                "SELECT d.*, rd.kennel_number as kennel
                 FROM $dogs_table d
                 INNER JOIN $reservation_dogs_table rd ON d.id = rd.dog_id
                 WHERE rd.reservation_id = %d",
                $reservation->id
            ));
        }
        
        wp_send_json_success($reservations);
    }
}
?>