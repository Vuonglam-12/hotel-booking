<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Hotel-owned metadata over the original physical-room inventory. No V2 activation or ID remap. */
class HotelRoomTypeService
{
    public static function enabled(): bool
    {
        if (config('roomtype-v2.production_manifest') !== null || config('roomtype-v2.prepared_routes',false)) return false;
        return Schema::hasTable('hotel_room_type') && Schema::hasTable('hotel_room_type_image') && Schema::hasColumn('room', 'catalog_retired');
    }

    public function record(int $hotel, int $type): HotelRoomType
    {
        Hotel::findOrFail($hotel);
        $record = HotelRoomType::where('hotel_id', $hotel)->where('room_type_id', $type)->where('status', '!=', 'archived')->first();
        abort_unless($record,404,'Loại phòng không thuộc khách sạn đang chọn hoặc không tồn tại.');
        return $record;
    }

    public function effective(int $hotel, int $type): RoomType
    {
        $legacy = RoomType::findOrFail($type);
        if (!self::enabled()) return $legacy;
        $record = HotelRoomType::where('hotel_id', $hotel)->where('room_type_id', $type)->first();
        if (!$record) return $legacy;
        $effective = clone $legacy;
        foreach (['name','description','base_price','capacity','bed_type','status','amenities'] as $key) $effective->setAttribute($key, $record->$key);
        return $effective;
    }

    public function index(int $hotel): array
    {
        Hotel::findOrFail($hotel);
        return HotelRoomType::where('hotel_id', $hotel)->where('status', '!=', 'archived')->with('images')->orderBy('room_type_id')->get()
            ->map(fn($record) => $this->dto($record))->all();
    }

    public function dto(HotelRoomType $record): array
    {
        $images = $record->images;
        $cover = $images->firstWhere('is_primary', 1) ?? $images->first();
        $fallback = null;
        if (!$cover && !$record->images_configured) $fallback = Room::where('hotel_id', $record->hotel_id)->where('room_type_id', $record->room_type_id)->managed()->with('images')->orderBy('id')->get()
            ->flatMap(fn($room) => $room->gallery_image_urls)->first();
        return [...$record->only(['hotel_id','name','description','base_price','capacity','bed_type','status','amenities']),
            'id' => $record->room_type_id, 'hotel_room_type_id' => $record->id,
            'created_at' => RoomType::whereKey($record->room_type_id)->value('created_at'),
            'total_rooms' => Room::where('hotel_id', $record->hotel_id)->where('room_type_id', $record->room_type_id)->managed()->count(),
            'cover_image_url' => $cover ? \App\Support\AccommodationImage::cover($images)
                : ($record->images_configured ? \App\Support\AccommodationImage::placeholder() : ($fallback ?? \App\Support\AccommodationImage::placeholder()))];
    }

    public function applyToRooms(Collection $rooms, bool $includeInactive = false): Collection
    {
        if (!self::enabled() || $rooms->isEmpty()) return $rooms;
        $records = HotelRoomType::whereIn('hotel_id', $rooms->pluck('hotel_id')->unique())
            ->whereIn('room_type_id', $rooms->pluck('room_type_id')->unique())->with('images')->get()
            ->keyBy(fn($record) => $record->hotel_id . ':' . $record->room_type_id);
        return $rooms->filter(function ($room) use ($records, $includeInactive) {
            $record = $records->get($room->hotel_id . ':' . $room->room_type_id);
            if (!$includeInactive && ($room->catalog_retired || in_array($record?->status, ['inactive', 'archived'], true))) return false;
            if (!$record) return true;
            $type = clone $room->roomType;
            foreach (['name','description','base_price','capacity','bed_type','status','amenities'] as $key) $type->setAttribute($key, $record->$key);
            $room->setRelation('roomType', $type);
            if ($record->images_configured || $record->images->isNotEmpty()) $room->setRelation('images', $record->images);
            return true;
        })->values();
    }

    public function applyToBooking(\App\Models\Booking $booking): \App\Models\Booking
    {
        if (self::enabled()) foreach ($booking->bookingRooms as $line) $line->setRelation('roomType',$this->effective($booking->hotel_id,$line->room_type_id));
        return $booking;
    }

