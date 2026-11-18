@extends('layout')

@section('title', $hotel->name)
@section('body-class', 'hv-detail-page')

{{-- Prepared V2 controller supplies owned RoomType DTOs; no physical room relation. --}}

@section('content')

    {{-- Detail page styles --}}
    @include('partials.hoteldetail.globals-styles')

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
                @include('partials.hoteldetail.policies-section')
                @include('partials.hoteldetail.reviews-section')
                @include('partials.hoteldetail.location-section')
            </div>

            <aside class="hv-detail-sidebar">
                @include('partials.hoteldetail.sidebar-section')
            </aside>
        </div>

        @include('prepared-v2.similar-section')
    </div>

    {{-- 4. Các thành phần ẩn --}}
    @include('partials.hoteldetail.room-detail-modal')
    @include('prepared-v2.checkout-staging')
    </div>
@endsection

@push('scripts')
    <script>window.AUREL_V2_STAGING_CHECKOUT=@json(\App\Support\RoomTypeV2CheckoutGate::staging());</script>
    {{-- THỨ TỰ SCRIPT: Biến toàn cục nạp trước, logic nạp sau --}}
    @include('partials.hoteldetail.globals-script')
    @include('partials.hoteldetail.dialog-script')
    @include('partials.hoteldetail.gallery-script')
    @include('partials.hoteldetail.rooms-script')
    @include('prepared-v2.sidebar-script')
    @include('prepared-v2.checkout-staging-script')
@endpush
