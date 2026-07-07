<?php

namespace WP_Application\CustomTable;

if (!class_exists('\WP_Application\CustomTable\WP_Queue')) {
    class WP_Queue extends Base
    {

        public static int $number = 10;

        public function __construct()
        {

            // On Insert/Update
            add_filter('ct_insert_data_' . static::table(), [$this, 'on_insert']);
            add_filter('ct_update_data_' . static::table(), [$this, 'on_update'], 20, 3);

            // Prepare Object
            add_filter('ct_prepare_' . static::table(), [$this, 'prepare_item']);

            // Setup CronJob
            add_filter('cron_schedules', [$this, 'cron_schedules']);
            add_action('plugins_loaded', [$this, 'setup'], 40);
            if (!empty(static::hook())) {
                add_action(static::hook(), [$this, 'process']);
            }
            add_action('wp_queues_deleted', [$this, 'wp_queues_deleted']);
        }

        /* @config */
        public static function slug(): string
        {
            return 'queue';
        }

        /* @config */
        public static function title(): string
        {
            return 'عملیات خودکار';
        }

        /* @config */
        public static function primary_key(): string
        {
            return 'ID';
        }

        /* @config */
        public static function get_json_fields(): array
        {
            return ['args'];
        }

        /* @config */
        public static function updated_at(): array
        {
            return [
                'name' => 'updated_at',
                'type' => 'datetime',
                'value' => current_time('mysql')
            ];
        }

        /* @config */
        public static function default(): array
        {
            return [
                'user_id' => 0,
                'object_id' => 0,
                'job' => '',
                'method' => '',
                'created_at' => current_time('mysql'),
                'args' => [],
                'run_at' => null,
                /**
                 * 1 = PENDING
                 * 2 = SUCCESS
                 * 3 = FAILED
                 */
                'status' => 1,
                'updated_at' => current_time('mysql'),
            ];
        }

        /* @hook */
        public static function setup_mysql_table(): void
        {
            global $wpdb;

            // Load DB delta
            if (!function_exists('dbDelta')) {
                require(ABSPATH . 'wp-admin/includes/upgrade.php');
            }

            // Charset Collate
            $collate = $wpdb->get_charset_collate();

            // Create WP Log
            $table_name = static::table();
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

        /* @method */
        public static function get_status_list(): array
        {
            return [
                '1' => 'در حال انجام',
                '2' => 'تکمیل شده',
                '3' => 'ناموفق',
            ];
        }

        /* @method */
        public static function get_status_name($status_id): string
        {
            $list = static::get_status_list();
            return (isset($list[$status_id]) ? trim($list[$status_id]) : 'نامشخص');
        }

        /* @method */
        public function on_insert($args)
        {
            return $args;
        }

        /* @method */
        public function on_update($changed, $item, $id)
        {
            return $changed;
        }

        /* @method */
        public function prepare_item($item)
        {
            return $item;
        }

        /* @method */
        public static function run($item)
        {
            $job = $item['job'];
            $method = $item['method'];
            if (empty($job) || empty($method)) {

                static::update_status($item, 3);
                return ['status' => false, 'message' => 'آرگومان کلاس یا تابع مشخص نیست'];
            }

            $class_name = '\\WP_Queue\\' . $job;
            if (!class_exists($class_name)) {

                static::update_status($item, 3);
                return ['status' => false, 'message' => 'آرگومان کلاس مشخص نیست'];
            }

            $item = apply_filters('wp_queue_item_run_args', $item);

            $job_instance = new $class_name($item);
            if (!method_exists($job_instance, $method)) {

                static::update_status($item, 3);
                return ['status' => false, 'message' => 'آرگومان تابع مشخص نیست'];
            }

            $result = $job_instance->$method();
            if ($result['status'] === false) {

                static::update_status($item, 3);
                return $result;
            }

            // Success
            static::update_status($item, 2);
            return ['status' => true];
        }

        /* @method */
        public static function update_status($item, $new_status = null): array
        {
            $update = static::update($item['ID'], ['status' => $new_status]);
            do_action('wp_queue_status_updated', $item, $item['status'], $new_status);
            return $update;
        }

        /* @method */
        public static function number()
        {
            return apply_filters('wp_queue_number_process', static::$number);
        }

        /* @hook */
        public function cron_schedules($schedules)
        {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display' => __('Every Minute', 'wp-queue')
            );

            return $schedules;
        }

        /* @method */
        public static function hook()
        {
            return apply_filters('wp_queue_hook', 'queue_job');
        }

        /* @hook */
        public function setup(): void
        {
            $args = ["WP Queue"];
            $hook = static::hook();
            $activate = static::activate();
            if (!$activate) {
                return;
            }

            if (!wp_next_scheduled($hook, $args)) {
                wp_schedule_event(
                    time(),
                    apply_filters('wp_queue_recurrence', 'every_minute'),
                    $hook,
                    $args
                );
            }

            if (!wp_next_scheduled('wp_queues_deleted', $args)) {
                wp_schedule_event(
                    time(),
                    'daily',
                    'wp_queues_deleted',
                    $args
                );
            }
        }

        /* @method */
        public static function activate()
        {
            return apply_filters('wp_queue_activate', true);
        }

        /* @hook */
        public function process(): void
        {
            $activate = static::activate();
            if (!$activate) {
                return;
            }

            $items = static::list([
                'order' => 'ASC',
                'number' => static::number(),
                'query' => [
                    [
                        'key' => 'status',
                        'value' => '1',
                        'compare' => '='
                    ],
                    [
                        'key' => 'run_at',
                        'value' => current_time('mysql'),
                        'compare' => '<='
                    ],
                ],
                'prepare' => true
            ]);
            foreach ($items as $item) {
                static::run($item);
            }
        }

        /* @hook */
        public function wp_queues_deleted(): void
        {
            global $wpdb;
            $table_name = static::table();
            $query = $wpdb->prepare("DELETE FROM {$table_name} WHERE status != %d AND run_at < DATE_SUB(CURDATE(), INTERVAL %d DAY)", 1, 30);
            $deleted = $wpdb->query($query);
        }

        /* @method */
        public static function exist($user_id, $object_id, $job, $method): int
        {
            $queues = \WP_Application\CustomTable\WP_Queue::list([
                'query' => [
                    [
                        'key' => 'user_id',
                        'value' => $user_id,
                        'compare' => '='
                    ],
                    [
                        'key' => 'object_id',
                        'value' => $object_id,
                        'compare' => '='
                    ],
                    [
                        'key' => 'job',
                        'value' => $job,
                        'compare' => '='
                    ],
                    [
                        'key' => 'method',
                        'value' => $method,
                        'compare' => '='
                    ],
                ],
                'fields' => 'ID'
            ]);
            if (empty($queues)) {
                return 0;
            }

            // $delete = \WP_Application\CustomTable\WP_Queue::delete();
            return (int) $queues[0]['ID'];
        }
    }
}


new WP_Queue();
