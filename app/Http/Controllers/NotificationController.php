<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotification as Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * NotificationController
 *
 * Controller xử lý các hành động liên quan đến thông báo của khách hàng.
 * Mỗi phương thức chỉ thao tác dữ liệu thông báo của user đang đăng nhập,
 * đảm bảo không thể truy cập hoặc sửa thông báo của user khác.
 */
class NotificationController extends Controller
{
    protected $table = 'customer_notifications';

    /**
     * Lấy danh sách thông báo của user hiện tại.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * Trả về danh sách phân trang notifications theo customer_id.
     * Nếu user chưa đăng nhập, sẽ trả về lỗi 401.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        $notifications = Notification::where('customer_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($notifications);
    }

    /**
     * Đánh dấu một thông báo đã đọc.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     *
     * Chỉ cập nhật thông báo thuộc customer_id của user hiện tại.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('customer_id', $request->user()->id)->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Đã đánh dấu đã đọc']);
    }

    /**
     * Đánh dấu tất cả thông báo chưa đọc là đã đọc.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * Cập nhật tất cả record có read_at = null cho customer hiện tại.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('customer_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Đã đánh dấu tất cả là đã đọc']);
    }

    /**
     * Lấy số lượng thông báo chưa đọc.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('customer_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Xóa một thông báo cụ thể.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     *
     * Hành động xóa chỉ tác động đến thông báo của user hiện tại.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('customer_id', $request->user()->id)->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Đã xóa thông báo']);
    }

    /**
     * Xóa sạch tất cả thông báo của user hiện tại.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAll(Request $request): JsonResponse
    {
        Notification::where('customer_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Đã xóa tất cả thông báo']);
    }
}
