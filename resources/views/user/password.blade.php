@extends('user.layouts.user')

@section('title', 'Đổi mật khẩu')
@section('page_title', 'Đổi mật khẩu')

@section('content')
    @include('user.password.password-section')
@endsection

@section('scripts')
    @include('user.password.password-script')  
@endsection