<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class BlogController extends Controller
{

    public function index()
    {

        $blogs = [
            [
                'id' => 1,
                'title' => 'Top View Resort Pool: Những hồ bơi vô cực đẹp nhất 2026',
                'image' => 'L-1.jpg',
                'tag' => 'CẢM HỨNG',
                'tag_color' => 'blue-600',
                'date' => '06/04/2026',
                'read_time' => '5 phút'
            ],
            [
                'id' => 2,
                'title' => 'So sánh Resort ven biển: Đâu là lựa chọn hoàn hảo cho bạn?',
                'image' => 'L-2.jpg',
                'tag' => 'REVIEW',
                'tag_color' => 'emerald-500',
                'date' => '05/04/2026',
                'read_time' => '8 phút'
            ],
            [
                'id' => 3,
                'title' => 'Mẹo du lịch 2027: Xu hướng đặt phòng qua AI và Mobile App',
                'image' => 'L-3.jpg',
                'tag' => 'MẸO',
                'tag_color' => 'amber-500',
                'date' => '04/04/2026',
                'read_time' => '3 phút'
            ],
            [
                'id' => 4,
                'title' => 'Top View Đà Nẵng: Ngắm trọn thành phố từ ban công phòng Suite',
                'image' => 'L-4.jpg',
                'tag' => 'REVIEW',
                'tag_color' => 'purple-500',
                'date' => '03/04/2026',
                'read_time' => '6 phút'
            ],
            [
                'id' => 5,
                'title' => 'So sánh Resort (Phần 2): Trải nghiệm nghỉ dưỡng tại Villa sườn đồi',
                'image' => 'L5.jpg',
                'tag' => 'REVIEW',
                'tag_color' => 'emerald-500',
                'date' => '02/04/2026',
                'read_time' => '7 phút'
            ],
            [
                'id' => 6,
                'title' => '18 mẹo săn giá rẻ 2027: Tiết kiệm tối đa ngân sách chuyến đi',
                'image' => 'L-6.jpg',
                'tag' => 'MẸO',
                'tag_color' => 'amber-500',
                'date' => '01/04/2026',
                'read_time' => '10 phút'
            ],
            [
                'id' => 7,
                'title' => 'Ẩm thực đường phố: Hành trình vị giác từ Bắc chí Nam',
                'image' => 'L-7.jpg',
                'tag' => 'ẨM THỰC',
                'tag_color' => 'red-500',
                'date' => '30/03/2026',
                'read_time' => '12 phút'
            ],
            [
                'id' => 8,
                'title' => 'Du lịch 232k: Cách đi chơi cực "chill" với ngân sách siêu nhỏ',
                'image' => 'L-8.jpg',
                'tag' => 'MẸO',
                'tag_color' => 'blue-400',
                'date' => '28/03/2026',
                'read_time' => '4 phút'
            ],
            [
                'id' => 9,
                'title' => 'Kinh nghiệm du lịch gia đình: Để trẻ nhỏ luôn vui và an toàn',
                'image' => 'L-9.jpg',
                'tag' => 'GIA ĐÌNH',
                'tag_color' => 'pink-500',
                'date' => '25/03/2026',
                'read_time' => '9 phút'
            ],
        ];

        return view('blog', compact('blogs'));
    }

    public function detail($id)
    {
        // ========== BƯỚC 1: Chuẩn bị dữ liệu ==========
        // Tạm thời dữ liệu được lưu trong mảng, sau sẽ lấy từ Database
        // VD: $blog = Blog::find($id);
        $blogs = [
            ['id'=>1,'title'=>'Top View Resort Pool...','image'=>'L-1.jpg','tag'=>'CẢM HỨNG','tag_color'=>'blue-600','date'=>'06/04/2026','read_time'=>'5 phút'],
            ['id'=>2,'title'=>'So sánh Resort ven biển...','image'=>'L-2.jpg','tag'=>'REVIEW','tag_color'=>'emerald-500','date'=>'05/04/2026','read_time'=>'8 phút'],
            ['id'=>3,'title'=>'Mẹo du lịch 2027...','image'=>'L-3.jpg','tag'=>'MẸO','tag_color'=>'amber-500','date'=>'04/04/2026','read_time'=>'3 phút'],
            ['id'=>4,'title'=>'Top View Đà Nẵng...','image'=>'L-4.jpg','tag'=>'REVIEW','tag_color'=>'purple-500','date'=>'03/04/2026','read_time'=>'6 phút'],
            ['id'=>5,'title'=>'So sánh Resort (Phần 2)...','image'=>'L5.jpg','tag'=>'REVIEW','tag_color'=>'emerald-500','date'=>'02/04/2026','read_time'=>'7 phút'],
            ['id'=>6,'title'=>'18 mẹo săn giá rẻ 2027...','image'=>'L-6.jpg','tag'=>'MẸO','tag_color'=>'amber-500','date'=>'01/04/2026','read_time'=>'10 phút'],
            ['id'=>7,'title'=>'Ẩm thực đường phố...','image'=>'L-7.jpg','tag'=>'ẨM THỰC','tag_color'=>'red-500','date'=>'30/03/2026','read_time'=>'12 phút'],
            ['id'=>8,'title'=>'Du lịch 232k...','image'=>'L-8.jpg','tag'=>'MẸO','tag_color'=>'blue-400','date'=>'28/03/2026','read_time'=>'4 phút'],
            ['id'=>9,'title'=>'Kinh nghiệm du lịch gia đình...','image'=>'L-9.jpg','tag'=>'GIA ĐÌNH','tag_color'=>'pink-500','date'=>'25/03/2026','read_time'=>'9 phút'],
        ];


        $blog = collect($blogs)->firstWhere('id', $id);

        if (!$blog) {
            abort(404); // Trả về lỗi 404 Not Found
        }

        $contentView = 'content.blog-' . $id . '-content';

        if (!view()->exists($contentView)) {
            abort(404); // Trả về lỗi 404 Not Found - View file không tồn tại
        }

        return view('blog_detail', compact('blog', 'contentView'));
    }
}