    private function validate(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';
        if (isset($input['amenities']) && is_array($input['amenities'])) $input['amenities'] = array_map(
            fn($name) => is_string($name) ? trim(preg_replace('/\s+/u', ' ', $name)) : $name, $input['amenities']);
        return Validator::make($input, [
            'hotel_id'=>'prohibited','room_type_id'=>'prohibited','inventory_model'=>'sometimes|in:scoped,legacy',
            'name'=>$required.'|string|max:100', 'base_price'=>[...explode('|',$required),'regex:/^\d{1,10}(\.\d{1,2})?$/D'],
            'capacity'=>$required.'|integer|min:1|max:10', 'bed_type'=>'sometimes|nullable|string|max:50',
            'description'=>['sometimes','nullable','string',function($attribute,$value,$fail){if(strlen($value)>65535)$fail('Mô tả tối đa 65.535 byte.');}], 'amenities'=>'sometimes|array|max:50',
            'amenities.*'=>'required|string|max:100|distinct:ignore_case', 'status'=>'sometimes|required|in:active,inactive',
            'total_rooms'=>($creating?'prohibited':'sometimes|required').'|integer|min:0|max:1000',
            'room_count'=>($creating?'required':'prohibited').'|integer|min:1|max:100', 'floor_start'=>'sometimes|nullable|integer|min:1|max:99',
        ], [
            ...\App\Support\RoomTypeValidationMessages::all(),
            'name.required'=>'Vui lòng nhập tên loại phòng.', 'name.max'=>'Tên loại phòng tối đa 100 ký tự.',
            'base_price.regex'=>'Giá phải không âm, tối đa 10 chữ số nguyên và 2 chữ số thập phân.',
            'capacity.integer'=>'Sức chứa phải là số nguyên.', 'capacity.min'=>'Sức chứa tối thiểu 1 khách.', 'capacity.max'=>'Sức chứa tối đa 10 khách mỗi phòng.',
            'total_rooms.integer'=>'Tổng số phòng phải là số nguyên.', 'total_rooms.min'=>'Tổng số phòng không được âm.', 'total_rooms.max'=>'Tổng số phòng tối đa 1.000.',
            'room_count.min'=>'Cần tạo ít nhất 1 phòng.', 'room_count.max'=>'Mỗi lần tạo tối đa 100 phòng.',
            'amenities.array'=>'Tiện nghi phải là một danh sách.', 'amenities.*.distinct'=>'Tiện nghi không được trùng nhau.',
            'status.in'=>'Trạng thái chỉ có thể là đang bán hoặc ngừng bán.',
        ])->validate();
    }

    public function create(int $hotelId, Request $request): array
    {
        $this->normalizeMultipart($request);
        $data = $this->validate($request->all(), true);
        $images = app(AdminAccommodationImageService::class);
        [$changes,$files] = $images->input($request);
        return $images->atomic(DB::connection(), function ($uploads) use ($hotelId,$data,$images,$changes,$files) {
            Hotel::whereKey($hotelId)->lockForUpdate()->firstOrFail();
            $legacy = RoomType::create(\Illuminate\Support\Arr::only($data, ['name','description','base_price','capacity','bed_type']));
            $record = HotelRoomType::create(['hotel_id'=>$hotelId,'room_type_id'=>$legacy->id,
                ...\Illuminate\Support\Arr::only($data,['name','description','base_price','capacity','bed_type']),
                'amenities'=>$data['amenities']??[], 'status'=>$data['status']??'active']);
            $this->addRooms($record, (int)$data['room_count'], (int)($data['floor_start']??1));
            $images->apply(DB::connection(),'hotel_room_type_image','hotel_room_type_id',$record->id,$changes,$files,$uploads);
            if ($changes || $files) $record->update(['images_configured'=>true]);
            return ['room_type'=>$this->dto($record->fresh()), 'rooms_created'=>(int)$data['room_count']];
        });
    }

