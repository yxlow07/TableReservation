<?php

namespace app\Models;

use core\Models\ValidationModel;

class AnnouncementModel extends ValidationModel
{
    public string $title = '';
    public string $summary = '';
    public string $content = '';
    public string $type = 'General';

    public function __construct($data = [])
    {
        parent::loadData($data);
    }

    public function rules(): array
    {
        return [
            'title' => [self::RULE_REQUIRED],
            'content' => [self::RULE_REQUIRED],
            'type' => [self::RULE_REQUIRED],
        ];
    }

    public function fieldNames(): array
    {
        return [
            'title' => 'Title',
            'content' => 'Content',
            'type' => 'Type',
        ];
    }
}