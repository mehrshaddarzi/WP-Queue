<?php
/**
 * Plugin Name: WordPress Queue
 * Description: WordPress Queue based on CronJob
 * Version:     1.0.0
 * Text Domain: wp-queue
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Tested up to: 7.0
 */

if (!defined('ABSPATH')) exit;

class WP_Queue
{

    public static string $plugin_url;

    public static string $plugin_path;

    public static string $plugin_version;

    public static string $plugin_basename;

    protected static ?WP_Queue $instance = null;

    public static function instance(): ?WP_Queue
    {
        null === self::$instance and self::$instance = new self;
        return self::$instance;
    }

    public function __construct()
    {
        // Define
        $this->define_constants();

        // Activation Hook
        register_activation_hook(__FILE__, [$this, 'register_activation_hook']);
        register_deactivation_hook(__FILE__, [$this, 'register_deactivation_hook']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'register_uninstall_hook']);

        // Plugin Loaded
        add_action('plugins_loaded', [$this, 'plugins_loaded'], 20);
    }

    public function plugins_loaded()
    {
        $this->includes();
    }

    public function define_constants()
    {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(__FILE__, true, false);

        self::$plugin_version = $plugin_data['Version'];
        self::$plugin_url = plugins_url('', __FILE__);
        self::$plugin_path = plugin_dir_path(__FILE__);
        self::$plugin_basename = plugin_basename(__FILE__);
    }

    public function includes()
    {
        // Main
        require_once dirname(__FILE__) . '/inc/Base.php';
        require_once dirname(__FILE__) . '/inc/Page.php';
        require_once dirname(__FILE__) . '/inc/Message.php';
        require_once self::$plugin_path . '/inc/wp-queue/WP_Queue.php';
        require_once self::$plugin_path . '/inc/wp-queue/WP_Queue_Hooks.php';

        // Jobs
        require_once self::$plugin_path . '/inc/wp-queue/jobs/Test.php';
        require_once self::$plugin_path . '/inc/wp-queue/jobs/SMS.php';

        // Custom Table
        if (is_admin()) {
            if (!class_exists('WP_List_Table')) {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
            }

            require_once dirname(__FILE__) . '/inc/wp-queue/WP_QueueListTable.php';
            require_once dirname(__FILE__) . '/inc/wp-queue/WP_QueueAdminPage.php';
        }
    }

    public function register_activation_hook()
    {
        global $wpdb;

        // Load DB delta
        if (!function_exists('dbDelta')) {
            require(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        // Charset Collate
        $collate = $wpdb->get_charset_collate();

        // Create WP Log
        $table_name = esc_sql($wpdb->prefix . 'queue');
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `ID` BIGINT(48) NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT(48) NOT NULL DEFAULT '0',
            `object_id` BIGINT(48) NOT NULL DEFAULT '0',
            `job` VARCHAR(50) NULL,
            `method` VARCHAR(50) NULL,
            `args` TEXT NULL,
            `run_at` DATETIME NOT NULL,
            `status` BIGINT(1) NOT NULL DEFAULT '1',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`ID`),
            INDEX run_at (`run_at`),
            INDEX runt_at_status (`run_at`, `status`),
            INDEX user_id_on_object_id (`user_id`, `object_id`, `job`, `method`)
            ) ENGINE = InnoDB {$collate};";
        dbDelta($sql);
    }

    public function register_deactivation_hook()
    {
    }

    public static function register_uninstall_hook()
    {
        global $wpdb;

        // Delete gateway log table
        $table_name = esc_sql($wpdb->prefix . 'queue');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $table_name));
    }

}

$GLOBALS['WP_Queue'] = WP_Queue::instance();