    public function save(int $hotelId, int $typeId, Request $request): array
    {
        $this->normalizeMultipart($request);
        $data = $this->validate($request->all(), false);
        $images = app(AdminAccommodationImageService::class);
        [$changes,$files] = $images->input($request);
        $result = $images->atomic(DB::connection(), function ($uploads) use ($hotelId,$typeId,$data,$images,$changes,$files) {
            Hotel::whereKey($hotelId)->lockForUpdate()->firstOrFail();
            $record = HotelRoomType::where('hotel_id',$hotelId)->where('room_type_id',$typeId)->where('status','!=','archived')->lockForUpdate()->first();
            abort_unless($record,404,'Loại phòng không thuộc khách sạn đang chọn hoặc không tồn tại.');
            $capacity = (int)($data['capacity']??$record->capacity);
            if ($capacity < $record->capacity) $this->validateCommittedCapacity($record,$capacity);
            if (isset($data['total_rooms'])) $this->resize($record,(int)$data['total_rooms'],(int)($data['floor_start']??1));
            $rooms = Room::where('hotel_id',$hotelId)->where('room_type_id',$typeId);
            // Update physical prices/capacities only on an intentional value change; preserve history snapshots.
            if (isset($data['base_price']) && $this->money((string)$data['base_price']) !== $record->base_price) $rooms->update(['price'=>$data['base_price']]);
            if (isset($data['capacity']) && $capacity !== $record->capacity) $rooms->update(['capacity'=>$capacity]);
            $record->fill(\Illuminate\Support\Arr::only($data,['name','description','base_price','capacity','bed_type','status','amenities']))->save();
            $images->apply(DB::connection(),'hotel_room_type_image','hotel_room_type_id',$record->id,$changes,$files,$uploads);
            if ($changes || $files) $record->update(['images_configured'=>true]);
            return $this->dto($record->fresh());
        });
        $images->cleanup();
        return $result;
    }

    private function money(string $amount): string
    {
        [$whole,$fraction] = array_pad(explode('.',$amount),2,'');
        return (ltrim($whole,'0') ?: '0') . '.' . str_pad($fraction,2,'0');
    }

    private function normalizeMultipart(Request $request): void
    {
        if ($request->input('amenities')==='[]' && str_starts_with($request->header('Content-Type',''),'multipart/form-data')) $request->merge(['amenities'=>[]]);
    }

    private function validateCommittedCapacity(HotelRoomType $record, int $capacity): void
    {
        $bookings = DB::table('booking')->where('hotel_id',$record->hotel_id)->whereNotIn('status',['cancelled','expired'])
            ->where('check_out','>',now()->toDateString())->whereExists(fn($q)=>$q->selectRaw('1')->from('booking_room')->whereColumn('booking_room.booking_id','booking.id')->where('room_type_id',$record->room_type_id))->get(['id','num_guests']);
        foreach ($bookings as $booking) {
            $lines = DB::table('booking_room')->where('booking_id',$booking->id)->get();
            $seats = 0;
            foreach ($lines->groupBy('room_type_id') as $type => $group) {
                $quantity = (int)$group->whereNull('room_id')->sum('quantity') + $group->whereNotNull('room_id')->groupBy('room_id')->sum(fn($rows)=>(int)$rows->max('quantity'));
                $seats += $quantity * ((int)$type === $record->room_type_id ? $capacity : $this->effective($record->hotel_id,(int)$type)->capacity);
            }
            if ($seats < $booking->num_guests) throw ValidationException::withMessages(['capacity'=>'Sức chứa mới không đáp ứng đơn đang tồn tại #' . $booking->id . '.']);
        }
    }

    private function resize(HotelRoomType $record, int $total, int $floor): void
    {
        $query = Room::where('hotel_id',$record->hotel_id)->where('room_type_id',$record->room_type_id);
        $current = (clone $query)->managed()->lockForUpdate()->get();
        $delta = $total - $current->count();
        if ($delta >= 0) {
            $retired = (clone $query)->where('catalog_retired',true)->orderBy('id')->limit($delta)->pluck('id');
            if ($retired->isNotEmpty()) (clone $query)->whereIn('id',$retired)->update(['catalog_retired'=>false]);
            $this->addRooms($record,$delta-$retired->count(),$floor);
            return;
        }
        $peak = app(RoomAvailabilityService::class)->forType($record->hotel_id,$record->room_type_id,now()->toDateString(),'9999-12-31')['booked_rooms'];
        if ($current->where('status','available')->count()+$delta < $peak) throw ValidationException::withMessages(['total_rooms'=>'Số phòng mới thấp hơn lượng phòng đã cam kết cho các đơn đang tồn tại.']);
        $safe = (clone $query)->managed()->where('status','available')->whereDoesntHave('bookingLines')->orderByDesc('id')->limit(-$delta)->pluck('id');
        if ($safe->count() !== -$delta) throw ValidationException::withMessages(['total_rooms'=>'Không đủ phòng chưa có liên kết booking để giảm số lượng an toàn. Không phòng nào bị xóa.']);
        (clone $query)->whereIn('id',$safe)->update(['catalog_retired'=>true]);
    }

