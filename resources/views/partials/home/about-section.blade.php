<!-- resources/views/partials/home/about-section.blade.php -->
<section id="about" class="relative py-24 overflow-hidden bg-slate-50">
    <!-- Background Gradient & Blur Effect (Glassmorphism Base) -->
    <div class="absolute top-0 left-0 w-72 h-72 bg-blue-400 rounded-full mix-blend-multiply filter blur-[80px] opacity-30 animate-blob"></div>
    <div class="absolute top-0 right-0 w-72 h-72 bg-teal-300 rounded-full mix-blend-multiply filter blur-[80px] opacity-30 animate-blob animation-delay-2000"></div>
    <div class="absolute bottom-[-10%] left-[20%] w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-[80px] opacity-30 animate-blob animation-delay-4000"></div>

    <div class="container relative z-10 px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
        <div class="grid grid-cols-1 gap-16 lg:grid-cols-2 lg:items-center">
            
            <!-- Left: Text Content -->
            <div class="space-y-8 reveal-left">
                <div class="inline-flex items-center px-4 py-2 space-x-2 font-semibold text-blue-700 rounded-full bg-white/50 backdrop-blur-md border border-white/60 shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                    <span>Về HolidayViet</span>
                </div>
                
                <h2 class="text-4xl font-extrabold text-slate-900 sm:text-5xl leading-tight">
                    Định nghĩa lại trải nghiệm <br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-teal-500">lưu trú thông minh</span>
                </h2>
                
                <p class="text-lg leading-relaxed text-slate-600">
                    HolidayViet không chỉ là nền tảng đặt phòng. Nơi đây là hệ sinh thái lưu trú hoàn hảo, kết hợp công nghệ AI phân tích dữ liệu để cá nhân hóa lịch trình của riêng bạn, mang đến sự tiện lợi và đẳng cấp trong từng chuyến đi.
                </p>

                <!-- Stats/Features Cards (Glassmorphism) -->
                <div class="grid grid-cols-2 gap-6 pt-4">
                    <div class="p-6 transition-all duration-300 bg-white/60 backdrop-blur-lg border border-white/50 rounded-2xl shadow-lg hover:-translate-y-2 hover:shadow-2xl">
                        <div class="flex items-center justify-center w-12 h-12 mb-4 text-blue-600 bg-blue-100 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-800">AI Smart</h3>
                        <p class="mt-1 text-sm font-medium text-slate-500">Gợi ý lịch trình tự động</p>
                    </div>
                    
                    <div class="p-6 transition-all duration-300 bg-white/60 backdrop-blur-lg border border-white/50 rounded-2xl shadow-lg hover:-translate-y-2 hover:shadow-2xl">
                        <div class="flex items-center justify-center w-12 h-12 mb-4 text-teal-600 bg-teal-100 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-800">VNPAY</h3>
                        <p class="mt-1 text-sm font-medium text-slate-500">Thanh toán bảo mật 100%</p>
                    </div>
                </div>
            </div>

            <!-- Right: Image Collage -->
            <div class="relative reveal-right">
                <!-- Main Image -->
                <div class="relative z-10 overflow-hidden rounded-[2rem] shadow-2xl">
                    <img src="{{ asset('image/banner_home/about.png') }}" alt="Không gian khách sạn HolidayViet" class="object-cover w-full h-[550px] transition-transform duration-700 hover:scale-105" />
                    <!-- Overlay gradient -->
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 to-transparent"></div>
                </div>

                <!-- Floating Glass Card (Overlapping image) -->
                <div class="absolute z-20 p-5 bg-white/40 backdrop-blur-xl border border-white/60 rounded-2xl shadow-2xl bottom-10 -left-6 md:-left-12 right-6 md:right-auto animate-float">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center justify-center w-14 h-14 text-white bg-gradient-to-br from-blue-600 to-teal-500 rounded-full shadow-inner">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                        </div>
                        <div>
                            <p class="text-xl font-bold text-slate-900">Hoàn thiện 100%</p>
                            <p class="text-sm text-slate-700">Hệ thống Invoice PDF tự động</p>
                        </div>
                    </div>
                </div>
                
                <!-- Decorative dot pattern -->
                <div class="absolute -top-6 -right-6 z-0 w-32 h-32 opacity-50 bg-[radial-gradient(#CBD5E1_2px,transparent_2px)] [background-size:16px_16px]"></div>
            </div>
            
        </div>
    </div>
</section>