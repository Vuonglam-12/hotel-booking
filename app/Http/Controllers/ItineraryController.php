<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\Location;
use App\Models\Attraction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ItineraryController extends Controller
{
    /* ============================================================
       GET /api/itineraries
       Danh sách lịch trình của customer
    ============================================================ */
    public function index(Request $request): JsonResponse
    {
        $itineraries = Itinerary::where('customer_id', $request->user()->id)
            ->with(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day'), 'location'])
            ->orderByDesc('id')
            ->get()
            ->map(fn($it) => $this->formatItinerary($it));

        return response()->json($itineraries);
    }

    /* ============================================================
       GET /api/itineraries/{id}
    ============================================================ */
    public function show(Request $request, int $id): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)
            ->with(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day'), 'location'])
            ->findOrFail($id);

        return response()->json($this->formatItinerary($itinerary));
    }

/* ============================================================
       POST /api/itineraries/generate
       Tạo lịch trình bằng AI (Groq) - Đã nâng cấp Reasoning & Hybrid Data
    ============================================================ */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'destination'    => 'required|string|max:150',
            'days'           => 'required|integer|min:1|max:21',
            'start_date'     => 'nullable|date',
            'people'         => 'nullable|integer|min:1|max:20',
            'budget'         => 'nullable|integer|min:0',
            'trip_style'     => 'nullable|string|max:100',
            'interests'      => 'nullable|array',
            'interests.*'    => 'string|max:50',
            'pace'           => 'nullable|in:relaxed,moderate,packed',
            'transport'      => 'nullable|string|max:100',
            'stay_area'      => 'nullable|string|max:150',
            'spend_priority' => 'nullable|string|max:150',
            'must_visit'     => 'nullable|string|max:500',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $user        = $request->user();
        $destination = $request->destination;
        $days        = $request->days;
        $people      = $request->people      ?? 1;
        $budget      = $request->budget      ?? 5000000;
        $startDate   = $request->start_date;
        $tripStyle   = $request->trip_style   ?? 'khám phá';
        $interests   = $request->interests    ? implode(', ', $request->interests) : 'đa dạng';
        $pace        = match($request->pace) {
            'relaxed'  => 'thư thả (3-4 hoạt động/ngày)',
            'packed'   => 'dày đặc (6-7 hoạt động/ngày)',
            default    => 'vừa phải (4-5 hoạt động/ngày)',
        };
        $transport      = $request->transport      ?? 'linh hoạt';
        $stayArea       = $request->stay_area       ?? 'trung tâm';
        $spendPriority  = $request->spend_priority  ?? 'cân bằng';
        $mustVisit      = $request->must_visit      ?? '';
        $notes          = $request->notes           ?? '';
        $budgetFmt      = number_format($budget, 0, ',', '.') . 'đ';
        $budgetPerDay   = number_format(intval($budget / $days), 0, ',', '.') . 'đ/ngày';

        // ===== MỚI: Query DB trước khi gọi AI =====
        $locationRecord = Location::where('name', 'like', "%{$destination}%")->first();

        $attractions = $locationRecord
            ? Attraction::where('location_id', $locationRecord->id)
                        ->where('is_active', true) // Chỉ lấy những địa điểm đang hoạt động, tránh gợi ý chỗ đóng cửa hoặc đang sửa chữa
                        ->get()
                        ->groupBy('item_type')
            : collect(); 

        // ===== Build prompt có ground truth =====
        $contextLines = [];
        foreach ($attractions as $type => $items) {
            $contextLines[] = strtoupper($type) . ':';
            foreach ($items as $item) {
                $price = $item->price_min > 0
                    ? number_format($item->price_min) . '-' . number_format($item->price_max) . 'đ'
                    : 'miễn phí';
                $contextLines[] = "  - {$item->name} | {$price} | ~{$item->duration_hours}h"
                                . ($item->open_time ? " | {$item->open_time}-{$item->close_time}" : "");
            }
        }

        $groundTruth = $attractions->isNotEmpty()
            ? "DANH SÁCH ĐỊA ĐIỂM THẬT TẠI {$locationRecord->name}:\n" . implode("\n", $contextLines)
            : ""; 

        $mustVisitLine   = $mustVisit   ? "\n- Điểm bắt buộc ghé thăm: {$mustVisit}"       : '';
        $notesLine       = $notes       ? "\n- Ghi chú đặc biệt: {$notes}"                  : '';
        $startDateLine   = $startDate   ? "\n- Ngày bắt đầu: {$startDate}"                  : '';

        // TỐI ƯU PROMPT THEO NGHIỆP VỤ (Reasoning AI + Hybrid Data)
        $prompt = <<<PROMPT
            Bạn là chuyên gia du lịch Việt Nam am hiểu sâu rộng, sáng tạo và luôn mang đến những trải nghiệm mới mẻ, thú vị.
            {$groundTruth}

            Nhiệm vụ của bạn là lập lịch trình {$days} ngày tại {$destination} cho {$people} người.

            THÔNG TIN CHUYẾN ĐI:
            - Ngân sách: {$budgetFmt} tổng ({$budgetPerDay}/ngày){$startDateLine}
            - Phong cách: {$tripStyle}, Nhịp độ: {$pace}
            - Tùy chỉnh: {$spendPriority}{$mustVisitLine}{$notesLine}

            [QUY TẮC NGHIỆP VỤ CỐT LÕI - PHẢI TUÂN THỦ]:
            1. SỰ PHONG PHÚ & CHỐNG TRÙNG LẶP: Lịch trình phải kỳ thú, mang tính trải nghiệm cao. Tuyệt đối KHÔNG lặp lại bất kỳ địa điểm, nhà hàng hay hoạt động nào trong suốt {$days} ngày.
            2. KIỂM CHỨNG THỰC TẾ (VALIDATION): 
            - Nếu khách yêu cầu những trải nghiệm hoàn toàn phi lý, trái với tự nhiên hoặc địa lý của {$destination} (VD: Ngắm tuyết ở Sài Gòn, lặn ngắm san hô ở Sapa, hái dâu tây ở sa mạc), PHẢI TỪ CHỐI bằng cách set "is_valid": false và ghi lý do giải thích vào "ai_note".
            - Nếu yêu cầu hợp lý hoặc chỉ là món ăn/địa điểm bình thường, set "is_valid": true.
            
            3. NGUỒN DỮ LIỆU (NGHIÊM CẤM VI PHẠM):
            - CHỈ được sử dụng địa điểm có trong [DANH SÁCH ĐỊA ĐIỂM THẬT] ở trên.
            - NGHIÊM CẤM TUYỆT ĐỐI thêm bất kỳ địa điểm nào KHÔNG có trong danh sách, dù là nổi tiếng hay hidden-gem.
            - NGHIÊM CẤM dùng địa điểm của tỉnh/thành khác (VD: Đà Lạt, Hà Nội, HCM...) vào lịch trình {$destination}.
            - Nếu không đủ địa điểm: được phép lặp lại hoặc sắp xếp lại thứ tự các địa điểm trong danh sách.

            YÊU CẦU JSON RESPONSE (Chỉ trả về JSON thuần, KHÔNG markdown, KHÔNG giải thích):
            {
            "is_valid": true,
            "ai_note": "Ghi chú của chuyên gia cho khách (hoặc lý do từ chối nếu is_valid = false)",
            "title": "Tên lịch trình ngắn gọn hấp dẫn",
            "summary": "Mô tả tổng quan 1-2 câu",
            "days": [
                {
                "day_number": 1,
                "theme": "Chủ đề ngày",
                "items": [
                    {
                    "item_type": "hotel|restaurant|attraction|transport|activity|shopping",
                    "title": "Tên địa điểm (CẤM TRÙNG LẶP VỚI NGÀY KHÁC)",
                    "description": "Mô tả thực tế, thu hút",
                    "start_time": "08:00",
                    "end_time": "10:00",
                    "estimated_cost": 150000,
                    "order_in_day": 1
                    }
                ]
                }
            ]
            }

            - Lịch mỗi ngày bắt đầu từ 07:00 và kết thúc lúc 22:00
            - Thứ tự thời gian phải tăng dần: sáng → trưa → chiều → tối
            - Không xếp check-in khách sạn trước 13:00
            - Bữa trưa: 11:30-13:30 | Bữa tối: 18:30-20:30  
        PROMPT;

        // ===== CALL GROQ API =====
        $parsed = null;

        try {
            $groqKey   = config('services.groq.api_key');
            $groqModel = 'llama-3.3-70b-versatile'; 

            if ($groqKey) {
                $response = Http::timeout(45)
                    ->withToken($groqKey)
                    ->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model'       => $groqModel,
                        'max_tokens'  => 4000,
                        'temperature' => 0.7,
                        'messages'    => [
                            [
                                'role'    => 'system',
                                'content' => 'Bạn là AI trợ lý du lịch có khả năng suy luận (reasoning). Phải trả về JSON thuần theo schema.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $prompt,
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $raw    = $response->json('choices.0.message.content');
                    $clean  = preg_replace('/^```(?:json)?\s*/m', '', $raw ?? '');
                    $clean  = preg_replace('/```\s*$/m', '', $clean);
                    $parsed = json_decode(trim($clean), true);

                    // XỬ LÝ KHI AI PHÁT HIỆN YÊU CẦU VÔ LÝ
                    if (isset($parsed['is_valid']) && $parsed['is_valid'] === false) {
                        return response()->json([
                            'message' => 'Yêu cầu không khả thi',
                            'error'   => $parsed['ai_note'] ?? 'AI không thể tạo lịch trình với các yêu cầu này tại ' . $destination
                        ], 400); 
                    }

                    if (!$parsed || !isset($parsed['days'])) {
                        Log::warning('Groq: JSON parse failed', ['raw' => substr($raw ?? '', 0, 500)]);
                        $parsed = null;
                    }
                } else {
                    Log::warning('Groq API error', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Groq exception: ' . $e->getMessage());
        }

        // ===== FALLBACK nếu AI fail =====
        if (!$parsed) {
            $parsed = $this->buildFallback($destination, $days, $budget, $people);
        }

        // ===== LƯU DATABASE =====
        $endDate = $startDate
            ? date('Y-m-d', strtotime("+{$days} days", strtotime($startDate)))
            : null;

        $itinerary = Itinerary::create([
            'customer_id'      => $user->id,
            'title'            => $parsed['title']            ?? "Lịch trình {$days} ngày tại {$destination}",
            'location_id'      => $locationRecord?->id, 
            'total_days'       => $days,
            'start_date'       => $startDate,
            'end_date'         => $endDate,
            'estimated_budget' => $budget,
            'status'           => 'draft',
        ]);

        foreach ($parsed['days'] as $day) {
            $dayNum = (int) ($day['day_number'] ?? 1);
            foreach ($day['items'] ?? [] as $item) {
                ItineraryItem::create([
                    'itinerary_id'   => $itinerary->id,
                    'day_number'     => $dayNum,
                    'item_type'      => $this->sanitizeType($item['item_type'] ?? ''),
                    'title'          => $item['title']          ?? 'Hoạt động',
                    'description'    => $item['description']    ?? null,
                    'start_time'     => $item['start_time']      ?? null,
                    'end_time'       => $item['end_time']        ?? null,
                    'estimated_cost' => isset($item['estimated_cost']) ? (int) $item['estimated_cost'] : null,
                    'order_in_day'   => (int) ($item['order_in_day'] ?? 0),
                ]);
            }
        }

        $itinerary->load(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day')]);

        return response()->json([
            'message'   => 'Đã tạo lịch trình thành công!',
            'itinerary' => $this->formatItinerary($itinerary),
            'summary'   => $parsed['summary'] ?? null,
            'ai_note'   => $parsed['ai_note'] ?? null,
        ], 201);
    }

/* ============================================================
   POST /api/itineraries/{id}/chat
   Chat tinh chỉnh lịch trình đã có
============================================================ */
    public function chat(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
            'history.*.role'    => 'in:user,assistant',
            'history.*.content' => 'string|max:2000',
        ]);

        $itinerary = Itinerary::where('customer_id', $request->user()->id)
            ->with(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day'), 'location'])
            ->findOrFail($id);

        $itineraryJson = json_encode($this->formatItinerary($itinerary), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $systemPrompt = <<<SYS
        Bạn là AI trợ lý du lịch. Bạn đang xem xét LỊCH TRÌNH HIỆN TẠI của khách.

        LỊCH TRÌNH HIỆN TẠI:
        {$itineraryJson}

        LUẬT SINH TỒN (NẾU VI PHẠM SẼ GÂY LỖI HỆ THỐNG):
        Khi khách yêu cầu Thêm, Sửa, hoặc Xóa bất kỳ hoạt động nào, bạn PHẢI LUÔN LUÔN trả về MỘT MẢNG "items" chứa TOÀN BỘ CÁC HOẠT ĐỘNG CỦA TẤT CẢ CÁC NGÀY (bao gồm cả những ngày không bị thay đổi).
        Tuyệt đối không được chỉ trả về 1 ngày. Nếu lịch trình cũ có 15 activities trong 3 ngày, và khách bảo xóa 1, bạn phải trả về 14 activities còn lại.
        - `action` luôn là "update_items".
        - `estimated_cost` phải là số nguyên (không chứa chữ 'đ' hay dấu chấm).
        - Mọi hoạt động phải có đủ: day_number, item_type, title, start_time, end_time, order_in_day.

        [QUY ĐỊNH JSON KẾT QUẢ - CHỈ TRẢ VỀ JSON]
        {
        "action": "update_items",
        "reply": "Thông báo bạn đã làm gì (VD: Đã xóa cà phê và thêm ăn trưa).",
        "items": [
            { "day_number": 1, "item_type": "attraction", "title": "Bà Nà Hills", "start_time": "08:00", "end_time": "12:00", "estimated_cost": 500000, "order_in_day": 1 },
            ... (GHI ĐẦY ĐỦ ITEMS CỦA TẤT CẢ CÁC NGÀY VÀO ĐÂY) ...
        ]
        }
        SYS;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($request->history ?? [] as $h) {
            if (in_array($h['role'], ['user', 'assistant'])) {
                $messages[] = ['role' => $h['role'], 'content' => $h['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        $replyText = null;
        $action    = 'reply';
        $items     = [];

        try {
            $groqKey = config('services.groq.api_key');
            if ($groqKey) {
                $response = Http::timeout(30)
                    ->withToken($groqKey)
                    ->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model'       => 'llama-3.3-70b-versatile',
                        'max_tokens'  => 3000,
                        'temperature' => 0.6,
                        'messages'    => $messages,
                    ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content') ?? '';

                    // Strip markdown nếu AI bọc backtick
                    $clean = preg_replace('/^```(?:json)?\s*/m', '', $content);
                    $clean = preg_replace('/```\s*$/m',          '', $clean);
                    $data  = json_decode(trim($clean), true);

                    if (is_array($data) && isset($data['action'])) {
                        $action    = $data['action'];
                        $replyText = $data['reply'] ?? 'Đã xử lý.';
                        $items     = $data['items'] ?? [];
                    } else {
                        // AI không trả JSON đúng → dùng raw text
                        $replyText = trim($content);
                        $action    = 'reply';
                    }
                } else {
                    Log::warning('Groq chat error', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Groq chat exception: ' . $e->getMessage());
        }

        if (!$replyText) {
            $replyText = 'Xin lỗi, không thể xử lý yêu cầu. Vui lòng thử lại!';
            $action    = 'reply';
        }

        // Áp dụng thay đổi
        $wasUpdated = false;

        if ($action === 'update_items' && !empty($items)) {
            $this->applyItemUpdates($itinerary, $items, 'replace');
            $itinerary->load(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day')]);
            $wasUpdated = true;
        }

        if ($action === 'merge_items' && !empty($items)) {
            $this->applyItemUpdates($itinerary, $items, 'merge');
            $itinerary->load(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day')]);
            $wasUpdated = true;
        }

        return response()->json([
            'reply'     => $replyText,
            'action'    => $action,
            'updated'   => $wasUpdated,
            'itinerary' => $wasUpdated ? $this->formatItinerary($itinerary) : null,
        ]);
    }

    /* ============================================================
       PUT /api/itineraries/{id}
    ============================================================ */
    public function update(Request $request, int $id): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)->findOrFail($id);
        $itinerary->update($request->only(['title', 'start_date', 'end_date', 'status', 'estimated_budget']));
        $itinerary->load(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day')]);

        return response()->json(['message' => 'Đã cập nhật!', 'itinerary' => $this->formatItinerary($itinerary)]);
    }

    /* ============================================================
       PUT /api/itineraries/{id}/items/{itemId}
    ============================================================ */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)->findOrFail($id);
        $item = ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId);

        $item->update($request->only([
            'title', 'description', 'start_time', 'end_time',
            'estimated_cost', 'item_type', 'order_in_day'
        ]));

        return response()->json(['message' => 'Đã cập nhật hoạt động!', 'item' => $item]);
    }

    /* ============================================================
       DELETE /api/itineraries/{id}/items/{itemId}
    ============================================================ */
    public function deleteItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)->findOrFail($id);
        ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId)->delete();
        return response()->json(['message' => 'Đã xóa hoạt động!']);
    }

    /* ============================================================
       DELETE /api/itineraries/{id}
    ============================================================ */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)->findOrFail($id);
        $itinerary->delete();
        return response()->json(['message' => 'Đã xóa lịch trình!']);
    }

    /* ============================================================
       PRIVATE HELPERS
    ============================================================ */
    private function formatItinerary(Itinerary $itinerary): array
    {
        $days = [];
        foreach ($itinerary->items->groupBy('day_number') as $dayNum => $items) {
            $days[] = [
                'day_number' => $dayNum,
                'items'      => $items->map(fn($item) => [
                    'id'             => $item->id,
                    'item_type'      => $item->item_type,
                    'title'          => $item->title,
                    'description'    => $item->description,
                    'start_time'     => $item->start_time,
                    'end_time'       => $item->end_time,
                    'estimated_cost' => $item->estimated_cost,
                    'order_in_day'   => $item->order_in_day,
                ])->values(),
            ];
        }

        return [
            'id'               => $itinerary->id,
            'title'            => $itinerary->title,
            'location_id'      => $itinerary->location_id,
            'destination'      => $itinerary->location_id
                                    ? Location::find($itinerary->location_id)?->name
                                    : null,
            'total_days'       => $itinerary->total_days,
            'start_date'       => $itinerary->start_date?->format('Y-m-d'),
            'end_date'         => $itinerary->end_date?->format('Y-m-d'),
            'estimated_budget' => $itinerary->estimated_budget,
            'status'           => $itinerary->status,
            'days'             => $days,
            'total_cost'       => $itinerary->items->sum('estimated_cost'),
        ];
    }

    private function sanitizeType(string $type): string
    {
        $allowed = ['hotel', 'restaurant', 'attraction', 'transport', 'activity', 'shopping'];
        return in_array($type, $allowed) ? $type : 'attraction';
    }

    private function applyItemUpdates(Itinerary $itinerary, array $items, string $mode = 'replace'): void
    {
        if ($mode === 'replace') {
            // Xoá toàn bộ items cũ, thay bằng items mới
            ItineraryItem::where('itinerary_id', $itinerary->id)->delete();
            foreach ($items as $item) {
                ItineraryItem::create([
                    'itinerary_id'   => $itinerary->id,
                    'day_number'     => (int) ($item['day_number']    ?? 1),
                    'item_type'      => $this->sanitizeType($item['item_type'] ?? ''),
                    'title'          => $item['title']          ?? 'Hoạt động',
                    'description'    => $item['description']    ?? null,
                    'start_time'     => $item['start_time']      ?? null,
                    'end_time'       => $item['end_time']        ?? null,
                    'estimated_cost' => isset($item['estimated_cost']) ? (int) $item['estimated_cost'] : null,
                    'order_in_day'   => (int) ($item['order_in_day']   ?? 0),
                ]);
            }
        } elseif ($mode === 'merge') {
            // Chỉ thêm mới items, giữ nguyên items cũ
            foreach ($items as $item) {
                ItineraryItem::create([
                    'itinerary_id'   => $itinerary->id,
                    'day_number'     => (int) ($item['day_number']    ?? 1),
                    'item_type'      => $this->sanitizeType($item['item_type'] ?? ''),
                    'title'          => $item['title']          ?? 'Hoạt động',
                    'description'    => $item['description']    ?? null,
                    'start_time'     => $item['start_time']      ?? null,
                    'end_time'       => $item['end_time']        ?? null,
                    'estimated_cost' => isset($item['estimated_cost']) ? (int) $item['estimated_cost'] : null,
                    'order_in_day'   => (int) ($item['order_in_day']   ?? 0),
                ]);
            }
        }
    }

    private function buildFallback(string $destination, int $days, int $budget, int $people): array
    {
        $perDay = intval($budget / $days);
        $templates = [
            ['item_type' => 'transport',  'title' => "Di chuyển xuất phát",                    'start_time' => '07:00', 'end_time' => '08:00', 'description' => 'Di chuyển đến điểm tham quan đầu tiên.',  'ratio' => 0.05],
            ['item_type' => 'attraction', 'title' => "Tham quan buổi sáng tại {$destination}", 'start_time' => '08:00', 'end_time' => '11:00', 'description' => "Khám phá địa danh nổi tiếng tại {$destination}.", 'ratio' => 0.15],
            ['item_type' => 'restaurant', 'title' => "Bữa trưa đặc sản {$destination}",        'start_time' => '11:30', 'end_time' => '13:00', 'description' => 'Thưởng thức ẩm thực địa phương.',          'ratio' => 0.10],
            ['item_type' => 'attraction', 'title' => "Tham quan buổi chiều",                   'start_time' => '13:30', 'end_time' => '16:30', 'description' => "Khám phá thêm địa điểm tại {$destination}.", 'ratio' => 0.15],
            ['item_type' => 'activity',   'title' => "Trải nghiệm văn hóa địa phương",         'start_time' => '17:00', 'end_time' => '18:30', 'description' => 'Tham gia hoạt động đặc trưng vùng miền.',  'ratio' => 0.10],
            ['item_type' => 'restaurant', 'title' => "Bữa tối ẩm thực đường phố",              'start_time' => '19:00', 'end_time' => '20:30', 'description' => 'Thưởng thức ẩm thực buổi tối.',            'ratio' => 0.10],
            ['item_type' => 'hotel',      'title' => "Khách sạn tại {$destination}",           'start_time' => '21:00', 'end_time' => '22:00', 'description' => 'Nhận phòng và nghỉ ngơi.',                'ratio' => 0.35],
        ];

        $dayz = [];
        for ($d = 1; $d <= $days; $d++) {
            $items = [];
            foreach ($templates as $order => $t) {
                $items[] = [
                    'item_type'      => $t['item_type'],
                    'title'          => $t['title'],
                    'description'    => $t['description'],
                    'start_time'     => $t['start_time'],
                    'end_time'       => $t['end_time'],
                    'estimated_cost' => intval($perDay * $t['ratio']),
                    'order_in_day'   => $order + 1,
                ];
            }
            $dayz[] = [
                'day_number' => $d,
                'theme'      => "Ngày {$d} tại {$destination}",
                'items'      => $items,
            ];
        }

        return [
            'title'   => "Lịch trình {$days} ngày tại {$destination}",
            'summary' => "Hành trình khám phá {$destination} trong {$days} ngày cho {$people} người.",
            'days'    => $dayz,
        ];
    }
}