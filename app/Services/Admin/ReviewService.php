<?php

namespace App\Services\Admin;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewService
{
    public function getList(array $filters): LengthAwarePaginator
    {
        return Review::with(['customer:id,name','hotel:id,name'])->orderByDesc('created_at')->orderByDesc('id')
            ->when(!empty($filters['status']),fn($q)=>$q->where('status',$filters['status']))
            ->when(!empty($filters['rating']),fn($q)=>$q->where('rating',$filters['rating']))
            ->when(!empty($filters['max_rating']),fn($q)=>$q->where('rating','<=',$filters['max_rating']))
            ->when(!empty($filters['search']),function($q)use($filters){
                $term='%'.$filters['search'].'%';
                $q->where(fn($q)=>$q->where('comment','like',$term)
                    ->orWhereHas('customer',fn($q)=>$q->where('name','like',$term))
                    ->orWhereHas('hotel',fn($q)=>$q->where('name','like',$term)));
            })->paginate($filters['per_page']??20);
    }

    public function stats(): array
    {
        $today=now('Asia/Ho_Chi_Minh')->startOfDay()->utc();
        return [
            'total'=>Review::count(),'avg_rating'=>(float)(Review::published()->avg('rating')??0),
            'today'=>Review::whereBetween('created_at',[$today,$today->copy()->addDay()])->count(),
            'pending'=>Review::where('status','pending')->count(),
        ];
    }

    public function delete(int $id): void { app(\App\Services\ReviewService::class)->delete($id); }
}
