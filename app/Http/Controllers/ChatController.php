<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    // URL API của Groq
    private string $groqUrl = 'https://api.groq.com/openai/v1/chat/completions';

    // ==========================================
    // Bắt đầu phiên chat mới
    // POST /api/chat/start
    // ==========================================
    public function startSession(Request $request)
    {
        $customerId = auth('sanctum')->id();

        // Tạo session token random — dùng để identify phiên chat
        $sessionToken = Str::random(32);

        $session = ChatSession::create([
            'customer_id'   => $customerId,
            'session_token' => $sessionToken,
            'title'         => 'Cuộc trò chuyện mới',
            'context_json'  => [], // Bộ nhớ bot ban đầu rỗng
            'status'        => 'active',
            'started_at'    => now(),
        ]);

        return response()->json([
            'message'       => 'Bắt đầu phiên chat mới',
            'session_id'    => $session->id,
            'session_token' => $sessionToken,
        ], 201);
    }

    // ==========================================
    // Gửi tin nhắn và nhận phản hồi từ AI
    // POST /api/chat/{session_id}/message
    // ==========================================
    public function sendMessage(Request $request, $session_id)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $customerId = auth('sanctum')->id();

        // Lấy session — verify thuộc về user này
        $session = ChatSession::where('id', $session_id)
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->firstOrFail();

        // Lưu tin nhắn của user vào DB
        ChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => $request->message,
            'created_at' => now(),
        ]);

        // Lấy lịch sử chat (tối đa 10 tin nhắn gần nhất) để gửi cho AI
        // AI cần biết context trước đó để trả lời đúng
        $history = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->map(fn($msg) => [
                'role'    => $msg->role === 'bot' ? 'assistant' : 'user',
                'content' => $msg->content,
            ])
            ->values()
            ->toArray();

        // Lấy dữ liệu KS để AI có thể gợi ý chính xác
        $hotels = Hotel::with('location')
            ->where('status', 'active')
            ->select('id', 'name', 'location_id', 'star_rating', 'avg_rating', 'address',)
            ->get()
            ->map(fn($h) => "[ID:{$h->id}] {$h->name} ({$h->location->name}, {$h->star_rating} sao, rating: {$h->avg_rating},)")
            ->join("\n");

        // System prompt — hướng dẫn AI cách hoạt động
            $systemPrompt = "Bạn là Minh và Linh — nhân viên tư vấn lâu năm tại HolidayViet, một nền tảng đặt phòng & lập lịch trình du lịch Việt Nam.

            NGUYÊN TẮC VÀNG:
            - KHÔNG bao giờ liệt kê tính năng hệ thống ra như đọc tài liệu
            - Chỉ đề cập tính năng KHI NÀO nó giúp ích cho câu hỏi của khách
            - Tư vấn như người bạn am hiểu, không như chatbot đọc FAQ
            - Nếu không chắc → thành thật nói không rõ, hướng dẫn liên hệ hotline

            PHÂN CÔNG:
            - **Minh:** thiên về lịch trình, địa điểm, thời điểm đi, kinh nghiệm thực tế
            - **Linh:** thiên về khách sạn, giá phòng, đặt phòng, thanh toán, ưu đãi
            - Hai người trao đổi tự nhiên, bổ sung cho nhau, không nói một lúc quá 4-5 câu

            VÍ DỤ ĐÚNG (khách hỏi 'đi Đà Nẵng 3 ngày nên ở đâu'):
            **Minh:** Đà Nẵng 3 ngày thì mình hay gợi ý ở khu Mỹ Khê cho tiện, buổi sáng ra biển luôn!
            **Linh:** Đúng! Bên HolidayViet có mấy khách sạn view biển đẹp khu đó, bạn ưu tiên tầm giá nào để Linh tư vấn cụ thể hơn nhé?

            VÍ DỤ SAI (TUYỆT ĐỐI KHÔNG LÀM):
            **Minh:** HolidayViet có các tính năng: 1. Đặt phòng 2. Lịch trình AI 3. Wishlist...
            **Linh:** Hệ thống hỗ trợ thanh toán VNPay, chuyển khoản, trạng thái pending/confirmed/cancelled...

            LIÊN HỆ KHI CẦN: Hotline 0354.313.031 | Email hahan8784@gmail.com

            KHÁCH SẠN TRONG HỆ THỐNG (dùng để tư vấn, không đọc ra hết):
            {$hotels}

            Trả lời tiếng Việt, tự nhiên như người thật.
            Context: " . json_encode($session->context_json);

        // Gọi Groq API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.groq.api_key'),
            'Content-Type'  => 'application/json',
        ])->post($this->groqUrl, [
            'model' => 'llama-3.1-8b-instant',
            'messages'    => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $history
            ),
            'max_tokens'  => 500,  // Giới hạn độ dài phản hồi
            'temperature' => 0.7,  // 0 = chính xác, 1 = sáng tạo
        ]);

        // Xử lý lỗi từ Groq
        if (!$response->successful()) {
            $errorBody = $response->json();
            $isRateLimit = $response->status() === 429;
            
            Log::warning('Groq API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'message' => $isRateLimit
                    ? 'AI đang quá tải, vui lòng thử lại sau ít phút! 🙏'
                    : 'AI đang bận, thử lại sau!',
            ], 200); // Trả 200 để frontend hiển thị bình thường thay vì crash
        }

        // Lấy nội dung phản hồi từ Groq
        $botReply = $response->json('choices.0.message.content');

        // Tìm KS nào được nhắc đến trong phản hồi (dựa vào ID)
        $referencedHotelId = null;
        if (preg_match('/\[ID:(\d+)\]/', $botReply, $matches)) {
            $referencedHotelId = (int)$matches[1];
        }

        // Lưu tin nhắn bot vào DB
        $botMessage = ChatMessage::create([
            'session_id'          => $session->id,
            'role'                => 'bot',
            'content'             => $botReply,
            'referenced_hotel_id' => $referencedHotelId,
            'created_at'          => now(),
        ]);

        // Cập nhật title session từ tin nhắn đầu tiên
        if ($session->title === 'Cuộc trò chuyện mới') {
            $session->update([
                'title' => mb_substr($request->message, 0, 50),
            ]);
        }

        // Lấy thêm tọa độ KS nếu bot gợi ý — để frontend ghim pin Google Maps
        $hotelData = null;
        if ($referencedHotelId) {
            $hotelData = Hotel::select('id', 'name','star_rating', 'avg_rating')
                ->find($referencedHotelId);
        }

        return response()->json([
            'message'    => $botReply,
            'session_id' => $session->id,
            'hotel_id'   => $referencedHotelId,
            'hotel'      => $hotelData, // Tọa độ để frontend ghim Google Maps
        ]);
    }

    // ==========================================
    // Xem lịch sử chat của 1 session
    // GET /api/chat/{session_id}
    // ==========================================
    public function getSession($session_id)
    {
        $session = ChatSession::with(['messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }])
        ->where('customer_id', auth('sanctum')->id())
        ->findOrFail($session_id);

        return response()->json($session);
    }

    // ==========================================
    // Xem tất cả phiên chat của user
    // GET /api/chat
    // ==========================================
    public function mySessions()
    {
        $sessions = ChatSession::where('customer_id', auth('sanctum')->id())
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json($sessions);
    }

    // ==========================================
    // Đóng phiên chat
    // PUT /api/chat/{session_id}/close
    // ==========================================
    public function closeSession($session_id)
    {
        ChatSession::where('id', $session_id)
            ->where('customer_id', auth('sanctum')->id())
            ->update([
                'status'   => 'closed',
                'ended_at' => now(),
            ]);

        return response()->json(['message' => 'Phiên chat đã đóng']);
    }
}