<?php
// 
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Customer;
use App\Models\Review;
use App\Models\Payment;
use App\Models\Location;
use App\Models\Attraction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ==========================================
    // MIDDLEWARE CHECK — chỉ staff/admin mới vào được
    // ==========================================
    private function checkAdmin(Request $request)
    {
        $staff = $request->user('admin');

        if (!$staff || !($staff instanceof \App\Models\Staff)) {
            abort(403, 'Chỉ admin mới có quyền truy cập');
        }

        return $staff;
    }

    // ==========================================
    // DASHBOARD — tổng quan hệ thống
    // ==========================================
    public function dashboard(Request $request)
    {
        $this->checkAdmin($request);

        $now        = now();
        $thisMonth  = $now->month;
        $thisYear   = $now->year;
        $lastMonth  = $now->copy()->subMonth();

        // ── Tổng booking tháng này vs tháng trước ──
        $totalBookings      = Booking::count();
        $bookingsThisMonth  = Booking::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->count();
        $bookingsLastMonth  = Booking::whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year)->count();
        $bookingChange      = $bookingsLastMonth > 0
            ? round((($bookingsThisMonth - $bookingsLastMonth) / $bookingsLastMonth) * 100)
            : null;

        // ── Doanh thu tháng này vs tháng trước ──
        $revenueThisMonth = Payment::where('payment_status', 'success')
            ->whereMonth('paid_at', $thisMonth)->whereYear('paid_at', $thisYear)
            ->sum('amount');
        $revenueLastMonth = Payment::where('payment_status', 'success')
            ->whereMonth('paid_at', $lastMonth->month)->whereYear('paid_at', $lastMonth->year)
            ->sum('amount');
        $revenueChange = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100)
            : null;

        // ── Khách hàng mới tháng này ──
        $newCustomers      = Customer::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->count();
        $newCustomersLast  = Customer::whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year)->count();
        $customerChange    = $newCustomersLast > 0
            ? round((($newCustomers - $newCustomersLast) / $newCustomersLast) * 100)
            : null;

        // ── Review chờ duyệt (pending) ──
        $pendingReviews     = Review::where('status', 'pending')->count();
        $pendingReviewsList = Review::with(['customer:id,name', 'hotel:id,name'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'created_at' => $r->created_at,
                'user'       => $r->customer ? ['name' => $r->customer->name] : null,
                'hotel'      => $r->hotel    ? ['name' => $r->hotel->name]    : null,
            ]);

        // ── Booking gần đây ──
        $recentBookings = Booking::with(['customer:id,name', 'hotel:id,name'])
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'status'      => $b->status,
                'total_price' => $b->total_price,
                'user'        => $b->customer ? ['name' => $b->customer->name] : null,
                'room'        => ['hotel' => $b->hotel ? ['name' => $b->hotel->name] : null],
            ]);

        // ── Doanh thu 7 ngày gần nhất ──
        $revenue7days = collect(range(6, 0))->map(function ($daysAgo) {
            $date   = now()->subDays($daysAgo);
            $amount = Payment::where('payment_status', 'success')
                ->whereDate('paid_at', $date->toDateString())
                ->sum('amount');
            $dayNames = ['CN','T2','T3','T4','T5','T6','T7'];
            return [
                'label'  => $dayNames[$date->dayOfWeek],
                'amount' => (int) $amount,
            ];
        });

        return response()->json([
            'total_bookings'       => $totalBookings,
            'booking_change'       => $bookingChange,
            'monthly_revenue'      => $revenueThisMonth,
            'revenue_change'       => $revenueChange,
            'new_customers'        => $newCustomers,
            'customer_change'      => $customerChange,
            'pending_reviews'      => $pendingReviews,
            'pending_bookings'     => Booking::where('status', 'pending')->count(),
            'recent_bookings'      => $recentBookings,
            'pending_reviews_list' => $pendingReviewsList,
            'revenue_7days'        => $revenue7days,
        ]);
    }

    // ==========================================
    // QUẢN LÝ BOOKING
    // ==========================================

    public function bookings(Request $request)
    {
        $this->checkAdmin($request);

        $query = Booking::with(['customer:id,name,email,phone', 'hotel:id,name', 'payment', 'rooms:id,room_number'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->hotel_id) {
            $query->where('hotel_id', $request->hotel_id);
        }

        if ($request->date_from) {
            $query->where('check_in', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('check_out', '<=', $request->date_to);
        }

        if ($request->search) {
            $query->whereHas('customer', fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
            );
        }

        $paginator = $query->paginate($request->per_page ?? 20);
        $paginator->through(fn($b) => array_merge($b->toArray(), [
            'room_numbers' => $b->rooms->pluck('room_number')->filter()->join(', '),
        ]));
        return response()->json($paginator);
    }

    // Cập nhật trạng thái booking 
    public function updateBookingStatus(Request $request, $id)
    {
        $this->checkAdmin($request);

        $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        $booking = Booking::with(['customer', 'bookingRooms.roomType', 'hotel'])->findOrFail($id);
        $oldStatus = $booking->status;
        $booking->update(['status' => $request->status]);

        // Thêm: admin confirm booking cash → tạo Invoice + gửi email
        if ($request->status === 'confirmed' && $oldStatus !== 'confirmed') {
            try {
                $payment = Payment::where('booking_id', $booking->id)->first();

                if ($payment && $payment->payment_method === 'cash') {
                    $payment->update(['payment_status' => 'success', 'paid_at' => now()]);

                    if (!\App\Models\Invoice::where('booking_id', $booking->id)->exists()) {
                        InvoiceController::createFromPayment($booking, $payment);
                    }

                    \Illuminate\Support\Facades\Mail::to($booking->customer->email)
                        ->send(new \App\Mail\BookingConfirmed($booking));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Admin confirm: lỗi invoice/email', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => "Đã cập nhật booking #{$id}: {$oldStatus} → {$request->status}",
            'booking' => $booking,
        ]);
    }

    // ==========================================
    // QUẢN LÝ KHÁCH SẠN
    // ==========================================

    public function hotels(Request $request)
    {
        $this->checkAdmin($request);

        $query = Hotel::with(['location', 'amenities'])
            ->withCount('rooms');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->star) {
            $query->where('star_rating', $request->star);
        }

        $query->orderBy('id', 'desc');

        return response()->json($query->paginate($request->per_page ?? 15));
    }


    // Tạo khách sạn mới
    public function createHotel(Request $request)
    {
        $this->checkAdmin($request);
    
        $request->validate([
            'name'        => 'required|string|max:255',
            'location_id' => 'required|exists:location,id',
            'star_rating' => 'required|integer|between:1,5',
            'address'     => 'required|string|max:500',
            'status'      => 'in:active,inactive,closed',
            'email'       => 'nullable|email',
            'latitude'    => 'nullable|numeric|between:-90,90',   // ← rule đúng
            'longitude'   => 'nullable|numeric|between:-180,180', // ← rule đúng
        ]);

        $hotel = Hotel::create([
            'name'           => $request->name,
            'location_id'    => $request->location_id,
            'star_rating'    => $request->star_rating,
            'address'        => $request->address,
            'description'    => $request->description,
            'phone'          => $request->phone,
            'email'          => $request->email,
            'check_in_time'  => $request->check_in_time  ?? '14:00:00',
            'check_out_time' => $request->check_out_time ?? '12:00:00',
            'latitude'       => $request->latitude  ?? 0, // ← ?? 0 nằm ở đây mới đúng
            'longitude'      => $request->longitude ?? 0, // ← ?? 0 nằm ở đây mới đúng
            'status'         => $request->status ?? 'active',
            'avg_rating'     => 0,
        ]);
    
        if ($request->has('amenities') && is_array($request->amenities)) {
            foreach ($request->amenities as $amenity) {
                \App\Models\HotelAmenity::create([
                    'hotel_id' => $hotel->id,
                    'amenity'  => $amenity,
                ]);
            }
        }
    
        return response()->json([
            'message' => 'Đã thêm khách sạn mới',
            'hotel'   => $hotel->load('location', 'amenities'),
        ], 201);
    }

    // ==========================================
    // QUẢN LÝ PHÒNG
    // ==========================================
    public function createRoomType(Request $request, $hotelId)
    {
        $this->checkAdmin($request);

        $request->validate([
            'name'       => 'required|string|max:100',
            'base_price' => 'required|numeric|min:0',
            'capacity'   => 'required|integer|min:1|max:10',
            'bed_type'   => 'nullable|string|max:50',
            'description'=> 'nullable|string|max:500',
            'room_count' => 'required|integer|min:1|max:100',
            'floor_start'=> 'nullable|integer|min:1|max:99',
        ]);

        $hotel = Hotel::findOrFail($hotelId);

        // Tạo room_type
        $roomType = \App\Models\RoomType::create([
            'name'        => $request->name,
            'base_price'  => $request->base_price,
            'capacity'    => $request->capacity,
            'bed_type'    => $request->bed_type,
            'description' => $request->description,
        ]);

        // Tạo N phòng thật
        $floorStart = $request->floor_start ?? 1;
        $count      = $request->room_count;

        for ($i = 1; $i <= $count; $i++) {
            $floor      = $floorStart + intdiv($i - 1, 10);
            $roomNumber = $floor . str_pad($i, 2, '0', STR_PAD_LEFT);

            \App\Models\Room::create([
                'hotel_id'     => $hotel->id,
                'room_type_id' => $roomType->id,
                'room_number'  => $roomNumber,
                'floor'        => $floor,
                'price'        => $request->base_price,
                'capacity'     => $request->capacity,
                'status'       => 'available',
            ]);
        }

        return response()->json([
            'message'   => "Đã tạo loại phòng và {$count} phòng thành công",
            'room_type' => $roomType,
            'rooms_created' => $count,
        ], 201);
    }

    // Lấy danh sách loại phòng của khách sạn kèm số lượng phòng trống
    public function getRoomTypes(Request $request, $hotelId)
    {
        $this->checkAdmin($request);

        $roomTypes = \App\Models\RoomType::whereHas('rooms', fn($q) => $q->where('hotel_id', $hotelId))
            ->withCount(['rooms' => fn($q) => $q->where('hotel_id', $hotelId)])
            ->get();

        return response()->json($roomTypes);
    }

    // Cập nhật thông tin khách sạn (có thể cập nhật từng phần)
    public function updateHotel(Request $request, $id)
    {
        $this->checkAdmin($request);
    
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'location_id' => 'sometimes|exists:location,id',
            'star_rating' => 'sometimes|integer|between:1,5',
            'address'     => 'sometimes|string|max:500',
            'status'      => 'sometimes|in:active,inactive,closed',
            'email'       => 'nullable|email',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
        ]);
    
        $hotel = Hotel::findOrFail($id);
    
        $hotel->update(array_filter([
            'name'           => $request->name,
            'location_id'    => $request->location_id,
            'star_rating'    => $request->star_rating,
            'address'        => $request->address,
            'description'    => $request->description,
            'phone'          => $request->phone,
            'email'          => $request->email,
            'check_in_time'  => $request->check_in_time,
            'check_out_time' => $request->check_out_time,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'status'         => $request->status,
        ], fn($v) => !is_null($v)));
    
        if ($request->has('amenities')) {
            \App\Models\HotelAmenity::where('hotel_id', $id)->delete();
            foreach ((array) $request->amenities as $amenity) {
                \App\Models\HotelAmenity::create([
                    'hotel_id' => $hotel->id,
                    'amenity'  => $amenity,
                ]);
            }
        }
    
        return response()->json([
            'message' => 'Đã cập nhật khách sạn',
            'hotel'   => $hotel->load('location', 'amenities'),
        ]);
    }

    // ==========================================
    // CẬP NHẬT TRẠNG THÁI KHÁCH SẠN
    // ==========================================

    public function updateHotelStatus(Request $request, $id)
    {
        $this->checkAdmin($request);

        $request->validate([
            'status' => 'required|in:active,inactive,closed',
        ]);

        $hotel = Hotel::findOrFail($id);
        $hotel->update(['status' => $request->status]);

        $labelMap = [
            'active'   => 'Đã bật khách sạn',
            'inactive' => 'Đã tạm ngưng khách sạn',
            'closed'   => 'Đã đóng cửa khách sạn',
        ];

        return response()->json([
            'message' => $labelMap[$request->status] ?? 'Đã cập nhật trạng thái',
            'hotel'   => $hotel->only(['id', 'name', 'status']),
        ]);
    }

    // ==========================================
    // QUẢN LÝ KHÁCH HÀNG
    // ==========================================

    public function customers(Request $request)
    {
        $this->checkAdmin($request);

        $query = Customer::withCount(['bookings', 'reviews', 'wishlist'])
            ->withSum(['payments as total_spent' => function($q) {
                $q->where('payment_status', 'success');
            }], 'amount')
            ->orderBy('created_at', 'desc');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        // KPI tổng quan
        $stats = [
            'total'       => Customer::count(),
            'new_month'   => Customer::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'total_booking' => \App\Models\Booking::count(),
            'total_revenue' => \App\Models\Payment::where('payment_status','success')->sum('amount'),
        ];

        $customers = $query->paginate($request->per_page ?? 20);

        $customers->getCollection()->transform(function ($c) {
            $c->avatar_url = $c->avatar_url
                ? \Illuminate\Support\Facades\Storage::url($c->avatar_url)
                : null;
            return $c;
        });

        return response()->json([
            'stats'     => $stats,
            'customers' => $customers,
        ]);
    }

    // THÊM method mới — chi tiết 1 khách hàng
    public function customerDetail(Request $request, $id)
    {
        $this->checkAdmin($request);

        $customer = Customer::withCount(['bookings', 'reviews'])
            ->withSum(['payments as total_spent' => function($q) {
                $q->where('payment_status', 'success');
            }], 'amount')
            ->findOrFail($id);
            $customer->avatar_url = $customer->avatar_url
                ? \Illuminate\Support\Facades\Storage::url($customer->avatar_url)
                : null;

        $bookings = \App\Models\Booking::with(['hotel:id,name', 'payment'])
            ->where('customer_id', $id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'hotel'       => $b->hotel?->name,
                'check_in'    => $b->check_in,
                'check_out'   => $b->check_out,
                'status'      => $b->status,
                'total_price' => $b->total_price,
                'room_numbers' => $b->rooms->pluck('room_number')->filter()->join(', '),
            ]);

        return response()->json([
            'customer' => $customer,
            'bookings' => $bookings,
        ]);
    }

    // ==========================================
    // QUẢN LÝ REVIEW
    // ==========================================

    public function reviews(Request $request)
    {
        $this->checkAdmin($request);

        $query = Review::with(['customer:id,name', 'hotel:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->max_rating) {
            $query->where('rating', '<=', $request->max_rating);
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    public function deleteReview(Request $request, $id)
    {
        $this->checkAdmin($request);

        $review = Review::findOrFail($id);
        $hotelId = $review->hotel_id;
        $review->delete();

        $avg = Review::where('hotel_id', $hotelId)->avg('rating') ?? 0;
        Hotel::where('id', $hotelId)->update(['avg_rating' => round($avg, 1)]);

        return response()->json(['message' => 'Đã xóa review']);
    }

    // ==========================================
    // BÁO CÁO DOANH THU
    // ==========================================

    public function revenue(Request $request)
    {
        $this->checkAdmin($request);

        $monthlyRevenue = Payment::where('payment_status', 'success')
            ->whereYear('paid_at', now()->year)
            ->selectRaw('MONTH(paid_at) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $topHotels = DB::table('payment as p')
            ->join('booking as b', 'b.id', '=', 'p.booking_id')
            ->join('hotel as h', 'h.id', '=', 'b.hotel_id')
            ->where('p.payment_status', 'success')
            ->selectRaw('h.id, h.name, SUM(p.amount) as revenue, COUNT(p.id) as transactions')
            ->groupBy('h.id', 'h.name')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();

        return response()->json([
            'monthly_revenue' => $monthlyRevenue,
            'top_hotels'      => $topHotels,
            'total_revenue'   => Payment::where('payment_status', 'success')->sum('amount'),
        ]);
    }
    
    // ==========================================
    // QUẢN LÝ LOCATIONS VÀ ATTRACTIONS (Sửa đổi từ Destination)
    // ==========================================

    // Lấy danh sách locations kèm số lượng attractions
    public function destinations(): JsonResponse
    {
        // Đổi model Destination -> Location
        $data = Location::withCount('attractions')
            ->orderBy('name')
            ->get();
        return response()->json($data);
    }

    public function createDestination(Request $request): JsonResponse
    {
        // Update validation rule để khớp với model Location
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'region'      => 'required|string|max:50',
            'country'     => 'nullable|string|max:100',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);
        
        return response()->json(Location::create($validated), 201);
    }

    public function attractions(int $locId): JsonResponse
    {
        $attractions = Attraction::where('location_id', $locId)
            ->orderBy('item_type')
            ->orderBy('name')
            ->get();
        return response()->json($attractions);
    }

    public function createAttraction(Request $request, int $locId): JsonResponse
    {
        $validated = $request->validate([
            'item_type'      => 'required|in:hotel,restaurant,attraction,activity,transport,shopping',
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'address'        => 'nullable|string|max:300',
            'price_min'      => 'nullable|integer|min:0',
            'price_max'      => 'nullable|integer|min:0',
            'duration_hours' => 'nullable|integer|min:0|max:24',
            'open_time'      => 'nullable|string',
            'close_time'     => 'nullable|string',
        ]);

        Location::findOrFail($locId); // check tồn tại

        $attraction = Attraction::create(array_merge($validated, [
            'location_id' => $locId,
        ]));

        return response()->json($attraction, 201);
    }

    public function toggleAttraction(int $id): JsonResponse
    {
        $attraction = Attraction::findOrFail($id);
        $attraction->update(['is_active' => !$attraction->is_active]);
        return response()->json(['is_active' => $attraction->is_active]);
    }

    public function updateAttraction(Request $request, int $id): JsonResponse
    {
        $attraction = Attraction::findOrFail($id);
        $attraction->update($request->only([
            'name', 'description', 'address',
            'price_min', 'price_max', 'duration_hours',
            'open_time', 'close_time', 'item_type',
        ]));
        return response()->json($attraction);
    }

    public function deleteAttraction(int $id): JsonResponse
    {
        Attraction::findOrFail($id)->delete();
        return response()->json(['message' => 'Đã xóa!']);
    }

    public function updateDestination(Request $request, int $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'region'    => 'sometimes|string|max:50',
            'country'   => 'nullable|string|max:100',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $location->update($validated);

        return response()->json($location);
    }

    public function toggleDestination(int $id): JsonResponse
    {
        // Location không có is_active — dùng cách xóa mềm hoặc
        // nếu bảng location có cột is_active thì dùng như dưới.
        // Nếu chưa có cột is_active, bỏ qua hoặc thêm migration trước.
        $location = Location::findOrFail($id);
        $location->update(['is_active' => !$location->is_active]);

        return response()->json(['is_active' => $location->is_active]);
    }
}