<?php

namespace WP_Queue;

class Test
{
    public array $queue;

    public function __construct($item)
    {
        $this->queue = $item;
    }

    public function moveTrash(): array
    {
        # wp_delete_post($this->queue['object_id']);
        return ['status' => true];
    }

}
