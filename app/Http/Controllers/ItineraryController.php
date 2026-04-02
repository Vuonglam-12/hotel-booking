<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\Destination;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ItineraryController extends Controller
{
    // ==========================================
    // Xem tất cả lịch trình của user
    // GET /api/itineraries
    // ==========================================
    public function index()
    {
        $itineraries = Itinerary::with(['destination', 'items'])
            ->where('customer_id', auth('sanctum')->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($itineraries);
    }

    // ==========================================
    // Xem chi tiết 1 lịch trình
    // GET /api/itineraries/{id}
    // ==========================================
    public function show($id)
    {
        $itinerary = Itinerary::with([
            'destination',
            'items.hotel',       // KS trong từng item
            'items.destination', // Địa điểm trong từng item
        ])
        ->where('customer_id', auth('sanctum')->id())
        ->findOrFail($id);

        return response()->json($itinerary);
    }

    // ==========================================
    // Tạo lịch trình thủ công
    // POST /api/itineraries
    // ==========================================
    public function store(Request $request)
    {
        $request->validate([
            'title'            => 'required|string|max:200',
            'start_date'       => 'nullable|date',
            'end_date'         => 'nullable|date|after:start_date',
            'estimated_budget' => 'nullable|numeric|min:0',
            'destination_id'   => 'nullable|integer|exists:destination,id',
        ]);

        $itinerary = Itinerary::create([
            'customer_id'      => auth('sanctum')->id(),
            'title'            => $request->title,
            'destination_id'   => $request->destination_id,
            'start_date'       => $request->start_date,
            'end_date'         => $request->end_date,
            'total_days'       => $request->start_date && $request->end_date
                ? \Carbon\Carbon::parse($request->start_date)->diffInDays($request->end_date) + 1
                : null,
            'estimated_budget' => $request->estimated_budget,
            'status'           => 'draft',
            'created_at'       => now(),
        ]);

        return response()->json([
            'message'   => 'Tạo lịch trình thành công',
            'itinerary' => $itinerary,
        ], 201);
    }

    // ==========================================
    // Thêm hoạt động vào lịch trình
    // POST /api/itineraries/{id}/items
    // ==========================================
    public function addItem(Request $request, $id)
    {
        $request->validate([
            'day_number'     => 'required|integer|min:1',
            'order_in_day'   => 'required|integer|min:1',
            'item_type'      => 'required|in:hotel,destination,restaurant,transport,activity',
            'title'          => 'required|string|max:200',
            'hotel_id'       => 'nullable|integer|exists:hotel,id',
            'destination_id' => 'nullable|integer|exists:destination,id',
            'description'    => 'nullable|string',
            'start_time'     => 'nullable|string',
            'end_time'       => 'nullable|string',
            'estimated_cost' => 'nullable|numeric|min:0',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
        ]);

        // Verify lịch trình thuộc về user này
        $itinerary = Itinerary::where('customer_id', auth('sanctum')->id())
            ->findOrFail($id);

        // Nếu không có tọa độ nhưng có hotel_id → lấy tọa độ từ hotel
        $lat = $request->latitude;
        $lng = $request->longitude;
        if (!$lat && $request->hotel_id) {
            $hotel = Hotel::find($request->hotel_id);
            $lat = $hotel?->latitude;
            $lng = $hotel?->longitude;
        }

        // Nếu không có tọa độ nhưng có destination_id → lấy tọa độ từ destination
        if (!$lat && $request->destination_id) {
            $dest = Destination::find($request->destination_id);
            $lat = $dest?->latitude;
            $lng = $dest?->longitude;
        }

        $item = ItineraryItem::create([
            'itinerary_id'   => $itinerary->id,
            'day_number'     => $request->day_number,
            'order_in_day'   => $request->order_in_day,
            'item_type'      => $request->item_type,
            'hotel_id'       => $request->hotel_id,
            'destination_id' => $request->destination_id,
            'title'          => $request->title,
            'description'    => $request->description,
            'start_time'     => $request->start_time,
            'end_time'       => $request->end_time,
            'estimated_cost' => $request->estimated_cost,
            'latitude'       => $lat,
            'longitude'      => $lng,
            'created_at'     => now(),
        ]);

        return response()->json([
            'message' => 'Thêm hoạt động thành công',
            'item'    => $item->load(['hotel', 'destination']),
        ], 201);
    }

    // ==========================================
    // Xóa hoạt động khỏi lịch trình
    // DELETE /api/itineraries/{id}/items/{item_id}
    // ==========================================
    public function removeItem($id, $item_id)
    {
        // Verify lịch trình thuộc về user này
        $itinerary = Itinerary::where('customer_id', auth('sanctum')->id())
            ->findOrFail($id);

        ItineraryItem::where('id', $item_id)
            ->where('itinerary_id', $itinerary->id)
            ->delete();

        return response()->json(['message' => 'Đã xóa hoạt động']);
    }

    // ==========================================
    // Cập nhật trạng thái lịch trình
    // PUT /api/itineraries/{id}/status
    // ==========================================
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:draft,confirmed,completed',
        ]);

        $itinerary = Itinerary::where('customer_id', auth('sanctum')->id())
            ->findOrFail($id);

        $itinerary->update(['status' => $request->status]);

        return response()->json([
            'message'   => 'Cập nhật trạng thái thành công',
            'itinerary' => $itinerary,
        ]);
    }

    // ==========================================
    // Tạo lịch trình bằng AI (Groq)
    // POST /api/itineraries/generate
    // User nhập: thành phố, số ngày, budget → AI tự lên lịch
    // ==========================================
    public function generateWithAI(Request $request)
    {
        $request->validate([
            'city'       => 'required|string',
            'days'       => 'required|integer|min:1|max:14',
            'budget'     => 'nullable|numeric',
            'start_date' => 'nullable|date',
            'interests'  => 'nullable|string', // VD: "biển, ẩm thực, lịch sử"
        ]);

        // Lấy danh sách KS và địa điểm tại thành phố đó
        $hotels = Hotel::whereHas('location', fn($q) => $q->where('name', 'like', '%' . $request->city . '%'))
            ->where('status', 'active')
            ->select('id', 'name', 'star_rating', 'avg_rating', 'address', 'latitude', 'longitude')
            ->get()
            ->map(fn($h) => "[KS-{$h->id}] {$h->name} ({$h->star_rating} sao, rating: {$h->avg_rating})")
            ->join("\n");

        $destinations = Destination::whereHas('location', fn($q) => $q->where('name', 'like', '%' . $request->city . '%'))
            ->select('id', 'name', 'category', 'description', 'latitude', 'longitude')
            ->get()
            ->map(fn($d) => "[DD-{$d->id}] {$d->name} (loại: {$d->category})")
            ->join("\n");

        // Prompt yêu cầu AI tạo lịch trình dạng JSON
        $prompt = "Hãy tạo lịch trình du lịch {$request->days} ngày tại {$request->city}.
" . ($request->budget ? "Ngân sách: " . number_format($request->budget) . " VND.\n" : "")
. ($request->interests ? "Sở thích: {$request->interests}.\n" : "")
. "
Khách sạn có sẵn:
{$hotels}

Địa điểm có sẵn:
{$destinations}

Hãy trả về JSON theo format sau (KHÔNG có text nào khác, chỉ JSON thuần):
{
  \"title\": \"Tên lịch trình\",
  \"estimated_budget\": 5000000,
  \"days\": [
    {
      \"day\": 1,
      \"activities\": [
        {
          \"order\": 1,
          \"type\": \"hotel\",
          \"title\": \"Check-in khách sạn\",
          \"hotel_id\": 1,
          \"destination_id\": null,
          \"start_time\": \"14:00\",
          \"end_time\": \"15:00\",
          \"estimated_cost\": 900000,
          \"description\": \"Nhận phòng và nghỉ ngơi\"
        }
      ]
    }
  ]
}";

        // Gọi Groq AI
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.groq.api_key'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.groq.com/openai/v1/chat/completions', [
            'model'       => config('services.groq.model', 'llama-3.1-8b-instant'),
            'messages'    => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens'  => 2000,
            'temperature' => 0.7,
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'AI đang bận, thử lại sau!'], 500);
        }

        $aiContent = $response->json('choices.0.message.content');

        // Parse JSON từ AI — xóa markdown code block nếu có
        $aiContent = preg_replace('/```json\s*|\s*```/', '', $aiContent);
        $aiData = json_decode(trim($aiContent), true);

        if (!$aiData) {
            return response()->json([
                'message' => 'AI trả về dữ liệu không hợp lệ, thử lại!',
                'raw'     => $aiContent,
            ], 500);
        }

        // Lưu lịch trình vào DB trong transaction
        $itinerary = DB::transaction(function () use ($request, $aiData) {
            // Tạo lịch trình
            $itinerary = Itinerary::create([
                'customer_id'      => auth('sanctum')->id(),
                'title'            => $aiData['title'] ?? "Lịch trình {$request->city} {$request->days} ngày",
                'start_date'       => $request->start_date,
                'end_date'         => $request->start_date
                    ? \Carbon\Carbon::parse($request->start_date)->addDays($request->days - 1)->format('Y-m-d')
                    : null,
                'total_days'       => $request->days,
                'estimated_budget' => $aiData['estimated_budget'] ?? $request->budget,
                'status'           => 'draft',
                'created_at'       => now(),
            ]);

            // Lưu từng hoạt động trong lịch trình
            foreach ($aiData['days'] ?? [] as $day) {
                foreach ($day['activities'] ?? [] as $activity) {
                    // Lấy tọa độ tự động từ hotel hoặc destination
                    $lat = $lng = null;
                    if (!empty($activity['hotel_id'])) {
                        $hotel = Hotel::find($activity['hotel_id']);
                        $lat = $hotel?->latitude;
                        $lng = $hotel?->longitude;
                    } elseif (!empty($activity['destination_id'])) {
                        $dest = Destination::find($activity['destination_id']);
                        $lat = $dest?->latitude;
                        $lng = $dest?->longitude;
                    }

                    ItineraryItem::create([
                        'itinerary_id'   => $itinerary->id,
                        'day_number'     => $day['day'],
                        'order_in_day'   => $activity['order'] ?? 1,
                        'item_type'      => $activity['type'] ?? 'activity',
                        'hotel_id'       => $activity['hotel_id'] ?? null,
                        'destination_id' => $activity['destination_id'] ?? null,
                        'title'          => $activity['title'],
                        'description'    => $activity['description'] ?? null,
                        'start_time'     => $activity['start_time'] ?? null,
                        'end_time'       => $activity['end_time'] ?? null,
                        'estimated_cost' => $activity['estimated_cost'] ?? 0,
                        'latitude'       => $lat,
                        'longitude'      => $lng,
                        'created_at'     => now(),
                    ]);
                }
            }

            return $itinerary;
        });

        return response()->json([
            'message'   => 'AI đã tạo lịch trình thành công! 🗺️',
            'itinerary' => $itinerary->load(['items.hotel', 'items.destination']),
        ], 201);
    }

    // ==========================================
    // Lấy route cho Google Maps
    // GET /api/itineraries/{id}/map-route
    // Trả về tất cả tọa độ theo thứ tự để vẽ đường đi
    // ==========================================
    public function mapRoute($id)
    {
        $itinerary = Itinerary::where('customer_id', auth('sanctum')->id())
            ->findOrFail($id);

        // Lấy tất cả item có tọa độ, sắp xếp theo ngày và thứ tự
        $route = ItineraryItem::where('itinerary_id', $id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('day_number')
            ->orderBy('order_in_day')
            ->select('day_number', 'order_in_day', 'title', 'item_type', 'latitude', 'longitude', 'start_time')
            ->get();

        return response()->json([
            'itinerary_id' => $id,
            'title'        => $itinerary->title,
            'route'        => $route, // Mảng tọa độ theo thứ tự → vẽ đường đi trên Maps
        ]);
    }
}