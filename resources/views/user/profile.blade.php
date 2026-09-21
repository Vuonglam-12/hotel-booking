@extends('user.layouts.user')

@section('title', 'Hồ sơ của tôi')
@section('page_title', 'Hồ sơ của tôi')

@section('content')
    @include('user.profile.profile-section')
@endsection

@section('scripts')
    @include('user.profile.profile-script')
@endsection