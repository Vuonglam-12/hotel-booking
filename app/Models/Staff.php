<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'staff';

    public $timestamps = false;

    protected $fillable = [
        'hotel_id',      // NULL = superadmin, có hotel_id = quản lý KS đó
        'name',
        'email',
        'phone',
        'password_hash',
        'role',          // superadmin / admin / manager / staff
        'created_at',
    ];

    protected $hidden = ['password_hash'];

    // Sanctum dùng password_hash thay vì password
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    // Quan hệ: staff quản lý KS nào
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    // Kiểm tra có phải superadmin không
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    // Kiểm tra có phải admin/manager không
    public function isAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'admin', 'manager']);
    }
}