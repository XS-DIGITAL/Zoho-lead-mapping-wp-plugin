<?php
/**
 * Plugin Name: Zoho CRM Lead Mapping
 * Plugin URI: https://my-portfolio-xs.vercel.app/
 * Description: Build customizable lead capture forms and submit data directly to Zoho CRM. (Fully Free)
 * Version: 1.2.1
 * Author: Sonde Omotayo
 * License: GPL v2 or later
 * Text Domain: zoho-crm-pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZCP_VERSION', '1.2.1');
define('ZCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZCP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main plugin class
class Zoho_CRM_Lead_Capture_Pro {
   
    private static $instance = null;
   
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
   
    private function __construct() {
        $this->init();
    }
   
    private function init() {
        // Load plugin features unconditionally
        $this->setup_hooks();
       
        // Always load admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
       
        // Register shortcode
        add_shortcode('zoho_lead_maping', array($this, 'render_frontend_form'));
    }
   
    public function setup_hooks() {
        // Admin hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
       
        // AJAX handlers
        add_action('wp_ajax_zcp_refresh_products', array($this, 'ajax_refresh_products'));
        add_action('wp_ajax_nopriv_zcp_submit_lead', array($this, 'ajax_submit_lead'));
        add_action('wp_ajax_zcp_submit_lead', array($this, 'ajax_submit_lead'));
       
        // Initialize settings
        add_action('admin_init', array($this, 'initialize_form_settings'));
    }
   
    public function add_admin_menu() {
        add_menu_page(
            'Zoho Lead Capture',
            'Zoho Lead Capture',
            'manage_options',
            'zoho-lead-capture',
            array($this, 'render_admin_page'),
            'dashicons-forms',
            30
        );
    }
   
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'form-builder';
        ?>
        <div class="wrap zcp-admin-wrap">
            <h1><?php _e('Zoho CRM Lead Capture', 'zoho-crm-pro'); ?></h1>
           
            <h2 class="nav-tab-wrapper">
                <a href="?page=zoho-lead-capture&tab=form-builder" class="nav-tab <?php echo $active_tab == 'form-builder' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Form Builder', 'zoho-crm-pro'); ?>
                </a>
                <a href="?page=zoho-lead-capture&tab=zoho-settings" class="nav-tab <?php echo $active_tab == 'zoho-settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Zoho CRM Settings', 'zoho-crm-pro'); ?>
                </a>
                <a href="?page=zoho-lead-capture&tab=products" class="nav-tab <?php echo $active_tab == 'products' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Product Sync', 'zoho-crm-pro'); ?>
                </a>
                <a href="?page=zoho-lead-capture&tab=messages" class="nav-tab <?php echo $active_tab == 'messages' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Messages', 'zoho-crm-pro'); ?>
                </a>
            </h2>
           
            <div class="zcp-admin-content">
                <?php
                switch ($active_tab) {
                    case 'form-builder':
                        $this->render_form_builder();
                        break;
                    case 'zoho-settings':
                        $this->render_zoho_settings();
                        break;
                    case 'products':
                        $this->render_products_page();
                        break;
                    case 'messages':
                        $this->render_messages_page();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
   
    private function render_form_builder() {
        $form_settings = get_option('zcp_form_settings', array());
        $available_fields = $this->get_all_available_fields();
        $enabled_fields = isset($form_settings['enabled_fields']) ? $form_settings['enabled_fields'] : array_keys($available_fields);
        $fields_order = isset($form_settings['fields_order']) ? $form_settings['fields_order'] : $this->get_default_fields_order();
        $field_configs = isset($form_settings['field_configs']) ? $form_settings['field_configs'] : $this->get_default_field_configs();
       
        if (isset($_POST['zcp_save_form']) && check_admin_referer('zcp_save_form', 'zcp_form_nonce')) {
            $enabled_fields = isset($_POST['enabled_fields']) ? array_map('sanitize_text_field', $_POST['enabled_fields']) : array();
            $fields_order = isset($_POST['fields_order']) ? explode(',', sanitize_text_field($_POST['fields_order'])) : array();
            $fields_order = array_intersect($fields_order, $enabled_fields);
            foreach ($enabled_fields as $field) {
                if (!in_array($field, $fields_order)) {
                    $fields_order[] = $field;
                }
            }
           
            $form_settings = array(
                'enabled_fields' => $enabled_fields,
                'fields_order' => $fields_order,
                'field_configs' => array(),
                'button_text' => sanitize_text_field($_POST['button_text']),
                'default_lead_source' => sanitize_text_field($_POST['default_lead_source']),
            );
           
            foreach ($available_fields as $field => $display_name) {
                if (in_array($field, $enabled_fields)) {
                    $form_settings['field_configs'][$field] = array(
                        'label' => isset($_POST[$field . '_label']) ? sanitize_text_field($_POST[$field . '_label']) : ($field_configs[$field]['label'] ?? $display_name),
                        'required' => isset($_POST[$field . '_required']) ? 1 : 0,
                    );
                    if ($field === 'file_upload') {
                        $form_settings['field_configs'][$field]['allowed_types'] = isset($_POST['file_upload_allowed_types']) ?
                            sanitize_text_field($_POST['file_upload_allowed_types']) : ($field_configs[$field]['allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png');
                        $form_settings['field_configs'][$field]['max_size'] = isset($_POST['file_upload_max_size']) ?
                            absint($_POST['file_upload_max_size']) : ($field_configs[$field]['max_size'] ?? 5);
                    }
                } else {
                    if (isset($field_configs[$field])) {
                        $form_settings['field_configs'][$field] = $field_configs[$field];
                    }
                }
            }
           
            update_option('zcp_form_settings', $form_settings);
            echo '<div class="notice notice-success"><p>' . __('Form settings saved!', 'zoho-crm-pro') . '</p></div>';
           
            $enabled_fields = $form_settings['enabled_fields'];
            $fields_order = $form_settings['fields_order'];
            $field_configs = $form_settings['field_configs'];
        }
        ?>
        <form method="post" class="zcp-form-builder">
            <?php wp_nonce_field('zcp_save_form', 'zcp_form_nonce'); ?>
           
            <div class="card">
                <h3><?php _e('Available Fields', 'zoho-crm-pro'); ?></h3>
                <p class="description"><?php _e('Select which fields to include in your form:', 'zoho-crm-pro'); ?></p>
               
                <div class="zcp-available-fields">
                    <?php foreach ($available_fields as $field => $display_name): ?>
                        <label class="zcp-field-checkbox">
                            <input type="checkbox" name="enabled_fields[]" value="<?php echo esc_attr($field); ?>"
                                   <?php checked(in_array($field, $enabled_fields)); ?>>
                            <span><?php echo esc_html($display_name); ?></span>
                            <span class="zcp-field-type-badge"><?php echo esc_html($this->get_field_type_badge($field)); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
           
            <div class="card">
                <h3><?php _e('Field Order & Configuration', 'zoho-crm-pro'); ?></h3>
                <p class="description"><?php _e('Drag and drop to reorder enabled fields, configure labels and requirements.', 'zoho-crm-pro'); ?></p>
               
                <input type="hidden" name="fields_order" id="fields_order" value="<?php echo esc_attr(implode(',', $fields_order)); ?>">
               
                <?php if (empty($enabled_fields)): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('No fields selected. Please enable at least one field from the list above.', 'zoho-crm-pro'); ?></p>
                    </div>
                <?php else: ?>
                    <ul id="zcp-sortable-fields" class="zcp-sortable-list">
                        <?php foreach ($fields_order as $field):
                            if (!in_array($field, $enabled_fields)) continue;
                            $config = $field_configs[$field] ?? array(
                                'label' => $available_fields[$field],
                                'required' => false
                            );
                        ?>
                        <li class="zcp-sortable-item" data-field="<?php echo esc_attr($field); ?>">
                            <div class="zcp-field-header">
                                <span class="dashicons dashicons-menu"></span>
                                <strong><?php echo esc_html($available_fields[$field]); ?></strong>
                                <span class="zcp-field-type"><?php echo esc_html($this->get_field_type($field)); ?></span>
                            </div>
                            <div class="zcp-field-config">
                                <table class="form-table">
                                    <tr>
                                        <th><label for="<?php echo $field; ?>_label"><?php _e('Label:', 'zoho-crm-pro'); ?></label></th>
                                        <td><input type="text" id="<?php echo $field; ?>_label" name="<?php echo $field; ?>_label" value="<?php echo esc_attr($config['label']); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Required:', 'zoho-crm-pro'); ?></th>
                                        <td>
                                            <input type="checkbox" id="<?php echo $field; ?>_required" name="<?php echo $field; ?>_required" value="1" <?php checked($config['required'] ?? 0, 1); ?>>
                                            <label for="<?php echo $field; ?>_required"><?php _e('Make this field required', 'zoho-crm-pro'); ?></label>
                                        </td>
                                    </tr>
                                    <?php if ($field === 'file_upload'): ?>
                                    <tr>
                                        <th><label for="file_upload_allowed_types"><?php _e('Allowed File Types:', 'zoho-crm-pro'); ?></label></th>
                                        <td>
                                            <input type="text" id="file_upload_allowed_types" name="file_upload_allowed_types" value="<?php echo esc_attr($config['allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png'); ?>" class="regular-text">
                                            <p class="description"><?php _e('Comma-separated extensions (e.g., pdf,doc,jpg,png)', 'zoho-crm-pro'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="file_upload_max_size"><?php _e('Maximum File Size (MB):', 'zoho-crm-pro'); ?></label></th>
                                        <td>
                                            <input type="number" id="file_upload_max_size" name="file_upload_max_size" value="<?php echo esc_attr($config['max_size'] ?? 5); ?>" min="1" max="50" step="1">
                                            <p class="description"><?php _e('Maximum file size in megabytes', 'zoho-crm-pro'); ?></p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
           
            <div class="card">
                <h3><?php _e('Form Settings', 'zoho-crm-pro'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="button_text"><?php _e('Submit Button Text', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <input type="text" id="button_text" name="button_text" value="<?php echo esc_attr($form_settings['button_text'] ?? __('Submit', 'zoho-crm-pro')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_lead_source"><?php _e('Default Lead Source', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <input type="text" id="default_lead_source" name="default_lead_source" value="<?php echo esc_attr($form_settings['default_lead_source'] ?? 'Website'); ?>" class="regular-text">
                            <p class="description"><?php _e('This will be used if lead source field is not shown or filled', 'zoho-crm-pro'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
           
            <?php submit_button(__('Save Form Settings', 'zoho-crm-pro'), 'primary', 'zcp_save_form'); ?>
        </form>
        <?php
    }
   
    private function render_zoho_settings() {
        $zoho_settings = get_option('zcp_zoho_settings', array());
       
        if (isset($_POST['zcp_save_zoho_settings']) && check_admin_referer('zcp_save_zoho_settings', 'zcp_zoho_nonce')) {
            $zoho_settings = array(
                'client_id' => sanitize_text_field($_POST['client_id']),
                'client_secret' => sanitize_text_field($_POST['client_secret']),
                'refresh_token' => sanitize_textarea_field($_POST['refresh_token']),
                'api_domain' => sanitize_text_field($_POST['api_domain']),
                'accounts_domain' => sanitize_text_field($_POST['accounts_domain']),
            );
           
            update_option('zcp_zoho_settings', $zoho_settings);
            delete_transient('zcp_zoho_access_token');
           
            echo '<div class="notice notice-success"><p>' . __('Zoho CRM settings saved!', 'zoho-crm-pro') . '</p></div>';
        }
        ?>
        <form method="post" class="zcp-zoho-settings">
            <?php wp_nonce_field('zcp_save_zoho_settings', 'zcp_zoho_nonce'); ?>
           
            <div class="card">
                <h3><?php _e('Zoho CRM OAuth Configuration', 'zoho-crm-pro'); ?></h3>
                <p class="description"><?php _e('Enter your Zoho CRM OAuth credentials. Make sure you have generated a refresh token with proper scopes.', 'zoho-crm-pro'); ?></p>
               
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="client_id"><?php _e('Client ID', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <input type="text" id="client_id" name="client_id" value="<?php echo esc_attr($zoho_settings['client_id'] ?? ''); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="client_secret"><?php _e('Client Secret', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <input type="text" id="client_secret" name="client_secret" value="<?php echo esc_attr($zoho_settings['client_secret'] ?? ''); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="refresh_token"><?php _e('Refresh Token', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <textarea id="refresh_token" name="refresh_token" rows="3" class="large-text" required><?php echo esc_textarea($zoho_settings['refresh_token'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="api_domain"><?php _e('API Domain', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <select id="api_domain" name="api_domain" required>
                                <option value="https://www.zohoapis.com" <?php selected($zoho_settings['api_domain'] ?? '', 'https://www.zohoapis.com'); ?>>US</option>
                                <option value="https://www.zohoapis.eu" <?php selected($zoho_settings['api_domain'] ?? '', 'https://www.zohoapis.eu'); ?>>EU</option>
                                <option value="https://www.zohoapis.in" <?php selected($zoho_settings['api_domain'] ?? '', 'https://www.zohoapis.in'); ?>>IN</option>
                            </select>
                            <p class="description"><?php _e('Select your Zoho CRM data center', 'zoho-crm-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="accounts_domain"><?php _e('Accounts Domain', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <input type="text" id="accounts_domain" name="accounts_domain" value="<?php echo esc_attr($zoho_settings['accounts_domain'] ?? 'https://accounts.zoho.com'); ?>" class="regular-text" required>
                            <p class="description"><?php _e('Usually https://accounts.zoho.com (US), https://accounts.zoho.eu (EU), or https://accounts.zoho.in (IN)', 'zoho-crm-pro'); ?></p>
                        </td>
                    </tr>
                </table>
               
                <p><strong><?php _e('Note:', 'zoho-crm-pro'); ?></strong>
                <?php _e('To generate a refresh token, you need to create a Zoho CRM OAuth client with scopes: ZohoCRM.modules.ALL,ZohoCRM.users.READ,ZohoCRM.settings.ALL,ZohoCRM.org.READ', 'zoho-crm-pro'); ?></p>
            </div>
           
            <?php submit_button(__('Save Zoho CRM Settings', 'zoho-crm-pro'), 'primary', 'zcp_save_zoho_settings'); ?>
        </form>
        <?php
    }
   
    private function render_products_page() {
        $products = get_option('zcp_products', array());
        $last_refresh = get_option('zcp_products_last_refresh', '');
        ?>
        <div class="card">
            <h3><?php _e('Product Sync from Zoho CRM', 'zoho-crm-pro'); ?></h3>
           
            <div class="zcp-products-info">
                <p><?php printf(__('Total Products: <strong>%d</strong>', 'zoho-crm-pro'), count($products)); ?></p>
                <?php if ($last_refresh): ?>
                    <p><?php printf(__('Last Refreshed: <strong>%s</strong>', 'zoho-crm-pro'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_refresh))); ?></p>
                <?php endif; ?>
            </div>
           
            <p class="description"><?php _e('Click the button below to fetch products from your Zoho CRM Products module. Products will be used in the form dropdown.', 'zoho-crm-pro'); ?></p>
           
            <button type="button" id="zcp-refresh-products" class="button button-primary">
                <?php _e('Refresh Products from Zoho CRM', 'zoho-crm-pro'); ?>
            </button>
            <span id="zcp-refresh-spinner" class="spinner" style="float: none; display: none;"></span>
           
            <div id="zcp-refresh-result" style="margin-top: 15px;"></div>
           
            <?php if (!empty($products)): ?>
            <div class="zcp-products-list" style="margin-top: 30px;">
                <h4><?php _e('Available Products', 'zoho-crm-pro'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Product ID', 'zoho-crm-pro'); ?></th>
                            <th><?php _e('Product Name', 'zoho-crm-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $id => $name): ?>
                        <tr>
                            <td><?php echo esc_html($id); ?></td>
                            <td><?php echo esc_html($name); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
   
    private function render_messages_page() {
        $messages = get_option('zcp_messages', array());
       
        if (isset($_POST['zcp_save_messages']) && check_admin_referer('zcp_save_messages', 'zcp_messages_nonce')) {
            $messages = array(
                'success_message' => wp_kses_post($_POST['success_message']),
                'error_message' => wp_kses_post($_POST['error_message']),
                'validation_error' => wp_kses_post($_POST['validation_error']),
                'server_error' => wp_kses_post($_POST['server_error']),
            );
           
            update_option('zcp_messages', $messages);
            echo '<div class="notice notice-success"><p>' . __('Messages saved!', 'zoho-crm-pro') . '</p></div>';
        }
        ?>
        <form method="post" class="zcp-messages-settings">
            <?php wp_nonce_field('zcp_save_messages', 'zcp_messages_nonce'); ?>
           
            <div class="card">
                <h3><?php _e('Customize Form Messages', 'zoho-crm-pro'); ?></h3>
                <p class="description"><?php _e('Customize the messages shown to users during form submission.', 'zoho-crm-pro'); ?></p>
               
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="success_message"><?php _e('Success Message', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <textarea id="success_message" name="success_message" rows="3" class="large-text"><?php echo esc_textarea($messages['success_message'] ?? __('Thank you! Your information has been submitted successfully.', 'zoho-crm-pro')); ?></textarea>
                            <p class="description"><?php _e('Message shown when form is submitted successfully', 'zoho-crm-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="error_message"><?php _e('General Error Message', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <textarea id="error_message" name="error_message" rows="2" class="large-text"><?php echo esc_textarea($messages['error_message'] ?? __('An error occurred. Please try again.', 'zoho-crm-pro')); ?></textarea>
                            <p class="description"><?php _e('Message shown for general errors', 'zoho-crm-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="validation_error"><?php _e('Validation Error Message', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <textarea id="validation_error" name="validation_error" rows="2" class="large-text"><?php echo esc_textarea($messages['validation_error'] ?? __('Please correct the errors below.', 'zoho-crm-pro')); ?></textarea>
                            <p class="description"><?php _e('Message shown when form validation fails', 'zoho-crm-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="server_error"><?php _e('Server Error Message', 'zoho-crm-pro'); ?></label></th>
                        <td>
                            <textarea id="server_error" name="server_error" rows="2" class="large-text"><?php echo esc_textarea($messages['server_error'] ?? __('Unable to connect to Zoho CRM. Please try again later.', 'zoho-crm-pro')); ?></textarea>
                            <p class="description"><?php _e('Message shown when Zoho CRM connection fails', 'zoho-crm-pro'); ?></p>
                        </td>
                    </tr>
                </table>
               
                <p><strong><?php _e('Available placeholders:', 'zoho-crm-pro'); ?></strong></p>
                <ul>
                    <li><code>{first_name}</code> - <?php _e('Customer\'s first name', 'zoho-crm-pro'); ?></li>
                    <li><code>{last_name}</code> - <?php _e('Customer\'s last name', 'zoho-crm-pro'); ?></li>
                    <li><code>{email}</code> - <?php _e('Customer\'s email address', 'zoho-crm-pro'); ?></li>
                    <li><code>{company}</code> - <?php _e('Customer\'s company name', 'zoho-crm-pro'); ?></li>
                </ul>
            </div>
           
            <?php submit_button(__('Save Messages', 'zoho-crm-pro'), 'primary', 'zcp_save_messages'); ?>
        </form>
        <?php
    }
   
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'zoho-lead-capture') === false) {
            return;
        }
       
        wp_enqueue_style('zcp-admin-styles', ZCP_PLUGIN_URL . 'assets/css/admin.css', array(), ZCP_VERSION);
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('zcp-admin-script', ZCP_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), ZCP_VERSION, true);
       
        wp_localize_script('zcp-admin-script', 'zcp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zcp_admin_nonce'),
            'strings' => array(
                'refreshing' => __('Refreshing products...', 'zoho-crm-pro'),
                'success' => __('Products refreshed successfully!', 'zoho-crm-pro'),
                'error' => __('Error refreshing products. Please check your Zoho CRM settings.', 'zoho-crm-pro'),
            )
        ));
    }
   
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('zcp-frontend-styles', ZCP_PLUGIN_URL . 'assets/css/frontend.css', array(), ZCP_VERSION);
        wp_enqueue_script('zcp-frontend-script', ZCP_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), ZCP_VERSION, true);
       
        wp_localize_script('zcp-frontend-script', 'zcp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zcp_lead_submit_nonce'),
            'strings' => array(
                'submitting' => __('Submitting...', 'zoho-crm-pro'),
                'required_field' => __('This field is required.', 'zoho-crm-pro'),
                'invalid_email' => __('Please enter a valid email address.', 'zoho-crm-pro'),
                'invalid_file' => __('Invalid file type or size.', 'zoho-crm-pro'),
            )
        ));
    }
   
    public function render_frontend_form($atts) {
        $form_settings = get_option('zcp_form_settings', array());
        $enabled_fields = isset($form_settings['enabled_fields']) ? $form_settings['enabled_fields'] : array_keys($this->get_all_available_fields());
        $fields_order = isset($form_settings['fields_order']) ? $form_settings['fields_order'] : $this->get_default_fields_order();
        $field_configs = isset($form_settings['field_configs']) ? $form_settings['field_configs'] : $this->get_default_field_configs();
        $button_text = $form_settings['button_text'] ?? __('Submit', 'zoho-crm-pro');
       
        $fields_order = array_intersect($fields_order, $enabled_fields);
       
        ob_start();
        ?>
        <div class="zcp-lead-form-wrapper">
            <form id="zcp-lead-form" class="zcp-lead-form" method="post" enctype="multipart/form-data">
                <div class="zcp-form-card">
                    <?php foreach ($fields_order as $field):
                        if (!in_array($field, $enabled_fields)) continue;
                       
                        $config = $field_configs[$field] ?? array(
                            'label' => $this->get_all_available_fields()[$field],
                            'required' => false
                        );
                        $required = $config['required'] ? 'required' : '';
                        $required_attr = $config['required'] ? 'required="required"' : '';
                        $required_mark = $config['required'] ? '<span class="zcp-required">*</span>' : '';
                    ?>
                    <div class="zcp-form-group zcp-field-<?php echo esc_attr($field); ?>">
                        <label for="zcp_<?php echo $field; ?>">
                            <?php echo esc_html($config['label']); ?> <?php echo $required_mark; ?>
                        </label>
                       
                        <?php if ($field === 'notes' || $field === 'additional_notes'): ?>
                            <textarea id="zcp_<?php echo $field; ?>" name="<?php echo $field; ?>" <?php echo $required_attr; ?> rows="4" placeholder="<?php echo esc_attr($config['placeholder'] ?? ''); ?>"></textarea>
                       
                        <?php elseif ($field === 'product_select'): ?>
                            <?php $products = get_option('zcp_products', array()); ?>
                            <select id="zcp_<?php echo $field; ?>" name="<?php echo $field; ?>" <?php echo $required_attr; ?>>
                                <option value=""><?php echo esc_attr($config['placeholder'] ?? __('-- Select a Product --', 'zoho-crm-pro')); ?></option>
                                <?php foreach ($products as $id => $name): ?>
                                    <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                       
                        <?php elseif ($field === 'file_upload'): ?>
                            <input type="file" id="zcp_<?php echo $field; ?>" name="<?php echo $field; ?>" <?php echo $required_attr; ?>
                                   accept="<?php echo esc_attr($this->get_accept_attribute($config['allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png')); ?>">
                            <small class="zcp-file-hint">
                                <?php printf(__('Max size: %dMB. Allowed: %s', 'zoho-crm-pro'),
                                    $config['max_size'] ?? 5,
                                    esc_html($config['allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png')); ?>
                            </small>
                       
                        <?php elseif ($field === 'lead_source'): ?>
                            <input type="text" id="zcp_<?php echo $field; ?>" name="<?php echo $field; ?>"
                                   value="<?php echo esc_attr($form_settings['default_lead_source'] ?? 'Website'); ?>" <?php echo $required_attr; ?>
                                   placeholder="<?php echo esc_attr($config['placeholder'] ?? ''); ?>">
                       
                        <?php elseif ($field === 'description'): ?>
                            <textarea id="zcp_<?php echo $field; ?>" name="<?php echo $field; ?>" <?php echo $required_attr; ?> rows="3" placeholder="<?php echo esc_attr($config['placeholder'] ?? ''); ?>"></textarea>
                       
                        <?php else: ?>
                            <input type="<?php echo $this->get_input_type($field); ?>" id="zcp_<?php echo $field; ?>"
                                   name="<?php echo $field; ?>" <?php echo $required_attr; ?>
                                   placeholder="<?php echo esc_attr($config['placeholder'] ?? ''); ?>">
                        <?php endif; ?>
                       
                        <div class="zcp-field-error" id="zcp_error_<?php echo $field; ?>"></div>
                    </div>
                    <?php endforeach; ?>
                   
                    <div class="zcp-form-submit">
                        <button type="submit" class="zcp-submit-button">
                            <?php echo esc_html($button_text); ?>
                        </button>
                        <div class="zcp-spinner" style="display: none;"></div>
                    </div>
                   
                    <div class="zcp-form-messages"></div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
   
    public function ajax_refresh_products() {
        if (!current_user_can('manage_options') || !check_ajax_referer('zcp_admin_nonce', 'nonce', false)) {
            wp_die('Unauthorized');
        }
       
        $access_token = $this->get_access_token();
        if (!$access_token) {
            wp_send_json_error(array('message' => __('Unable to get access token. Check Zoho CRM settings.', 'zoho-crm-pro')));
        }
       
        $zoho_settings = get_option('zcp_zoho_settings', array());
        $api_domain = $zoho_settings['api_domain'] ?? 'https://www.zohoapis.com';
       
        $all_products = array();
        $page = 1;
        $per_page = 200;
        $has_more = true;
       
        while ($has_more) {
            $url = $api_domain . '/crm/v8/Products?fields=id,Product_Name&page=' . $page . '&per_page=' . $per_page;
           
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 30,
            ));
           
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => $response->get_error_message()));
            }
           
            $body = json_decode(wp_remote_retrieve_body($response), true);
           
            if (isset($body['data'])) {
                foreach ($body['data'] as $product) {
                    $all_products[$product['id']] = $product['Product_Name'];
                }
            }
           
            $has_more = isset($body['info']['more_records']) && $body['info']['more_records'];
            $page++;
           
            if ($page > 10) break;
        }
       
        update_option('zcp_products', $all_products);
        update_option('zcp_products_last_refresh', current_time('mysql'));
       
        wp_send_json_success(array(
            'count' => count($all_products),
            'message' => sprintf(__('Successfully fetched %d products.', 'zoho-crm-pro'), count($all_products))
        ));
    }
   
    public function ajax_submit_lead() {
        if (!check_ajax_referer('zcp_lead_submit_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'zoho-crm-pro')));
        }
       
        $form_settings = get_option('zcp_form_settings', array());
        $enabled_fields = isset($form_settings['enabled_fields']) ? $form_settings['enabled_fields'] : array_keys($this->get_all_available_fields());
        $field_configs = isset($form_settings['field_configs']) ? $form_settings['field_configs'] : $this->get_default_field_configs();
       
        $messages = get_option('zcp_messages', array());
       
        $errors = array();
        $data = array();
       
        foreach ($enabled_fields as $field) {
            $config = $field_configs[$field] ?? array(
                'label' => $this->get_all_available_fields()[$field],
                'required' => false
            );
           
            $value = '';
           
            if ($field === 'file_upload') {
                if (!empty($_FILES[$field]['name'])) {
                    $file_error = $this->validate_uploaded_file($field, $config);
                    if ($file_error) {
                        $errors[$field] = $file_error;
                    } else {
                        $data[$field] = $_FILES[$field];
                    }
                } elseif ($config['required']) {
                    $errors[$field] = __('Please upload a file.', 'zoho-crm-pro');
                }
                continue;
            }
           
            if (isset($_POST[$field])) {
                if ($field === 'notes' || $field === 'additional_notes' || $field === 'description') {
                    $value = sanitize_textarea_field(wp_unslash($_POST[$field]));
                } else {
                    $value = sanitize_text_field(wp_unslash($_POST[$field]));
                }
            }
           
            if ($config['required'] && empty($value)) {
                $errors[$field] = __('This field is required.', 'zoho-crm-pro');
            }
           
            if ($field === 'email' && !empty($value) && !is_email($value)) {
                $errors[$field] = __('Please enter a valid email address.', 'zoho-crm-pro');
            }
           
            $data[$field] = $value;
        }
       
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => $messages['validation_error'] ?? __('Please correct the errors below.', 'zoho-crm-pro'),
                'errors' => $errors
            ));
        }
       
        $access_token = $this->get_access_token();
        if (!$access_token) {
            wp_send_json_error(array('message' => $messages['server_error'] ?? __('Unable to connect to Zoho CRM. Please try again later.', 'zoho-crm-pro')));
        }
       
        $zoho_settings = get_option('zcp_zoho_settings', array());
        $api_domain = $zoho_settings['api_domain'] ?? 'https://www.zohoapis.com';
       
        $lead_data = array(
            'First_Name' => $data['first_name'] ?? '',
            'Last_Name' => $data['last_name'] ?? '',
            'Company' => $data['company'] ?? '',
            'Email' => $data['email'] ?? '',
            'Phone' => $data['phone'] ?? '',
            'Mobile' => $data['mobile'] ?? '',
            'Lead_Source' => $data['lead_source'] ?? ($form_settings['default_lead_source'] ?? 'Website'),
            'Description' => $data['description'] ?? '',
        );
       
        $lead_data = array_filter($lead_data, function($value) {
            return $value !== '';
        });
       
        $lead_id = $this->create_zoho_lead($access_token, $api_domain, $lead_data);
       
        if (!$lead_id) {
            wp_send_json_error(array('message' => $messages['server_error'] ?? __('Failed to create lead in Zoho CRM.', 'zoho-crm-pro')));
        }
       
        if (!empty($data['file_upload'])) {
            $this->upload_file_to_lead($access_token, $api_domain, $lead_id, $data['file_upload']);
        }
       
        $notes_content = '';
        if (!empty($data['notes'])) {
            $notes_content = $data['notes'];
        } elseif (!empty($data['additional_notes'])) {
            $notes_content = $data['additional_notes'];
        }
       
        if (!empty($notes_content)) {
            $this->add_note_to_lead($access_token, $api_domain, $lead_id, $notes_content);
        }
       
        if (!empty($data['product_select'])) {
            $this->associate_product_to_lead($access_token, $api_domain, $lead_id, $data['product_select']);
        }
       
        $success_message = $messages['success_message'] ?? __('Thank you! Your information has been submitted successfully.', 'zoho-crm-pro');
       
        $placeholders = array(
            '{first_name}' => $data['first_name'] ?? '',
            '{last_name}' => $data['last_name'] ?? '',
            '{email}' => $data['email'] ?? '',
            '{company}' => $data['company'] ?? '',
        );
       
        $success_message = str_replace(array_keys($placeholders), array_values($placeholders), $success_message);
       
        wp_send_json_success(array('message' => $success_message));
    }
   
    private function create_zoho_lead($access_token, $api_domain, $lead_data) {
        $url = $api_domain . '/crm/v8/Leads';
       
        $body = json_encode(array(
            'data' => array($lead_data)
        ));
       
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => $body,
            'timeout' => 30,
        ));
       
        if (is_wp_error($response)) {
            $this->log_error('Create Lead Error: ' . $response->get_error_message());
            return false;
        }
       
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
       
        if (isset($response_body['data'][0]['details']['id'])) {
            return $response_body['data'][0]['details']['id'];
        } else {
            $this->log_error('Create Lead Response: ' . print_r($response_body, true));
            return false;
        }
    }
   
    private function upload_file_to_lead($access_token, $api_domain, $lead_id, $file) {
        $url = $api_domain . '/crm/v8/Leads/' . $lead_id . '/Attachments';
       
        $boundary = wp_generate_password(24);
        $payload = '';
       
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file['name']) . '"' . "\r\n";
        $payload .= 'Content-Type: ' . $file['type'] . "\r\n";
        $payload .= "\r\n";
        $payload .= file_get_contents($file['tmp_name']);
        $payload .= "\r\n";
        $payload .= '--' . $boundary . '--';
       
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $payload,
            'timeout' => 60,
        ));
       
        if (is_wp_error($response)) {
            $this->log_error('File Upload Error: ' . $response->get_error_message());
        }
    }
   
    private function add_note_to_lead($access_token, $api_domain, $lead_id, $notes) {
        $url = $api_domain . '/crm/v8/Leads/' . $lead_id . '/Notes';
       
        $body = json_encode(array(
            'data' => array(array(
                'Note_Title' => 'Form Submission Notes',
                'Note_Content' => $notes
            ))
        ));
       
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => $body,
            'timeout' => 30,
        ));
       
        if (is_wp_error($response)) {
            $this->log_error('Add Note Error: ' . $response->get_error_message());
        }
    }
   
    private function associate_product_to_lead($access_token, $api_domain, $lead_id, $product_id) {
        $url = $api_domain . '/crm/v8/Leads/' . $lead_id . '/Products/' . $product_id;
       
        $body = json_encode(array(
            'data' => array(array(
                'Product' => array('id' => $product_id),
                'Quantity' => 1,
                'List_Price' => 0.00
            ))
        ));
       
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => $body,
            'timeout' => 30,
        ));
       
        if (is_wp_error($response)) {
            $this->log_error('Associate Product Error: ' . $response->get_error_message());
        }
    }
   
    private function get_access_token() {
        $cached_token = get_transient('zcp_zoho_access_token');
        if ($cached_token) {
            return $cached_token;
        }
       
        $zoho_settings = get_option('zcp_zoho_settings', array());
       
        if (empty($zoho_settings['client_id']) || empty($zoho_settings['client_secret']) || empty($zoho_settings['refresh_token'])) {
            return false;
        }
       
        $accounts_domain = $zoho_settings['accounts_domain'] ?? 'https://accounts.zoho.com';
       
        $url = $accounts_domain . '/oauth/v2/token';
       
        $response = wp_remote_post($url, array(
            'body' => array(
                'grant_type' => 'refresh_token',
                'client_id' => $zoho_settings['client_id'],
                'client_secret' => $zoho_settings['client_secret'],
                'refresh_token' => $zoho_settings['refresh_token'],
            ),
            'timeout' => 30,
        ));
       
        if (is_wp_error($response)) {
            $this->log_error('Access Token Error: ' . $response->get_error_message());
            return false;
        }
       
        $body = json_decode(wp_remote_retrieve_body($response), true);
       
        if (isset($body['access_token'])) {
            set_transient('zcp_zoho_access_token', $body['access_token'], 55 * MINUTE_IN_SECONDS);
            return $body['access_token'];
        } else {
            $this->log_error('Access Token Response: ' . print_r($body, true));
            return false;
        }
    }
   
    private function validate_uploaded_file($field, $config) {
        $file = $_FILES[$field];
       
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return __('File upload error. Please try again.', 'zoho-crm-pro');
        }
       
        $max_size = ($config['max_size'] ?? 5) * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return sprintf(__('File size exceeds maximum limit of %dMB.', 'zoho-crm-pro'), $config['max_size'] ?? 5);
        }
       
        $allowed_types = explode(',', $config['allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png');
        $allowed_types = array_map('trim', $allowed_types);
        $allowed_types = array_map('strtolower', $allowed_types);
       
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
       
        if (!in_array($file_ext, $allowed_types)) {
            return sprintf(__('File type not allowed. Allowed types: %s', 'zoho-crm-pro'), $config['allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png');
        }
       
        return '';
    }
   
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Zoho CRM Lead Capture: ' . $message);
        }
       
        $errors = get_option('zcp_error_log', array());
        $errors[] = array(
            'time' => current_time('mysql'),
            'message' => $message
        );
       
        if (count($errors) > 50) {
            $errors = array_slice($errors, -50);
        }
       
        update_option('zcp_error_log', $errors);
    }
   
    private function get_all_available_fields() {
        return array(
            'first_name' => __('First Name', 'zoho-crm-pro'),
            'last_name' => __('Last Name', 'zoho-crm-pro'),
            'company' => __('Company', 'zoho-crm-pro'),
            'email' => __('Email', 'zoho-crm-pro'),
            'phone' => __('Phone', 'zoho-crm-pro'),
            'mobile' => __('Mobile', 'zoho-crm-pro'),
            'lead_source' => __('Lead Source', 'zoho-crm-pro'),
            'description' => __('Description', 'zoho-crm-pro'),
            'notes' => __('Additional Info (Notes)', 'zoho-crm-pro'),
            'additional_notes' => __('Additional Notes', 'zoho-crm-pro'),
            'product_select' => __('Product Select', 'zoho-crm-pro'),
            'file_upload' => __('File Upload', 'zoho-crm-pro'),
        );
    }
   
    private function get_default_fields_order() {
        return array(
            'first_name',
            'last_name',
            'company',
            'email',
            'phone',
            'mobile',
            'lead_source',
            'description',
            'notes',
            'additional_notes',
            'product_select',
            'file_upload',
        );
    }
   
    private function get_default_field_configs() {
        return array(
            'first_name' => array('label' => __('First Name', 'zoho-crm-pro'), 'required' => true),
            'last_name' => array('label' => __('Last Name', 'zoho-crm-pro'), 'required' => true),
            'company' => array('label' => __('Company', 'zoho-crm-pro'), 'required' => false),
            'email' => array('label' => __('Email', 'zoho-crm-pro'), 'required' => true),
            'phone' => array('label' => __('Phone', 'zoho-crm-pro'), 'required' => false),
            'mobile' => array('label' => __('Mobile', 'zoho-crm-pro'), 'required' => false),
            'lead_source' => array('label' => __('Lead Source', 'zoho-crm-pro'), 'required' => false),
            'description' => array('label' => __('Description', 'zoho-crm-pro'), 'required' => false),
            'notes' => array('label' => __('Additional Info (Notes)', 'zoho-crm-pro'), 'required' => false),
            'additional_notes' => array('label' => __('Additional Notes', 'zoho-crm-pro'), 'required' => false),
            'product_select' => array('label' => __('Product Select', 'zoho-crm-pro'), 'required' => false),
            'file_upload' => array(
                'label' => __('File Upload', 'zoho-crm-pro'),
                'required' => false,
                'allowed_types' => 'pdf,doc,docx,jpg,jpeg,png',
                'max_size' => 5,
            ),
        );
    }
   
    private function get_field_type_badge($field) {
        $types = array(
            'first_name' => 'text',
            'last_name' => 'text',
            'company' => 'text',
            'email' => 'email',
            'phone' => 'tel',
            'mobile' => 'tel',
            'lead_source' => 'text',
            'description' => 'textarea',
            'notes' => 'textarea',
            'additional_notes' => 'textarea',
            'product_select' => 'select',
            'file_upload' => 'file',
        );
        return $types[$field] ?? 'text';
    }
   
    private function get_field_type($field) {
        $types = array(
            'first_name' => 'text',
            'last_name' => 'text',
            'company' => 'text',
            'email' => 'email',
            'phone' => 'tel',
            'mobile' => 'tel',
            'lead_source' => 'text',
            'description' => 'textarea',
            'notes' => 'textarea',
            'additional_notes' => 'textarea',
            'product_select' => 'select',
            'file_upload' => 'file',
        );
        return $types[$field] ?? 'text';
    }
   
    private function get_input_type($field) {
        $types = array(
            'email' => 'email',
            'phone' => 'tel',
            'mobile' => 'tel',
            'first_name' => 'text',
            'last_name' => 'text',
            'company' => 'text',
            'lead_source' => 'text',
        );
        return $types[$field] ?? 'text';
    }
   
    private function get_accept_attribute($allowed_types) {
        $mime_types = array(
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        );
       
        $types = explode(',', $allowed_types);
        $types = array_map('trim', $types);
       
        $accepts = array();
        foreach ($types as $type) {
            if (isset($mime_types[$type])) {
                $accepts[] = $mime_types[$type];
            } else {
                $accepts[] = '.' . $type;
            }
        }
       
        return implode(',', $accepts);
    }
   
    public function register_settings() {
        register_setting('zcp_settings', 'zcp_form_settings');
        register_setting('zcp_settings', 'zcp_zoho_settings');
        register_setting('zcp_settings', 'zcp_messages');
        register_setting('zcp_settings', 'zcp_products');
        register_setting('zcp_settings', 'zcp_products_last_refresh');
    }
   
    public function initialize_form_settings() {
        if (false === get_option('zcp_form_settings')) {
            update_option('zcp_form_settings', array(
                'enabled_fields' => array_keys($this->get_all_available_fields()),
                'fields_order' => $this->get_default_fields_order(),
                'field_configs' => $this->get_default_field_configs(),
                'button_text' => __('Submit', 'zoho-crm-pro'),
                'default_lead_source' => 'Website',
            ));
        }
       
        if (false === get_option('zcp_messages')) {
            update_option('zcp_messages', array(
                'success_message' => __('Thank you! Your information has been submitted successfully.', 'zoho-crm-pro'),
                'error_message' => __('An error occurred. Please try again.', 'zoho-crm-pro'),
                'validation_error' => __('Please correct the errors below.', 'zoho-crm-pro'),
                'server_error' => __('Unable to connect to Zoho CRM. Please try again later.', 'zoho-crm-pro'),
            ));
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    Zoho_CRM_Lead_Capture_Pro::get_instance();
});

// Create necessary directories and files on activation
register_activation_hook(__FILE__, function() {
    $upload_dir = wp_upload_dir();
    $plugin_assets_dir = ZCP_PLUGIN_DIR . 'assets';
   
    $dirs = array(
        $plugin_assets_dir . '/css',
        $plugin_assets_dir . '/js',
    );
   
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
   
    // Admin CSS
    $admin_css = <<<CSS
/* Admin Styles */
.zcp-admin-wrap { margin-top: 20px; }
.zcp-admin-content { margin-top: 20px; }
.zcp-available-fields { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
.zcp-field-checkbox { display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; cursor: pointer; transition: all 0.2s; position: relative; width: calc(33.333% - 15px); box-sizing: border-box; }
@media (max-width: 1200px) { .zcp-field-checkbox { width: calc(50% - 15px); } }
@media (max-width: 782px) { .zcp-field-checkbox { width: 100%; } }
.zcp-field-checkbox:hover { border-color: #007cba; box-shadow: 0 0 0 1px #007cba; }
.zcp-field-checkbox input[type="checkbox"] { margin: 0; }
.zcp-field-type-badge { background: #f0f0f0; padding: 2px 8px; border-radius: 3px; font-size: 11px; color: #666; margin-left: auto; text-transform: uppercase; font-weight: normal; }
.zcp-sortable-list { list-style: none; padding: 0; margin: 20px 0; }
.zcp-sortable-item { background: #fff; border: 1px solid #ccd0d4; margin: 0 0 10px; padding: 15px; border-radius: 4px; cursor: move; }
.zcp-sortable-item.ui-sortable-helper { box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
.zcp-field-header { display: flex; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; cursor: pointer; }
.zcp-field-header .dashicons-menu { margin-right: 10px; color: #a0a5aa; }
.zcp-field-header strong { flex-grow: 1; margin-right: 15px; }
.zcp-field-type { background: #f0f0f0; padding: 2px 8px; border-radius: 3px; font-size: 12px; color: #666; }
.zcp-field-config { display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f0; }
.zcp-field-config table { margin: 0; }
.zcp-field-config td { padding: 5px 10px 5px 0; }
.zcp-products-info { background: #f8f9fa; border-left: 4px solid #007cba; padding: 10px 15px; margin: 15px 0; }
.zcp-products-info p { margin: 5px 0; }
.notice { margin: 20px 0 10px; }
CSS;
    file_put_contents($plugin_assets_dir . '/css/admin.css', $admin_css);
   
    // Frontend CSS
    $frontend_css = <<<CSS
/* Frontend Form Styles */
.zcp-lead-form-wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
.zcp-form-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
.zcp-form-group { margin-bottom: 20px; }
.zcp-form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
.zcp-required { color: #dc3232; }
.zcp-form-group input[type="text"], .zcp-form-group input[type="email"], .zcp-form-group input[type="tel"], .zcp-form-group textarea, .zcp-form-group select, .zcp-form-group input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; box-sizing: border-box; font-family: inherit; }
.zcp-form-group textarea { min-height: 100px; resize: vertical; font-family: inherit; }
.zcp-form-group select { height: 42px; }
.zcp-form-group input[type="file"] { padding: 8px 0; border: none; }
.zcp-file-hint { display: block; color: #666; font-size: 13px; margin-top: 5px; }
.zcp-form-submit { margin-top: 30px; text-align: center; }
.zcp-submit-button { background: #007cba; color: #fff; border: none; padding: 12px 30px; font-size: 16px; border-radius: 4px; cursor: pointer; transition: background 0.3s; }
.zcp-submit-button:hover { background: #005a87; }
.zcp-submit-button:disabled { background: #ccc; cursor: not-allowed; }
.zcp-spinner { display: inline-block; margin-left: 10px; width: 20px; height: 20px; border: 3px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #007cba; animation: spin 1s ease-in-out infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.zcp-form-messages { margin-top: 20px; padding: 15px; border-radius: 4px; display: none; }
.zcp-form-messages.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.zcp-form-messages.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.zcp-field-error { color: #dc3232; font-size: 13px; margin-top: 5px; display: none; }
.zcp-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; border: 1px solid #f5c6cb; text-align: center; }
CSS;
    file_put_contents($plugin_assets_dir . '/css/frontend.css', $frontend_css);
   
    // Admin JS
    $admin_js = <<<JS
jQuery(document).ready(function($) {
    if ($('#zcp-sortable-fields').hasClass('ui-sortable')) {
        $('#zcp-sortable-fields').sortable('destroy');
    }

    $('#zcp-sortable-fields').sortable({
        handle: '.zcp-field-header',
        items: '.zcp-sortable-item',
        update: function(event, ui) {
            var order = [];
            $('#zcp-sortable-fields > li').each(function() {
                order.push($(this).data('field'));
            });
            $('#fields_order').val(order.join(','));
        }
    });

    $(document).on('click', '.zcp-field-header', function(e) {
        e.stopPropagation();
        $(this).closest('.zcp-sortable-item').find('.zcp-field-config').slideToggle(200);
    });

    $('.zcp-field-config').hide();

    $('.zcp-field-checkbox input').on('change', function() {
        var field = $(this).val();
        var isChecked = $(this).is(':checked');
        var \$label = $(this).closest('.zcp-field-checkbox');
        var fieldName = \$label.find('span').first().text().trim();
        var fieldType = \$label.find('.zcp-field-type-badge').text().trim();

        var \$item = $('#zcp-sortable-fields li[data-field="' + field + '"]');

        if (isChecked && \$item.length === 0) {
            var configHtml = '<table class="form-table">' +
                '<tr><th><label>Label:</label></th>' +
                '<td><input type="text" name="' + field + '_label" value="' + fieldName + '" class="regular-text"></td></tr>' +
                '<tr><th>Required:</th>' +
                '<td><input type="checkbox" name="' + field + '_required" value="1">' +
                '<label>Make this field required</label></td></tr>';

            if (field === 'file_upload') {
                configHtml += '<tr><th><label>Allowed File Types:</label></th>' +
                    '<td><input type="text" name="file_upload_allowed_types" value="pdf,doc,docx,jpg,jpeg,png" class="regular-text">' +
                    '<p class="description">Comma-separated extensions (e.g., pdf,doc,jpg,png)</p></td></tr>' +
                    '<tr><th><label>Maximum File Size (MB):</label></th>' +
                    '<td><input type="number" name="file_upload_max_size" value="5" min="1" max="50">' +
                    '<p class="description">Maximum file size in megabytes</p></td></tr>';
            }

            configHtml += '</table>';

            var itemHtml = '<li class="zcp-sortable-item" data-field="' + field + '">' +
                '<div class="zcp-field-header">' +
                '<span class="dashicons dashicons-menu"></span>' +
                '<strong>' + fieldName + '</strong>' +
                '<span class="zcp-field-type">' + fieldType + '</span>' +
                '</div>' +
                '<div class="zcp-field-config" style="display:none;">' + configHtml + '</div>' +
                '</li>';

            $('#zcp-sortable-fields').append(itemHtml);

            var currentOrder = $('#fields_order').val() ? $('#fields_order').val().split(',') : [];
            currentOrder.push(field);
            $('#fields_order').val(currentOrder.join(','));

            $('#zcp-sortable-fields').sortable('refresh');
        } else if (!isChecked && \$item.length > 0) {
            \$item.remove();

            var currentOrder = $('#fields_order').val().split(',');
            var index = currentOrder.indexOf(field);
            if (index > -1) {
                currentOrder.splice(index, 1);
            }
            $('#fields_order').val(currentOrder.join(','));
        }
    });

    $('#zcp-refresh-products').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var \$button = $(this);
        var \$spinner = $('#zcp-refresh-spinner');
        var \$result = $('#zcp-refresh-result');

        \$button.prop('disabled', true);
        \$spinner.show();
        \$result.html('<p>' + zcp_admin.strings.refreshing + '</p>');

        $.ajax({
            url: zcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'zcp_refresh_products',
                nonce: zcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    \$result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    \$result.html('<div class="notice notice-error"><p>' + (response.data.message || zcp_admin.strings.error) + '</p></div>');
                }
            },
            error: function() {
                \$result.html('<div class="notice notice-error"><p>' + zcp_admin.strings.error + '</p></div>');
            },
            complete: function() {
                \$button.prop('disabled', false);
                \$spinner.hide();
            }
        });
    });
});
JS;
    file_put_contents($plugin_assets_dir . '/js/admin.js', $admin_js);
   
    // Frontend JS
    $frontend_js = <<<JS
jQuery(document).ready(function($) {
    var form = $('#zcp-lead-form');
    var submitButton = $('.zcp-submit-button');
    var spinner = $('.zcp-spinner');
    var messagesDiv = $('.zcp-form-messages');
   
    form.find('input, textarea, select').on('blur', function() {
        validateField($(this));
    });
   
    form.find('input[type="file"]').on('change', function() {
        validateField($(this));
    });
   
    form.on('submit', function(e) {
        e.preventDefault();
       
        var isValid = true;
        form.find('input, textarea, select').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });
       
        if (!isValid) {
            showMessage('Please correct the errors below.', 'error');
            return;
        }
       
        var formData = new FormData(this);
        formData.append('action', 'zcp_submit_lead');
        formData.append('nonce', zcp_ajax.nonce);
       
        submitButton.prop('disabled', true).text(zcp_ajax.strings.submitting);
        spinner.show();
        hideMessage();
        hideAllErrors();
       
        $.ajax({
            url: zcp_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    form[0].reset();
                } else {
                    showMessage(response.data.message, 'error');
                    if (response.data.errors) {
                        $.each(response.data.errors, function(field, error) {
                            showFieldError(field, error);
                        });
                    }
                }
            },
            error: function() {
                showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                submitButton.prop('disabled', false).text(form.data('button-text') || 'Submit');
                spinner.hide();
            }
        });
    });
   
    function validateField(field) {
        var fieldName = field.attr('name');
        var value = field.val();
        var errorDiv = $('#zcp_error_' + fieldName);
       
        errorDiv.hide();
       
        if (field.prop('required') && !value.trim()) {
            showFieldError(fieldName, zcp_ajax.strings.required_field);
            return false;
        }
       
        if (fieldName === 'email' && value && !isValidEmail(value)) {
            showFieldError(fieldName, zcp_ajax.strings.invalid_email);
            return false;
        }
       
        if (fieldName === 'file_upload' && field[0].files.length > 0) {
            var file = field[0].files[0];
            var maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                showFieldError(fieldName, zcp_ajax.strings.invalid_file);
                return false;
            }
        }
       
        return true;
    }
   
    function showFieldError(field, message) {
        var errorDiv = $('#zcp_error_' + field);
        errorDiv.text(message).show();
    }
   
    function hideAllErrors() {
        $('.zcp-field-error').hide();
    }
   
    function showMessage(message, type) {
        messagesDiv.removeClass('success error')
                  .addClass(type)
                  .html('<p>' + message + '</p>')
                  .show();
       
        $('html, body').animate({
            scrollTop: messagesDiv.offset().top - 100
        }, 500);
       
        if (type === 'success') {
            setTimeout(function() {
                messagesDiv.fadeOut();
            }, 5000);
        }
    }
   
    function hideMessage() {
        messagesDiv.hide();
    }
   
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+\$/;
        return re.test(email);
    }
});
JS;
    file_put_contents($plugin_assets_dir . '/js/frontend.js', $frontend_js);
});

// Clean up on deactivation
register_deactivation_hook(__FILE__, function() {
    // Left over cleanup of the previous version's scheduled hook
    wp_clear_scheduled_hook('zcp_daily_license_check');
});
