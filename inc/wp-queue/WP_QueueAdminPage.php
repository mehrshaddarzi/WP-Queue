<?php

namespace WP_Application\CustomTable;


class WP_QueueAdminPage extends Page
{

    public \WP_List_Table $admin_list_table;

    public static int $ListTablePerPage = 50;

    public function __construct()
    {
        // Add Menu
        add_action('admin_menu', array($this, 'admin_menu'));

        // Set Screen Option (Admin List Table)
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);

        // Custom JS
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));

        // Search Box
        add_filter('admin_post_type_search_box_fields', [$this, 'search_box_field']);
        add_action('admin_footer', [$this, 'search_box_template']);

        // Handler
        add_action("admin_init", array($this, 'handler'));

        // Admin Notices
        add_action('admin_notices', [$this, 'admin_notices'], 30);
    }

    /**
     * @return WP_Queue|string
     */
    public static function model(): WP_Queue|string
    {
        return WP_Queue::class;
    }

    public static function page_slug(): string
    {
        return (static::model())::slug();
    }

    public function admin_menu(): void
    {
        $hook = add_submenu_page(
            'tools.php',
            (static::model())::title(),
            (static::model())::title(),
            'manage_options',
            static::page_slug(),
            array($this, 'page')
        );

        add_action("load-$hook", array($this, 'screen_option'));
    }

    public function admin_assets(): void
    {
        if (!static::is_page()) {
            return;
        }

        // Load On Admin List Table
        if (empty(static::screen())) {

            // Add Thickbox
            add_thickbox();
        }
    }

    public static function set_screen_option($status, $option, $value)
    {
        // this filter run when saved
        if ($option == 'wp_' . static::page_slug() . '_per_page') {
            if ((int)$value < 1) {
                $value = static::$ListTablePerPage;
            }
        }

        return $value;
    }

    public static function is_page(): bool
    {
        global $pagenow;
        return ($pagenow == "tools.php" and isset($_GET['page']) and $_GET['page'] == static::page_slug());
    }

    public static function url($args = []): string
    {
        return add_query_arg(array_replace([
            'page' => static::page_slug()
        ], $args), admin_url('tools.php'));
    }

    public function screen_option(): void
    {
        if (static::is_page() and empty($_GET['screen'])) {

            // Set Screen Option
            add_screen_option('per_page', array(
                'label' => 'تعداد در هر صفحه',
                'default' => 50,
                'option' => 'wp_' . static::page_slug() . '_per_page' // user_meta_name
            ));

            // Load WP_List_Table
            $this->admin_list_table = new WP_QueueListTable();
            $this->admin_list_table->prepare_items();
        }
    }

    public function handler(): void
    {
        // if (static::is_page()) {}
    }

    public function admin_notices(): void
    {
        if (static::is_page() and !empty(static::screen())) {

            $flashMessage = Message::get();
            if (!empty($flashMessage)) {
                Message::admin_notice($flashMessage['data'], $flashMessage['type']);
            }
        }
    }

    public function page(): void
    {

        // Admin List Table
        if (!isset($_GET['screen'])) {

            $table = $this->admin_list_table;
            $title = (static::model())::title();
            $buttons = [];
            include \WP_Queue::$plugin_path . '/inc/' . str_ireplace("_", "-", 'wp-' . (static::model())::slug()) . '/views/list-table.php';
        }
    }

    public function search_box_template(): void
    {
        if (static::is_page() and empty($_GET['screen'])) {

            include \WP_Queue::$plugin_path . '/inc/wp-queue/views/search-box.php';
            echo '<style>th#args {width: 320px;} .widefat td, .widefat th { font-size: 11px;}</style>';
        }
    }

    public function search_box_field($list)
    {
        if (static::is_page() and empty($_GET['screen'])) {

            $list = [
                'ID' => 'شناسه سیستم',
                'user_id' => 'شناسه کاربر',
                'object_id' => 'شناسه آبجکت',
                'job' => 'نوع عملیات',
                'method' => 'متد',
                'status' => array(
                    'title' => 'وضعیت',
                    'type' => 'select',
                    'choices' => WP_Queue::get_status_list()
                ),
            ];
        }

        return $list;
    }

}

new WP_QueueAdminPage();
