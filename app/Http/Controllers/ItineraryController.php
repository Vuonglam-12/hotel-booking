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
        return response()->json([
            'message' => 'T?o l?ch tr?nh m?i s? d?ng API /api/v2/itineraries/generate.',
            'code' => 'use_itinerary_v2_api',
        ], 409);
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

        app(\App\Services\Itinerary\WriteGuard::class)->legacyEndpoint($itinerary);
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
        app(\App\Services\Itinerary\WriteGuard::class)->legacyEndpoint($itinerary);
        $itinerary->update($request->validate([
            'title' => 'sometimes|required|string|max:200',
            'start_date' => 'sometimes|nullable|date_format:Y-m-d',
            'end_date' => 'sometimes|nullable|date_format:Y-m-d|after_or_equal:'.($request->input('start_date') ?: ($itinerary->start_date?->format('Y-m-d') ?: '0001-01-01')),
            'status' => 'sometimes|required|in:draft,saved,confirmed,completed',
            'estimated_budget' => 'sometimes|nullable|numeric|min:0|max:9999999999.99',
        ]));
        $itinerary->load(['items' => fn($q) => $q->orderBy('day_number')->orderBy('order_in_day')]);

        return response()->json(['message' => 'Đã cập nhật!', 'itinerary' => $this->formatItinerary($itinerary)]);
    }

    /* ============================================================
       PUT /api/itineraries/{id}/items/{itemId}
    ============================================================ */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)->findOrFail($id);
        app(\App\Services\Itinerary\WriteGuard::class)->legacyEndpoint($itinerary);
        $item = ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId);

        $item->update($request->validate([
            'title' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string|max:5000',
            'start_time' => ['sometimes', 'nullable', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D'],
            'end_time' => ['sometimes', 'nullable', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D'],
            'estimated_cost' => 'sometimes|nullable|numeric|min:0|max:99999999.99',
            'item_type' => 'sometimes|required|in:hotel,restaurant,attraction,destination,transport,activity,shopping',
            'order_in_day' => 'sometimes|integer|min:0|max:1000',
        ]));

        return response()->json(['message' => 'Đã cập nhật hoạt động!', 'item' => $item]);
    }

    /* ============================================================
       DELETE /api/itineraries/{id}/items/{itemId}
    ============================================================ */
    public function deleteItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)->findOrFail($id);
        app(\App\Services\Itinerary\WriteGuard::class)->legacyEndpoint($itinerary);
        ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId)->delete();
        return response()->json(['message' => 'Đã xóa hoạt động!']);
    }

    /* ============================================================
       DELETE /api/itineraries/{id}
    ============================================================ */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $itinerary = Itinerary::where('customer_id', $request->user()->id)->findOrFail($id);
        app(\App\Services\Itinerary\WriteGuard::class)->legacyEndpoint($itinerary);
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
            'schema_version'   => (int)($itinerary->getAttribute('schema_version') ?? 1),
            'title'            => $itinerary->title,
            'created_at'       => $itinerary->getAttribute('created_at'),
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
