<?php
// 
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Customer;
use App\Models\Review;
use App\Models\Payment;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // Keep the existing protected route explicit while outbound email is unavailable.
    public function sendEmailToCustomer(Request $request, int $id)
    {
        $this->checkAdmin($request);
        return response()->json(['message' => 'Gửi email cho khách hàng hiện chưa khả dụng.'], 503)->header('Cache-Control', 'no-store');
    }

    // ==========================================
    // MIDDLEWARE CHECK — chỉ Admin mới vào được
    // ==========================================
    private function checkAdmin(Request $request)
    {
        $admin = $request->user('admin');

        if (!$admin || !($admin instanceof Customer) || !$admin->isAdmin()) {
            abort(403, 'Chỉ admin mới có quyền truy cập');
        }

        return $admin;
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
        $revenueThisMonth = Payment::withoutDemo()->where('payment_status', 'success')
            ->whereMonth('paid_at', $thisMonth)->whereYear('paid_at', $thisYear)
            ->sum('amount');
        $revenueLastMonth = Payment::withoutDemo()->where('payment_status', 'success')
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
            $amount = Payment::withoutDemo()->where('payment_status', 'success')
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

        $query = Booking::with(['customer:id,name,email,phone', 'hotel:id,name', 'payments', 'rooms:id,room_number', 'bookingRooms.roomType'])
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
        $paginator->through(function ($b) {
            app(\App\Services\HotelRoomTypeService::class)->applyToBooking($b);
            return array_merge($b->toArray(), [
            'room_numbers' => $b->rooms->pluck('room_number')->filter()->join(', '),
            'room_quantity' => (int) $b->bookingRooms->sum('quantity'),
            'room_type_summary' => $b->bookingRooms->map(fn($line) => ($line->roomType?->name ?? 'Loại phòng') . ' × ' . $line->quantity . ' phòng')->join(', '),
            'is_demo' => $b->payments->contains('payment_method','demo'),
            'payment' => $b->payments->sortByDesc('id')->firstWhere('payment_status','success') ?? $b->payments->sortByDesc('id')->first(),
            ]);
        });
        return response()->json($paginator);
    }

    // Cập nhật trạng thái booking 
    public function updateBookingStatus(Request $request, $id)
    {
        $this->checkAdmin($request);
        $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);
        if (\App\Support\LocalBookingDemo::isDemo((int)$id)) {
            \App\Support\LocalBookingDemo::assertRequest($request);
            abort_unless($request->status==='completed',422,'Hãy mở “Xử lý thanh toán” để cập nhật kết quả thanh toán.');
            return response()->json(app(\App\Services\LocalBookingDemoService::class)->completeStay((int)$id,(int)$request->user('admin')->id));
        }

        $candidate = Booking::select('id', 'hotel_id')->findOrFail($id);
        [$booking, $oldStatus] = DB::transaction(function () use ($candidate, $request, $id) {
            Hotel::whereKey($candidate->hotel_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::with(['customer', 'bookingRooms.roomType', 'hotel'])->whereKey($id)->lockForUpdate()->firstOrFail();
            $oldStatus = $booking->status;
            \App\Support\BookingLifecyclePolicy::transition(DB::connection(),$booking,$request->status);
            if (in_array($oldStatus, ['cancelled', 'expired'], true) && $request->status !== 'cancelled') {
                foreach ($booking->bookingRooms->groupBy('room_type_id') as $typeId => $lines) {
                    $inspection = app(\App\Services\RoomAvailabilityService::class)->forTypeWithRooms(
                        $booking->hotel_id, (int) $typeId, $booking->check_in->format('Y-m-d'), $booking->check_out->format('Y-m-d')
                    );
                    $assigned = $lines->whereNotNull('room_id')->groupBy('room_id');
                    $quantity = (int) $lines->whereNull('room_id')->sum('quantity')
                        + $assigned->sum(fn($duplicates) => max(0, (int) $duplicates->max('quantity')));
                    if ($quantity < 1 || $inspection['availability']['available_rooms'] < $quantity) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Không thể khôi phục Booking: không đủ phòng trong toàn bộ khoảng lưu trú.']);
                    }
                    foreach ($assigned->keys() as $roomId) {
                        if (!($inspection['rooms'][$roomId]['bookable'] ?? false)) {
                            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Không thể khôi phục Booking: phòng cụ thể không còn trống.']);
                        }
                    }
                }
            }
            $booking->update(['status' => $request->status]);
            return [$booking, $oldStatus];
        });

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

    public function hotelAmenities(Request $request)
    {
        $this->checkAdmin($request);
        try {
            return response()->json(['amenities' => \App\Support\HotelAmenityCatalog::storedOptions()]);
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
            return response()->json(['message' => 'Không tải được danh mục tiện nghi. Vui lòng thử lại.'], 503);
        }
    }

    public function createHotelAmenity(Request $request)
    {
        $this->checkAdmin($request);
        if (is_string($request->input('name'))) $request->merge([
            'name' => \App\Support\HotelAmenityCatalog::normalize($request->input('name')),
        ]);
        $data = $request->validate(['name' => 'required|string|max:100'], [
            'name.required' => 'Vui lòng nhập tên tiện nghi.',
            'name.string' => 'Tên tiện nghi phải là chuỗi ký tự.',
            'name.max' => 'Tên tiện nghi tối đa 100 ký tự.',
        ]);
        try {
            $amenity = \App\Support\HotelAmenityCatalog::remember($data['name']);
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
            return response()->json(['message' => 'Không thể lưu tiện nghi vào danh mục. Vui lòng thử lại.'], 503);
        }
        if (!$amenity->wasRecentlyCreated) throw \Illuminate\Validation\ValidationException::withMessages([
            'name' => 'Tiện nghi đã có trong danh mục. Vui lòng tìm và chọn tiện nghi này.',
        ]);
        return response()->json(['message' => 'Đã thêm tiện nghi vào danh mục.',
            'amenity' => \App\Support\HotelAmenityCatalog::option($amenity)], 201);
    }

    public function hotels(Request $request)
    {
        $this->checkAdmin($request);
        $query = Hotel::with(['location', 'amenities', 'images' => fn($images) => $images->orderByDesc('is_primary')->orderBy('id')])
            ->withCount(['rooms'=>fn($rooms)=>$rooms->managed()]);
        if ($request->search) $query->where('name', 'like', '%' . $request->search . '%');
        if ($request->status) $query->where('status', $request->status);
        if ($request->star) $query->where('star_rating', $request->star);

        $stats = (clone $query)->select([])->selectRaw(
            "COUNT(*) AS total, COALESCE(SUM(status = 'active'), 0) AS active, "
            . "COALESCE(SUM(status = 'inactive'), 0) AS inactive, COALESCE(SUM(status = 'closed'), 0) AS closed, AVG(avg_rating) AS average_rating"
        )->first();
        $paginator = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 15);
        $paginator->getCollection()->each(fn($hotel) => $hotel->append('cover_image_url'));
        return response()->json(array_merge($paginator->toArray(), ['stats' => [
            'total' => (int) $stats->total, 'active' => (int) $stats->active,
            'inactive' => (int) $stats->inactive, 'closed' => (int) $stats->closed,
            'other' => (int) $stats->total - (int) $stats->active - (int) $stats->inactive - (int) $stats->closed,
            'average_rating' => $stats->average_rating === null ? null : round((float) $stats->average_rating, 1),
        ]]));
    }

    public function hotelDetail(Request $request, int $id)
    {
        $this->checkAdmin($request);
        $hotel = Hotel::with(['location', 'amenities', 'images' => fn($images) => $images->orderByDesc('is_primary')->orderBy('id')])
            ->findOrFail($id);
        $hotel->append('cover_image_url');

        return response()->json(['hotel' => $hotel]);
    }

    private function validateHotelInput(Request $request, bool $creating, ?Hotel $hotel = null): array
    {
        // Multipart has no representation for an empty array; preserve the existing amenities=[] contract.
        if ($request->input('amenities') === '[]' && str_starts_with($request->header('Content-Type', ''), 'multipart/form-data')) $request->merge(['amenities' => []]);
        $presence = $creating ? 'required' : 'sometimes|required';
        if (is_array($request->input('amenities'))) $request->merge(['amenities' => array_map(
            fn($name) => is_string($name) ? \App\Support\HotelAmenityCatalog::normalize($name) : $name,
            $request->input('amenities'))]);
        $data = $request->validate([
            'name' => $presence . '|string|max:200',
            'location_id' => $presence . '|integer|exists:location,id',
            'star_rating' => $presence . '|integer|between:1,5',
            'address' => $presence . '|string|max:300',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:100',
            'description' => ['bail', 'sometimes', 'nullable', 'string', function ($attribute, $value, $fail) {
                if (strlen($value) > 65535) $fail('Mô tả khách sạn vượt giới hạn 65.535 byte.');
            }],
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'status' => 'sometimes|required|in:active,inactive,closed',
            'amenities' => 'sometimes|array|max:100',
            'amenities.*' => ['required', 'string', 'max:100'],
        ], [
            'name.max' => 'Tên khách sạn tối đa 200 ký tự.',
            'address.max' => 'Địa chỉ tối đa 300 ký tự.',
            'phone.max' => 'Số điện thoại tối đa 20 ký tự.',
            'email.max' => 'Email tối đa 100 ký tự.',
            'amenities.array' => 'Tiện nghi phải là danh sách; gửi [] nếu muốn xóa tất cả.',
            'amenities.*.required' => 'Tên tiện nghi không được để trống.',
        ]);
        foreach (['latitude', 'longitude'] as $coordinate) {
            if (array_key_exists($coordinate, $data) && $data[$coordinate] === null) $data[$coordinate] = 0;
        }
        if (array_key_exists('amenities', $data)) {
            $identities = array_map([\App\Support\HotelAmenityCatalog::class, 'identity'], $data['amenities']);
            if (count(array_unique($identities)) !== count($identities)) throw \Illuminate\Validation\ValidationException::withMessages([
                'amenities' => 'Tên tiện nghi không được trùng nhau (không phân biệt hoa/thường).',
            ]);
        }
        return $data;
    }

    private function syncHotelAmenities(Hotel $hotel, array $amenities): void
    {
        $query = \App\Models\HotelAmenity::where('hotel_id', $hotel->id);
        $existing = (clone $query)->lockForUpdate()->get()->groupBy(fn($row) => \App\Support\HotelAmenityCatalog::identity($row->amenity));
        $keep = [];
        foreach ($amenities as $amenity) {
            \App\Support\HotelAmenityCatalog::remember($amenity);
            $row = $existing->get(\App\Support\HotelAmenityCatalog::identity($amenity))?->first();
            if ($row) $row->update(['amenity' => $amenity]);
            else $row = \App\Models\HotelAmenity::create(['hotel_id' => $hotel->id, 'amenity' => $amenity]);
            $keep[] = $row->id;
        }
        // Match identities in PHP, independent of the database's case-insensitive collation.
        // Keep the existing row/icon and remove only unselected or duplicate links of this hotel.
        (clone $query)->whereNotIn('id', $keep)->delete();
    }

    public function createHotel(Request $request)
    {
        $this->checkAdmin($request);
        $data = $this->validateHotelInput($request, true);
        $images = app(\App\Services\AdminAccommodationImageService::class);
        [$changes, $files] = $images->input($request);
        $hotel = $images->atomic(DB::connection(), function ($uploads) use ($data, $images, $changes, $files) {
            $attributes = \Illuminate\Support\Arr::except($data, 'amenities');
            $hotel = Hotel::create(array_merge([
                'latitude' => 0, 'longitude' => 0, 'status' => 'active', 'avg_rating' => 0,
            ], $attributes));
            if (array_key_exists('amenities', $data)) $this->syncHotelAmenities($hotel, $data['amenities']);
            $images->apply(DB::connection(), 'hotel_image', 'hotel_id', $hotel->id, $changes, $files, $uploads);
            return $hotel->load('location', 'amenities', 'images');
        });
        return response()->json(['message' => 'Đã thêm khách sạn mới', 'hotel' => $hotel], 201);
    }

    public function createRoomType(Request $request, $hotelId)
    {
        $this->checkAdmin($request);
        if ($this->roomTypeUsesV2($request)) {
            // The quick Admin form uses owned Types; retain the V2 connection/deployment guard.
            [$changes, $files] = app(\App\Services\AdminAccommodationImageService::class)->input($request);
            $type = app(\App\Services\RoomTypeAdminServiceV2::class)->save('mysql', (int) $hotelId, null, $request->all(), $changes, $files);
            return response()->json(['room_type' => $type], 201);
        }
        if (\App\Services\HotelRoomTypeService::enabled()) {
            $result = app(\App\Services\HotelRoomTypeService::class)->create((int)$hotelId, $request);
            return response()->json(['message'=>'Đã tạo loại phòng và phòng cho khách sạn.', ...$result], 201);
        }
        $data = $request->validate([
            'inventory_model' => 'sometimes|in:legacy',
            'total_rooms' => 'prohibited', 'status' => 'prohibited',
            'images' => 'prohibited', 'image_changes' => 'prohibited',
            'name' => 'required|string|max:100', 'base_price' => 'required|numeric|min:0|max:9999999999.99',
            'capacity' => 'required|integer|min:1|max:10', 'bed_type' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500', 'room_count' => 'required|integer|min:1|max:100',
            'floor_start' => 'nullable|integer|min:1|max:99',
        ], \App\Support\RoomTypeValidationMessages::all());
        $result = DB::transaction(function () use ($hotelId, $data) {
            $hotel = Hotel::whereKey($hotelId)->lockForUpdate()->firstOrFail();
            $roomType = \App\Models\RoomType::create(\Illuminate\Support\Arr::only($data, ['name', 'base_price', 'capacity', 'bed_type', 'description']));
            $used = array_fill_keys($hotel->rooms()->pluck('room_number')->all(), true);
            $floorStart = $data['floor_start'] ?? 1;
            $candidate = 1;
            for ($created = 0; $created < $data['room_count']; $created++) {
                do {
                    $floor = $floorStart + intdiv($candidate - 1, 10);
                    $number = $floor . str_pad($candidate, 2, '0', STR_PAD_LEFT);
                    $candidate++;
                } while (isset($used[$number]));
                \App\Models\Room::create([
                    'hotel_id' => $hotel->id, 'room_type_id' => $roomType->id,
                    'room_number' => $number, 'floor' => $floor, 'price' => $data['base_price'],
                    'capacity' => $data['capacity'], 'status' => 'available',
                ]);
                $used[$number] = true;
            }
            return ['room_type' => $roomType, 'rooms_created' => (int) $data['room_count']];
        });
        return response()->json(array_merge(['message' => "Đã tạo loại phòng và {$result['rooms_created']} phòng thành công"], $result), 201);
    }

    public function getRoomTypes(Request $request, $hotelId)
    {
        $this->checkAdmin($request);

        $model = $request->query('inventory_model');
        $automatic = $model === 'auto';
        // Select the deployed workflow, never use a failed V2 guard as a reason to fall back.
        $useV2 = $model === 'v2' || ($automatic && (config('roomtype-v2.production_manifest') !== null
            || config('roomtype-v2.prepared_routes', false)));
        if ($useV2) {
            try {
                $session = \App\Support\RoomTypeV2Inventory::open('mysql');
            } catch (\RuntimeException $exception) {
                \Illuminate\Support\Facades\Log::warning('Admin RoomType V2 catalog is unavailable.', [
                    'reason' => $exception->getMessage(),
                ]);

                return response()->json([
                    'code' => 'room_type_v2_unavailable',
                    'message' => 'Quản lý loại phòng V2 chưa được cấp quyền cho môi trường này. Cần hoàn tất cấu hình database kiểm thử cô lập hoặc deployment manifest đã được phê duyệt; hệ thống chưa thay đổi schema hay bật cutover.',
                ], 503);
            }

            $session->hotel((int) $hotelId);
            $roomTypes = $session->db->table('room_type')->where('hotel_id', $hotelId)->orderBy('id')->get();
            $typeIds = $roomTypes->pluck('id')->all();
            $primaryImages = $typeIds
                ? $session->db->table('room_type_image')->whereIn('room_type_id', $typeIds)
                    ->orderByDesc('is_primary')->orderBy('id')->get()->groupBy('room_type_id')
                : collect();
            $roomTypes->transform(function ($type) use ($primaryImages) {
                $primary = $primaryImages->get($type->id)?->first();
                $type->cover_image_url = $primary
                    ? (\App\Support\AccommodationImage::localUrl($primary->image_url) ?? \App\Support\AccommodationImage::placeholder())
                    : \App\Support\AccommodationImage::placeholder();
                return $type;
            });

            return response()->json(['room_types' => $roomTypes, 'inventory_model' => 'v2']);
        }

        if (\App\Services\HotelRoomTypeService::enabled()) {
            $types = app(\App\Services\HotelRoomTypeService::class)->index((int)$hotelId);
            return $automatic ? response()->json(['inventory_model'=>'scoped','room_types'=>$types])
                : response()->json(array_map(fn($type)=>[...$type,'rooms_count'=>$type['total_rooms']],$types));
        }
        if ($automatic) Hotel::findOrFail($hotelId);
        $roomTypes = \App\Models\RoomType::whereHas('rooms', fn($q) => $q->where('hotel_id', $hotelId))
            ->withCount(['rooms' => fn($q) => $q->where('hotel_id', $hotelId)])
            ->when($automatic, fn($q) => $q->with(['rooms' => fn($rooms) => $rooms->where('hotel_id', $hotelId)
                ->orderBy('id')->with('images')]))
            ->get();

        if ($automatic) {
            return response()->json([
                'inventory_model' => 'legacy',
                'room_types' => $roomTypes->map(fn($type) => [
                    'id' => $type->id, 'hotel_id' => (int) $hotelId,
                    'name' => $type->name, 'base_price' => $type->base_price, 'capacity' => $type->capacity,
                    'bed_type' => $type->bed_type, 'description' => $type->description,
                    'total_rooms' => $type->rooms_count, 'status' => null,
                    'cover_image_url' => $type->rooms->map(fn($room) => $room->gallery_image_urls[0] ?? null)
                        ->filter()->first() ?? \App\Support\AccommodationImage::placeholder(),
                ]),
            ]);
        }

        return response()->json($roomTypes);
    }

    private function roomTypeUsesV2(Request $request): bool
    {
        return $request->input('inventory_model') === 'v2' || config('roomtype-v2.production_manifest') !== null || config('roomtype-v2.prepared_routes', false);
    }

    public function roomTypeDetail(Request $request, int $hotel, int $type)
    {
        $this->checkAdmin($request);
        if ($this->roomTypeUsesV2($request)) {
            $session = \App\Support\RoomTypeV2Inventory::open('mysql');
            $session->hotel($hotel);
            return response()->json(['room_type'=>$session->types($hotel,[$type])[$type]]);
        }
        abort_unless(\App\Services\HotelRoomTypeService::enabled(),503,'Cấu hình quản lý loại phòng riêng chưa hoàn tất.');
        $service = app(\App\Services\HotelRoomTypeService::class);
        return response()->json(['room_type'=>$service->dto($service->record($hotel,$type))]);
    }

    public function updateRoomType(Request $request, int $hotel, int $type)
    {
        $this->checkAdmin($request);
        if ($this->roomTypeUsesV2($request)) return app(\App\Http\Controllers\PreparedV2\AdminRoomTypeController::class)
            ->update($request,$hotel,$type,app(\App\Services\RoomTypeAdminServiceV2::class));
        abort_unless(\App\Services\HotelRoomTypeService::enabled(),503,'Cấu hình quản lý loại phòng riêng chưa hoàn tất.');
        return response()->json(['room_type'=>app(\App\Services\HotelRoomTypeService::class)->save($hotel,$type,$request)]);
    }

    public function roomTypeImages(Request $request, int $hotel, int $type)
    {
        $this->checkAdmin($request);
        if ($this->roomTypeUsesV2($request)) return app(\App\Http\Controllers\PreparedV2\AdminRoomTypeController::class)
            ->images($hotel,$type,app(\App\Services\RoomTypeAdminServiceV2::class));
        abort_unless(\App\Services\HotelRoomTypeService::enabled(),503,'Cấu hình quản lý loại phòng riêng chưa hoàn tất.');
        return response()->json(app(\App\Services\HotelRoomTypeService::class)->gallery($hotel,$type));
    }

    public function deleteRoomType(Request $request, int $hotel, int $type)
    {
        $this->checkAdmin($request);
        abort_if($this->roomTypeUsesV2($request), 503, 'Thao tác này chỉ hỗ trợ danh mục loại phòng theo khách sạn đang hoạt động.');
        abort_unless(\App\Services\HotelRoomTypeService::enabled(), 503, 'Cấu hình quản lý loại phòng riêng chưa hoàn tất.');
        return response()->json(app(\App\Services\HotelRoomTypeService::class)->delete($hotel, $type));
    }

    // Cập nhật thông tin khách sạn (có thể cập nhật từng phần)

    public function updateHotel(Request $request, $id)
    {
        $this->checkAdmin($request);
        $hotel = Hotel::findOrFail($id);
        $data = $this->validateHotelInput($request, false, $hotel);
        $images = app(\App\Services\AdminAccommodationImageService::class);
        [$changes, $files] = $images->input($request);
        $hotel = $images->atomic(DB::connection(), function ($uploads) use ($id, $data, $images, $changes, $files) {
            $hotel = Hotel::whereKey($id)->lockForUpdate()->firstOrFail();
            $hotel->update(\Illuminate\Support\Arr::except($data, 'amenities'));
            if (array_key_exists('amenities', $data)) $this->syncHotelAmenities($hotel, $data['amenities']);
            $images->apply(DB::connection(), 'hotel_image', 'hotel_id', (int) $id, $changes, $files, $uploads);
            return $hotel->load('location', 'amenities', 'images');
        });
        return response()->json(['message' => 'Đã cập nhật khách sạn', 'hotel' => $hotel]);
    }

    public function hotelImages(Request $request, int $id)
    {
        $this->checkAdmin($request);
        Hotel::findOrFail($id);
        $images = app(\App\Services\AdminAccommodationImageService::class);
        return response()->json(['images' => $images->gallery(DB::connection(), 'hotel_image', 'hotel_id', $id), 'image_validation' => $images::limits()]);
    }

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
                $q->withoutDemo()->where('payment_status', 'success');
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
            'total_revenue' => \App\Models\Payment::withoutDemo()->where('payment_status','success')->sum('amount'),
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
                $q->withoutDemo()->where('payment_status', 'success');
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

        $data=$request->validate(['search'=>'nullable|string|max:120','status'=>'nullable|in:pending,approved,hidden','rating'=>'nullable|integer|between:1,5','max_rating'=>'nullable|integer|between:1,5','per_page'=>'sometimes|integer|between:1,100','page'=>'sometimes|integer|min:1']);
        $service=app(\App\Services\Admin\ReviewService::class);
        return response()->json(array_merge($service->getList($data)->toArray(),['stats'=>$service->stats()]));
    }

    public function updateReviewStatus(Request $request,int $id)
    {
        $this->checkAdmin($request);$data=$request->validate(['status'=>'required|in:pending,approved,hidden']);
        return response()->json(['message'=>'Đã cập nhật trạng thái đánh giá.','review'=>app(\App\Services\ReviewService::class)->moderate($id,$data['status'])]);
    }

    public function deleteReview(Request $request, $id)
    {
        $this->checkAdmin($request);

        app(\App\Services\ReviewService::class)->delete((int)$id);

        return response()->json(['message' => 'Đã xóa review']);
    }

    // ==========================================
    // BÁO CÁO DOANH THU
    // ==========================================

    public function revenue(Request $request)
    {
        $this->checkAdmin($request);
        return response()->json(app(\App\Services\Admin\RevenueService::class)->getSummary($request->only([
            'period', 'date_from', 'date_to', 'payment_scope', 'page',
        ])))->header('Cache-Control', 'no-store');
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

    public function updateDestination(Request $request, int $id): JsonResponse
    {
        if ($request->has('code')) app(\App\Support\ExperienceSchema::class)->ensureReady();
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', \Illuminate\Validation\Rule::unique('location', 'code')->ignore($id)],
            'name'      => 'sometimes|string|max:100',
            'region'    => 'sometimes|string|max:50',
            'country'   => 'nullable|string|max:100',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if (isset($validated['code'])) {
            $location = \App\Support\LocationIdentity::assignCode($id, $validated['code']);
            unset($validated['code']);
        }
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
