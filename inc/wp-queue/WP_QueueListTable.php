<?php

namespace WP_Application\CustomTable;

class WP_QueueListTable extends \WP_List_Table
{
    public static array $query_search = [
        's',
        'search-type',
    ];

    public static array $query_filter = [
        'user_id',
        'object_id',
        'status',
        'job',
        'method'
    ];

    /**
     * @return WP_Queue|string
     */
    public static function model(): WP_Queue|string
    {
        return WP_Queue::class;
    }

    /**
     * @return WP_QueueAdminPage|string
     */
    public static function pageClass(): WP_QueueAdminPage|string
    {
        return WP_QueueAdminPage::class;
    }

    public function __construct()
    {

        parent::__construct(array(
            'singular' => 'wp-' . (static::model())::slug(),
            'plural' => 'wp-' . (static::model())::slug(),
            'ajax' => false
        ));

        // Fixed Params
        $this->sanitize_query_link();
    }

    public function sanitize_query_link(): void
    {
        foreach (array_merge(static::$query_filter, static::$query_search) as $key) {
            if (isset($_REQUEST[$key])) {
                $_GET[$key] = $_REQUEST[$key];
            }
        }
    }

    public function url($args = []): string
    {
        // Setup Default Params
        foreach (array_merge(static::$query_filter, static::$query_search) as $key) {
            if (isset($_REQUEST[$key])) {
                $args[$key] = $_REQUEST[$key];
            }
        }

        // Return
        return add_query_arg($args, remove_query_arg(['action', '_wpnonce', 'del']));
    }

