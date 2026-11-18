<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use App\Support\AccommodationImage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RoomTypeAdminServiceV2
{
    public function save(string $connection,int $hotelId,?int $typeId,array $input,array $imageChanges=[],array $files=[]): array
    {
        $s=RoomTypeV2Inventory::open($connection); $required=$typeId===null?'required':'sometimes|required';
        if (array_key_exists('hotel_id',$input)) throw ValidationException::withMessages(['hotel_id'=>'Ownership lấy từ URL và không thể chỉnh, kể cả null.']);
        $data=Validator::make($input,[
            'hotel_id'=>'prohibited','name'=>$required.'|string|max:100','capacity'=>$required.'|integer|min:1|max:2147483647',
            'base_price'=>[...explode('|',$required),function ($key,$v,$fail) { if ((!is_string($v)&&!is_int($v)) || !preg_match('/^\d{1,10}(\.\d{1,2})?$/D',(string)$v)) $fail(\App\Support\RoomTypeValidationMessages::all()['base_price.regex']); }],
            'total_rooms'=>$required.'|integer|min:0|max:2147483647','status'=>$required.'|in:active,inactive',
            'bed_type'=>'nullable|string|max:50','description'=>['nullable','string',function ($key,$v,$fail) { if (strlen($v)>65535) $fail('Mô tả vượt TEXT schema.'); }],
        ], \App\Support\RoomTypeValidationMessages::all())->validate();
        if (isset($data['base_price'])) { [$whole,$fraction]=array_pad(explode('.',(string)$data['base_price']),2,''); $data['base_price']=$whole.'.'.str_pad($fraction,2,'0'); }
        $images=app(AdminAccommodationImageService::class);
        return $images->atomic($s->db,function ($uploads) use ($s,$hotelId,$typeId,$data,$images,$imageChanges,$files) {
            $s->hotel($hotelId,true); $db=$s->db;
            if ($typeId!==null) {
                $type=$s->types($hotelId,[$typeId],true)[$typeId];
                if (array_key_exists('total_rooms',$data)) {
                    $a=$s->inspect($type,now()->toDateString(),'9999-12-31',null,true);
                    if ($data['total_rooms']<$a['booked_rooms']) throw ValidationException::withMessages(['total_rooms'=>'Tổng tồn thấp hơn peak đã cam kết.']);
                }
                if ($data) $db->table('room_type')->where('id',$typeId)->update($data);
            } else $typeId=$db->table('room_type')->insertGetId(['hotel_id'=>$hotelId,...$data]);
            $images->apply($db,'room_type_image','room_type_id',$typeId,$imageChanges,$files,$uploads);
            return (array)$db->table('room_type')->where('id',$typeId)->first();
        });
    }

    public function gallery(string $connection,int $hotelId,int $typeId,?array $image=null,?int $deleteId=null): array
    {
        $s=RoomTypeV2Inventory::open($connection);
        return $s->db->transaction(function () use ($s,$hotelId,$typeId,$image,$deleteId) {
            $s->hotel($hotelId,true); $s->types($hotelId,[$typeId],true); $db=$s->db;
            if ($deleteId!==null) {
                $found=$db->table('room_type_image')->where('id',$deleteId)->where('room_type_id',$typeId)->first();
                if (!$found) throw ValidationException::withMessages(['image_id'=>'Ảnh không thuộc loại phòng.']);
                $db->table('room_type_image')->where('id',$found->id)->delete(); // Link only; never delete asset or RoomImage.
            }
            if ($image!==null) {
                $data=Validator::make($image,['image_url'=>'required|string|max:500','is_primary'=>'sometimes|boolean'])->validate();
                if (!AccommodationImage::localUrl($data['image_url'])) throw ValidationException::withMessages(['image_url'=>'Chỉ liên kết asset local hiện có, hợp lệ.']);
                if (!empty($data['is_primary'])) $db->table('room_type_image')->where('room_type_id',$typeId)->update(['is_primary'=>false]);
                $existing=$db->table('room_type_image')->where('room_type_id',$typeId)->where('image_url',$data['image_url'])->first();
                if ($existing) $db->table('room_type_image')->where('id',$existing->id)->update(['is_primary'=>(bool)($data['is_primary'] ?? $existing->is_primary)]);
                else $db->table('room_type_image')->insert(['room_type_id'=>$typeId,'image_url'=>$data['image_url'],'is_primary'=>(bool)($data['is_primary'] ?? false)]);
            }
            return $db->table('room_type_image')->where('room_type_id',$typeId)->orderByDesc('is_primary')->orderBy('id')->get()->map(fn($r)=>(array)$r)->all();
        });
    }
}
