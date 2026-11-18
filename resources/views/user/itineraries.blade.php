@extends('user.layouts.user')

@section('title', 'Lịch trình AI')
@section('body-class', 'aurel-itinerary-account')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/aurel-ai-chat.css') }}?v={{ filemtime(public_path('css/aurel-ai-chat.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/aurel-itinerary-planner.css') }}?v={{ filemtime(public_path('css/aurel-itinerary-planner.css')) }}">
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endsection

@section('content')
    @include('user.itineraries.planner-section')
@endsection

@section('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
    <script src="{{ asset('vendor/aurel-ai/photos.js') }}?v={{ filemtime(public_path('vendor/aurel-ai/photos.js')) }}" defer></script>
    <script src="{{ asset('vendor/aurel-ai/hours.js') }}?v={{ filemtime(public_path('vendor/aurel-ai/hours.js')) }}" defer></script>
    <script src="{{ asset('vendor/aurel-ai/planner.js') }}?v={{ filemtime(public_path('vendor/aurel-ai/planner.js')) }}" defer></script>
    <script src="{{ asset('vendor/aurel-ai/chat.js') }}?v={{ filemtime(public_path('vendor/aurel-ai/chat.js')) }}" defer></script>
@endsection
