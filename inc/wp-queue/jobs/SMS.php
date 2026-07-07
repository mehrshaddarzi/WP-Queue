<?php

namespace WP_Queue;

class SMS
{
    public array $queue;

    public function __construct($item)
    {
        $this->queue = $item;
    }

    public function orderCompleted(): array
    {
        $args = $this->queue['args']['body'];
        $value = $this->queue['args']['mobile'];

        // Do SMS

        return ['status' => true];
    }

    public static function eng_number($number)
    {
        return str_replace(
            array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'),
            range(0, 9),
            $number
        );
    }

    public static function sanitize_mobile($mobile)
    {
        // Convert To English
        $mobile = static::eng_number($mobile);

        // Get Only 10 Character From Last for example +9898980[911129228]
        $mobile = substr($mobile, -10);

        // Convert Plus To 00
        $mobile = (int)str_ireplace('+', '00', $mobile);

        // Get Only Numeric
        $mobile = preg_replace('/[^0-9]/', '', $mobile);

        // Check Character Mobile
        $forth_character = substr($mobile, 0, 4);
        if ($forth_character == "0098") {
            $mobile = substr($mobile, 4);
        }

        $twice_character = substr($mobile, 0, 2);
        if ($twice_character == "98") {
            $mobile = substr($mobile, 2);
        }

        $first_character = substr($mobile, 0, 1);
        if ($first_character == "9") {
            $mobile = '0' . $mobile;
        }

        return $mobile;
    }

    public static function mobile_check($mobile)
    {
        $result = array(
            'success' => true,
            'text' => ''
        );

        if (strlen($mobile) !== 11) {
            $result['text'] = 'شماره همراه 11 کاراکتر می باشد';
            $result['success'] = false;
        }

        if (substr($mobile, 0, 2) !== "09") {
            $result['text'] = 'شماره همراه با 09 شروع می شود';
            $result['success'] = false;
        }

        if (!is_numeric($mobile)) {
            $result['text'] = 'شماره همراه تنها شامل کاراکتر عدد می باشد';
            $result['success'] = false;
        }

        return $result;
    }

}
