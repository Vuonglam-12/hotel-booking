@extends('layout')

@section('title', $blog['title'])

@section('content')
<div class="max-w-3xl mx-auto py-10">
    <img src="{{ asset('image/' . $blog['image']) }}" alt="{{ $blog['title'] }}" class="w-full rounded-xl mb-6">
    <h1 class="text-3xl font-bold mb-4">{{ $blog['title'] }}</h1>
    <p class="text-slate-500 mb-2">{{ $blog['date'] }} — {{ $blog['read_time'] }}</p>

    {{-- Nội dung riêng của từng bài --}}
    @include($contentView)
</div>
@endsection
