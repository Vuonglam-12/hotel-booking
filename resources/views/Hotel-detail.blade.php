@extends('layout')

@section('title', $hotel->name)
@section('body-class', 'hv-detail-page')

@php
    // A customer chooses inventory by type, never a physical room number.
    $detailRoomTypes = $hotel->rooms->groupBy('room_type_id')->map(function ($rooms, $id) {
        $type = $rooms->first()->roomType;
        $images = $rooms->flatMap(fn($room) => $room->gallery_image_urls)->unique()->values()->all();
        return [
            'id' => (int) $id,
            'name' => $type?->name ?? 'Loại phòng',
            'description' => $type?->description,
            'capacity' => $type?->capacity,
            'bed_type' => $type?->bed_type,
            'area' => null,
            'amenities' => $type?->amenities ?? [],
            'policies' => [],
            'price' => $type ? (float) $type->base_price : null,
            'images' => $images,
            'cover' => $rooms->first()->cover_image_url !== \App\Support\AccommodationImage::placeholder() ? $rooms->first()->cover_image_url : ($images[0] ?? \App\Support\AccommodationImage::placeholder()),
        ];
    })->values()->all();
@endphp

@section('content')

    {{-- Detail page styles --}}
    @include('partials.hoteldetail.globals-styles')
    @include('partials.hoteldetail.reviews-styles')

    <div class="hv-hotel-detail hv-ui">
    {{-- Hotel identity --}}
    @include('partials.hoteldetail.hero-section')

    {{-- Photo gallery --}}
    @include('partials.hoteldetail.gallery-section')

    <div class="hv-detail-container hv-detail-content">
        <div class="hv-detail-layout">
            <div class="hv-detail-sections">
                @include('partials.hoteldetail.info-section')
                @include('partials.hoteldetail.rooms-section')
                @include('partials.hoteldetail.amenities-section')
                @include('partials.hoteldetail.reviews-section')
                @include('partials.hoteldetail.location-section')
            </div>

            <aside class="hv-detail-sidebar">
                @include('partials.hoteldetail.sidebar-section')
            </aside>
        </div>

        @include('partials.hoteldetail.similar-section')
    </div>

    {{-- 4. Các thành phần ẩn --}}
    @include('partials.hoteldetail.modals-section')
    @include('partials.hoteldetail.room-detail-modal')
    </div>
@endsection

@push('scripts')
    {{-- THỨ TỰ SCRIPT: Biến toàn cục nạp trước, logic nạp sau --}}
    @include('partials.hoteldetail.globals-script')
    @include('partials.hoteldetail.dialog-script')
    @include('partials.hoteldetail.gallery-script')
    @include('partials.hoteldetail.rooms-script')
    @include('partials.hoteldetail.sidebar-script')
    @include('partials.hoteldetail.modals-script')
    @include('partials.hoteldetail.reviews-script')
@endpush