    public function prepare_items(): void
    {

        //Column Option
        $this->_column_headers = $this->get_column_info();

        //Process Bulk and Row Action
        $this->process_bulk_action();

        //Prepare Data
        $per_page = $this->get_items_per_page('wp_' . (static::pageClass())::page_slug() . '_per_page', (static::pageClass())::$ListTablePerPage);
        $current_page = $this->get_pagenum();
        $total_items = self::record_count();

        //Create Pagination
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page
        ));

        //return items
        $this->items = self::get_lists($per_page, $current_page);
    }

    public static function get_lists($per_page = 10, $page_number = 1): array|object|null
    {
        global $wpdb;

        $tbl = (static::model())::table();
        $sql = "SELECT * FROM `$tbl`";

        // Where conditional
        $conditional = self::conditional_sql();
        if (!empty($conditional)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditional);
        }

        // Check Order By
        if (!empty($_REQUEST['orderby'])) {
            $sql .= ' ORDER BY ' . esc_sql($_REQUEST['orderby']);
        } else {
            $sql .= ' ORDER BY `' . (static::model())::primary_key() . '`';
        }

        // Check Order
        $sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' DESC';
        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ($page_number - 1) * $per_page;

        // Save Query
        if (defined('WP_DEBUG') and WP_DEBUG === true) {
            error_log($sql);
        }

        return array_map([static::model(), 'prepare'], $wpdb->get_results($sql, ARRAY_A));
    }

    public static function record_count($condition = true): ?string
    {
        global $wpdb;
        $tbl = (static::model())::table();
        $sql = "SELECT COUNT(*) FROM `$tbl`";

        // Where conditional
        if ($condition) {

            $conditional = self::conditional_sql();
            if (!empty($conditional)) {
                $sql .= ' WHERE ' . implode(' AND ', $conditional);
            }
        }

        $cache_key = 'db_var_' . md5($sql);
        $cached = wp_cache_get($cache_key, 'db_var');
        if ($cached !== false) {
            return $cached;
        }

        $result = $wpdb->get_var($sql);
        wp_cache_set($cache_key, $result, 'db_var', 3600);

        return $result;
    }

    public static function conditional_sql(): array
    {
        // Where conditional
        $where = [];

        // Check Search
        if (!empty($_REQUEST['search-type']) and !empty($_REQUEST['s'])) {

            // Get search Input
            $search = sanitize_text_field($_REQUEST['s']);

            // Setup Case Switch
            switch (strtolower(trim($_REQUEST['search-type']))) {

                case "ID":
                    $explodeIds = array_filter(array_map('trim', explode(",", $search)));
                    $where[] = "`" . (static::model())::primary_key() . "` IN ('" . implode("','", $explodeIds) . "')";
                    break;

                default:
                    break;
            }

            $where[] = "`" . trim($_REQUEST['search-type']) . "` = '{$search}'";
        }

        // Setup Filter Query
        foreach (self::$query_filter as $key) {
            if (isset($_REQUEST[$key]) and $_REQUEST[$key] != '') {
                $search = sanitize_text_field($_REQUEST[$key]);
                $where[] = "`$key` = '{$search}'";
            }
        }

        // Return
        return $where;
    }

    public static function delete_action($id): array
    {
        return (static::model())::delete($id);
    }

    public function no_items(): void
    {
        _e('لیست ' . (static::model())::title() . ' می باشد', '');
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'ID' => 'شناسه',
            'object_id' => 'شناسه آبجکت',
            'user_id' => 'کاربر',
            'job' => 'نوع عملیات',
            'method' => 'متد',
            'run_at' => 'تاریخ اجرا',
            'status' => 'وضعیت',
            'args' => 'پارامتر',
            'created_at' => 'تاریخ ایجاد',
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="bulk-' . (static::model())::primary_key() . '[]" value="%s" />',
            $item[(static::model())::primary_key()]
        );
    }

    public function column_default($item, $column_name): string
    {
        // Default unknown Column Value
        $unknown = '<span aria-hidden="true">—</span><span class="screen-reader-text">_</span>';

        switch ($column_name) {
            case 'ID' :

                // Row actions `trash`
                $actions['trash'] = '<a onclick="return confirm(\'آیا مطمئن هستید؟\')"
                    href="' . $this->url(array(
                        'page' => (static::pageClass())::page_slug(),
                        'action' => 'delete',
                        '_wpnonce' => wp_create_nonce('delete_action_nonce'),
                        'ID' => $item[(static::model())::primary_key()]
                    )) . '">حذف</a>';

                return $item[(static::model())::primary_key()] . $this->row_actions($actions);
                break;

            case 'user_id':
                if (empty($item['user_id'])) {
                    return $unknown;
                }

                $user = get_userdata($item['user_id']);
                if (!$user) {
                    return $unknown;
                }

                return '<a href="' . get_edit_user_link($item['user_id']) . '" target="_blank">' . $user->display_name . '</a>';
                break;

            case 'created_at':
                if (empty($item[$column_name])) {
                    return $unknown;
                }

                $text = parsidate("Y-m-d", strtotime($item[$column_name]), "eng");
                $text .= '<br />';
                $text .= parsidate("H:i:s", strtotime($item[$column_name]), "eng");
                $text .= '<br />';
                $text .= '<span style="color: #b1b1b1;">' . human_time_diff(strtotime($item[$column_name]), current_time('timestamp')) . ' پیش </span>';
                return $text;
                break;

            case 'run_at':
                if (empty($item[$column_name])) {
                    return $unknown;
                }

                $text = parsidate("Y-m-d", strtotime($item[$column_name]), "eng");
                $text .= '<br />';
                $text .= parsidate("H:i:s", strtotime($item[$column_name]), "eng");
                return $text;
                break;

            case 'status':
                $statusName = (static::model())::get_status_name($item['status']);
                return '<span ' . (in_array($item['status'], [2, 3]) ? 'style="color: ' . ($item['status'] == 2 ? 'green' : 'red') . '";' : '') . '>' . $statusName . '</span>';
                break;


            case 'args':
                if (empty($item['args'])) {
                    return $unknown;
                }

                $text = '<div style="direction: ltr; text-align:left;">';
                $text .= json_encode($item['args'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $text .= '</div>';

                return $text;
                break;

            default:
                return $item[$column_name];
        }
    }

    public function get_sortable_columns(): array
    {
        return [
            'ID' => array('ID', true),
            'status' => array('status', false),
            'object_id' => array('object_id', false),
            'job' => array('job', false),
            'method' => array('method', false),
        ];
    }

    protected function get_views(): array
    {
        $views = [];

        $current = (!empty($_REQUEST['status']) ? $_REQUEST['status'] : 'all');
        $class = ($current == 'all' ? ' class="current"' : '');
        $all_url = (static::pageClass())::url();
        $views['all'] = "<a href='{$all_url}' {$class}>" . __("همه") . " <span class=\"count\">(" . number_format(static::record_count()) . ")</span></a>";

        return $views;
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
        }
    }

    public function get_bulk_actions(): array
    {
        return array(
            'bulk-delete' => __('حذف'),
        );
    }

    public function search_box($text, $input_id): void
    {
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            return;
        }

        $input_id = (empty($input_id) ? (static::pageClass())::page_slug() : $input_id) . '-search-input';

        if (!empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if (!empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        ?>

        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
            <input type="search" placeholder="جستجو ..." id="<?php echo $input_id ?>" name="s"
                   value="<?php _admin_search_query(); ?>" autocomplete="off"/>
            <?php submit_button($text, 'button', false, false, array('id' => 'search-submit')); ?>
        </p>

        <?php

    }

    public function process_bulk_action(): void
    {

        // Row Action `Delete`
        if ('delete' === $this->current_action()) {

            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'delete_action_nonce')) {

                die(__("You are not Permission for this action."));
            } else {

                $deleteItem = self::delete_action(absint($_REQUEST['ID']));
                if ($deleteItem['status'] === false) {
                    $text = 'خطا در زمان حذف آیتم به شناسه ' . absint($_REQUEST['ID']) . ': ' . $deleteItem['message'];
                    wp_die($text);
                }

                Message::admin_notice_handler('success', 'آیتم با موفقیت حذف گردید', ['action', '_wpnonce', (static::model())::primary_key()]);
            }
        }

        // Bulk Action `Delete`
        if ((isset($_POST['action']) && $_POST['action'] == 'bulk-delete')) {

            $item_ids = esc_sql($_POST['bulk-ID']);
            if (is_array($item_ids) and count($item_ids) > 0) {
                $logs = [];
                foreach ($item_ids as $id) {
                    $deleteItem = self::delete_action($id);
                    if ($deleteItem['status'] === false) {
                        $text = 'خطا در زمان حذف آیتم به شناسه ' . $id . ': ' . $deleteItem['message'];
                        wp_die($text);
                    }

                    $logs[] = 'آیتم با شناسه ' . $id . 'با موفقیت حذف شد.';
                }

                Message::admin_notice_handler('success', implode("<br />", $logs), ['action', '_wpnonce']);
            }
        }
    }
}