<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
            ->select('id', 'name', 'location_id', 'star_rating', 'avg_rating', 'address', 'latitude', 'longitude', 'google_place_id')
            ->get()
            ->map(fn($h) => "[ID:{$h->id}] {$h->name} ({$h->location->name}, {$h->star_rating} sao, rating: {$h->avg_rating}, toa do: {$h->latitude},{$h->longitude})")
            ->join("\n");

        // System prompt — hướng dẫn AI cách hoạt động
        $systemPrompt = "Bạn là trợ lý đặt phòng khách sạn thông minh của Hotel Booking System.
Nhiệm vụ của bạn:
1. Gợi ý khách sạn phù hợp dựa theo yêu cầu của khách
2. Lên lịch trình du lịch
3. Trả lời câu hỏi về khách sạn và địa điểm du lịch tại Việt Nam
4. QUAN TRỌNG: Nếu khách có yêu cầu khiếu nại, muốn gặp nhân viên thật, đặt phòng số lượng lớn, hoặc hỏi những vấn đề nằm ngoài khả năng, hãy lịch sự xin lỗi và hướng dẫn họ liên hệ trực tiếp với lễ tân qua Hotline: 0354.313.031 hoặc Email: hahan8784@gmail.com. Không tự bịa ra thông tin.

Danh sách khách sạn hiện có: {$hotels}

Khi gợi ý KS, hãy đề cập ID của KS để hệ thống có thể xử lý.
Trả lời bằng tiếng Việt, thân thiện và ngắn gọn.
Context hiện tại: " . json_encode($session->context_json);

        // Gọi Groq API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.groq.api_key'),
            'Content-Type'  => 'application/json',
        ])->post($this->groqUrl, [
            'model'       => config('services.groq.model', 'llama3-8b-8192'),
            'messages'    => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $history
            ),
            'max_tokens'  => 500,  // Giới hạn độ dài phản hồi
            'temperature' => 0.7,  // 0 = chính xác, 1 = sáng tạo
        ]);

        // Kiểm tra Groq có trả về lỗi không
        if (!$response->successful()) {
            return response()->json([
                'message' => 'AI đang bận, thử lại sau!',
                'error'   => $response->json(),
            ], 500);
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
            $hotelData = Hotel::select('id', 'name', 'latitude', 'longitude', 'google_place_id', 'star_rating', 'avg_rating')
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