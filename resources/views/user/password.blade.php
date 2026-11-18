@extends('user.layouts.user')
@section('title', 'Bảo mật tài khoản')
@section('content')
@include('user.password.password-section')
@endsection
@section('scripts')
@include('user.password.password-script')
@endsection
