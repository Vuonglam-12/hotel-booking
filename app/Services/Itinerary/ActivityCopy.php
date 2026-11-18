<?php

namespace App\Services\Itinerary;

/** Present older generated wording without rewriting the customer's saved notes. */
final class ActivityCopy
{
    private const DESCRIPTIONS = [
            'Nghỉ ngơi hoặc tự khám phá; không phát sinh đặt dịch vụ hoặc chi phí đã xác nhận.' => 'Bạn có thể nghỉ ngơi, lấy lại sức hoặc thong thả khám phá theo sở thích để tiếp tục hành trình.',
            'Khoảng trống để khám phá theo sở thích, không cần thêm điểm tham quan.' => 'Bạn có thể thong thả dạo quanh, chụp ảnh hoặc nghỉ chân trước hoạt động tiếp theo.',
            'Khoảng trống linh hoạt trước bữa trưa.' => 'Bạn có thể nghỉ chân hoặc dạo quanh một chút trước khi dùng bữa trưa.',
            'Tự chọn nơi ăn gần điểm vừa tham quan; cần xác minh thực đơn, giờ hoạt động và chi phí.' => 'Bạn có thể chọn một quán ăn gần điểm vừa ghé, thưởng thức bữa trưa và nghỉ ngơi để tiếp tục hành trình buổi chiều.',
            'Tự chọn quán ăn phù hợp gần điểm đang tham quan; chưa xác minh địa chỉ hoặc chi phí.' => 'Bạn có thể chọn một quán ăn gần điểm vừa ghé, thưởng thức bữa trưa và nghỉ ngơi để tiếp tục hành trình buổi chiều.',
            'Khoảng nghỉ dự kiến cho nhóm có trẻ nhỏ; không phải một địa điểm đặt dịch vụ.' => 'Cả nhà có thể nghỉ chân, uống nước và để các bé lấy lại sức trước khi đi tiếp.',
            'Thời gian linh hoạt trước khung mở cửa thường lệ.' => 'Bạn có thể nghỉ chân hoặc dạo quanh trong lúc chờ đến giờ mở cửa.',
    ];

    public static function descriptions(): array { return self::DESCRIPTIONS; }

    public static function description(?string $description): ?string
    {
        return self::DESCRIPTIONS[$description ?? ''] ?? $description;
    }
}
