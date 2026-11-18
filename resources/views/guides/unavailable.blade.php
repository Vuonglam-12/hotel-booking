@extends('Layout')
@section('title', 'Cẩm nang đang được cập nhật — Angel')
@section('content')
@include('guides.styles')
<div class="gd-page"><div class="gd-wrap"><section class="gd-state" role="alert"><h1>Cẩm nang đang được cập nhật</h1><p>Vui lòng quay lại sau hoặc xem những lựa chọn khác cho hành trình.</p><a class="gd-button" href="{{ route('guides.index') }}">Về Cẩm nang</a></section></div></div>
@endsection
