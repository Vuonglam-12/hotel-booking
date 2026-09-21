<?php

namespace App\Services\Notification;

use App\Models\CustomerNotification as Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function getList(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return Notification::where('customer_id', $customerId)->latest()->paginate($perPage);
    }

    public function markRead(int $customerId, int $id): void
    {
        Notification::where('customer_id', $customerId)->findOrFail($id)->markAsRead();
    }

    public function markAllRead(int $customerId): void
    {
        Notification::where('customer_id', $customerId)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function unreadCount(int $customerId): int
    {
        return Notification::where('customer_id', $customerId)->whereNull('read_at')->count();
    }

    public function delete(int $customerId, int $id): void
    {
        Notification::where('customer_id', $customerId)->findOrFail($id)->delete();
    }

    public function deleteAll(int $customerId): void
    {
        Notification::where('customer_id', $customerId)->delete();
    }
}