    private function addRooms(HotelRoomType $record, int $count, int $floorStart): void
    {
        $used = array_fill_keys(Room::where('hotel_id',$record->hotel_id)->pluck('room_number')->all(),true);
        $candidate = 1;
        for ($i=0;$i<$count;$i++) {
            do { $floor=$floorStart+intdiv($candidate-1,10);$number=$floor.str_pad($candidate,2,'0',STR_PAD_LEFT);$candidate++; } while(isset($used[$number]));
            if ($floor>99) throw ValidationException::withMessages(['floor_start'=>'Không còn tầng hợp lệ để tạo số phòng.']);
            Room::create(['hotel_id'=>$record->hotel_id,'room_type_id'=>$record->room_type_id,'room_number'=>$number,'floor'=>$floor,
                'price'=>$record->base_price,'capacity'=>$record->capacity,'status'=>'available']);
            $used[$number]=true;
        }
    }

    public function gallery(int $hotel, int $type): array
    {
        $record = $this->record($hotel,$type);
        $images = app(AdminAccommodationImageService::class);
        $images->cleanup();
        return ['images'=>$images->gallery(DB::connection(),'hotel_room_type_image','hotel_room_type_id',$record->id), 'image_validation'=>$images::limits(), 'image_ordering'=>true];
    }

    public function delete(int $hotel, int $type): array
    {
        return DB::transaction(function () use ($hotel, $type) {
            // Booking creation takes this same hotel lock before checking sellable stock.
            Hotel::whereKey($hotel)->lockForUpdate()->firstOrFail();
            $record = $this->record($hotel, $type);
            $record = HotelRoomType::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $rooms = Room::where('hotel_id', $hotel)->where('room_type_id', $type)->lockForUpdate()->get();
            $bookings = DB::table('booking')->whereExists(function ($query) use ($hotel, $type, $rooms) {
                $query->selectRaw('1')->from('booking_room')->whereColumn('booking_room.booking_id', 'booking.id')
                    ->where(function ($links) use ($hotel, $type, $rooms) {
                        $links->where(fn($pair) => $pair->where('booking.hotel_id', $hotel)->where('booking_room.room_type_id', $type));
                        if ($rooms->isNotEmpty()) $links->orWhereIn('booking_room.room_id', $rooms->pluck('id'));
                    });
            })->lockForUpdate()->get();
            if ($bookings->contains(fn($booking) => !in_array($booking->status, ['completed', 'cancelled', 'expired'], true))) {
                abort(409, 'Loại phòng có đặt phòng chưa hoàn tất. Hãy dùng trạng thái Ngừng bán; chỉ xóa sau khi xử lý xong các đơn.');
            }
            if ($rooms->contains(fn($room) => in_array($room->status, ['locked', 'occupied', 'booked'], true))) {
                abort(409, 'Loại phòng còn phòng vật lý đang sử dụng hoặc giữ chỗ. Hãy ngừng bán và xử lý các phòng trước khi xóa.');
            }
            if (Schema::hasTable('room_availability') && DB::table('room_availability')->where('hotel_id', $hotel)->where('room_type_id', $type)
                ->where('date', '>=', now()->toDateString())->where('booked_count', '>', 0)->lockForUpdate()->exists()) {
                abort(409, 'Loại phòng còn tồn phòng đã giữ trong lịch. Hãy dùng trạng thái Ngừng bán và kiểm tra tồn phòng trước khi xóa.');
            }
            // Retain numbered rooms, image links, snapshots and shared legacy IDs. No cascade deletion.
            if ($bookings->isNotEmpty() || $rooms->isNotEmpty()) {
                $record->update(['status' => 'archived']);
                Room::whereIn('id', $rooms->pluck('id'))->update(['catalog_retired' => true]);
                if (Schema::hasTable('room_availability')) DB::table('room_availability')->where('hotel_id', $hotel)->where('room_type_id', $type)
                    ->where('date', '>=', now()->toDateString())->update(['total_rooms' => 0, 'available_count' => 0]);
                return ['archived' => true, 'message' => 'Đã xóa loại phòng khỏi danh sách bán và lưu trữ các dữ liệu liên quan.'];
            }
            // An unused, empty catalog entry can be removed without touching the global RoomType.
            if (Schema::hasTable('room_availability')) DB::table('room_availability')->where('hotel_id', $hotel)->where('room_type_id', $type)->delete();
            DB::table('hotel_room_type_image')->where('hotel_room_type_id', $record->id)->delete();
            $record->delete();
            return ['archived' => false, 'message' => 'Đã xóa loại phòng chưa sử dụng.'];
        });
    }
}
