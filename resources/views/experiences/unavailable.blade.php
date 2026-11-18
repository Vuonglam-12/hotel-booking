@extends('Layout')
@section('title', 'Trải nghiệm đang được cập nhật')
@section('content')
@include('experiences.styles')
<div class="exp-page"><div class="exp-wrap" style="padding-top:40px"><section class="exp-state"><h1 style="font-size:30px">Trải nghiệm đang được cập nhật</h1><p>Vui lòng quay lại sau để khám phá thêm những địa điểm mới.</p><a class="exp-button" href="{{ route('experiences.index') }}">Về trang Trải nghiệm</a></section></div></div>
@endsection
