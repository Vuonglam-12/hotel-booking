@extends('layout')

@section('title', 'Blog & Tin tức du lịch')

@section('content')

<style>
    body { scroll-behavior: smooth; }

    .blog-card {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .blog-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -6px rgba(0,0,0,0.08);
    }
    .img-container img {
        transition: transform 0.6s ease;
    }
    .blog-card:hover .img-container img {
        transform: scale(1.1);
    }
    .hero-overlay {
        background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.65));
    }
    .btn-readmore::after {
        content: ' →';
        transition: margin-left 0.3s ease;
    }
    .btn-readmore:hover::after {
        margin-left: 8px;
    }
    .reveal {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.8s ease-out;
    }
    .reveal.active {
        opacity: 1;
        transform: translateY(0);
    }

    /* Tag colors */
    .tag-blue-600   { background: #2563EB; }
    .tag-emerald-500{ background: #10B981; }
    .tag-amber-500  { background: #F59E0B; }
    .tag-purple-500 { background: #8B5CF6; }
    .tag-red-500    { background: #EF4444; }
    .tag-blue-400   { background: #60A5FA; }
    .tag-pink-500   { background: #EC4899; }
</style>

{{-- HERO BANNER --}}
<header class="relative w-full h-[55vh] min-h-[400px] flex items-center justify-center overflow-hidden">
    <img src="{{ asset('image/bannerblog.jpg') }}" alt="Blog Banner"
        class="absolute inset-0 w-full h-full object-cover"
        onerror="this.style.display='none'">

    {{-- Fallback gradient nếu chưa có ảnh --}}
    <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(30,58,95,0.35) 0%, rgba(15,52,96,0.35) 60%, rgba(26,82,118,0.35) 100%);"></div>

    <div class="absolute inset-0 hero-overlay"></div>

    <div class="relative z-10 text-center px-6 max-w-5xl">
        <p class="text-[#87CEFA] text-xs font-bold tracking-widest uppercase mb-4">✦ Góc nhìn & Trải nghiệm</p>
        <h1 class="text-4xl md:text-6xl font-extrabold text-white mb-5 tracking-tight leading-tight">
            KHÁM PHÁ GÓC NHÌN <br>
            <span class="text-[#87CEFA]">&amp;</span> MẸO DU LỊCH
        </h1>
        <p class="text-lg md:text-xl text-slate-200 font-light max-w-3xl mx-auto leading-relaxed italic">
            "Nguồn Cảm Hứng, Bình Giá Chân Thực, và Kinh Nghiệm Thực Tế cho Chuyến Đi Hoàn Hảo."
        </p>
    </div>
</header>

{{-- BLOG GRID --}}
<main class="max-w-7xl mx-auto px-4 py-16">

    {{-- Filter tags --}}
    <div class="flex gap-3 flex-wrap mb-10">
        <button onclick="filterBlog('all', this)"
            class="filter-tag active px-5 py-2 rounded-full text-sm font-semibold border-2 border-[#87CEFA] bg-[#87CEFA] text-[#1E3A5F] transition">
            Tất cả
        </button>
        @foreach(['CẢM HỨNG','REVIEW','MẸO','ẨM THỰC','GIA ĐÌNH'] as $tag)
        <button onclick="filterBlog('{{ $tag }}', this)"
            class="filter-tag px-5 py-2 rounded-full text-sm font-semibold border-2 border-[#E5E7EB] text-[#6B7280] hover:border-[#87CEFA] hover:text-[#1E3A5F] transition">
            {{ $tag }}
        </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="blog-grid">
        @foreach($blogs as $blog)
        <article class="blog-card reveal bg-white rounded-2xl overflow-hidden border border-slate-100"
            data-tag="{{ $blog['tag'] }}">
            <div class="img-container relative h-60 overflow-hidden bg-[#EAF3FF]">
                <img src="{{ asset('image/' . $blog['image']) }}"
                    alt="{{ $blog['title'] }}"
                    class="w-full h-full object-cover"
                    onerror="this.src='https//picsum.photos/seed/blog{{ $blog['id'] }}/600/400'">
                <span class="absolute top-4 left-4 tag-{{ $blog['tag_color'] }} text-white text-[11px] font-bold px-4 py-1.5 rounded-full uppercase tracking-wider">
                    {{ $blog['tag'] }}
                </span>
            </div>
            <div class="p-7">
                <div class="flex items-center text-slate-400 text-sm mb-4 font-medium gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    {{ $blog['date'] }} — {{ $blog['read_time'] }} đọc
                </div>
                <h3 class="text-xl font-bold mb-4 leading-snug hover:text-[#87CEFA] cursor-pointer transition-colors text-[#1E3A5F]">
                    {{ $blog['title'] }}
                </h3>
                <a href="{{ route('blog.detail', $blog['id']) }}"
                class="btn-readmore text-[#87CEFA] font-bold hover:text-[#5BB8F5] transition-all uppercase text-sm tracking-widest">
                Đọc tiếp
                </a>
            </div>
        </article>
        @endforeach
    </div>

</main>

@endsection

@push('scripts')
<script>
// Scroll reveal
function revealOnScroll() {
    document.querySelectorAll('.reveal').forEach(el => {
        const top = el.getBoundingClientRect().top;
        if (top < window.innerHeight - 150) {
            el.classList.add('active');
        }
    });
}
window.addEventListener('scroll', revealOnScroll);
document.addEventListener('DOMContentLoaded', revealOnScroll);

// Filter theo tag
function filterBlog(tag, btn) {
    document.querySelectorAll('.filter-tag').forEach(b => {
        b.classList.remove('active', 'bg-[#87CEFA]', 'text-[#1E3A5F]', 'border-[#87CEFA]');
        b.classList.add('border-[#E5E7EB]', 'text-[#6B7280]');
    });
    btn.classList.add('active', 'bg-[#87CEFA]', 'text-[#1E3A5F]', 'border-[#87CEFA]');
    btn.classList.remove('border-[#E5E7EB]', 'text-[#6B7280]');

    document.querySelectorAll('#blog-grid article').forEach(card => {
        const cardTag = card.dataset.tag || '';
        card.style.display = (tag === 'all' || cardTag === tag) ? '' : 'none';
    });
}
</script>
@endpush