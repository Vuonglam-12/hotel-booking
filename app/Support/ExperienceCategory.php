<?php

namespace App\Support;

final class ExperienceCategory
{
    public const LABELS = [
        'tham-quan' => 'Tham quan', 'am-thuc' => 'Ẩm thực',
        'vui-choi' => 'Vui chơi', 'dia-phuong' => 'Hoạt động địa phương',
    ];
    public const TYPES = ['tham-quan' => 'attraction', 'am-thuc' => 'restaurant', 'vui-choi' => 'activity', 'dia-phuong' => 'activity'];
}
