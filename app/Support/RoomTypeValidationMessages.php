<?php

namespace App\Support;

/** Field-specific messages shared by the existing Admin room editors. */
final class RoomTypeValidationMessages
{
    public static function all(): array
    {
        return [
            'name.required'=>'Tên loại phòng chưa nhập.', 'name.string'=>'Tên loại phòng phải là văn bản.', 'name.max'=>'Tên loại phòng tối đa :max ký tự.',
            'base_price.required'=>'Giá phòng chưa nhập.', 'base_price.numeric'=>'Giá phòng phải là một số.',
            'base_price.regex'=>'Giá phòng phải là số không âm, tối đa 10 chữ số nguyên và 2 chữ số thập phân. Không nhập dấu phân cách hàng nghìn.',
            'base_price.min'=>'Giá phòng không được âm.', 'base_price.max'=>'Giá phòng vượt mức tối đa cho phép.',
            'capacity.required'=>'Sức chứa chưa nhập.', 'capacity.integer'=>'Sức chứa phải là số nguyên.',
            'capacity.min'=>'Sức chứa tối thiểu :min khách.', 'capacity.max'=>'Sức chứa tối đa :max khách mỗi phòng.',
            'total_rooms.required'=>'Tổng số phòng chưa nhập.', 'total_rooms.integer'=>'Tổng số phòng phải là số nguyên.',
            'total_rooms.min'=>'Tổng số phòng không được âm.', 'total_rooms.max'=>'Tổng số phòng tối đa :max.',
            'room_count.required'=>'Số phòng cần tạo chưa nhập.', 'room_count.integer'=>'Số phòng cần tạo phải là số nguyên.',
            'room_count.min'=>'Cần tạo ít nhất :min phòng.', 'room_count.max'=>'Mỗi lần tạo tối đa :max phòng.',
            'floor_start.integer'=>'Tầng bắt đầu phải là số nguyên.', 'floor_start.min'=>'Tầng bắt đầu tối thiểu :min.', 'floor_start.max'=>'Tầng bắt đầu tối đa :max.',
            'bed_type.string'=>'Loại giường phải là văn bản.', 'bed_type.max'=>'Loại giường tối đa :max ký tự.',
            'description.string'=>'Mô tả phải là văn bản.', 'description.max'=>'Mô tả tối đa :max ký tự.',
            'status.required'=>'Trạng thái phòng chưa chọn.', 'status.in'=>'Vui lòng chọn trạng thái đang bán hoặc ngừng bán.',
            'amenities.array'=>'Tiện nghi phải là một danh sách.', 'amenities.max'=>'Tiện nghi riêng tối đa :max mục.',
            'amenities.*.required'=>'Tiện nghi không được để trống.', 'amenities.*.string'=>'Mỗi tiện nghi phải là văn bản.',
            'amenities.*.max'=>'Mỗi tiện nghi tối đa :max ký tự.', 'amenities.*.distinct'=>'Tiện nghi riêng có mục trùng nhau.',
        ];
    }
}
