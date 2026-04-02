<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * HÀM STORE: CHỊU TRÁCH NHIỆM XỬ LÝ KHI KHÁCH BẤM NÚT "ĐẶT PHÒNG"
     * Đây là hàm quan trọng nhất, gánh vác việc giữ chỗ và tạo đơn hàng.
     */
    public function store(Request $request)
    {
        // --- BƯỚC 1: KIỂM DUYỆT ĐẦU VÀO (VALIDATION) ---
        // Nhiệm vụ: Chặn lại những dữ liệu tào lao. Ví dụ khách cố tình nhập ngày đi trước ngày đến, 
        // hoặc nhập số lượng phòng là số âm. Nếu sai ở đây, Laravel tự động đá văng ra lỗi 422.
        $request->validate([
            'hotel_id'        => 'required|integer|exists:hotel,id', // Khách sạn phải tồn tại trong DB
            'room_type_id'    => 'required|integer|exists:room_type,id', // Loại phòng phải tồn tại
            'quantity'        => 'required|integer|min:1|max:10', // Đặt từ 1 đến 10 phòng
            'check_in'        => 'required|date|after_or_equal:today', // Ngày nhận phòng từ hôm nay trở đi
            'check_out'       => 'required|date|after:check_in', // Ngày trả phải sau ngày nhận
            'num_guests'      => 'required|integer|min:1', // Ít nhất 1 khách
            'special_request' => 'nullable|string|max:500', // Yêu cầu đặc biệt (không bắt buộc)
            'payment_method'  => 'nullable|in:vnpay,banking,cash', // Chỉ nhận 3 loại thanh toán này
        ]);

        // --- BƯỚC 2: CHUẨN BỊ DỮ LIỆU ---
        // Nhiệm vụ: Gom nhặt các thông tin cần thiết để tính toán
        $customerId    = auth('sanctum')->id(); // Lấy ID của khách đang đăng nhập
        $checkIn       = $request->check_in;
        $checkOut      = $request->check_out;
        // Tính toán khách ở bao nhiêu đêm để lát nữa nhân tiền
        $nights        = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        // Nếu không chọn phương thức thanh toán, mặc định cho xài VNPay
        $paymentMethod = $request->payment_method ?? 'vnpay'; 

        // --- BƯỚC 3: KIỂM TRA PHÒNG TRỐNG (AVAILABILITY CHECK) ---
        // Nhiệm vụ: Chọc vào bảng room_availability xem trong khoảng ngày khách chọn, 
        // khách sạn có còn đủ số lượng phòng mà khách muốn đặt không.
        $available = DB::table('room_availability')
            ->where('hotel_id',     $request->hotel_id)
            ->where('room_type_id', $request->room_type_id)
            ->whereBetween('date',  [$checkIn, Carbon::parse($checkOut)->subDay()])
            ->min('available_count'); // Lấy số phòng trống ít nhất trong các ngày đó

        // Nếu ngày đó chưa cấu hình phòng (null) hoặc số phòng trống ít hơn số phòng khách muốn đặt -> Báo lỗi ngay
        if (is_null($available) || $available < $request->quantity) {
            return response()->json([
                'message' => 'Không đủ phòng trống trong khoảng thời gian này',
            ], 422);
        }

        // Kéo giá gốc của loại phòng ra để tính tổng tiền
        $roomType   = RoomType::findOrFail($request->room_type_id);
        $totalPrice = $roomType->base_price * $nights * $request->quantity; // Công thức: Giá x Số đêm x Số phòng

        // --- BƯỚC 4: TẠO ĐƠN HÀNG VÀ CHỐT PHÒNG (DATABASE TRANSACTION) ---
        // Nhiệm vụ: Gói tất cả các thao tác DB vào 1 cục (Transaction). 
        // Rất quan trọng: Lỡ đang lưu mà cúp điện hay lỗi mạng, hệ thống tự động Rollback (phục hồi như cũ), không bị mất phòng oan.
        $booking = DB::transaction(function () use ($request, $customerId, $totalPrice, $roomType, $checkIn, $checkOut, $nights) {
            
            // 4.1 Tạo đơn đặt phòng chính thức (Trạng thái Pending)
            $booking = Booking::create([
                'customer_id'     => $customerId,
                'hotel_id'        => $request->hotel_id,
                'check_in'        => $checkIn,
                'check_out'       => $checkOut,
                'num_guests'      => $request->num_guests,
                'total_price'     => $totalPrice,
                'status'          => 'pending', // Khóa lại, chờ thanh toán
                'expires_at'      => now()->addMinutes(15), // Cho khách 15 phút để thao tác thanh toán
                'special_request' => $request->special_request,
            ]);

            // 4.2 Lưu chi tiết đơn: Khách đặt loại phòng nào và CHỐT GIÁ NGAY LÚC NÀY
            // Chốt giá (price_at_booking) để lỡ ngày mai KS tăng giá thì khách vẫn đóng theo giá cũ
            BookingRoom::create([
                'booking_id'       => $booking->id,
                'room_type_id'     => $request->room_type_id,
                'price_at_booking' => $roomType->base_price,
                'nights'           => $nights,
                'quantity'         => $request->quantity,
            ]);

            // 4.3 Trừ dần số phòng trống trong kho (Giữ chỗ cho khách)
            $dates = CarbonPeriod::create($checkIn, Carbon::parse($checkOut)->subDay());
            foreach ($dates as $date) {
                // Trừ số lượng phòng trống đi (available_count)
                DB::table('room_availability')
                    ->where('hotel_id',     $request->hotel_id)
                    ->where('room_type_id', $request->room_type_id)
                    ->where('date',         $date->format('Y-m-d'))
                    ->decrement('available_count', $request->quantity);

                // Tăng số lượng phòng đã được đặt lên (booked_count)
                DB::table('room_availability')
                    ->where('hotel_id',     $request->hotel_id)
                    ->where('room_type_id', $request->room_type_id)
                    ->where('date',         $date->format('Y-m-d'))
                    ->increment('booked_count', $request->quantity);
            }

            return $booking; // Thành công thì trả ra cục thông tin đặt phòng
        });


        // --- BƯỚC 5: XỬ LÝ THANH TOÁN THỦ CÔNG (TIỀN MẶT HOẶC CHUYỂN KHOẢN TRỰC TIẾP) ---
        // Nhiệm vụ: Nếu khách không dùng VNPay (có luồng đi riêng), thì mình tạo trước cho khách 
        // một lịch sử thanh toán là 'pending' (chờ lễ tân xác nhận), đồng thời xuất hóa đơn và gửi email luôn.
        if (in_array($paymentMethod, ['banking', 'cash'])) {
            try {
                // Tạo bảng ghi thanh toán
                $payment = Payment::create([
                    'booking_id'     => $booking->id,
                    'amount'         => $totalPrice,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'pending', // Chờ tiền ting ting vào tài khoản hoặc nhận tiền mặt
                    // Gắn chữ MANUAL để phân biệt với mã giao dịch tự động của VNPay
                    'transaction_id' => 'MANUAL_' . time() . '_' . $booking->id,
                ]);

                // Gọi hàm bên InvoiceController để in cái hóa đơn cho khách
                InvoiceController::createFromPayment($booking, $payment);

                // Kéo email của khách ra và gửi Mail xác nhận giữ chỗ
                $booking->load('customer');
                Mail::to($booking->customer->email)
                    ->send(new \App\Mail\BookingConfirmed($booking));

            } catch (\Exception $e) {
                // Nếu gửi mail bị lỗi mạng, không làm sập chức năng đặt phòng. Ghi log lại để Admin sửa.
                Log::error('BookingController@store: Lỗi tạo invoice/email cho ' . $paymentMethod, [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // --- BƯỚC 6: TRẢ KẾT QUẢ VỀ CHO GIAO DIỆN (FRONTEND) ---
        // Nhiệm vụ: Format lại tiền tệ cho đẹp (thêm dấu phẩy, chữ VND) để báo cho Frontend hiện thông báo thành công.
        $bookingData = $booking->load(['bookingRooms.roomType', 'hotel'])->toArray();
        $bookingData['total_price'] = number_format($booking->total_price, 0, '.', ',') . ' VND';
        foreach ($bookingData['booking_rooms'] as &$br) {
            $br['price_at_booking']        = number_format($br['price_at_booking'], 0, '.', ',') . ' VND';
            $br['room_type']['base_price'] = number_format($br['room_type']['base_price'], 0, '.', ',') . ' VND';
        }

        return response()->json([
            'message'        => 'Đặt phòng thành công' . ($paymentMethod === 'vnpay' ? ', vui lòng thanh toán trong 15 phút' : ''),
            'booking'        => $bookingData,
            'payment_method' => $paymentMethod,
        ], 201); // 201 là mã HTTP báo hiệu Đã tạo mới thành công
    }

    /**
     * HÀM MYBOOKINGS: XEM LỊCH SỬ ĐẶT PHÒNG CỦA MÌNH
     * Khách bấm vào profile để xem danh sách các đơn đã đặt.
     */
    public function myBookings(Request $request)
    {
        // Lấy tất cả đơn hàng của cái ông đang đăng nhập (auth('sanctum')->id()), 
        // kèm theo tên KS, loại phòng. Sắp xếp đơn mới nhất nổi lên đầu (desc).
        $bookings = Booking::with(['hotel', 'bookingRooms.roomType', 'payment'])
            ->where('customer_id', auth('sanctum')->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10); // Phân trang, 10 đơn 1 trang

        return response()->json($bookings);
    }

    /**
     * HÀM SHOW: XEM CHI TIẾT 1 ĐƠN HÀNG
     * Khách bấm vào nút "Xem chi tiết" của 1 cái đơn cụ thể trong lịch sử.
     */
    public function show($id)
    {
        $booking = Booking::with(['hotel', 'bookingRooms.roomType', 'payment'])
            ->where('customer_id', auth('sanctum')->id()) // Phải đúng ông đó mới xem được đơn của ổng
            ->findOrFail($id); // Tìm đúng mã ID đơn hàng, không thấy thì quăng lỗi 404

        return response()->json($booking);
    }

    /**
     * HÀM CANCEL: KHÁCH HÀNG TỰ HỦY ĐƠN
     * Xử lý luồng hủy phòng và cộng trả lại số lượng phòng trống cho hệ thống.
     */
    public function cancel($id)
    {
        $booking = Booking::with('bookingRooms')
            ->where('customer_id', auth('sanctum')->id()) // Đảm bảo đúng người đang thao tác
            ->findOrFail($id);

        // --- BƯỚC 1: KIỂM TRA ĐIỀU KIỆN ĐƯỢC PHÉP HỦY ---
        // Chỉ cho hủy khi đơn hàng đang ở trạng thái 'pending' hoặc 'confirmed'
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Không thể huỷ booking ở trạng thái ' . $booking->status,
            ], 422);
        }

        // Chặn không cho hủy sát giờ (Dưới 24 tiếng trước khi check-in thì cấm)
        $hoursUntilCheckIn = now()->diffInHours($booking->check_in, false);
        if ($hoursUntilCheckIn < 24) {
            return response()->json([
                'message' => 'Không thể huỷ booking trong vòng 24 giờ trước ngày nhận phòng',
            ], 422);
        }

        // --- BƯỚC 2: TIẾN HÀNH HỦY VÀ HOÀN TRẢ PHÒNG LẠI CHO KHO (Transaction) ---
        DB::transaction(function () use ($booking) {
            
            // Cập nhật trạng thái đơn thành 'Đã hủy'
            $booking->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'customer',
            ]);

            // Lấy ra số lượng phòng và loại phòng mà khách đã đặt trước đó
            $quantity   = $booking->bookingRooms->sum('quantity');
            $roomTypeId = $booking->bookingRooms->first()->room_type_id;
            $dates      = CarbonPeriod::create(
                $booking->check_in->format('Y-m-d'),
                $booking->check_out->copy()->subDay()->format('Y-m-d')
            );

            // Chạy vòng lặp y như lúc đặt, nhưng lần này đi CỘNG ngược lại
            foreach ($dates as $date) {
                // Tăng số phòng trống lên để khách khác mua
                DB::table('room_availability')
                    ->where('hotel_id',     $booking->hotel_id)
                    ->where('room_type_id', $roomTypeId)
                    ->where('date',         $date->format('Y-m-d'))
                    ->increment('available_count', $quantity);

                // Giảm số phòng đã đặt xuống
                DB::table('room_availability')
                    ->where('hotel_id',     $booking->hotel_id)
                    ->where('room_type_id', $roomTypeId)
                    ->where('date',         $date->format('Y-m-d'))
                    ->decrement('booked_count', $quantity);
            }
        });

        // --- BƯỚC 3: TRẢ KẾT QUẢ CHO FRONTEND ---
        // Load lại dữ liệu mới nhất (fresh), format lại tiền và báo thành công
        $bookingData = $booking->fresh(['bookingRooms.roomType', 'hotel'])->toArray();
        $bookingData['total_price'] = number_format($booking->total_price, 0, '.', ',') . ' VND';
        foreach ($bookingData['booking_rooms'] as &$br) {
            $br['price_at_booking']        = number_format($br['price_at_booking'], 0, '.', ',') . ' VND';
            $br['room_type']['base_price'] = number_format($br['room_type']['base_price'], 0, '.', ',') . ' VND';
        }

        return response()->json([
            'message' => 'Huỷ booking thành công',
            'booking' => $bookingData,
        ]);
    }
}