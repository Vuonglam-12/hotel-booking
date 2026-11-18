@extends('user.layouts.user')
@section('title', 'Thông tin cá nhân')
@section('content')
@include('user.profile.profile-section')
@endsection
@section('scripts')
@include('user.profile.profile-script')
@include('user.profile.wishlist-script')
@endsection
