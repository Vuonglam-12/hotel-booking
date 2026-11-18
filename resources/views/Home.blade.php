@extends('layout')

@section('title', 'Khám phá khách sạn')
@section('body-class', 'hv-home-page')

@section('content')
    <div class="hv-home hv-ui" id="homeExplore">
        @include('partials.home.all')
        @include('partials.home.chatbot-section')
    </div>
@endsection

@push('scripts')
    @include('partials.home.script')
    @include('partials.home.chatbot-script')
@endpush
